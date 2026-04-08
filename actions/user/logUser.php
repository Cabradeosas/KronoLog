<?php
    session_start();
    require_once '../KronoLog_connection.php';
    // Si se ha enviado el POST (Única manera de llegar aquí) ->
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $user = trim(filter_input(INPUT_POST, 'user', FILTER_SANITIZE_STRING));
        $pass = trim(filter_input(INPUT_POST, 'pass'));
        // Conecta con la base de datos
        require_once "../KronoLog_connection.php";
        // Recoge si existe
        $stmt = $PDOconnection->prepare("SELECT * FROM user WHERE BINARY name =
    ?");
        $stmt->execute([$user]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);
        // Si existe, y la contraseña es correcta, abre la sesión con los datos correctos
        if ($userData && password_verify($pass, $userData['hash'])) {
                $_SESSION['id'] = $userData['id'];
                $_SESSION['role'] = $userData['role'];
                $_SESSION['username'] = $userData['name'];
                $_SESSION['status'] = $userData['status'];
                // Quita un posible error
                unset($_SESSION['error']);
        } else {
            // Si no es correcto o no existe, se guarda el error para mostrarlo en index.php
            $_SESSION['error'] = "Error de autenticación. La contraseña o usuario no son correctas." . $userData['role'];
        }
    }
    // Inmediatamente manda a index.php
    header("Location: ../../index.php");
?>
