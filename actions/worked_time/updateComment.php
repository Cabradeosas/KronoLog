<?php
session_start();
require '../time_hours_connection.php';
require '../../utility/auth.php';

auth::needs_role(['admin', 'moderator', 'user']);

if (!isset($_POST['id'], $_POST['message'])) {
    echo json_encode(['success' => false, 'error' => 'Faltan datos']);
    exit;
}

$userId = $_SESSION['id'] ?? null;
$workedId = $_POST['id'];
$message = $_POST['message'];

// Actualizar el message directamente en time_worked
$stmt = $PDOconnection->prepare("UPDATE time_worked SET message = ? WHERE id = ?");
if ($stmt->execute([$message, $workedId])) {
    $date = $_POST['date'] ?? null;
    $entries = [];
    if($date) {
        $stmt2 = $PDOconnection->prepare("
            SELECT tw.id, cs.id AS client_service_id, c.name AS client_name, s.name AS service_name, tw.minutes, tw.message
            FROM time_worked tw
            INNER JOIN client_service cs ON tw.client_service_id = cs.id
            INNER JOIN client c ON cs.client_id = c.id
            INNER JOIN service s ON cs.service_id = s.id
            WHERE tw.user_id = ? AND tw.date = ?
            ORDER BY c.name, s.name
        ");
        $stmt2->execute([$userId, $date]);
        $entries = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    }
    echo json_encode(['success' => true, 'entries' => $entries]);
} else {
    echo json_encode(['success' => false, 'error' => 'Error al guardar la nota']);
}
