<?php
session_start();
require '../time_hours_connection.php';
require '../../utility/auth.php';

auth::needs_role(['admin', 'moderator', 'user']);
$userId = $_SESSION['id'];

// Obtener estado actual del usuario - Lógica original
$stmt = $PDOconnection->prepare("
    SELECT * FROM user_status 
    WHERE user_id = ? 
    ORDER BY start_date DESC LIMIT 1
");
$stmt->execute([$userId]);
$status = $stmt->fetch(PDO::FETCH_ASSOC);
$currentStatus = ($status && $status['end_date'] === null) ? $status['motive'] : 'active';

// Fecha seleccionada (por defecto hoy)
$selectedDate = $_GET['date'] ?? date('Y-m-d');

// Obtener registros de trabajo de ese día
$stmt = $PDOconnection->prepare("
    SELECT tw.id, cs.id AS client_service_id, c.name AS client_name, s.name AS service_name, tw.minutes, tw.message
    FROM time_worked tw
    INNER JOIN client_service cs ON tw.client_service_id = cs.id
    INNER JOIN client c ON cs.client_id = c.id
    INNER JOIN service s ON cs.service_id = s.id
    WHERE tw.user_id = ? AND tw.date = ?
    ORDER BY c.name, s.name
");
$stmt->execute([$userId, $selectedDate]);
$workEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular horas totales del día
$totalMinutes = array_sum(array_column($workEntries, 'minutes'));
$totalHours = floor($totalMinutes / 60);
$totalMins = $totalMinutes % 60;
$totalHoursDisplay = "{$totalHours}h {$totalMins}m";

// Obtener lista de clientes para el desplegable
$stmt = $PDOconnection->prepare("SELECT id, name FROM client ORDER BY name ASC");
$stmt->execute();
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
// Obtener los 5 servicios más frecuentes del usuario en la última semana
$stmtRecents = $PDOconnection->prepare("
    SELECT 
        cs.id AS client_service_id, 
        cs.client_id, -- Añadimos esto
        c.name AS client_name, 
        s.name AS service_name,
        COUNT(*) as frecuencia
    FROM time_worked tw
    INNER JOIN client_service cs ON tw.client_service_id = cs.id
    INNER JOIN client c ON cs.client_id = c.id
    INNER JOIN service s ON cs.service_id = s.id
    WHERE tw.user_id = ? AND tw.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY cs.id
    ORDER BY frecuencia DESC
    LIMIT 5
");
$stmtRecents->execute([$userId]);
$recentServices = $stmtRecents->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registro de Horas | KronoLog</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="icon" href="../../media/icon/favicon.svg">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #BD80CD;
            --primary-dark: #9F5FB2;
            --success: #C992D6;
            --danger: #C992D6;
            --bg-body: #f4f7fa;
            --text-main: #1a202c;
            --text-muted: #718096;
            --surface: #ffffff;
            --radius: 8px;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            margin: 0;
            position: relative;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* --- LOGO DE FONDO (WATERMARK) --- */
        .bg-watermark {
            position: fixed;
            left: -100px;
            top: 50%;
            transform: translateY(-50%);
            z-index: -1;
            opacity: 0.3;
            pointer-events: none;
            user-select: none;
            mask-image: linear-gradient(to right, black 40%, transparent 90%);
            -webkit-mask-image: linear-gradient(to right, black 40%, transparent 90%);
        }

        /* --- HEADER AZUL CORPORATIVO (Sincronizado) --- */
        header {
            background: var(--primary);
            color: white;
            padding: 0 3rem;
            height: 75px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 15px rgba(64, 46, 182, 0.2);
        }

        /* Tamaño del logo actualizado a 48px */
        .brand img { width: 48px; height: 48px; object-fit: contain; display: block; }

        .header-right { display: flex; align-items: center; gap: 2.5rem; }
        .user-info { text-align: right; }
        .user-info .name { font-weight: 700; font-size: 1.1rem; line-height: 1.2; }
        .user-info .role { font-size: 0.8rem; opacity: 0.8; font-weight: 500; text-transform: uppercase; }

        .logout-icon {
            width: 38px;
            height: 38px;
            filter: brightness(0) invert(1);
            opacity: 0.85;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .logout-icon:hover { opacity: 1; transform: scale(1.1); }

        /* --- NAVEGACIÓN --- */
        /* Eliminado para usar style.css global */

        /* --- CONTENIDO PRINCIPAL --- */
        .main-container { max-width: 1400px; margin: 30px auto; padding: 0 30px; }

        /* RESUMEN SUPERIOR */
        .summary-header {
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;
        }
        .total-display {
            background: white; padding: 1.5rem 2rem; border-radius: var(--radius); box-shadow: var(--shadow);
            border-left: 5px solid var(--primary);
        }
        .total-display h3 { margin: 0; font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .total-display span { font-size: 2rem; font-weight: 800; color: var(--primary); }

        /* CONTROLES */
        .controls-row {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px; margin-bottom: 25px; background: white; padding: 25px;
            border-radius: var(--radius); box-shadow: var(--shadow);
        }
        .control-group { display: flex; flex-direction: column; gap: 8px; }
        .control-group label { font-size: 0.75rem; font-weight: 700; color: #1f163a; text-transform: uppercase; }

        input, select { padding: 12px; border: 1.5px solid #E6C9EC; border-radius: 6px; outline: none; font-family: inherit; font-size: 0.95rem; }
        input:focus, select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(64, 46, 182, 0.1); }

        /* MENSAJES DE ESTADO */
        .status-message { padding: 15px 25px; border-radius: var(--radius); margin-bottom: 25px; font-weight: 600; display: none; }
        .status-message.show { display: block; background: #FBF4FD; color: #BD80CD; border: 1px solid #c8bbff; border-left: 5px solid var(--danger); }

        /* FORMULARIO AÑADIR */
        .collapsible-content {
            background: white; border-radius: var(--radius); box-shadow: var(--shadow);
            overflow: hidden; transition: all 0.3s ease-out; max-height: 0; margin-bottom: 25px;
        }
        .collapsible-content.show { 
            padding: 25px; max-height: 500px; border: 1px solid #E6C9EC; 
            overflow: visible; /* Permite ver el buscador */
            transition: all 0.3s ease-in, overflow 0s linear 0.3s; /* El overflow cambia tras 0.3s */
        }

        .add-work-layout { display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; }
        .adjust-buttons { display: flex; gap: 6px; }
        
        .formAdjustBtn {
            background: #FBF4FD; color: var(--text-main); border: 1px solid #E6C9EC;
            padding: 10px 14px; border-radius: 6px; cursor: pointer; font-weight: 700; font-size: 0.85rem; transition: 0.2s;
        }
        .formAdjustBtn:hover { border-color: var(--primary); color: var(--primary); background: #f0f0ff; }

        .btn-submit-main {
            background: var(--success); color: white; border: none; padding: 12px 25px;
            border-radius: 6px; font-weight: 700; cursor: pointer; transition: 0.2s;
        }
        .btn-submit-main:hover { background: #9F5FB2; transform: translateY(-2px); }

        /* TABLA (SIN HOVER) */
        
        .table-container {
            background: white; border-radius: var(--radius); overflow: hidden; box-shadow: var(--shadow); 
            border: 1px solid #E6C9EC;
        }
        table { width: 100% !important; border-collapse: collapse; display: table !important; }
        thead th {
            background: var(--primary); color: white; padding: 1rem 1.5rem;
            font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; text-align: left;
        }
        td { padding: 1.25rem 1.5rem; border-bottom: 1px solid #e7e0ff; font-size: 0.95rem; }

        /* ACCIONES RÁPIDAS */
        .btn-action {
            padding: 8px 12px; border: 1px solid #E6C9EC; border-radius: 4px; cursor: pointer;
            font-size: 0.75rem; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;
            transition: 0.2s; background: white; flex: 1; justify-content: center;
            min-width: 0; /* Allow shrinking if needed */
        }
        
        .actions-cell-wrapper {
            display: flex;
            gap: 6px;
            width: 100%;
            justify-content: flex-end;
        }
        .editBtn { color: #9F5FB2; }
        .editBtn:hover { background: #F6E8F9; border-color: #C992D6; }
        .add15Btn, .sub15Btn { color: #9F5FB2; }
        .add15Btn:hover, .sub15Btn:hover { background: #FBF4FD; border-color: #C992D6; }
        .deleteBtn { color: var(--danger); border-color: #E6C9EC; }
        .deleteBtn:hover { background: #FBF4FD; border-color: var(--danger); }
        .addCommentBtn { color: var(--primary); border-color: #E6C9EC; }
        .addCommentBtn:hover { background: #FBF4FD; border-color: var(--primary); }

        .btn-action:disabled { opacity: 0.4; cursor: not-allowed; }

        /* FILA COMENTARIOS */
        .comment-row td { background: #FBF4FD; padding: 15px 30px; }
        .comment-box {
            background: white; border: 1px solid #E6C9EC; border-radius: 8px; padding: 15px;
            display: flex; gap: 10px; align-items: center;
        }
        .comment-input { flex-grow: 1; border: 1.5px solid #E6C9EC; padding: 10px; border-radius: 6px; font-family: inherit; }
        .btn-save-comment { background: var(--primary); color: white; border: none; padding: 10px 20px; border-radius: 6px; font-weight: 700; cursor: pointer; transition: 0.2s; }
        .btn-save-comment:hover { background: var(--primary-dark); }

        /* MODAL COMENTARIOS (SÓLO PARA MÓVIL) */
        .modal { display: none; position: fixed; inset: 0; background: rgba(26, 32, 44, 0.6); backdrop-filter: blur(4px); z-index: 2000; }
        .modal-content { 
            background: white; margin: 10% auto; padding: 25px; 
            width: 88%; max-width: 400px; border-radius: var(--radius); 
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2); 
            position: relative;
        }
        .close-modal { position: absolute; top: 15px; right: 15px; font-size: 24px; font-weight: bold; cursor: pointer; color: #cbd5e0; }
        
        #mobileCommentArea { width: 100%; border: 1.5px solid #E6C9EC; padding: 12px; border-radius: 6px; font-family: inherit; margin-top: 15px; box-sizing: border-box; resize: vertical; min-height: 100px; }

        @media (max-width: 768px) {
            .summary-header { flex-direction: column; align-items: stretch; gap: 20px; }
            .adjust-buttons { width: 100%; justify-content: space-between; }
            
            .main-container { padding: 0 15px; margin: 20px auto; }
            .controls-row { grid-template-columns: 1fr; padding: 20px; }
            
            .table-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                border: 1px solid #E6C9EC !important;
                box-shadow: var(--shadow) !important;
                border-radius: var(--radius) !important;
            }
            table { min-width: 750px !important; width: 100% !important; display: table !important; }

            .add-work-layout { flex-direction: column; align-items: stretch; }
            .add-work-layout .control-group { min-width: 100% !important; }
        }

        /* SEARCHABLE SELECT STYLES */
        .searchable-select { position: relative; }
        .dropdown-options {
            display: none;
            position: absolute;
            top: 100%; left: 0; right: 0;
            background: white;
            border: 1px solid #E6C9EC;
            border-radius: 0 0 6px 6px;
            max-height: 250px;
            overflow-y: auto;
            z-index: 100;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        .dropdown-item { padding: 10px 12px; cursor: pointer; border-bottom: 1px solid #F6E8F9; font-size: 0.95rem; transition: background 0.1s; }
        .dropdown-item:hover { background-color: #f0f4ff; color: var(--primary); }
        .dropdown-item strong { color: var(--primary); }
    </style>
</head>

<body>

    <div class="bg-watermark">
        <img src="../../kronoLogIconPurple.png" alt="KronoLog watermark" width="800" height="800">
    </div>

    <header>
        <div class="brand">
    <a href="../../index.php">
        <img src="../../kronoLogIcon.png" alt="KronoLog">
    </a>
</div>

        <div class="header-right">
            <div class="user-info">
                <div class="name"><?= htmlspecialchars($_SESSION['username']); ?></div>
                <div class="role">AÑADIR HORAS</div>
            </div>
            <a href="../user/logout.php">
                <img src="../../media/svg/cerrar-sesion.svg" class="logout-icon" title="Cerrar Sesión">
            </a>
            <button class="hamburger-menu" onclick="toggleMenu()" aria-label="Abrir menú">
                <svg viewBox="0 0 24 24">
                    <path d="M3,6H21V8H3V6M3,11H21V13H3V11M3,16H21V18H3V16Z" />
                </svg>
            </button>
        </div>
    </header>

    <nav id="main-nav">
        <?php if (auth::has_role('admin')): ?>
            <a href='../user/user.php'><button>Usuarios</button></a>
            <a href='../reports/report.php'><button>Reportes</button></a>
        <?php endif; ?>
        <?php if (auth::has_role('admin') || auth::has_role('moderator')): ?>
            <a href="../service/service.php"><button>Servicios</button></a>
            <a href="../client/client.php"><button>Clientes</button></a>
        <?php endif; ?>
        <a href="#"><button class='actual-page'>Horas</button></a>
        <!-- Logout solo para móvil dentro del menú -->
        <a href="../user/logout.php" class="logout-mobile">
            <img src="../../media/svg/cerrar-sesion.svg" alt=""> Cerrar Sesión
        </a>
    </nav>

    <div class="main-container">
        
         

        <section class="controls-row">
            <div class="control-group">
                <label>Estado del Operario</label>
                <select id="statusSelect">
                    <option value="active" <?= $currentStatus == 'active' ? 'selected' : '' ?>>Activo</option>
                    <option value="vacation" <?= $currentStatus == 'vacation' ? 'selected' : '' ?>>Vacaciones</option>
                    <option value="absent" <?= $currentStatus == 'absent' ? 'selected' : '' ?>>Ausente</option>
                </select>
            </div>
            <div class="control-group">
                <label>Fecha de Trabajo</label>
                <input type="date" id="dateSelect" value="<?= $selectedDate ?>">
            </div>
        </section>
            <?php if (!empty($recentServices)): ?>
<div class="recent-shortcuts" style="margin-bottom: 15px; background: white; padding: 15px; border-radius: var(--radius); border: 1px dashed var(--primary);">
    <span style="font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; display: block; margin-bottom: 10px;">⚡ Servicios habituales (Última semana):</span>
    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
    <?php foreach ($recentServices as $recent): ?>
        <button type="button" 
                class="btn-recent" 
                onclick="quickFill('<?= $recent['client_service_id'] ?>', '<?= $recent['client_id'] ?>', '<?= addslashes($recent['client_name']) ?>', '<?= addslashes($recent['service_name']) ?>')"
                style="background: #FBF4FD; border: 1.5px solid var(--primary); color: var(--primary); padding: 6px 14px; border-radius: 4px; font-size: 0.85rem; cursor: pointer; font-weight: 600;">
            + <?= htmlspecialchars($recent['client_name']) ?> (<?= htmlspecialchars($recent['service_name']) ?>)
        </button>
    <?php endforeach; ?>
</div>
</div>
<?php endif; ?>
             <button class="btn-toggle" onclick="toggleForm('formAddHours', this)">
                Añadir Registro <img src="../../media/svg/mas.svg">
            </button>

        <div class="status-message">
            <p></p>
        </div>

        <div id="formAddHours" class="collapsible-content">
            <h3 style="margin-top:0; font-size:1.1rem; font-weight:800; color:var(--primary);">Nuevo Registro de Actividad</h3>
            <form id="addWorkForm" method="POST" action="addHours.php" class="add-work-layout">
                <div class="control-group" style="flex: 1; min-width: 200px;">
                    <label>Cliente</label>
                    <div class="searchable-select">
                        <input type="text" id="client_search" placeholder="Buscar cliente..." autocomplete="off" <?= $currentStatus != 'active' ? 'disabled' : '' ?>>
                        <div id="client_dropdown" class="dropdown-options"></div>
                        
                        <!-- Select original oculto pero funcional para la lógica existente -->
                        <select name="client_temp" id="client_id" style="display:none;" onchange="loadClientServicesForHours(this, 'service_id')">
                            <option value="" disabled selected>-- Selecciona Cliente --</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?= $client['id'] ?>"><?= htmlspecialchars($client['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="control-group" style="flex: 1; min-width: 200px;">
                    <label>Servicio</label>
                    <!-- El name debe ser 'client_service' para que addHours.php lo reciba correctamente -->
                    <select name="client_service" id="service_id" required disabled>
                        <option value="" disabled selected>-- Primero selecciona cliente --</option>
                    </select>
                </div>
                
                <div class="control-group" style="flex: 1; min-width: 120px;">
                    <label>Minutos</label>
                    <input type="number" name="minutes" id="minutesInput" min="1" value="60" <?= $currentStatus != 'active' ? 'disabled' : '' ?> required>
                </div>

                <div class="control-group">
                    <label>Acciones rápidas</label>
                    <div class="adjust-buttons">
                        <button type="button" class="formAdjustBtn" data-min="60">+1h</button>
                        <button type="button" class="formAdjustBtn" data-min="15">+15m</button>
                        <button type="button" class="formAdjustBtn" data-min="-15">-15m</button>
                        <button type="button" class="formAdjustBtn" data-min="-60">-1h</button>
                    </div>
                </div>

                <div class="control-group" style="flex-basis: 100%; min-width: 100%;">
                    <label>Descripción</label>
                    <input type="text" name="message" id="messageInput" placeholder="Añadir comentario..." <?= $currentStatus != 'active' ? 'disabled' : '' ?>>
                </div>

                <button type="submit" class="btn-submit-main" <?= $currentStatus != 'active' ? 'disabled' : '' ?>>Guardar Registro</button>
            </form>
        </div>

        <div class="table-container">
            <h3 style="padding: 20px 25px; margin: 0; font-size: 1rem; font-weight:800; border-bottom: 1px solid #e7e0ff; background: #FBF4FD;">
                Detalle del día: <span style="color: var(--primary);"><?= $selectedDate ?></span>
            </h3>
            <table id="workTable">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Servicio</th>
                        <th>Tiempo</th>
                        <th style="text-align: right;">Acciones rápidas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($workEntries as $entry): ?>
                        <tr data-id="<?= $entry['id'] ?>">
                            <td><strong style="font-weight:700;"><?= htmlspecialchars($entry['client_name']) ?></strong></td>
                            <td style="color: var(--text-muted);"><?= htmlspecialchars($entry['service_name']) ?></td>
                            <td class="minutes">
                                <span style="background: #F6E8F9; color: var(--primary); padding: 5px 12px; border-radius: 4px; font-weight: 700; font-family:monospace;">
                                    <?= $entry['minutes'] ?> min
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <div class="actions-cell-wrapper">
                                    <button class="btn-action editBtn" title="Editar minutos">✏️</button>
                                    <button class="btn-action add15Btn" title="+15 min">+15</button>
                                    <button class="btn-action sub15Btn" title="-15 min">-15</button>
                                    <button class="btn-action addCommentBtn" title="Notas">💬 Notas</button>
                                    <button class="btn-action deleteBtn" title="Borrar">🗑️</button>
                                </div>
                            </td>
                        </tr>
                        <tr class="comment-row" style="display:none;">
                            <td colspan="4">
                                <div class="comment-box">
                                    <form class="comment-form" data-id="<?= $entry['id'] ?>" style="display:flex; width:100%; gap:10px;">
                                        <input type="text" name="message" class="comment-input" placeholder="Añadir observación..." value="<?= htmlspecialchars($entry['message'] ?? '') ?>">
                                        <button type="submit" class="btn-save-comment">Guardar Nota</button>
                                    </form>
                                    <div class="comment-message" style="font-size: 0.8rem; font-weight: 700;"></div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- MODAL PARA MÓVIL -->
    <div id="commentModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeCommentModal()">&times;</span>
            <h3 style="margin-top:0; color:var(--primary); font-weight:800; font-size:1.1rem;">Añadir Nota</h3>
            <form id="commentModalForm">
                <input type="hidden" id="modalEntryId">
                <textarea id="mobileCommentArea" placeholder="Escribe aquí tu observación..."></textarea>
                <button type="submit" class="btn-save-comment" style="width:100%; margin-top:15px; padding: 14px;">Guardar Nota</button>
            </form>
            <div id="modalMessage" style="margin-top:10px; font-weight:700; font-size:0.8rem; text-align:center;"></div>
        </div>
    </div>

    <!-- MODAL DE BORRADO DE HORAS -->
    <div id="deleteHoursModal" class="modal">
        <div class="modal-content" style="max-width: 450px; text-align: center; padding: 30px;">
            <span class="close-modal" onclick="closeDeleteHoursModal()">&times;</span>
            <div style="margin-bottom: 20px;">
                <div style="width: 60px; height: 60px; background: #F6E8F9; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#BD80CD" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                </div>
                <h3 style="color: #1a202c; margin: 0 0 10px; font-size: 1.4rem; font-weight: 800;">¿Eliminar registro?</h3>
                <p style="color: #6b5fa0; margin: 0; font-size: 0.95rem; line-height: 1.5;">Estás a punto de eliminar un registro de tiempo. Esta acción no se puede deshacer.</p>
                <p id="deleteHoursInfo" style="margin-top: 10px; font-weight: 600; background: #F6E8F9; padding: 8px; border-radius: 6px;"></p>
            </div>
            <div style="display: flex; gap: 10px; justify-content: center; margin-top: 25px;">
                <input type="hidden" id="deleteHoursId">
                <button type="button" onclick="closeDeleteHoursModal()" style="background: white; border: 1px solid #E6C9EC; color: #5b4f7f; padding: 12px 24px; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.2s;">Cancelar</button>
                <button type="button" id="confirmDeleteHoursBtn" style="background: #BD80CD; border: none; color: white; padding: 12px 24px; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.2s; box-shadow: 0 4px 6px rgba(220, 38, 38, 0.2);">Sí, eliminar</button>
            </div>
        </div>
    </div>

    <script src="../../js/forms_logic.js?v=<?= time() ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const statusSelect = document.getElementById('statusSelect');
            const addWorkForm = document.getElementById('addWorkForm');
            const dateSelect = document.getElementById('dateSelect');
            const workTable = document.getElementById('workTable');
            const statusMessageDiv = document.querySelector('.status-message');
            const statusMessageP = statusMessageDiv.querySelector('p');
            
            // --- LÓGICA BUSCADOR DE CLIENTES ---
            const clientSelect = document.getElementById('client_id');
            const searchInput = document.getElementById('client_search');
            const dropdown = document.getElementById('client_dropdown');
            const deleteHoursModal = document.getElementById('deleteHoursModal');

            function populateDropdown(filter = '') {
                dropdown.innerHTML = '';
                const options = Array.from(clientSelect.options);
                let found = false;
                const filterLower = filter.toLowerCase();

                options.forEach(opt => {
                    if (opt.value === '') return;
                    if (opt.text.toLowerCase().includes(filterLower)) {
                        const div = document.createElement('div');
                        div.className = 'dropdown-item';
                        div.textContent = opt.text;
                        div.addEventListener('click', () => {
                            clientSelect.value = opt.value;
                            searchInput.value = opt.text;
                            dropdown.style.display = 'none';
                            clientSelect.dispatchEvent(new Event('change')); // Disparar carga de servicios
                        });
                        dropdown.appendChild(div);
                        found = true;
                    }
                });

                if (!found) {
                    const div = document.createElement('div');
                    div.className = 'dropdown-item';
                    div.style.color = '#999';
                    div.textContent = 'No se encontraron resultados';
                    dropdown.appendChild(div);
                }
            }

            searchInput.addEventListener('focus', () => { populateDropdown(searchInput.value); dropdown.style.display = 'block'; });
            searchInput.addEventListener('input', () => { populateDropdown(searchInput.value); dropdown.style.display = 'block'; });
            
            document.addEventListener('click', (e) => {
                if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
                    dropdown.style.display = 'none';
                    // Si el input no coincide con la selección actual, revertir (opcional)
                    const selected = clientSelect.options[clientSelect.selectedIndex];
                    if(selected && selected.value && searchInput.value === '') searchInput.value = selected.text;
                }
            });
            // -----------------------------------

            function updateStatusMessage() {
                const state = statusSelect.value;
                if (state !== 'active') {
                    statusMessageP.textContent = `Restricción: No se pueden añadir horas en modo ${state.toUpperCase()}.`;
                    statusMessageDiv.className = `status-message show`;
                } else {
                    statusMessageDiv.className = 'status-message';
                }
            }

            function toggleUIByStatus() {
                const enabled = statusSelect.value === 'active';
                if(searchInput) searchInput.disabled = !enabled;
                addWorkForm.querySelectorAll('input,select,button').forEach(el => el.disabled = !enabled);
                workTable.querySelectorAll('.btn-action').forEach(btn => {
                    btn.disabled = !enabled;
                });
            }

            statusSelect.addEventListener('change', () => {
                fetch('changeStatus.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `motive=${statusSelect.value}` })
                    .then(res => res.json())
                    .then(data => { if (data.success) { toggleUIByStatus(); updateStatusMessage(); } else alert('Error: ' + data.error); });
            });

            addWorkForm.addEventListener('submit', e => {
                e.preventDefault();
                const formData = new FormData(addWorkForm);
                formData.append('date', dateSelect.value);
                
                // Validación manual ya que el select está oculto
                if(!clientSelect.value) { alert('Por favor selecciona un cliente de la lista.'); return; }

                fetch('addHours.php', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => { if (data.success) { // Dentro del .then(data => { if (data.success) { ... } })
const toast = document.createElement('div');
toast.innerHTML = '✅ ¡Registro guardado con éxito!';
toast.style = 'position:fixed; top:20px; right:20px; background:var(--success); color:white; padding:15px 25px; border-radius:8px; z-index:9999; box-shadow:var(--shadow); font-weight:700;';
document.body.appendChild(toast);
setTimeout(() => toast.remove(), 3000);
renderWorkEntries(data.entries); 
addWorkForm.reset(); 
document.getElementById('minutesInput').value = 60; 
searchInput.value = ''; // Limpiar buscador
} else alert('Error: ' + data.error); });
            });

            // --- AQUÍ ESTÁ EL CAMBIO PRINCIPAL ---
            workTable.addEventListener('click', e => {
                const btn = e.target.closest('button');
                if (!btn) return;
                const row = btn.closest('tr');
                const id = row.dataset.id;
                
                // 1. Lógica del botón EDITAR (Copiada del código 2 y adaptada)
                if (btn.classList.contains('editBtn')) {
                    // Obtenemos el texto actual (ej: "60 min") y lo convertimos a número
                    const minutesElement = row.querySelector('.minutes').textContent.trim();
                    const current = parseInt(minutesElement);
                    
                    // Pedimos el nuevo valor total
                    const newMinutes = prompt('Minutos trabajados:', current);
                    
                    // Si el usuario pone un número válido, calculamos la diferencia y actualizamos
                    if (newMinutes !== null && !isNaN(newMinutes) && newMinutes.trim() !== "") {
                        // Importante: updateMinutes espera la diferencia (delta), no el total
                        updateMinutes(id, newMinutes - current);
                    }
                }

                // 2. Lógica del botón NOTAS (Mantiene la funcionalidad del código 1: Modal o Desplegable)
                if (btn.classList.contains('addCommentBtn')) {
                    if (window.innerWidth <= 768) {
                        // En móvil: Abrir modal
                        const currentComment = row.nextElementSibling.querySelector('.comment-input').value || '';
                        openCommentModal(id, currentComment);
                    } else {
                        // En escritorio: Desplegar fila
                        const commentRow = row.nextElementSibling;
                        commentRow.style.display = commentRow.style.display === 'table-row' ? 'none' : 'table-row';
                    }
                }

                // Resto de botones
                if (btn.classList.contains('add15Btn')) updateMinutes(id, 15);
                if (btn.classList.contains('sub15Btn')) updateMinutes(id, -15);
                if (btn.classList.contains('deleteBtn')) { 
                    const clientName = row.querySelector('td:nth-child(1) strong').textContent;
                    const serviceName = row.querySelector('td:nth-child(2)').textContent;
                    const minutesText = row.querySelector('.minutes span').textContent;
                    openDeleteHoursModal(id, clientName, serviceName, minutesText);
                }
            });
            // --- FIN DE LOS CAMBIOS ---

            // GESTIÓN DEL MODAL (SÓLO MÓVIL)
            const commentModal = document.getElementById('commentModal');
            const commentFormModal = document.getElementById('commentModalForm');
            const modalEntryId = document.getElementById('modalEntryId');
            const mobileCommentArea = document.getElementById('mobileCommentArea');
            const modalMsg = document.getElementById('modalMessage');

            window.openCommentModal = function(id, comment) {
                modalEntryId.value = id;
                mobileCommentArea.value = comment;
                modalMsg.textContent = '';
                commentModal.style.display = 'block';
            }

            window.closeCommentModal = function() {
                commentModal.style.display = 'none';
            }

            // Cerrar al clickar fuera
            window.addEventListener('click', (e) => {
                if (e.target == commentModal) closeCommentModal();
                if (e.target == deleteHoursModal) closeDeleteHoursModal();
            });

            // Lógica del modal de borrado de horas
            function openDeleteHoursModal(id, client, service, minutes) {
                document.getElementById('deleteHoursId').value = id;
                document.getElementById('deleteHoursInfo').innerHTML = `<strong>${client}</strong> (${service}) - <strong>${minutes}</strong>`;
                deleteHoursModal.style.display = 'block';
            }

            window.closeDeleteHoursModal = function() {
                deleteHoursModal.style.display = 'none';
            }

            document.getElementById('confirmDeleteHoursBtn').addEventListener('click', () => {
                const id = document.getElementById('deleteHoursId').value;
                fetch('deleteHours.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `id=${id}&date=${dateSelect.value}` })
                    .then(res => res.json())
                    .then(data => {
                        closeDeleteHoursModal();
                        if (data.success) {
                            renderWorkEntries(data.entries);
                        } else {
                            alert('Error al borrar: ' + (data.error || 'Error desconocido'));
                        }
                    });
            });

            commentFormModal.addEventListener('submit', e => {
                e.preventDefault();
                modalMsg.style.color = 'var(--primary)';
                modalMsg.textContent = 'Guardando...';

                fetch('updateComment.php', { 
                    method: 'POST', 
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, 
                    body: `id=${modalEntryId.value}&message=${encodeURIComponent(mobileCommentArea.value)}&date=${dateSelect.value}` 
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        modalMsg.style.color = 'var(--success)';
                        modalMsg.textContent = '✓ Nota guardada';
                        setTimeout(() => {
                            renderWorkEntries(data.entries || []);
                            closeCommentModal();
                        }, 600);
                    } else {
                        modalMsg.style.color = 'var(--danger)';
                        modalMsg.textContent = 'Error al guardar';
                    }
                });
            });

            function updateMinutes(id, minutes) {
                fetch('editHours.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `id=${id}&minutes=${minutes}&date=${dateSelect.value}` })
                    .then(res => res.json())
                    .then(data => { if (data.success) renderWorkEntries(data.entries); });
            }

            function renderWorkEntries(entries) {
                const tbody = workTable.querySelector('tbody');
                tbody.innerHTML = '';
                let totalMinutes = 0;

                entries.forEach(entry => {
                    const mins = parseInt(entry.minutes) || 0;
                    totalMinutes += mins;
                    
                    const row = document.createElement('tr');
                    row.dataset.id = entry.id;
                    row.innerHTML = `
                        <td><strong style="font-weight:700;">${escapeHtml(entry.client_name)}</strong></td>
                        <td style="color: var(--text-muted);">${escapeHtml(entry.service_name)}</td>
                        <td class="minutes">
                            <span style="background: #F6E8F9; color: var(--primary); padding: 5px 12px; border-radius: 4px; font-weight: 700; font-family:monospace;">
                                ${mins} min
                            </span>
                        </td>
                        <td style="text-align: right;">
                            <div class="actions-cell-wrapper">
                                <button class="btn-action editBtn" title="Editar minutos">✏️</button>
                                <button class="btn-action add15Btn" title="+15 min">+15</button>
                                <button class="btn-action sub15Btn" title="-15 min">-15</button>
                                <button class="btn-action addCommentBtn" title="Notas">💬 Notas</button>
                                <button class="btn-action deleteBtn" title="Borrar">🗑️</button>
                            </div>
                        </td>
                    `;
                    tbody.appendChild(row);

                    const commentRow = document.createElement('tr');
                    commentRow.className = 'comment-row';
                    commentRow.style.display = 'none';
                    commentRow.innerHTML = `
                        <td colspan="4">
                            <div class="comment-box">
                                <form class="comment-form" data-id="${entry.id}" style="display:flex; width:100%; gap:10px;">
                                    <input type="text" name="message" class="comment-input" placeholder="Añadir observación..." value="${escapeHtml(entry.message || '')}">
                                    <button type="submit" class="btn-save-comment">Guardar Nota</button>
                                </form>
                                <div class="comment-message" style="font-size: 0.8rem; font-weight: 700;"></div>
                            </div>
                        </td>
                    `;
                    tbody.appendChild(commentRow);
                });

                const h = Math.floor(totalMinutes / 60);
                const m = totalMinutes % 60;
                const totalHoursEl = document.getElementById('totalHours');
                if (totalHoursEl) totalHoursEl.textContent = `${h}h ${m}m`;
                
                toggleUIByStatus();
            }

            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            dateSelect.addEventListener('change', e => { location.href = `workLog.php?date=${e.target.value}`; });

            document.querySelectorAll('.formAdjustBtn').forEach(btn => btn.addEventListener('click', e => {
                const delta = parseInt(e.target.dataset.min);
                const input = document.getElementById('minutesInput');
                let current = parseInt(input.value) || 0;
                input.value = Math.max(1, current + delta);
            }));

            workTable.addEventListener('submit', e => {
                if (e.target.classList.contains('comment-form')) {
                    e.preventDefault();
                    const form = e.target;
                    const id = form.dataset.id;
                    const message = form.querySelector('.comment-input').value;
                    const msgDiv = form.nextElementSibling;
                    
                    fetch('updateComment.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `id=${id}&message=${encodeURIComponent(message)}&date=${dateSelect.value}` })
                        .then(res => res.json())
                        .then(data => {
                            msgDiv.textContent = data.success ? '✓ Guardado' : 'Error';
                            msgDiv.style.color = data.success ? 'var(--success)' : 'var(--danger)';
                        });
                }
            });
            
            // Función auxiliar del primer código para desplegar formulario (aunque no se usa en la lógica principal, se mantiene por compatibilidad)
            window.toggleForm = function(id, btn) {
                const content = document.getElementById(id);
                content.classList.toggle('show');
            }

            toggleUIByStatus(); updateStatusMessage();
        });
        window.quickFill = function(clientServiceId, clientId, clientName, serviceName) {
    const clientSelect = document.getElementById('client_id');
    const serviceSelect = document.getElementById('service_id');
    const formContainer = document.getElementById('formAddHours');
    
    // 1. Seleccionamos el cliente en el primer select
    clientSelect.value = clientId;
    
    // Actualizar el buscador visual
    const searchInput = document.getElementById('client_search');
    if(searchInput) searchInput.value = clientName;

    // 2. Limpiamos y preparamos el select de servicios
    serviceSelect.innerHTML = ''; // Borramos el "-- Primero selecciona cliente --"
    
    // 3. Creamos la opción del servicio específico y la seleccionamos
    const option = document.createElement('option');
    option.value = clientServiceId;
    option.text = serviceName;
    option.selected = true;
    
    serviceSelect.appendChild(option);
    serviceSelect.disabled = false; // Importante para que se envíe en el POST

    // 4. Abrimos el formulario si está oculto
    if (!formContainer.classList.contains('show')) {
        toggleForm('formAddHours', document.querySelector('.btn-toggle'));
    }

    // 5. Feedback visual (opcional)
    [clientSelect, serviceSelect].forEach(el => {
        el.style.backgroundColor = '#FBF4FD'; // Color verde suave
        setTimeout(() => { el.style.backgroundColor = ''; }, 1000);
    });
};
    </script>
</body>
</html>







