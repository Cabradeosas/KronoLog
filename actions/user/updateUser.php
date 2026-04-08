<?php
session_start();
require_once '../../utility/auth.php';
auth::needs_role(['admin']);
require_once '../KronoLog_connection.php';

header('Content-Type: application/json');

$id = $_POST['id'] ?? null;
$name = $_POST['user'] ?? null;
$role = $_POST['role'] ?? null;
$status = $_POST['status'] ?? null;
$mensual_cost = $_POST['mensual_cost'] ?? null;
$hours = $_POST['weekly_hours'] ?? null;
$password = $_POST['password'] ?? null;

// ❌ Evitar que un usuario pueda cambiar su propio rol
if ($id == $_SESSION['id']) {
    $role = $_SESSION['role']; // Mantener el rol actual
}

if ($id === null || $name === null || $role === null || $status === null || $mensual_cost === null || $hours === null)
{
    echo json_encode(['error' => 'Datos incompletos.']);
    exit;
}

// Verificar que no exista otro usuario con el mismo nombre
$stmtCheck = $PDOconnection->prepare("SELECT * FROM user WHERE BINARY name = ? AND id != ?");
$stmtCheck->execute([$name, $id]);
if ($stmtCheck->fetch()) {
    echo json_encode(['error' => 'Ese nombre de usuario ya existe.']);
    exit;
}

// Actualizar datos del usuario
if (!empty($password)) {
    $stmt = $PDOconnection->prepare(
        "UPDATE user SET name=?, role=?, mensual_cost=?, weekly_hours=?, hash=? WHERE id=?"
    );
    $stmt->execute([
        $name,
        $role,
        $mensual_cost,
        $hours,
        password_hash($password, PASSWORD_DEFAULT),
        $id
    ]);
} else {
    $stmt = $PDOconnection->prepare(
        "UPDATE user SET name=?, role=?, mensual_cost=?, weekly_hours=? WHERE id=?"
    );
    $stmt->execute([
        $name,
        $role,
        $mensual_cost,
        $hours,
        $id
    ]);
}

// Gestionar tabla user_status
date_default_timezone_set('Europe/Madrid');
$now = date('Y-m-d H:i:s');

// Cerrar cualquier estado abierto
$stmtClose = $PDOconnection->prepare("UPDATE user_status SET end_date=? WHERE user_id=? AND end_date IS NULL");
$stmtClose->execute([$now, $id]);

if ($status !== 'active') {
    // Insertar nuevo estado abierto
    $stmtInsert = $PDOconnection->prepare(
        "INSERT INTO user_status (user_id, motive, start_date) VALUES (?, ?, ?)"
    );
    $stmtInsert->execute([$id, $status, $now]);
}

echo json_encode(['success' => true]);
exit;

