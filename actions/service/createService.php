<?php
session_start();
require_once '../../utility/auth.php';
// Solo admin !!! Sale si no tiene rol, o no es el rol correcto
auth::needs_role(['admin','moderator']);

// Si se ha enviado el POST ->
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Guarda el nombre
    $serviceToCreate = $_POST['service_name'];
    // Conecta con la base de datos
    require_once '../time_hours_connection.php';
    // Recoge si ya existe
    $stmt = $PDOconnection->prepare("SELECT * FROM service WHERE BINARY name = ?");
    $stmt->execute([$serviceToCreate]);
    $serviceData = $stmt->fetch(PDO::FETCH_ASSOC);
    // Si no existe, lo inserta
    if (!$serviceData) {
        $stmtInsert = $PDOconnection->prepare("INSERT INTO service(name) VALUES (?)");
        $stmtInsert->execute([
                $_POST['service_name']
              ]);
            $_SESSION['success'] = 'Servicio creado correctamente';
    
    } else {
        // Si existe, hace un error
        $_SESSION['error'] = 'Ese servicio ya estaba creado';
    }
    header("Location: ./service.php");
}