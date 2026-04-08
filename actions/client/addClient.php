<?php
session_start();
require_once '../../utility/auth.php';
// Solo admin !!! Sale si no tiene rol, o no es el rol correcto
auth::needs_role(['admin','moderator']);

// Si se ha enviado el POST ->
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Conecta con la base de datos
    require_once '../KronoLog_connection.php';
    // Recoge si ya existe
    $stmt = $PDOconnection->prepare("SELECT * FROM client WHERE name = ? OR cif = ?");
    $stmt->execute([$_POST['client_name'], $_POST['client_cif']]);
    $clientData = $stmt->fetch(PDO::FETCH_ASSOC);
    // Si no existe, lo inserta
    if (!$clientData) {
        try {
            $stmtInsert = $PDOconnection->prepare("INSERT INTO client(name, cif) VALUES (?,?)");
            $stmtInsert->execute([
                    $_POST['client_name'],
                    $_POST['client_cif']
                  ]);
            $_SESSION['success'] = 'Cliente añadido correctamente';
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                $_SESSION['error'] = 'El CIF facilitado ya está en uso, por favor use otro o ponga un identificativo';
            } else {
                $_SESSION['error'] = 'Error al guardar: ' . $e->getMessage();
            }
        }
    } else {
        // Si existe, hace un error
        if ($clientData['cif'] === $_POST['client_cif']) {
            $_SESSION['error'] = 'Error: Ya existe un cliente con ese CIF.';
        } else {
            $_SESSION['error'] = 'Error: Ya existe un cliente con ese Nombre.';
        }
    }
        // Inmediatamente manda a client.php
    header("Location: ./client.php");
}

