<?php
session_start();
require_once '../../utility/auth.php';
// Solo admin !!! Sale si no tiene rol, o no es el rol correcto
auth::needs_role(['admin','moderator']);

// Si se ha enviado el POST ->
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Conecta con la base de datos
    require_once '../time_hours_connection.php';

    $clientId = $_POST['client'];
    $services = $_POST['services'] ?? [];
    
    $addedCount = 0;
    $errorCount = 0;

    foreach ($services as $serviceId => $data) {
        // Solo procesar si está marcado el checkbox 'selected'
        if (!isset($data['selected'])) {
            continue;
        }

        // Verificar si ya existe
        $stmt = $PDOconnection->prepare(
            "SELECT id FROM client_service WHERE service_id = ? AND client_id = ?"
        );
        $stmt->execute([$serviceId, $clientId]);
        
        if (!$stmt->fetch()) {
            $stmtInsert = $PDOconnection->prepare(
                "INSERT INTO client_service
                 (service_id, client_id, cost_per_month, cost_per_hour)
                 VALUES (?,?,?,?)"
            );

            $stmtInsert->execute([
                $serviceId,
                $clientId,
                !empty($data['price_per_month']) ? $data['price_per_month'] : 0,
                !empty($data['price_per_hour']) ? $data['price_per_hour'] : 0
            ]);
            $addedCount++;
        } else {
            $errorCount++;
        }
    }

    if ($addedCount > 0) {
        $_SESSION['success'] = "Se han añadido $addedCount servicios correctamente.";
    }
    
    if ($errorCount > 0) {
        $msg = "$errorCount servicios ya estaban asignados.";
        $_SESSION['error'] = isset($_SESSION['success']) ? $_SESSION['success'] . " " . $msg : $msg;
    }
    
    if ($addedCount === 0 && $errorCount === 0) {
        $_SESSION['error'] = "No has seleccionado ningún servicio.";
    }

    header("Location: ./client.php");
}
