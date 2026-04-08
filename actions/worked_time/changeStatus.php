<?php
session_start();
require '../KronoLog_connection.php';
require '../../utility/auth.php';
auth::needs_role(['admin', 'moderator','user']);

header('Content-Type: application/json; charset=utf-8');

$userId = $_SESSION['id'] ?? null;
if (!$userId) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$motive = $_POST['motive'] ?? null;
if (!in_array($motive, ['active', 'vacation', 'absent'], true)) {
    echo json_encode(['error' => 'Estado inválido']);
    exit;
}

try {
    $PDOconnection->beginTransaction();

    // 1. Obtener último estado abierto
    $stmt = $PDOconnection->prepare("
        SELECT id, motive 
        FROM user_status 
        WHERE user_id = ? AND end_date IS NULL
        ORDER BY start_date DESC
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Si cambia el estado, cerrar el anterior
    if ($current && $current['motive'] !== $motive) {
        $stmt = $PDOconnection->prepare("
            UPDATE user_status 
            SET end_date = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$current['id']]);
    }

    // 3. Insertar nuevo estado SOLO si no es active
    if ($motive !== 'active' && (!$current || $current['motive'] !== $motive)) {
        $stmt = $PDOconnection->prepare("
            INSERT INTO user_status (user_id, motive, start_date)
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$userId, $motive]);
    }

    // 4. Actualizar estado actual en tabla user
    $stmt = $PDOconnection->prepare("
        UPDATE user 
        SET status = ? 
        WHERE id = ?
    ");
    $stmt->execute([$motive, $userId]);

    $PDOconnection->commit();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $PDOconnection->rollBack();
    echo json_encode(['error' => $e->getMessage()]);
}

