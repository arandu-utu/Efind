<?php
/**
 * E-Find — Procesa un pago simulado y emite el recibo (crea la transacción)
 * POST /api/pagos.php  { punto_carga_id, vehiculo_id, conector_id, soc_pct }
 *
 * Ninguna magnitud que determine el importe llega desde el navegador. La
 * capacidad sale del vehículo del usuario, la potencia del conector de esa
 * estación y el precio del propio punto de carga. Lo único que aporta el
 * cliente es el estado de carga actual, que no tiene forma de conocerse del
 * lado del servidor y sólo puede acotarse entre 0 y 100.
 */
require_once '../includes/sesion.php';
iniciar_sesion_segura();
header('Content-Type: application/json; charset=utf-8');
require_once '../includes/db.php';
require_once '../includes/auth.php';

const RENDIMIENTO_CARGA = 0.92;   // mismo valor que js/estimador.js
const COMISION          = 0.10;   // 10 % para la plataforma

try {
    $db = db_connect();
    requiere_login();
    $u = usuario_actual();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405); echo json_encode(['ok'=>false,'error'=>'Método no permitido.']); exit;
    }

    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $pcid = (int)($body['punto_carga_id'] ?? 0);
    $vid  = (int)($body['vehiculo_id'] ?? 0);
    $cid  = (int)($body['conector_id'] ?? 0);
    $soc  = max(0, min(100, (float)($body['soc_pct'] ?? 0)));

    if (!$pcid) throw new Exception('Falta el punto de carga.');
    if (!$vid)  throw new Exception('Elegí un vehículo antes de confirmar la carga.');
    if (!$cid)  throw new Exception('Elegí un conector antes de confirmar la carga.');

    /* El vehículo tiene que ser del usuario autenticado. La consulta liga
       ambas condiciones, así que un id ajeno simplemente no devuelve fila. */
    $stmt = $db->prepare("SELECT capacidad_bateria_kwh FROM vehiculos
                          WHERE id = :vid AND usuario_id = :uid AND activo = 1");
    $stmt->execute([':vid' => $vid, ':uid' => $u['id']]);
    $veh = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$veh) throw new Exception('El vehículo no existe o no está registrado a tu nombre.');

    $cap = (float)$veh['capacidad_bateria_kwh'];
    if ($cap <= 0) throw new Exception('El vehículo no tiene una capacidad de batería válida.');

    $stmt = $db->prepare("SELECT nombre, costo_kwh, propietario_id FROM puntos_carga
                          WHERE id = :id AND activo = 1");
    $stmt->execute([':id' => $pcid]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$p) throw new Exception('El punto de carga no existe o no está disponible.');

    /* El conector tiene que pertenecer a esta estación, no a otra cualquiera. */
    $stmt = $db->prepare("SELECT potencia_kw FROM conectores
                          WHERE id = :cid AND punto_carga_id = :pcid");
    $stmt->execute([':cid' => $cid, ':pcid' => $pcid]);
    $con = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$con) throw new Exception('El conector no corresponde a este punto de carga.');

    $pot = (float)$con['potencia_kw'];
    if ($pot <= 0) throw new Exception('El conector no tiene una potencia válida.');

    /* Cálculo, con la misma fórmula del estimador del cliente. */
    $kwh   = round($cap * (1 - $soc / 100), 1);
    $horas = $kwh / ($pot * RENDIMIENTO_CARGA);
    $h = floor($horas); $m = round(($horas - $h) * 60);
    $tiempo = $horas < 1/60 ? 'Batería completa' : ($h == 0 ? "$m min" : ($m == 0 ? "$h h" : "$h h $m min"));

    $subtotal = round($kwh * (float)$p['costo_kwh'], 2);
    $comision = round($subtotal * COMISION, 2);
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
