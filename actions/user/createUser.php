<?php

session_start();
require_once '../../utility/auth.php';
// Solo admin !!! Sale si no tiene rol, o no es el rol correcto
auth::needs_role(['admin']);

// Si se ha enviado el POST ->
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Guarda el nombre
    $userToCreate = $_POST['user'];
    // Conecta con la base de datos
    require_once '../time_hours_connection.php';    
    // Recoge si ya existe
    $stmt = $PDOconnection->prepare("SELECT * FROM user WHERE BINARY name = ?");
    $stmt->execute([$userToCreate]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    // Si no existe, lo inserta
    if (!$userData) {
        $stmtInsert = $PDOconnection->prepare("INSERT INTO user(name,hash,role,mensual_cost,weekly_hours) VALUES (?,?,?,?,?)");
        $stmtInsert->execute([
                $_POST['user'],
                password_hash($_POST['password'], PASSWORD_DEFAULT),
                $_POST['role'],
                $_POST['mensual_cost'],
                $_POST['weekly_hours']
              ]);
    } else {
        // Si existe, hace un error
        $_SESSION['error'] = 'Ese usuario ya estaba creado';
    }
    // Se sale fuera
    header("Location: ./user.php");
}
