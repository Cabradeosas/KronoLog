<?php
require '../time_hours_connection.php';
error_reporting(0); // Evita que warnings rompan el JSON
header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['client_id'])) {
    http_response_code(400);
    echo json_encode([]);
    exit;
}

$clientId = (int) $_GET['client_id'];
$mode = $_GET['mode'] ?? 'add';

if ($mode === 'add') {
    // Devuelve solo servicios que el cliente NO tiene
    $stmt = $PDOconnection->prepare("
        SELECT s.id, s.name
        FROM service AS s
        WHERE s.id NOT IN (
            SELECT service_id
            FROM client_service
            WHERE client_id = ?
        )
    ");
} elseif ($mode === 'hours') {
    // Devuelve servicios asignados con el ID de la relación (client_service.id)
    // Esto es necesario para el formulario de añadir horas
    $stmt = $PDOconnection->prepare("
        SELECT cs.id, s.name
        FROM service AS s
        INNER JOIN client_service AS cs ON s.id = cs.service_id
        WHERE cs.client_id = ?
        ORDER BY s.name ASC
    ");
} else {
    // Devuelve solo servicios que el cliente YA tiene
    $stmt = $PDOconnection->prepare("
        SELECT s.id, s.name
        FROM service AS s
        INNER JOIN client_service AS cs ON s.id = cs.service_id
        WHERE cs.client_id = ?
    ");
}

$stmt->execute([$clientId]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
