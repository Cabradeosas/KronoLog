<?php
session_start();
require_once '../../utility/auth.php';
auth::needs_role(['admin','moderator','user']);

require_once '../KronoLog_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientId = (int) $_POST['client'];
    $serviceId = (int) $_POST['service'];
    $comment = trim($_POST['comment']);

    if (!$clientId || !$serviceId || $comment === '') {
        echo json_encode(['error' => 'Datos inválidos']);
        exit;
    }

    $stmt = $PDOconnection->prepare("UPDATE client_service SET comment = ? WHERE client_id = ? AND service_id = ?");
    $stmt->execute([$comment, $clientId, $serviceId]);

    // Obtener los servicios actualizados del cliente
    $stmt = $PDOconnection->prepare("
        SELECT s.name, cs.cost_per_month, cs.comment
        FROM client_service cs
        INNER JOIN service s ON s.id = cs.service_id
        WHERE cs.client_id = ?
    ");
    $stmt->execute([$clientId]);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'services' => $services, 'client_id' => $clientId]);
}

