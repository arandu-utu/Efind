<?php
/**
 * E-Find — Procesa un pago simulado y emite el recibo (crea la transacción)
 * POST /api/pagos.php  { punto_carga_id, capacidad_kwh, soc_pct, potencia_kw }
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../includes/db.php';
require_once '../includes/auth.php';

try {
    $db = db_connect();
    $db->exec("CREATE TABLE IF NOT EXISTS transacciones (
        id INT AUTO_INCREMENT PRIMARY KEY, usuario_id INT NOT NULL, punto_carga_id INT NOT NULL,
        propietario_id INT, recibo VARCHAR(30) NOT NULL, kwh DECIMAL(6,2) NOT NULL,
        tiempo VARCHAR(20), monto_total DECIMAL(10,2) NOT NULL DEFAULT 0,
        comision DECIMAL(10,2) NOT NULL DEFAULT 0, calificado TINYINT(1) NOT NULL DEFAULT 0,
        fecha DATE NOT NULL
    )");
    requiere_login();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false,'error'=>'Método no permitido.']); exit; }

    $u    = usuario_actual();
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $pcid = (int)($body['punto_carga_id'] ?? 0);
    $cap  = (float)($body['capacidad_kwh'] ?? 0);
    $soc  = max(0, min(100, (float)($body['soc_pct'] ?? 0)));
    $pot  = max(0.1, (float)($body['potencia_kw'] ?? 7.4));
    if (!$pcid || $cap <= 0) throw new Exception('Datos de carga inválidos.');

    $stmt = $db->prepare("SELECT nombre, costo_kwh, propietario_id FROM puntos_carga WHERE id = ?");
    $stmt->execute([$pcid]);
    $p = $stmt->fetch();
    if (!$p) throw new Exception('Cargador no encontrado.');

    $kwh   = round($cap * (1 - $soc / 100), 1);
    $horas = $kwh / ($pot * 0.92);
    $h = floor($horas); $m = round(($horas - $h) * 60);
    $tiempo = $horas < 1/60 ? 'Batería completa' : ($h == 0 ? "$m min" : ($m == 0 ? "$h h" : "$h h $m min"));
    $costo    = (float)$p['costo_kwh'];
    $subtotal = round($kwh * $costo, 2);
    $comision = round($subtotal * 0.10, 2);
    $recibo   = 'EF-' . time() . rand(100, 999);

    $stmt = $db->prepare("INSERT INTO transacciones
        (usuario_id, punto_carga_id, propietario_id, recibo, kwh, tiempo, monto_total, comision, fecha)
        VALUES (:uid, :pcid, :prop, :recibo, :kwh, :tiempo, :total, :com, CURDATE())");
    $stmt->execute([
        ':uid'=>$u['id'], ':pcid'=>$pcid, ':prop'=>$p['propietario_id'], ':recibo'=>$recibo,
        ':kwh'=>$kwh, ':tiempo'=>$tiempo, ':total'=>$subtotal, ':com'=>$comision,
    ]);

    echo json_encode(['ok'=>true, 'data'=>[
        'id'=>(int)$db->lastInsertId(), 'recibo'=>$recibo, 'cargador_nombre'=>$p['nombre'],
        'kwh'=>$kwh, 'tiempo'=>$tiempo, 'monto_total'=>$subtotal, 'comision'=>$comision,
        'propietario_id'=>$p['propietario_id'] !== null ? (int)$p['propietario_id'] : null,
    ]]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
