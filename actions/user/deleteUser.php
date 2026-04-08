<?php
session_start();
require_once '../../utility/auth.php';
// Solo los administradores pueden borrar
auth::needs_role(['admin']);

require_once '../KronoLog_connection.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // Opcional: Evitar que el admin se borre a sí mismo si fuera necesario
    // if ($id == $_SESSION['user_id']) { ... }

    try {
        $stmt = $PDOconnection->prepare("DELETE FROM user WHERE id = ?");
        
        if ($stmt->execute([$id])) {
            $_SESSION['success'] = "Usuario eliminado correctamente.";
        } else {
            $_SESSION['error'] = "No se pudo eliminar el usuario.";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error al eliminar: " . $e->getMessage();
    }
} else {
    $_SESSION['error'] = "ID de usuario no proporcionado.";
}

// Redirigir de vuelta a la lista
header('Location: ./user.php');
exit;
