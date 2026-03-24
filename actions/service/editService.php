<?php
session_start();
require_once '../../utility/auth.php';
auth::needs_role(['admin','moderator']);

require_once '../time_hours_connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Método no permitido.']);
    exit;
}

$id = $_POST['id'] ?? null;
$name = $_POST['service_name'] ?? null;

if (!$id || !$name) {
    echo json_encode(['error' => 'Datos incompletos.']);
    exit;
}

// Verifica si existe otro servicio con el mismo nombre
$stmtCheck = $PDOconnection->prepare("SELECT * FROM service WHERE BINARY name = ? AND id != ?");
$stmtCheck->execute([$name, $id]);
if ($stmtCheck->fetch()) {
    echo json_encode(['error' => 'Ese nombre de servicio ya existe.']);
    exit;
}

// Actualiza servicio
$stmtUpdate = $PDOconnection->prepare("UPDATE service SET name = ? WHERE id = ?");
$stmtUpdate->execute([$name, $id]);

echo json_encode(['success' => true]);
exit;
