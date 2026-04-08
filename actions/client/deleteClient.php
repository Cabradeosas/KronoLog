<?php
session_start();
require_once '../../utility/auth.php';
// Solo admin/ moderador
auth::needs_role(['admin','moderator']);

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../KronoLog_connection.php';

    if (!isset($_POST['client_id'])) {
        $_SESSION['error'] = 'No se especificó el cliente';
        header("Location: ./client.php");
        exit;
    }

    $clientId = (int) $_POST['client_id'];

    try {
        // Iniciar transacción
        $PDOconnection->beginTransaction();

        // Primero borrar todos los servicios del cliente
        $stmt = $PDOconnection->prepare("DELETE FROM client_service WHERE client_id = ?");
        $stmt->execute([$clientId]);

        // Luego borrar el cliente
        $stmt = $PDOconnection->prepare("DELETE FROM client WHERE id = ?");
        $stmt->execute([$clientId]);

        $PDOconnection->commit();

        $_SESSION['success'] = 'Cliente y sus servicios borrados correctamente';
    } catch (Exception $e) {
        $PDOconnection->rollBack();
        $_SESSION['error'] = 'Error al borrar el cliente: ' . $e->getMessage();
    }

    header("Location: ./client.php");
    exit;
}
?>

