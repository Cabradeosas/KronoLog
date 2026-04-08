<?php
session_start();
require '../KronoLog_connection.php';
require '../../utility/auth.php';

auth::needs_role(['admin', 'moderator','user']);

// ID de usuario desde la sesión
$userId = $_SESSION['id'] ?? null;
if (!$userId) {
    http_response_code(403);
    echo json_encode(['error' => 'No estás autenticado']);
    exit;
}

// Revisar estado actual
$stmt = $PDOconnection->prepare("
    SELECT * FROM user_status 
    WHERE user_id = ? 
    ORDER BY start_date DESC LIMIT 1
");
$stmt->execute([$userId]);
$status = $stmt->fetch(PDO::FETCH_ASSOC);
$currentStatus = ($status && $status['end_date'] === null) ? $status['motive'] : 'active';

// Si no está activo, no permite borrar
if ($currentStatus !== 'active') {
    echo json_encode(['error' => "No puedes borrar registros mientras estás en estado '$currentStatus'"]);
    exit;
}

// Obtener ID del registro a borrar y fecha
$id = $_POST['id'] ?? null;
$date = $_POST['date'] ?? null; // <-- fecha seleccionada
if (!$id || !$date) {
    echo json_encode(['error' => 'Datos inválidos']);
    exit;
}

// Validar formato de fecha
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['error' => 'Fecha inválida']);
    exit;
}

// Borrar registro
$stmt = $PDOconnection->prepare("DELETE FROM time_worked WHERE id = ? AND user_id = ?");
if ($stmt->execute([$id, $userId])) {
    // Devolver los registros actualizados de la fecha seleccionada
    $stmt2 = $PDOconnection->prepare("
        SELECT tw.id, cs.id AS client_service_id, c.name AS client_name, s.name AS service_name, tw.minutes, cs.comment
        FROM time_worked tw
        INNER JOIN client_service cs ON tw.client_service_id = cs.id
        INNER JOIN client c ON cs.client_id = c.id
        INNER JOIN service s ON cs.service_id = s.id
        WHERE tw.user_id = ? AND tw.date = ?
        ORDER BY c.name, s.name
    ");
    $stmt2->execute([$userId, $date]);
    $entries = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'entries' => $entries]);
} else {
    echo json_encode(['error' => 'Error al borrar registro']);
}

