<?php
session_start();
require '../time_hours_connection.php';
require '../../utility/auth.php';

auth::needs_role(['admin', 'moderator','user']);

// ID de usuario desde la sesión
$userId = $_SESSION['id'] ?? null;
if (!$userId) {
    http_response_code(403);
    echo json_encode(['error'=>'No estás autenticado']);
    exit;
}

// Comprobar si el usuario está activo
$stmt = $PDOconnection->prepare("
    SELECT * FROM user_status 
    WHERE user_id = ? 
    ORDER BY start_date DESC LIMIT 1
");
$stmt->execute([$userId]);
$status = $stmt->fetch(PDO::FETCH_ASSOC);

$currentStatus = (!$status || $status['end_date'] !== null) ? 'active' : $status['motive'];

if ($currentStatus !== 'active') {
    http_response_code(403);
    echo json_encode(['error'=>'No puedes añadir trabajo si no estás activo']);
    exit;
}

// Recoger datos POST
$clientServiceId = $_POST['client_service'] ?? null;
$minutes = (int) ($_POST['minutes'] ?? 0);
$date = $_POST['date'] ?? date('Y-m-d'); // <-- tomar la fecha elegida o hoy
$message = trim($_POST['message'] ?? '');

// Validar fecha
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['error'=>'Fecha inválida']);
    exit;
}

if (!$clientServiceId || $minutes <= 0) {
    http_response_code(400);
    echo json_encode(['error'=>'Datos inválidos']);
    exit;
}

// Comprobar si ya hay un registro para la fecha
$stmt = $PDOconnection->prepare("
    SELECT id, minutes FROM time_worked
    WHERE user_id = ? AND client_service_id = ? AND date = ?
");
$stmt->execute([$userId, $clientServiceId, $date]);
$existing = $stmt->fetch(PDO::FETCH_ASSOC);

try {
    if ($existing) {
        // Actualizar sumando minutos
        $stmtUpdate = $PDOconnection->prepare("
            UPDATE time_worked
            SET minutes = minutes + ?, message = ?
            WHERE id = ?
        ");
        $stmtUpdate->execute([$minutes, $message, $existing['id']]);
    } else {
        // Insertar nuevo registro
        $stmtInsert = $PDOconnection->prepare("
            INSERT INTO time_worked(user_id, client_service_id, date, minutes, message)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmtInsert->execute([$userId, $clientServiceId, $date, $minutes, $message]);
    }

// Devolver todos los registros de la fecha incluyendo el comentario
$stmt = $PDOconnection->prepare("
    SELECT tw.id, cs.id AS client_service_id, c.name AS client_name, s.name AS service_name, tw.minutes, tw.message
    FROM time_worked tw
    INNER JOIN client_service cs ON tw.client_service_id = cs.id
    INNER JOIN client c ON cs.client_id = c.id
    INNER JOIN service s ON cs.service_id = s.id
    WHERE tw.user_id = ? AND tw.date = ?
    ORDER BY c.name, s.name
");
$stmt->execute([$userId, $date]);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);


    echo json_encode([
        'success' => true,
        'entries' => $entries
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error'=>'Error al guardar el registro: '.$e->getMessage()]);
}
