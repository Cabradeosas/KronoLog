<?php
session_start();
require_once '../../utility/auth.php';
auth::needs_role(['admin']);
require_once '../KronoLog_connection.php';

/**
 * LÓGICA DE DATOS ORIGINAL
 */
$users = [];
$total_monthly_investment = 0;
$active_operatives = 0;

$stmt = $PDOconnection->prepare("
    SELECT u.id, u.name, u.role, u.mensual_cost, u.weekly_hours, us.motive AS current_status
    FROM user u
    LEFT JOIN user_status us ON us.user_id = u.id AND us.end_date IS NULL
    ORDER BY u.name asc
");
$stmt->execute();

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $users[] = $row;
    $total_monthly_investment += $row['mensual_cost'];
    if (($row['current_status'] ?? 'active') === 'active') {
        $active_operatives++;
    }
}

$status_translations = [
    'active' => 'Activo',
    'vacation' => 'Vacaciones',
    'absent' => 'Ausente'
];
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios | KronoLog</title>
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

        /* --- HEADER AZUL CORPORATIVO (Sincronizado con Reportes) --- */
        header {
            background: var(--primary);
            color: white;
            padding: 0 3rem;
            height: 75px;
            /* Altura idéntica a Reportes */
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 15px rgba(64, 46, 182, 0.2);
        }

        /* Tamaño del logo ajustado a 48px para igualar a Reportes */
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

        /* --- CONTENIDO PRINCIPAL --- */
        /* CONTENIDO PRINCIPAL - Usando el global main-container de style.css */

        /* STATS CARDS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--surface);
            padding: 1.5rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border-top: 4px solid var(--primary);
        }

        .stat-card span {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card h3 {
            font-size: 2rem;
            margin: 0.5rem 0 0;
            font-weight: 800;
            color: var(--primary);
        }

        /* SECTION HEADER */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }



        /* COLLAPSIBLE FORM */
        .collapsible-content {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
            margin-bottom: 25px;
        }

        .collapsible-content.show {
            padding: 25px;
            max-height: 1000px;
            border: 1px solid #E6C9EC;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
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
        }

        input:focus,
        select:focus {
            border-color: var(--primary);
            outline: none;
        }

        .btn-submit-main {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 6px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 20px;
            width: fit-content;
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
            background: var(--primary);
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
        }

        .user-id {
            font-weight: 700;
            color: var(--primary);
            font-family: monospace;
            font-size: 1rem;
        }

        .user-name {
            font-weight: 700;
            color: var(--text-main);
        }

        /* BADGES */
        .badge {
            padding: 5px 12px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .badge-active {
            background: #F6E8F9;
            color: #BD80CD;
        }

         .badge-vacation {
            background: #F6E8F9;
            color: #9F5FB2;
        }

        .badge-absent {
            background: #F6E8F9;
            color: #8A4CA0;
        }

        /* BOTONES ACCIONES */
        .btn-edit,
        .btn-delete {
            background: #FBF4FD; border: 1px solid #E6C9EC;
            padding: 6px 14px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 700;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
        }

        .btn-edit { color: #9F5FB2; }

        .btn-edit:hover { background: #BD80CD; color: white; border-color: #BD80CD; }

        .btn-edit img,
        .btn-delete img {
            width: 14px;
            transition: filter 0.2s;
        }

        .btn-edit img {
            filter: sepia(100%) saturate(300%) hue-rotate(200deg);
        }

        .btn-edit:hover img,
        .btn-delete:hover img {
            filter: brightness(0) invert(1);
        }

        .btn-delete { color: #9F5FB2; border-color: #E6C9EC; background: #fff; }

        .btn-delete:hover { background: #9F5FB2; color: white; border-color: #9F5FB2; }

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
            background-color: var(--surface);
            margin: 50px auto;
            padding: 2.5rem;
            border: none;
            width: 90%;
            max-width: 900px;
            border-radius: var(--radius);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            position: relative;
        }

        .modal-content h3 {
            color: var(--primary);
            margin-top: 0;
            border-bottom: 2px solid #F6E8F9;
            padding-bottom: 15px;
            font-weight: 800;
        }

        .modal-content label {
            display: block;
            margin-top: 15px;
            font-weight: 700;
            font-size: 0.75rem;
            color: #1f163a;
            text-transform: uppercase;
        }

        .modal-content button[type="submit"] {
            width: 100%;
            background: var(--primary);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 6px;
            font-weight: 700;
            margin-top: 30px;
            cursor: pointer;
        }

        .close {
            float: right;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            color: #cbd5e0;
            transition: 0.2s;
        }

        .close:hover {
            color: var(--danger);
        }

        /* FEEDBACK */
        .error,
        .success {
            padding: 1rem 2.5rem;
            font-weight: 600;
            border-radius: 0;
            border-left: 5px solid;
        }

        .error {
            background: #FBF4FD;
            color: #BD80CD;
            border-color: #C992D6;
        }

        .success {
            background: #FBF4FD;
            color: #BD80CD;
            border-color: #C992D6;
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

            .btn-submit-main {
                width: 100%;
            }

            /* Mejora de visualización de tabla en móvil */
            .table-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            /* Ajuste del modal para que no desborde en móvil */
            .modal-content {
                width: 88% !important;
                margin: 20px auto !important;
                padding: 1.5rem !important;
                max-height: 85vh;
                overflow-y: auto;
                box-sizing: border-box;
            }

            .modal-content div[style*="display:grid"] {
                grid-template-columns: 1fr !important;
                gap: 0 !important;
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
        }
    </style>
</head>

<body>

    <div class="bg-watermark">
        <img src="../../kronoLogIconPurple.png" alt="KronoLog watermark" width="800" height="800">
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="error"><?php echo '<p>' . $_SESSION['error'] . '</p>';
        unset($_SESSION['error']); ?></div>
    <?php endif ?>
    <?php if (isset($_SESSION['success'])): ?>
        <div class="success"><?php echo '<p>' . $_SESSION['success'] . '</p>';
        unset($_SESSION['success']); ?></div>
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
                <div class="role">Gestión de usuarios</div>
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
            <a href='../user/user.php'><button class='actual-page'>Usuarios</button></a>
            <a href='../reports/report.php'><button>Reportes</button></a>
        <?php endif ?>
        <a href="../service/service.php"><button>Servicios</button></a>
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
                <span>TOTAL PERSONAL</span>
                <h3><?php echo count($users); ?></h3>
            </div>
            <div class="stat-card" style="border-color: var(--success);">
                <span>ACTIVOS AHORA</span>
                <h3 style="color: var(--success);"><?php echo $active_operatives; ?></h3>
            </div>
            <div class="stat-card">
                <span>COSTE MENSUAL TOTAL</span>
                <h3><?php echo number_format($total_monthly_investment, 0, ',', '.'); ?> €</h3>
            </div>
        </div>

        <div class="section-header">
            <div style="display:flex; align-items:center; gap:15px;">
                <h1 style="margin:0; font-size:1.6rem; font-weight:800;">Listado de Usuarios</h1>
            </div>
            <button class="btn-toggle" onclick="toggleForm('formUsuarios', this)">
                Añadir Usuario <img src="../../media/svg/mas.svg" alt="">
            </button>
        </div>

        <div id="formUsuarios" class="collapsible-content">
            <h3 style="margin-top:0; color: var(--primary); font-weight:800;">Registrar Nuevo Usuario</h3>
            <form method="POST" action="./createUser.php">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Nombre de usuario</label>
                        <input type="text" name="user" placeholder="Ej. Juan Pérez" required />
                    </div>
                    <div class="form-group">
                        <label>Contraseña</label>
                        <input type="password" maxlength="12" name="password" required />
                    </div>
                    <div class="form-group">
                        <label>Rol</label>
                        <select name="role">
                            <option value="user">Usuario</option>
                            <option value="moderator">Moderador</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Estado</label>
                        <select name="status">
                            <option value="active">Activo</option>
                            <option value="vacation">Vacaciones</option>
                            <option value="absent">Ausente</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Coste Mensual (€)</label>
                        <input type="number" step="0.01" name="mensual_cost" min="0" required />
                    </div>
                    <div class="form-group">
                        <label>Horas Semanales</label>
                        <input type="number" name="weekly_hours" min="0" step="0.1" required />
                    </div>
                </div>
                <button type="submit" class="btn-submit-main">Guardar Nuevo Usuario</button>
            </form>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Coste Mensual</th>
                        <th>Horas/Semana</th>
                        <th style="padding-left: 20px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user):
                        $statusClass = 'badge-' . ($user['current_status'] ?? 'active');
                        $statusText = $status_translations[$user['current_status'] ?? 'active'];
                        ?>
                        <tr data-id="<?= $user['id'] ?>" data-name="<?= htmlspecialchars($user['name'], ENT_QUOTES) ?>"
                            data-role="<?= $user['role'] ?>" data-status="<?= $user['current_status'] ?? 'active' ?>"
                            data-mensual_cost="<?= $user['mensual_cost'] ?>" data-hours="<?= $user['weekly_hours'] ?>">
                            <td class="user-id">#<?= str_pad($user['id'], 3, '0', STR_PAD_LEFT) ?></td>
                            <td class="user-name"><?= htmlspecialchars($user['name']) ?></td>
                            <td
                                style="font-weight: 600; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase;">
                                <?= $user['role'] ?>
                            </td>
                            <td><span class="badge <?= $statusClass ?>"><?= $statusText ?></span></td>
                            <td style="font-weight: 700;"><?= number_format($user['mensual_cost'], 2, ',', '.') ?> €</td>
                            <td><?= $user['weekly_hours'] ?> h</td>
                            <td style="padding-left: 15px;">
                                <button class="btn-edit" style="justify-content: flex-start;"
                                    onclick="openEditModal(<?= $user['id'] ?>)">
                                    Editar
                                </button>
                                <?php $isSelf = ($user['id'] == $_SESSION['id']); ?>

                                <button class="btn-delete"
                                    style="justify-content: flex-start; <?= $isSelf ? 'opacity: 0.5; cursor: not-allowed; filter: grayscale(1);' : '' ?>"
                                    <?= $isSelf ? 'disabled' : '' ?>
                                    onclick="<?= $isSelf ? 'return false;' : "openDeleteModal(" . $user['id'] . ", '" . htmlspecialchars($user['name'], ENT_QUOTES) . "')" ?>">
                                    Borrar
                                </button>
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
            <h3>Editar Perfil de Usuario</h3>
            <form id="editUserForm">
                <input type="hidden" name="id" id="editUserId">
                <label>Nombre Completo</label>
                <input type="text" name="user" id="editUserName" required>

                <label>Nueva Contraseña (dejar en blanco para mantener)</label>
                <input type="password" name="password" maxlength="12">

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                    <div>
                        <label>Rol</label>
                        <select name="role" id="editUserRole">
                            <option value="admin">Admin</option>
                            <option value="moderator">Moderador</option>
                            <option value="user">Usuario</option>
                        </select>
                    </div>
                    <div>
                        <label>Estado</label>
                        <select name="status" id="editUserStatus">
                            <option value="active">Activo</option>
                            <option value="vacation">Vacaciones</option>
                            <option value="absent">Ausente</option>
                        </select>
                    </div>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                    <div>
                        <label>Coste Mensual (€)</label>
                        <input type="number" name="mensual_cost" id="editUserSalary" min="0" required>
                    </div>
                    <div>
                        <label>Horas Semanales</label>
                        <input type="number" name="weekly_hours" id="editUserHours" min="0" step="0.1" required>
                    </div>
                </div>
                <button type="submit">Guardar Cambios</button>
                <div id="editError" class="error" style="display:none; margin: 15px 0;"></div>
            </form>
        </div>
    </div>

    <!-- MODAL DE BORRADO -->
    <div id="deleteModal" class="modal">
        <div class="modal-content" style="max-width: 450px; text-align: center; padding: 30px;">
            <span class="close" onclick="closeDeleteModal()">&times;</span>
            <div style="margin-bottom: 20px;">
                <div style="width: 60px; height: 60px; background: #F6E8F9; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#BD80CD" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                </div>
                <h3 style="color: #1a202c; margin: 0 0 10px; font-size: 1.4rem; font-weight: 800; border-bottom: none; padding-bottom: 0;">¿Eliminar usuario?</h3>
                <p style="color: #6b5fa0; margin: 0; font-size: 0.95rem; line-height: 1.5;">Estás a punto de eliminar a <strong id="deleteUserName" style="color: #1a202c;"></strong>.<br>Esta acción es irreversible.</p>
            </div>
            <div style="display: flex; gap: 10px; justify-content: center; margin-top: 25px;">
                <button type="button" onclick="closeDeleteModal()" style="background: white; border: 1px solid #E6C9EC; color: #5b4f7f; padding: 12px 24px; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.2s; width: auto; margin-top: 0;">Cancelar</button>
                <a id="confirmDeleteBtn" href="#" style="background: #BD80CD; border: none; color: white; padding: 12px 24px; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.2s; box-shadow: 0 4px 6px rgba(220, 38, 38, 0.2); text-decoration: none; display: inline-block;">Sí, eliminar</a>
            </div>
        </div>
    </div>

    <script src="../../js/forms_logic.js"></script>
    <script>


        const editModal = document.getElementById('editModal');
        const deleteModal = document.getElementById('deleteModal');
        const editForm = document.getElementById('editUserForm');
        const editError = document.getElementById('editError');

        function openEditModal(id) {
            // Corrección: Buscar específicamente en la tabla de usuarios para evitar conflictos
            const row = document.querySelector(`.table-container tr[data-id='${id}']`);
            document.getElementById('editUserId').value = id;
            document.getElementById('editUserName').value = row.dataset.name;
            const roleSelect = document.getElementById('editUserRole');
            roleSelect.value = row.dataset.role;

            // Si el usuario está editándose a sí mismo → bloquear rol
            if (id == <?= $_SESSION['id'] ?>) {
                roleSelect.disabled = true;
            } else {
                roleSelect.disabled = false;
            }

            document.getElementById('editUserStatus').value = row.dataset.status;
            document.getElementById('editUserSalary').value = row.dataset.mensual_cost;
            document.getElementById('editUserHours').value = row.dataset.hours;
            editError.style.display = 'none';
            editModal.style.display = 'block';
        }

        function closeEditModal() {
            editModal.style.display = 'none';
        }
        
        function openDeleteModal(id, name) {
            document.getElementById('deleteUserName').textContent = name;
            document.getElementById('confirmDeleteBtn').href = './deleteUser.php?id=' + id;
            deleteModal.style.display = 'block';
        }
        function closeDeleteModal() { deleteModal.style.display = 'none'; }

        window.onclick = function (event) {
            if (event.target == editModal) closeEditModal();
            if (event.target == deleteModal) closeDeleteModal();
        }

        editForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(editForm);
            fetch('./updateUser.php', {
                method: 'POST',
                body: formData
            })
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
                        location.reload();
                    } else {
                        editError.textContent = data.error || 'Error desconocido';
                        editError.style.display = 'block';
                    }
                })
                .catch(err => {
                    console.error(err);
                    editError.textContent = err.message;
                    editError.style.display = 'block';
                });
        });
    </script>
</body>

</html>








