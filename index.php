<?php
session_start();
require_once './utility/auth.php';

// --- LÓGICA DE REDIRECCIÓN (Prioridad absoluta para evitar "Headers already sent") ---
if (isset($_SESSION['username'])) {
    if (auth::has_role('user')) {
        header('Location: ./actions/worked_time/workLog.php');
        exit();
    }
    if (auth::has_role('admin')) {
        header('Location: ./actions/reports/report.php');
        exit();
    }
    if (auth::has_role('moderator')) {
        header('Location: ./actions/client/client.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso | KronoLog</title>
    <link rel="stylesheet" href="./css/style.css">
    <link rel="icon" href="./media/icon/favicon.svg">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;900&display=swap"
        rel="stylesheet">

    <style>
        :root {
            --primary: #BD80CD;
            --primary-dark: #332491;
            --bg-canvas: #f0f2f5;
            --card-bg: #ffffff;
            --text-main: #1a202c;
            --danger: #C992D6;
            --border-color: #E6C9EC;
            --radius: 12px;
            --shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-canvas);
            background-image: radial-gradient(at 0% 0%, rgba(64, 46, 182, 0.05) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(64, 46, 182, 0.05) 0px, transparent 50%);
            color: var(--text-main);
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            overflow: hidden;
            position: relative;
        }

        /* --- LOGO DE FONDO CON MÁS VISIBILIDAD --- */
        .bg-watermark {
            position: fixed;
            left: -80px;
            /* Un poco más hacia adentro */
            top: 50%;
            transform: translateY(-50%);
            z-index: -1;
            opacity: 0.4;
            /* Subido al 10% para que se vea más */
            filter: blur(0px);
            /* Eliminado el blur para mayor nitidez */
            pointer-events: none;
            user-select: none;
            /* Difuminado más suave al final */
            mask-image: linear-gradient(to right, black 60%, transparent 95%);
            -webkit-mask-image: linear-gradient(to right, black 60%, transparent 95%);
        }
        .bg-watermark img {
            width: 800px;
            height: auto;
            display: block;
        }

        .login-card {
            background: var(--card-bg);
            padding: 3rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            width: 100%;
            max-width: 420px;
            animation: fadeIn 0.6s ease-out;
            border: 1px solid rgba(255, 255, 255, 0.8);
            position: relative;
            z-index: 10;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .logo-box {
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: center;
        }
        .logo-box img {
            width: 300px;
            height: auto;
            display: block;
        }

        .login-header h2 {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-main);
            margin: 0;
            letter-spacing: -0.02em;
            text-transform: uppercase;
        }

        .login-header p {
            color: #718096;
            margin-top: 0.5rem;
            font-size: 0.9rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-size: 0.8rem;
            font-weight: 700;
            color: #1f163a;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.6rem;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 0.85rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
            transition: all 0.2s ease;
            box-sizing: border-box;
            background-color: #FBF4FD;
        }

        input:focus {
            outline: none;
            border-color: var(--primary);
            background-color: #fff;
            box-shadow: 0 0 0 4px rgba(64, 46, 182, 0.1);
        }

        .btn-submit {
            width: 100%;
            background: var(--primary);
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px -1px rgba(64, 46, 182, 0.2);
            margin-top: 1rem;
        }

        .btn-submit:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(64, 46, 182, 0.3);
        }

        .btn-submit img {
            width: 20px;
            filter: brightness(0) invert(1);
        }

        .error-toast {
            display: flex;
            align-items: center;
            gap: 12px;
            background-color: #FBF4FD;
            color: var(--danger);
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid var(--danger);
            margin-top: 1.5rem;
            font-size: 0.85rem;
            font-weight: 500;
        }

        @media (max-width: 480px) {
            .login-card {
                padding: 2rem;
                margin: 1rem;
            }

            .bg-watermark {
                display: none;
            }
        }
    </style>
</head>

<body>

    <?php if (!isset($_SESSION['username'])): ?>

        <div class="bg-watermark">
            <img src="./kronoLogIconPurple.png" alt="KronoLog watermark">
        </div>

        <main class="login-card">
            <div class="login-header">

                <div class="logo-box">
                    <img src="./kronoLogIconPurple.png" alt="KronoLog Logo">
                </div>

                <h2>Iniciar Sesión</h2>
                <p>Gestiona tu tiempo y productividad</p>
            </div>

            <form method="post" action="./actions/user/logUser.php">
                <div class="form-group">
                    <label for="user">Usuario</label>
                    <input type="text" id="user" name="user" placeholder="Escriba nombre de usuario..."
                        autocomplete="username" required>
                </div>

                <div class="form-group">
                    <label for="pass">Contraseña</label>
                    <input type="password" id="pass" name="pass" placeholder="Escriba contraseña..."
                        autocomplete="current-password" required>
                </div>

                <button type="submit" class="btn-submit">
                    <span>ACCEDER AL PANEL</span>
                    <img src="./media/svg/entrar.svg" alt="Acceder">
                </button>
            </form>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="error-toast">
                    <strong>⚠️</strong>
                    <span><?php echo htmlspecialchars($_SESSION['error']);
                    unset($_SESSION['error']); ?></span>
                </div>
            <?php endif ?>
        </main>

    <?php endif ?>

</body>

</html>



