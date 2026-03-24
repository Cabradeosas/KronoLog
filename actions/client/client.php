<?php
session_start();
require_once '../../utility/auth.php';
auth::needs_role(['admin', 'moderator']);
require_once '../time_hours_connection.php';

// Servicios existentes - Lógica original
$services = [];
$stmt = $PDOconnection->prepare("SELECT * FROM service");
$stmt->execute();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $services[] = $row;
}

// Clientes con sus servicios y precios - Lógica original
$client_services = [];
$stmt = $PDOconnection->prepare("
    SELECT
        c.id AS client_id,
        c.name AS client_name,
        c.cif,
        s.name AS service_name,
        cs.id AS client_service_id,
        cs.cost_per_month,
        cs.cost_per_hour,
        cs.comment
    FROM client c
    LEFT JOIN client_service cs ON c.id = cs.client_id
    LEFT JOIN service s ON cs.service_id = s.id
    ORDER BY c.name asc;
");
$stmt->execute();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $id = $row['client_id'];
    if (!isset($client_services[$id])) {
        $client_services[$id] = [
            'id' => $id,
            'name' => $row['client_name'],
            'cif' => $row['cif'],
            'services' => []
        ];
    }
    if ($row['service_name'] !== null) {
        $client_services[$id]['services'][] = [
            'id' => $row['client_service_id'],
            'name' => $row['service_name'],
            'cost_per_month' => $row['cost_per_month'],
            'cost_per_hour' => $row['cost_per_hour'],
            'comment' => $row['comment']
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Clientes | KronoLog</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="icon" href="../../media/icon/favicon.svg">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --brand-primary: #BD80CD;
            --brand-dark: #9F5FB2;
            --success: #C992D6;
            --danger: #C992D6;
            --surface: #ffffff;
            --background: #f4f5f7;
            --text-main: #1a202c;
            --text-light: #ffffff;
            --text-muted: #718096;
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

        /* --- HEADER CORPORATIVO --- */
        header {
            background: var(--brand-primary);
            color: var(--text-light);
            padding: 0 3rem;
            height: 75px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .brand img {
            width: 48px;
            height: 48px;
            object-fit: contain;
            display: block;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 2.5rem;
        }

        .user-info {
            text-align: right;
        }

        .user-info .name {
            font-weight: 700;
            font-size: 1.1rem;
            line-height: 1.2;
        }

        .user-info .role {
            font-size: 0.8rem;
            opacity: 0.8;
            font-weight: 500;
            text-transform: uppercase;
        }

        .logout-icon {
            width: 38px;
            height: 38px;
            filter: brightness(0) invert(1);
            opacity: 0.85;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .logout-icon:hover {
            opacity: 1;
            transform: scale(1.1);
        }

        /* --- NAVEGACIÓN --- */
        /* Eliminado para usar style.css global */

        /* --- CONTENIDO --- */


        /* STATS CARDS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .stat-card {
            background: var(--surface);
            padding: 1.5rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border-top: 4px solid var(--brand-primary);
        }

        .stat-card span {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card h3 {
            font-size: 1.8rem;
            margin: 0.4rem 0 0;
            font-weight: 800;
            color: var(--brand-primary);
        }

        /* SECTION HEADER */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .section-header h1 {
            margin: 0;
            font-size: 1.6rem;
            font-weight: 800;
        }

        .btn-group-actions {
            display: flex;
            gap: 10px;
        }



        /* COLLAPSIBLE FORM */
        .collapsible-content {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            max-height: 0;
            overflow: hidden;
            transition: 0.3s ease-out;
            margin-bottom: 25px;
        }

        .collapsible-content.show {
            padding: 25px;
            max-height: 2000px;
            /* Aumentado para asegurar que se vea el botón */
            border: 1px solid #E6C9EC;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-size: 0.75rem;
            font-weight: 700;
            color: #1f163a;
            text-transform: uppercase;
        }

        input,
        select {
            padding: 12px;
            border: 1.5px solid #E6C9EC;
            border-radius: 6px;
            font-family: inherit;
            font-size: 0.95rem;
            box-sizing: border-box !important;
        }

        input:focus,
        select:focus {
            border-color: var(--brand-primary);
            outline: none;
        }

        /* TABLA (SIN HOVER) */
        .table-container {
            background: white;
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            border: 1px solid #E6C9EC;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead th {
            background: var(--brand-primary);
            color: white;
            padding: 1rem 1.5rem;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            text-align: left;
        }

        td {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #1f163a;
            font-size: 0.95rem;
            vertical-align: top;
        }

        /* LISTA DE SERVICIOS DENTRO DE TABLA */
        .service-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .service-item {
            background: #FBF4FD;
            padding: 8px 12px;
            border-radius: 4px;
            margin-bottom: 6px;
            font-size: 0.85rem;
            border-left: 4px solid var(--brand-primary);
            color: var(--text-main);
        }

        /* ACCIONES MINIMALISTAS */
        .btn-action {
            background: #FBF4FD; border: 1px solid #E6C9EC;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-edit { color: #9F5FB2; }

        .btn-edit:hover { background: #BD80CD; color: white; border-color: #BD80CD; }

        .btn-delete { color: #9F5FB2; border-color: #E6C9EC; background: #fff; }

        .btn-delete:hover { background: #9F5FB2; color: white; border-color: #9F5FB2; }

        .btn-action img {
            width: 12px;
        }

        .btn-edit:hover img,
        .btn-delete:hover img {
            filter: brightness(0) invert(1);
        }

        /* MODAL */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(26, 32, 44, 0.6);
            backdrop-filter: blur(4px);
            z-index: 2000;
        }

        .modal-content {
            background: #fff;
            margin: 50px auto;
            padding: 2.5rem;
            width: 90%;
            max-width: 900px;
            border-radius: var(--radius);
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);
            position: relative;
        }

        .close {
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #cbd5e0;
        }

        .close:hover {
            color: var(--danger);
        }

        .table-edit-client {
            width: 100%;
            border-collapse: collapse;
            margin: 25px 0;
            font-size: 0.85rem;
        }

        .table-edit-client th {
            background: #FBF4FD;
            color: var(--brand-primary);
            font-weight: 800;
            border: 1px solid #E6C9EC;
            padding: 12px;
        }

        .table-edit-client td {
            border: 1px solid #E6C9EC;
            padding: 10px;
        }

        .table-edit-client input {
            width: 100%;
            border: 1px solid #E6C9EC;
            padding: 8px;
            border-radius: 4px;
        }

        .btn-save-modal {
            background: var(--brand-primary) !important;
            color: white;
            border: none;
            padding: 14px 25px;
            border-radius: 6px;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            margin-top: 20px;
            transition: 0.2s;
        }

        .btn-save-modal:hover {
            background: var(--brand-dark) !important;
        }

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

            .btn-group-actions {
                flex-direction: column;
            }

            .btn-toggle {
                width: 100%;
                justify-content: center;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .collapsible-content.show {
                max-height: 1000px;
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
                background: #F6E8F9;
                width: 32px;
                height: 32px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 50%;
                z-index: 10;
            }

            /* Mejora de visualización de tabla en móvil */
            .table-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                border: 1px solid #E6C9EC;
            }

            table {
                min-width: 850px;
            }
        }
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
                <div class="role">gestion de clientes</div>
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
        <a href="../service/service.php"><button>Servicios</button></a>
        <a href="#"><button class="actual-page">Clientes</button></a>
        <a href="../worked_time/workLog.php"><button>Horas</button></a>
        <!-- Logout solo para móvil -->
        <a href="../user/logout.php" class="logout-mobile">
            <img src="../../media/svg/cerrar-sesion.svg" alt=""> Cerrar Sesión
        </a>
    </nav>

    <div class="main-container">

        <div class="stats-grid">
            <div class="stat-card">
                <span>TOTAL CLIENTES</span>
                <h3><?= count($client_services); ?></h3>
            </div>
        </div>

        <div class="section-header">
            <div style="display:flex; align-items:center; gap:15px;">
                <h1>Clientes</h1>
            </div>
            <div class="btn-group-actions">
                <button class="btn-toggle btn-add-client" onclick="toggleForm('formCliente', this)">
                    Nuevo Cliente <img src="../../media/svg/mas.svg">
                </button>
                <button class="btn-toggle" onclick="toggleForm('formServicio', this)">
                    Asignar Servicio <img src="../../media/svg/mas.svg">
                </button>
            </div>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="error">
                <?= $_SESSION['error'];
                unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="success">
                <?= $_SESSION['success'];
                unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <div id="formCliente" class="collapsible-content">
            <h3 style="margin-top:0; color: var(--success); font-weight:800;">Registrar Nuevo Cliente</h3>
            <form method="POST" action="./addClient.php">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Nombre</label>
                        <input type="text" name="client_name" placeholder="Nombre de la empresa" required>
                    </div>
                    <div class="form-group">
                        <label>CIF / NIF</label>
                        <input type="text" name="client_cif" placeholder="Ej: A1234567B" required>
                    </div>
                </div>
                <button type="submit"
                    style="background:var(--success); color:white; border:none; padding:12px 30px; border-radius:6px; margin-top:20px; font-weight:700; cursor:pointer;">Crear
                    Cliente</button>
            </form>
        </div>

        <div id="formServicio" class="collapsible-content">
            <h3 style="margin-top:0; color: var(--brand-primary); font-weight:800;">Vincular Servicio a Cliente</h3>
            <form method="POST" action="./addService.php" id="assignServiceForm">
                <div class="form-grid">
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Cliente Destino</label>
                        <select name="client" required style="width: 100%;">
                            <option value="">-- Seleccionar --</option>
                            <?php foreach ($client_services as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label style="margin-bottom: 10px; display: block;">Seleccionar Servicios</label>
                        <div
                            style="max-height: 300px; overflow-y: auto; border: 1px solid #E6C9EC; border-radius: 6px; background: #FBF4FD;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead style="position: sticky; top: 0; background: var(--brand-primary); z-index: 1;">
                                    <tr>
                                        <th
                                            style="padding: 10px; text-align: left; font-size: 0.75rem; color: white; min-width: 200px;">
                                            <div style="display:flex; flex-direction:column; gap:5px;">
                                                <span>SERVICIO</span>
                                                <select id="serviceSort" onchange="sortServices()"
                                                    style="width:100%; padding:4px; border-radius:4px; border:none; font-size:0.75rem; color:#333; font-weight:600;">
                                                    <option value="az">A - Z</option>
                                                    <option value="za">Z - A</option>
                                                </select>
                                                <input type="text" id="serviceSearch" onkeyup="filterServices()"
                                                    placeholder="Buscar..."
                                                    style="width:95%; padding:4px; border-radius:4px; border:none; font-size:0.75rem; color:#333;">
                                            </div>
                                        </th>
                                        <th style="padding: 10px; width: 100px; font-size: 0.75rem; color: white;">€ /
                                            Hora</th>
                                        <th style="padding: 10px; width: 100px; font-size: 0.75rem; color: white;">Cuota
                                            (€)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($services as $s): ?>
                                        <tr data-id="<?= $s['id'] ?>" data-name="<?= htmlspecialchars($s['name']) ?>"
                                            style="border-bottom: 1px solid #1f163a;">
                                            <td style="padding: 8px 10px;">
                                                <label
                                                    style="display: flex; align-items: center; gap: 10px; cursor: pointer; margin: 0; font-weight: 600; color: var(--text-main);">
                                                    <input type="checkbox" name="services[<?= $s['id'] ?>][selected]"
                                                        value="1"
                                                        style="width: 16px; height: 16px; accent-color: var(--brand-primary);">
                                                    <?= htmlspecialchars($s['name']) ?>
                                                </label>
                                            </td>
                                            <td style="padding: 8px 10px;">
                                                <input type="number" min="0" step="0.01"
                                                    name="services[<?= $s['id'] ?>][price_per_hour]" placeholder="0.00"
                                                    style="width: 100%; padding: 6px; font-size: 0.9rem;">
                                            </td>
                                            <td style="padding: 8px 10px;">
                                                <input type="number" min="0" step="0.01"
                                                    name="services[<?= $s['id'] ?>][price_per_month]" placeholder="0.00"
                                                    style="width: 100%; padding: 6px; font-size: 0.9rem;">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <p style="font-size: 0.8rem; color: #718096; margin-top: 5px;">* Marca los servicios que quieras
                            añadir y establece sus precios.</p>
                    </div>
                </div>

                <button type="submit"
                    style="width: 100%; margin-top: 20px; background: linear-gradient(135deg, var(--brand-primary) 0%, var(--brand-dark) 100%); color: white; padding: 15px; border-radius: 8px; border: none; font-weight: 800; letter-spacing: 0.5px; cursor: pointer; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: transform 0.2s, box-shadow 0.2s; display: flex; align-items: center; justify-content: center; gap: 10px;"
                    onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 12px rgba(0,0,0,0.15)';"
                    onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 6px rgba(0,0,0,0.1)';">
                    GUARDAR SERVICIOS SELECCIONADOS <img src="../../media/svg/salvar.svg"
                        style="width: 18px; filter: brightness(0) invert(1);">
                </button>
            </form>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th style="min-width: 280px;">
                            <div style="display:flex; flex-direction:column; gap:8px;">
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <span>CLIENTE</span>
                                </div>
                                <select id="clientSort" onchange="sortClients()"
                                    style="width:100%; padding:6px; border-radius:4px; border:none; font-size:0.85rem; color:#333; cursor:pointer; font-weight:600;">
                                    <option value="az">Orden: A - Z</option>
                                    <option value="za">Orden: Z - A</option>
                                    <option value="newest">Más recientes</option>
                                    <option value="oldest">Más antiguos</option>
                                </select>
                                <input type="text" id="clientSearch" onkeyup="filterClients()"
                                    placeholder="Buscar por nombre..."
                                    style="width:100%; padding:6px; border-radius:4px; border:none; font-size:0.85rem; color:#333;">
                            </div>
                        </th>
                        <th>Identificación (CIF)</th>
                        <th>Servicios Contratados</th>
                        <th style="width:220px; padding-left: 20px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($client_services as $client): ?>
                        <tr data-id="<?= $client['id'] ?>" data-name="<?= htmlspecialchars($client['name'], ENT_QUOTES) ?>"
                            data-cif="<?= htmlspecialchars($client['cif'], ENT_QUOTES) ?>"
                            data-services='<?= htmlspecialchars(json_encode($client['services'] ?: []), ENT_QUOTES, 'UTF-8') ?>'>
                            <td><strong><?= htmlspecialchars($client['name']) ?></strong></td>
                            <td><code
                                    style="background:#F6E8F9; padding:4px 8px; border-radius:4px; font-weight:600;"><?= htmlspecialchars($client['cif']) ?></code>
                            </td>
                            <td>
                                <div class="service-list">
                                    <?php if (empty($client['services'])): ?>
                                        <span style="color:#b8abd9; font-style:italic; font-size:0.8rem;">
                                            Sin servicios activos
                                        </span>
                                    <?php else: ?>
                                        <?php foreach ($client['services'] as $s): ?>
                                            <div class="service-item">
                                                <strong><?= htmlspecialchars($s['name']) ?></strong>:
                                                <?= number_format($s['cost_per_hour'], 2, ',', '.') ?>€/h |
                                                <?= number_format($s['cost_per_month'], 2, ',', '.') ?>€/m

                                                <!-- Comentario debajo -->
                                                <?php if (!empty($s['comment'])): ?>
                                                    <div style="color:#8c7fb5; font-size:0.8rem; margin-top:2px;">
                                                        <?= htmlspecialchars($s['comment']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php
                                            $totalHour = array_sum(array_column($client['services'], 'cost_per_hour'));
                                            $totalMonth = array_sum(array_column($client['services'], 'cost_per_month'));
                                        ?>
                                        <div class="service-item" style="border-left: 4px solid var(--success); background: #FBF4FD; font-weight: 700; margin-top: 8px;">
                                            TOTAL:
                                            <?= number_format($totalHour, 2, ',', '.') ?>€/h |
                                            <?= number_format($totalMonth, 2, ',', '.') ?>€/m
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td style="padding-left: 15px;">
                                <button class="btn-action btn-edit"
                                    onclick="openEditModal(<?= $client['id'] ?>)">Editar</button>
                                <button type="button" class="btn-action btn-delete"
                                    onclick="openDeleteModal(<?= $client['id'] ?>, '<?= htmlspecialchars($client['name'], ENT_QUOTES) ?>')">Borrar</button>
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
            <h3
                style="color:var(--brand-primary); margin-top:0; font-weight:800; border-bottom:2px solid #F6E8F9; padding-bottom:15px;">
                Editar Ficha del Cliente</h3>
            <form id="editClientForm">
                <input type="hidden" name="client_id" id="editClientId">
                <div class="form-grid" style="margin:25px 0;">
                    <div class="form-group">
                        <label>Nombre</label>
                        <input type="text" name="name" id="editClientName" required>
                    </div>
                    <div class="form-group">
                        <label>CIF / NIF</label>
                        <input type="text" name="cif" id="editClientCif" required>
                    </div>
                </div>

                <h4
                    style="color:#6b5fa0; font-size:0.8rem; text-transform:uppercase; letter-spacing:1px; margin-bottom:15px;">
                    Servicios y Tarifas Vigentes</h4>
                <div style="overflow-x:auto;">
                    <table class="table-edit-client" id="servicesTable">
                        <thead>
                            <tr>
                                <th>Servicio</th>
                                <th style="width:110px;">€ / Hora</th>
                                <th style="width:110px;">€ / Mes</th>
                                <th>Comentario</th>
                                <th style="width:70px; text-align:center;">Borrar servicio</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <button type="submit" class="btn-save-modal">Guardar Cambios</button>
                <div id="editClientMessage"
                    style="margin-top:15px; font-weight:700; text-align:center; font-size:0.9rem;"></div>
            </form>
        </div>
    </div>

    <!-- MODAL DE BORRADO -->
    <div id="deleteModal" class="modal">
        <div class="modal-content" style="max-width: 450px; text-align: center; padding: 30px;">
            <span class="close" onclick="closeDeleteModal()">&times;</span>
            <div style="margin-bottom: 20px;">
                <div style="width: 60px; height: 60px; background: #F6E8F9; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#BD80CD" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                </div>
                <h3 style="color: #1a202c; margin: 0 0 10px; font-size: 1.4rem; font-weight: 800;">¿Eliminar cliente?</h3>
                <p style="color: #6b5fa0; margin: 0; font-size: 0.95rem; line-height: 1.5;">Estás a punto de eliminar a <strong id="deleteClientName" style="color: #1a202c;"></strong>.<br>Esta acción borrará también sus servicios asociados.</p>
            </div>
            <form id="deleteClientForm" method="POST" action="./deleteClient.php">
                <input type="hidden" name="client_id" id="deleteClientId">
                <div style="display: flex; gap: 10px; justify-content: center; margin-top: 25px;">
                    <button type="button" onclick="closeDeleteModal()" style="background: white; border: 1px solid #E6C9EC; color: #5b4f7f; padding: 12px 24px; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.2s;">Cancelar</button>
                    <button type="submit" style="background: #BD80CD; border: none; color: white; padding: 12px 24px; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.2s; box-shadow: 0 4px 6px rgba(220, 38, 38, 0.2);">Sí, eliminar</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../../js/forms_logic.js"></script>
    <script>
        const editModal = document.getElementById('editModal');
        const deleteModal = document.getElementById('deleteModal');
        const editForm = document.getElementById('editClientForm');
        const messageDiv = document.getElementById('editClientMessage');

        function escapeHtml(text) {
            if (text == null) return '';
            return text.toString()
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        function openEditModal(id) {
            // Corrección: Buscar solo dentro de la tabla de clientes (.table-container) para evitar conflicto con la tabla de servicios
            const row = document.querySelector(`.table-container tr[data-id='${id}']`);
            editModal.style.display = 'block';

            // Limpiar formulario previo
            document.getElementById('editClientForm').reset();
            document.querySelector('#servicesTable tbody').innerHTML = '';

            document.getElementById('editClientId').value = id;
            document.getElementById('editClientName').value = row.dataset.name;
            document.getElementById('editClientCif').value = row.dataset.cif || '';

            let services = [];
            try {
                services = JSON.parse(row.dataset.services || '[]');
            } catch (e) {
                console.error("Error al leer servicios:", e);
                services = [];
            }

            const tbody = document.querySelector('#servicesTable tbody');

            services.forEach(s => {
                const tr = document.createElement('tr');
                tr.dataset.serviceId = s.id;
                tr.innerHTML = `
                    <td style="font-weight:700; color:var(--brand-primary);">${escapeHtml(s.name)}</td>
                    <td><input type="number" min="0" step="0.01" name="services[${s.id}][cost_per_hour]" value="${escapeHtml(s.cost_per_hour || 0)}"></td>
                    <td><input type="number" min="0" step="0.01" name="services[${s.id}][cost_per_month]" value="${escapeHtml(s.cost_per_month)}" required></td>
                    <td><input type="text" name="services[${s.id}][comment]" value="${escapeHtml(s.comment || '')}" placeholder="Añadir nota..."></td>
                    <td style="text-align:center;"><input type="checkbox" name="services[${s.id}][delete]" value="1" style="width:18px; height:18px; cursor:pointer; accent-color:var(--danger);"></td>
                `;
                tbody.appendChild(tr);
            });
        }

        function closeEditModal() { editModal.style.display = 'none'; messageDiv.textContent = ''; }
        
        function openDeleteModal(id, name) {
            document.getElementById('deleteClientId').value = id;
            document.getElementById('deleteClientName').textContent = name;
            deleteModal.style.display = 'block';
        }
        function closeDeleteModal() { deleteModal.style.display = 'none'; }

        window.onclick = (e) => { 
            if (e.target == editModal) closeEditModal(); 
            if (e.target == deleteModal) closeDeleteModal();
        }

        editForm.addEventListener('submit', e => {
            e.preventDefault();
            messageDiv.style.color = 'var(--brand-primary)';
            messageDiv.textContent = 'Actualizando base de datos...';
            const formData = new FormData(editForm);

            fetch('./updateClient.php', { method: 'POST', body: formData })
                .then(res => {
                    if (!res.ok) throw new Error("Error HTTP: " + res.status);
                    return res.text();
                })
                .then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        throw new Error("Respuesta inválida del servidor: " + text);
                    }
                })
                .then(data => {
                    if (data.success) {
                        messageDiv.style.color = 'var(--success)';
                        messageDiv.textContent = '✓ Cambios guardados con éxito';

                        // Actualizar la fila de la tabla inmediatamente (DOM)
                        const id = document.getElementById('editClientId').value;
                        // Corrección: Buscar específicamente en la tabla de clientes para evitar conflicto con la tabla de servicios
                        const row = document.querySelector(`.table-container tr[data-id='${id}']`);
                        if (row) {
                            row.dataset.name = data.client.name;
                            row.dataset.cif = data.client.cif;
                            row.dataset.services = JSON.stringify(data.services);

                            // Actualizar texto visible
                            const nameEl = row.querySelector('td:nth-child(1) strong');
                            if (nameEl) nameEl.textContent = data.client.name;

                            const cifEl = row.querySelector('td:nth-child(2) code');
                            if (cifEl) cifEl.textContent = data.client.cif;
                        }

                        setTimeout(() => { closeEditModal(); location.reload(); }, 1000);
                    } else {
                        messageDiv.style.color = 'var(--danger)';
                        messageDiv.textContent = 'Error: ' + (data.error || 'Fallo técnico');
                    }
                })
                .catch(err => {
                    console.error("Error detallado:", err);
                    messageDiv.style.color = 'var(--danger)';
                    messageDiv.textContent = err.message;
                });
        });

        // Validación del formulario de Asignar Servicios
        document.getElementById('assignServiceForm').addEventListener('submit', function (e) {
            const clientSelect = this.querySelector('select[name="client"]');
            if (!clientSelect.value) {
                e.preventDefault();
                alert('⚠️ Error: Debes seleccionar un cliente primero.');
                clientSelect.focus();
                return;
            }

            const selectedServices = this.querySelectorAll('input[type="checkbox"][name*="[selected]"]:checked');
            if (selectedServices.length === 0) {
                e.preventDefault();
                alert('⚠️ Error: Debes seleccionar al menos un servicio de la lista.');
                return;
            }

            let missingPrice = false;
            selectedServices.forEach(checkbox => {
                const row = checkbox.closest('tr');
                const priceMonth = row.querySelector('input[name*="[price_per_month]"]');
                const priceHour = row.querySelector('input[name*="[price_per_hour]"]');

                // Resetear bordes
                priceMonth.style.border = '1px solid #E6C9EC';
                priceHour.style.border = '1px solid #E6C9EC';

                if (!priceMonth.value || priceMonth.value.trim() === '' || !priceHour.value || priceHour.value.trim() === '') {
                    missingPrice = true;
                    if (!priceMonth.value) priceMonth.style.border = '2px solid var(--danger)';
                    if (!priceHour.value) priceHour.style.border = '2px solid var(--danger)';
                }
            });
            if (missingPrice) {
                e.preventDefault();
                alert('⚠️ Error: Para guardar, es OBLIGATORIO indicar la Cuota (€) y el Precio Hora (€) de todos los servicios marcados.');
            }
        });

        // Lógica para filtrar y ordenar la tabla de clientes
        function filterClients() {
            const input = document.getElementById('clientSearch');
            const filter = input.value.toUpperCase();
            const table = document.querySelector('.table-container table');
            const trs = table.getElementsByTagName('tr');

            // Empezamos en 1 para saltar el encabezado
            for (let i = 1; i < trs.length; i++) {
                const td = trs[i].getElementsByTagName('td')[0]; // La primera columna ahora es el Nombre
                if (td) {
                    const txtValue = td.textContent || td.innerText;
                    trs[i].style.display = txtValue.toUpperCase().indexOf(filter) > -1 ? "" : "none";
                }
            }
        }

        function sortClients() {
            const sortValue = document.getElementById('clientSort').value;
            const table = document.querySelector('.table-container table');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));

            rows.sort((a, b) => {
                const nameA = a.dataset.name.toUpperCase();
                const nameB = b.dataset.name.toUpperCase();
                const idA = parseInt(a.dataset.id);
                const idB = parseInt(b.dataset.id);

                switch (sortValue) {
                    case 'az': return nameA.localeCompare(nameB);
                    case 'za': return nameB.localeCompare(nameA);
                    case 'newest': return idB - idA;
                    case 'oldest': return idA - idB;
                    default: return 0;
                }
            });

            rows.forEach(row => tbody.appendChild(row));
        }

        // Lógica para filtrar y ordenar la tabla de SERVICIOS (en el formulario de asignar)
        function filterServices() {
            const input = document.getElementById('serviceSearch');
            const filter = input.value.toUpperCase();
            const table = document.querySelector('#assignServiceForm table');
            const trs = table.getElementsByTagName('tr');

            // Empezamos en 1 para saltar el encabezado
            for (let i = 1; i < trs.length; i++) {
                const name = trs[i].dataset.name;
                if (name) {
                    trs[i].style.display = name.toUpperCase().indexOf(filter) > -1 ? "" : "none";
                }
            }
        }

        function sortServices() {
            const sortValue = document.getElementById('serviceSort').value;
            const table = document.querySelector('#assignServiceForm table');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));

            rows.sort((a, b) => {
                const nameA = a.dataset.name.toUpperCase();
                const nameB = b.dataset.name.toUpperCase();
                const idA = parseInt(a.dataset.id);
                const idB = parseInt(b.dataset.id);

                switch (sortValue) {
                    case 'az': return nameA.localeCompare(nameB);
                    case 'za': return nameB.localeCompare(nameA);
                    default: return 0;
                }
            });

            rows.forEach(row => tbody.appendChild(row));
        }
    </script>
</body>

</html>






