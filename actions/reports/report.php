<?php

session_start();

require_once __DIR__ . '/../../utility/auth.php';
require_once __DIR__ . '/../time_hours_connection.php';

auth::needs_role(['admin']);

// Obtener lista de usuarios
$stmt = $PDOconnection->query("SELECT id, name FROM user ORDER BY name");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$currentYear = date('Y');
$currentMonth = date('m');
$currentWeek = date('W');
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes | KronoLog</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="icon" href="../../media/icon/favicon.svg">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --brand-primary: #BD80CD;
            --brand-dark: #9F5FB2;
            --surface: #ffffff;
            --background: #f4f5f7;
            --text-main: #1a202c;
            --text-light: #ffffff;
            --text-muted: #718096;
            --radius: 12px;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--background);
            color: var(--text-main);
            margin: 0;
            min-height: 100vh;
            position: relative;
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

        .brand img { width: 48px; height: 48px; object-fit: contain; display: block; }

        .header-right { display: flex; align-items: center; gap: 2.5rem; }
        .user-info { text-align: right; }
        .user-info .name { font-weight: 700; font-size: 1.1rem; line-height: 1.2; }
        .user-info .role { font-size: 0.8rem; opacity: 0.8; font-weight: 500;text-transform: uppercase; }

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

        /* --- LAYOUT DE REPORTES --- */
        .report-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 30px;
            align-items: start;
        }

        .report-card {
            background: var(--surface);
            padding: 35px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid #E6C9EC;
            position: relative;
            transition: transform 0.2s;
        }

        .report-card:hover { transform: translateY(-3px); }

        .report-card::before {
            content: "";
            position: absolute;
            left: 0; top: 0; bottom: 0;
            width: 6px;
            background: var(--brand-primary);
        }

        .report-card h4 {
            margin: 0 0 30px 0;
            color: var(--brand-primary);
            font-size: 1.1rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* --- FORMULARIOS --- */
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
        }

        .form-group { flex: 1; display: flex; flex-direction: column; }

        label {
            font-size: 0.75rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: #1f163a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        select {
            padding: 12px;
            border: 1.5px solid #E6C9EC;
            border-radius: 8px;
            background: #FBF4FD;
            color: var(--text-main);
            outline: none;
            font-family: inherit;
            font-weight: 500;
            transition: all 0.2s;
        }

        select:focus { border-color: var(--brand-primary); background: #fff; box-shadow: 0 0 0 3px rgba(64, 46, 182, 0.1); }

        .btn-submit {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            background: var(--brand-primary);
            color: white;
            border: none;
            padding: 16px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 6px rgba(64, 46, 182, 0.2);
        }

        .btn-submit:hover { background: var(--brand-dark); transform: translateY(-2px); box-shadow: 0 8px 15px rgba(64, 46, 182, 0.3); }
        .btn-submit img { filter: brightness(0) invert(1); width: 20px; }

        /* --- LISTADO DE USUARIOS --- */
        .user-selection-container { margin-bottom: 30px; }

        .user-selection-box {
            max-height: 220px;
            overflow-y: auto;
            border: 1.5px solid #E6C9EC;
            padding: 10px;
            border-radius: 8px;
            background: #FBF4FD;
        }

        .user-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px;
            border-radius: 6px;
            transition: 0.2s;
            font-weight: 500;
            font-size: 0.95rem;
        }

        .user-item:hover { background: #F6E8F9; color: var(--brand-primary); }

        input[type="checkbox"] {
            accent-color: var(--brand-primary);
            width: 18px; height: 18px;
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .report-grid { grid-template-columns: 1fr; padding: 20px; }
            .form-row { flex-direction: column; }
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
                <div class="name"><?= isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Admin'; ?></div>
                <div class="role">Descarga de reportes</div>
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
        <?php endif; ?>
        <a href="#"><button class="actual-page">Reportes</button></a>
        <a href="../service/service.php"><button>Servicios</button></a>
        <a href="../client/client.php"><button>Clientes</button></a>
        <a href="../worked_time/workLog.php"><button>Horas</button></a>
        <!-- Logout solo para móvil -->
        <a href="../user/logout.php" class="logout-mobile">
            <img src="../../media/svg/cerrar-sesion.svg" alt=""> Cerrar Sesión
        </a>
    </nav>

    <main class="main-container report-grid">
        
        <section class="report-card">
            <h4>reporte Mensual</h4>
            <form method="post" action="./exportMonthlyReport.php">
                <div class="form-row">
                    <div class="form-group">
                        <label>seleccionar año</label>
                        <select name="year" required>
                            <?php for ($y = $currentYear - 5; $y <= $currentYear + 1; $y++): ?>
                                <option value="<?= $y ?>" <?= ($y == $currentYear) ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>seleccionar mes</label>
                        <select name="month" required>
                            <?php for ($m = 1; $m <= 12; $m++): 
                                $mm = str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                                <option value="<?= $mm ?>" <?= ($mm == $currentMonth) ? 'selected' : '' ?>><?= $mm ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <button class="btn-submit" type="submit">
                    REPORTE MENSUAL  <img src="../../media/svg/salvar.svg" alt="">
                </button>
            </form>
        </section>

        <section class="report-card">
            <h4>reporte Anual Completo</h4>
            <form method="post" action="./exportAnualReport.php">
                <div class="form-row">
                    <div class="form-group">
                        <label>Seleccionar año</label>
                        <select name="year" required>
                            <?php for ($y = $currentYear - 5; $y <= $currentYear + 1; $y++): ?>
                                <option value="<?= $y ?>" <?= ($y == $currentYear) ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <button class="btn-submit" type="submit">
                    REPORTE ANUAL <img src="../../media/svg/salvar.svg" alt="">
                </button>
            </form>
        </section>

        <?php if(false): /* SECCIÓN OCULTA: REPORTE SEMANAL */ ?>
        <section class="report-card">
            <h4>Reporte por Semanas</h4>
            <form method="POST" action="./exportWeeklyReport.php">
                <div class="form-row">
                    <div class="form-group">
                        <label>seleccionar Año</label>
                        <select name="year" required>
                            <?php for ($y = $currentYear - 5; $y <= $currentYear + 1; $y++): ?>
                                <option value="<?= $y ?>" <?= ($y == $currentYear) ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Número de Semana</label>
                        <select name="week" required>
                            <?php for ($w = 1; $w <= 53; $w++): 
                                $ww = str_pad($w, 2, '0', STR_PAD_LEFT); ?>
                                <option value="<?= $ww ?>" <?= ($ww == $currentWeek) ? 'selected' : '' ?>><?= $ww ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <button class="btn-submit" type="submit">
                    REPORTE SEMANAL <img src="../../media/svg/salvar.svg" alt="">
                </button>
            </form>
        </section>
        <?php endif; ?>

        <section class="report-card">
            <h4>Reporte Mensual por trabajador</h4>
            <form class="monthly-report" method="post" action="./workerReport.php">
                <div class="form-row">
                    <div class="form-group">
                        <label>seleccionar año</label>
                        <select name="year" required>
                            <?php for ($y = $currentYear - 5; $y <= $currentYear + 1; $y++): ?>
                                <option value="<?= $y ?>" <?= ($y == $currentYear) ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>seleccionar Mes</label>
                        <select name="month" required>
                            <?php for ($m = 1; $m <= 12; $m++): 
                                $mm = str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                                <option value="<?= $mm ?>" <?= ($mm == $currentMonth) ? 'selected' : '' ?>><?= $mm ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <div class="user-selection-container">
                    <label>Seleccionar Trabajadores</label>
                    <div class="user-selection-box">
                        <?php foreach ($users as $u): ?>
                            <label class="user-item">
                                <input class="worker-opt" type="checkbox" name="users[]" value="<?= $u['id'] ?>">
                                <span><?= htmlspecialchars($u['name']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button class="btn-submit" type="submit">
                    REPORTAR DATOS DEL TRABAJADOR <img src="../../media/svg/salvar.svg" alt="">
                </button>
            </form>
        </section>
    </main>
       <script>
        document.querySelector('.monthly-report').addEventListener('submit', function (e) {
    const checked = document.querySelectorAll('.worker-opt:checked').length;
    if (checked === 0) {
        e.preventDefault();
        alert('Debes seleccionar al menos una opción.');
    }
});
       </script>                     
    <script src="../../js/forms_logic.js"></script>
</body>
</html>




