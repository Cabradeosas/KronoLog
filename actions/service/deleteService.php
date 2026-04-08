<?php
session_start();
require_once '../../utility/auth.php';
auth::needs_role(['admin','moderator']);

require_once '../KronoLog_connection.php';

if (!isset($_GET['id'])) {
    $_SESSION['error'] = "Servicio no encontrado.";
    header("Location: ./service.php");
    exit;
}

$id = $_GET['id'];

// Borrar servicio
$stmt = $PDOconnection->prepare("DELETE FROM service WHERE id = ?");
$stmt->execute([$id]);

$_SESSION['success'] = "Servicio eliminado correctamente.";
header("Location: ./service.php");
exit;

