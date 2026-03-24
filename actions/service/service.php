<?php
session_start();
require_once '../../utility/auth.php';
auth::needs_role(['admin','moderator']);

require_once '../time_hours_connection.php';

// Lógica de datos original
$services = [];
$stmt = $PDOconnection->prepare("SELECT id, name FROM service ORDER BY name asc");
$stmt->execute();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $services[] = $row;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Servicios | KronoLog</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="icon" href="../../media/icon/favicon.svg">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --brand-primary: #BD80CD;
            --brand-dark: #9F5FB2;
            --surface: #ffffff;
            --background: #FBF4FD;
            --text-main: #1a202c;
            --text-light: #ffffff;
            --text-muted: #718096;
            --success: #C992D6;
            --danger: #9F5FB2;
            --radius: 8px;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--background);
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

        /* --- HEADER AZUL CORPORATIVO --- */
        header {
            background: var(--brand-primary);
            color: var(--text-light);
            padding: 0 3rem;
            height: 75px; /* Sincronizado */
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        /* Tamaño del logo actualizado a 48px para consistencia total */
        .brand img { 
            width: 48px; 
            height: 48px; 
            object-fit: contain;
            display: block;
        }

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

        /* --- CONTENIDO OPTIMIZADO --- */


        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem; }
        .stat-card {
            background: var(--surface); padding: 1.2rem; border-radius: var(--radius);
            box-shadow: var(--shadow); border-top: 4px solid var(--brand-primary);
        }
        .stat-card span { font-size: 0.7rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-card h3 { font-size: 1.8rem; margin: 0.3rem 0 0; font-weight: 800; color: var(--brand-primary); }

        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .section-header h1 { margin: 0; font-size: 1.4rem; font-weight: 800; color: var(--text-main); }
        


       
        /* FORMULARIO */
        .collapsible-content {
            background: white; border-radius: var(--radius); box-shadow: var(--shadow);
            max-height: 0; overflow: hidden; transition: 0.3s ease-out; margin-bottom: 20px;
        }
        .collapsible-content.show { padding: 20px; max-height: 200px; border: 1px solid #e2e8f0; }
        .form-group label { font-size: 0.75rem; font-weight: 700; color: #000; text-transform: uppercase; margin-bottom: 8px; display: block; }
        input[type="text"] { width: 100%; padding: 12px; border: 1.5px solid #e2e8f0; border-radius: 6px; font-family: inherit; box-sizing: border-box; }
        .btn-submit-main { background: var(--brand-primary) !important; color: white; border: none; padding: 12px; border-radius: 6px; font-weight: 700; cursor: pointer; margin-top: 15px; width: 100%; }
        .btn-submit-main:hover { background: var(--brand-dark) !important; }

        /* TABLA COMPACTA */
        .data-card { background: white; border-radius: var(--radius); overflow: hidden; box-shadow: var(--shadow); border: 1px solid #e2e8f0; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        thead th {
            background: var(--brand-primary); color: white;
            padding: 12px 20px; font-size: 0.75rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 1px; text-align: left;
        }
        
        th:nth-child(1), td:nth-child(1) { width: 15%; }
        th:nth-child(2), td:nth-child(2) { width: 60%; }
        th:nth-child(3), td:nth-child(3) { width: 25%; text-align: right; }

        td { padding: 14px 20px; border-bottom: 1px solid #edf2f7; font-size: 0.9rem; }
        .service-id { font-weight: 700; color: var(--brand-primary); font-family: monospace; }
        .service-name { font-weight: 600; color: var(--text-main); }

        /* ACCIONES */
        .btn-action {
            background: #FBF4FD; border: 1px solid #E6C9EC; padding: 6px 10px; border-radius: 4px;
            font-size: 0.75rem; font-weight: 700; cursor: pointer; transition: 0.2s;
            display: inline-flex; align-items: center; gap: 5px;
        }
        .btn-edit { color: #9F5FB2; }
        .btn-edit:hover { background: #BD80CD; color: white; border-color: #BD80CD; }
        .btn-delete { color: #9F5FB2; background: #fff; border-color: #E6C9EC; }
        .btn-delete:hover { background: #9F5FB2; color: white; border-color: #9F5FB2; }

        /* MODAL */
        .modal { display: none; position: fixed; inset: 0; background: rgba(26, 32, 44, 0.6); backdrop-filter: blur(4px); z-index: 2000; }
        .modal-content { 
            background: white; 
            margin: 10% auto; 
            padding: 30px; 
            width: 400px; 
            border-radius: var(--radius); 
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2); 
            position: relative;
        }
        .close { float: right; font-size: 24px; font-weight: bold; cursor: pointer; color: #cbd5e0; transition: 0.2s; }
        .close:hover { color: var(--danger); }

        .error, .success { padding: 1rem 3rem; font-weight: 600; border-left: 5px solid; margin-bottom: 1px; }
        .error { background: #fff5f5; color: #c53030; border-color: #f56565; }
        .success { background: #f0fff4; color: #276749; border-color: #48bb78; }

        /* --- RESPONSIVE - Móvil --- */
        @media (max-width: 768px) {
            .section-header {
                flex-direction: column;
                align-items: stretch;
                gap: 15px;
            }

            .section-header h1 {
                font-size: 1.4rem;
                text-align: center;
            }

            .btn-toggle {
                width: 100%;
                justify-content: center;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .collapsible-content.show {
                max-height: 500px;
            }

            .modal-content {
                width: 88% !important;
                margin: 20px auto !important;
                padding: 1.5rem !important;
                max-height: 85vh;
                overflow-y: auto;
                box-sizing: border-box;
            }

            .close {
                position: absolute;
                top: 15px;
                right: 15px;
                background: #f1f5f9;
                width: 32px;
                height: 32px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 50%;
                z-index: 10;
            }

            /* Tabla responsiva con ancho de acciones mejorado */
            .data-card {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            table {
                min-width: 600px;
                table-layout: auto;
            }

            /* En móvil damos ancho fijo suficiente a acciones y lo alineamos a la izquierda */
            th:nth-child(1), td:nth-child(1) { width: 60px; }
            th:nth-child(2), td:nth-child(2) { width: auto; }
            th:nth-child(3), td:nth-child(3) { width: 160px; text-align: left; padding-left: 15px; }
        }
    </style>
</head>
<body>

    <div class="bg-watermark">
        <img src="../../kronoLogIconPurple.png" alt="KronoLog watermark" width="800" height="800">
    </div>

    <?php if(isset($_SESSION['error'])): ?>
        <div class="error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif ?>
    <?php if(isset($_SESSION['success'])): ?>
        <div class="success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif ?>

    <header>
        <div class="brand">
    <a href="../../index.php">
        <img src="../../kronoLogIcon.png" alt="KronoLog">
    </a>
</div>
        <div class="header-right">
            <div class="user-info">
                <div class="name"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                <div class="role">Gestión de servicios</div>
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
        <?php if(auth::has_role('admin')): ?>
            <a href='../user/user.php'><button>Usuarios</button></a>
            <a href='../reports/report.php'><button>Reportes</button></a>
        <?php endif ?>
        <a href="#"><button class="actual-page">Servicios</button></a>
        <a href="../client/client.php"><button>Clientes</button></a>
        <a href="../worked_time/workLog.php"><button>Horas</button></a>
        <!-- Logout solo para móvil -->
        <a href="../user/logout.php" class="logout-mobile">
            <img src="../../media/svg/cerrar-sesion.svg" alt=""> Cerrar Sesión
        </a>
    </nav>

    <div class="main-container">
        
        <div class="stats-grid">
            <div class="stat-card">
                <span>Catálogo Actual</span>
                <h3><?php echo count($services); ?> Servicios</h3>
            </div>
        </div>

        <div class="section-header">
            <div style="display:flex; align-items:center; gap:12px;">
                <h1>Gestión de Servicios</h1>
            </div>
            <button class="btn-toggle" onclick="toggleForm('formServicios', this)">
                Añadir Servicio <img src="../../media/svg/mas.svg" alt="">
            </button>
        </div>

        <div id="formServicios" class="collapsible-content">
            <form method="POST" action="./createService.php">
                <div class="form-group">
                    <label>Nombre del servicio</label>
                    <input type="text" name="service_name" placeholder="Ej. SEO, Desarrollo Web..." required/>
                </div>
                <button type="submit" class="btn-submit-main">Guardar Servicio</button>
            </form>
        </div>

        <div class="data-card">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre de Servicio</th>
                        <th style="text-align: right;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $service): ?>
                        <tr data-id="<?php echo $service['id']; ?>">
                            <td class="service-id">#<?php echo str_pad($service['id'], 3, '0', STR_PAD_LEFT); ?></td>
                            <td class="service-name"><?php echo htmlspecialchars($service['name']); ?></td>
                            <td style="text-align: right;">
                                <button class="btn-action btn-edit" onclick="openEditModal(<?php echo $service['id']; ?>)">Editar</button>
                                <button class="btn-action btn-delete" onclick="openDeleteModal(<?php echo $service['id']; ?>, '<?php echo htmlspecialchars($service['name']); ?>')">Borrar</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h3 style="margin-top:0; color:var(--brand-primary); font-weight:800;">Actualizar Servicio</h3>
            <form id="editForm">
                <input type="hidden" name="id" id="editServiceId">
                <div class="form-group" style="margin-top:20px">
                    <label>Nombre del Servicio</label>
                    <input type="text" name="service_name" id="editServiceName" required>
                </div>
                <button type="submit" class="btn-submit-main">Guardar cambios</button>
            </form>
            <div id="editError" class="error" style="display:none; margin-top:15px; font-size:0.8rem"></div>
        </div>
    </div>

    <!-- MODAL DE BORRADO -->
    <div id="deleteModal" class="modal">
        <div class="modal-content" style="max-width: 450px; text-align: center; padding: 30px;">
            <span class="close" onclick="closeDeleteModal()">&times;</span>
            <div style="margin-bottom: 20px;">
                <div style="width: 60px; height: 60px; background: #fee2e2; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                </div>
                <h3 style="color: #1a202c; margin: 0 0 10px; font-size: 1.4rem; font-weight: 800;">¿Eliminar servicio?</h3>
                <p style="color: #64748b; margin: 0; font-size: 0.95rem; line-height: 1.5;">Estás a punto de eliminar el servicio <strong id="deleteServiceName" style="color: #1a202c;"></strong>.<br>Esta acción es irreversible.</p>
            </div>
            <div style="display: flex; gap: 10px; justify-content: center; margin-top: 25px;">
                <button type="button" onclick="closeDeleteModal()" style="background: white; border: 1px solid #e2e8f0; color: #4a5568; padding: 12px 24px; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.2s;">Cancelar</button>
                <a id="confirmDeleteBtn" href="#" style="background: #dc2626; border: none; color: white; padding: 12px 24px; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.2s; box-shadow: 0 4px 6px rgba(220, 38, 38, 0.2); text-decoration: none; display: inline-block;">Sí, eliminar</a>
            </div>
        </div>
    </div>

    <script src="../../js/forms_logic.js"></script>
    <script>


        const editModal = document.getElementById('editModal');
        const deleteModal = document.getElementById('deleteModal');
        const editForm = document.getElementById('editForm');
        const editError = document.getElementById('editError');

        function openEditModal(id) {
            const row = document.querySelector(`tr[data-id='${id}']`);
            const name = row.querySelector('.service-name').textContent;
            document.getElementById('editServiceId').value = id;
            document.getElementById('editServiceName').value = name;
            editError.style.display = 'none';
            editModal.style.display = 'block';
        }

        function closeEditModal() { editModal.style.display = 'none'; }
        
        function openDeleteModal(id, name) {
            document.getElementById('deleteServiceName').textContent = name;
            document.getElementById('confirmDeleteBtn').href = './deleteService.php?id=' + id;
            deleteModal.style.display = 'block';
        }
        function closeDeleteModal() { deleteModal.style.display = 'none'; }

        window.onclick = function(event) { 
            if (event.target == editModal) closeEditModal(); 
            if (event.target == deleteModal) closeDeleteModal();
        }

        editForm.addEventListener('submit', function(e){
            e.preventDefault();
            const formData = new FormData(editForm);
            fetch('./editService.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    location.reload();
                } else {
                    editError.textContent = data.error;
                    editError.style.display = 'block';
                }
            })
            .catch(err => {
                editError.textContent = "Error de red.";
                editError.style.display = 'block';
            });
        });
    </script>
</body>
</html>

