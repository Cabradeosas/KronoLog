<?php
session_start();
require_once '../../utility/auth.php';
auth::needs_role(['admin','moderator']);
require_once '../KronoLog_connection.php';

header('Content-Type: application/json; charset=UTF-8');

$clientId = $_POST['client_id'] ?? null;
$name = trim($_POST['name'] ?? '');
$cif  = trim($_POST['cif'] ?? '');
$servicesPost = $_POST['services'] ?? [];

if(!$clientId || !$name || !$cif){
    echo json_encode(['success'=>false,'error'=>'Datos incompletos.']);
    exit;
}

try{
    $PDOconnection->beginTransaction();

    // Actualizar cliente
    $stmt = $PDOconnection->prepare("UPDATE client SET name=?, cif=? WHERE id=?");
    $stmt->execute([$name,$cif,$clientId]);

    // Actualizar servicios
    foreach($servicesPost as $csid => $s){
        if(!empty($s['delete'])){
            $stmt = $PDOconnection->prepare("DELETE FROM client_service WHERE id=?");
            $stmt->execute([$csid]);
        } else {
            $stmt = $PDOconnection->prepare(
                "UPDATE client_service 
                 SET cost_per_month=?, cost_per_hour=?, comment=? 
                 WHERE id=?"
            );
            $stmt->execute([
                !empty($s['cost_per_month']) ? $s['cost_per_month'] : 0,
                !empty($s['cost_per_hour']) ? $s['cost_per_hour'] : 0,
                $s['comment'] ?? '',
                $csid
            ]);
        }
    }

    $PDOconnection->commit();

    // Traer servicios actualizados
    $stmt = $PDOconnection->prepare("
        SELECT cs.id, s.name, cs.cost_per_month, cs.cost_per_hour, cs.comment
        FROM client_service cs
        INNER JOIN service s ON s.id = cs.service_id
        WHERE cs.client_id=?
    ");
    $stmt->execute([$clientId]);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'=>true,
        'services'=>$services,
        'client' => ['name' => $name, 'cif' => $cif]
    ]);

}catch(Exception $e){
    $PDOconnection->rollBack();
    $errorMsg = $e->getMessage();
    // Detectar error de duplicado (Código 1062 en MySQL)
    if (strpos($errorMsg, '1062') !== false) {
        $errorMsg = 'El CIF facilitado ya está en uso, por favor use otro o ponga un identificativo';
    }
    echo json_encode(['success'=>false,'error'=>$errorMsg]);
}

