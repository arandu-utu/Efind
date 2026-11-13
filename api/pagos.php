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
require_once '../includes/errores.php';

const RENDIMIENTO_CARGA = 0.92;   // mismo valor que js/estimador.js
const COMISION          = 0.10;   // 10 % para la plataforma

try {
    $db = db_connect();
    requiere_login();
    $u = usuario_actual();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405); echo json_encode(['ok'=>false,'error'=>'Método no permitido.']); exit;
    }

    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) $body = [];
    $pcid = (int)($body['punto_carga_id'] ?? 0);
    $vid  = (int)($body['vehiculo_id'] ?? 0);
    $cid  = (int)($body['conector_id'] ?? 0);
    if (isset($body['soc_pct']) && !is_numeric($body['soc_pct'])) throw new Exception('El estado de carga no es un número.', 400);
    $soc  = max(0, min(100, (float)($body['soc_pct'] ?? 0)));

    if (!$pcid) throw new Exception('Falta el punto de carga.', 400);
    if (!$vid)  throw new Exception('Elegí un vehículo antes de confirmar la carga.', 400);
    if (!$cid)  throw new Exception('Elegí un conector antes de confirmar la carga.', 400);

    /* El vehículo tiene que ser del usuario autenticado. La consulta liga
       ambas condiciones, así que un id ajeno simplemente no devuelve fila. */
    $stmt = $db->prepare("SELECT capacidad_bateria_kwh FROM vehiculos
                          WHERE id = :vid AND usuario_id = :uid AND activo = 1");
    $stmt->execute([':vid' => $vid, ':uid' => $u['id']]);
    $veh = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$veh) throw new Exception('El vehículo no existe o no está registrado a tu nombre.', 404);

    $cap = (float)$veh['capacidad_bateria_kwh'];
    if ($cap <= 0) throw new Exception('El vehículo no tiene una capacidad de batería válida.', 422);

    $stmt = $db->prepare("SELECT nombre, costo_kwh, propietario_id, acceso, fuente, estado
                          FROM puntos_carga
                          WHERE id = :id AND activo = 1");
    $stmt->execute([':id' => $pcid]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$p) throw new Exception('El punto de carga no existe o no está disponible.', 404);

    /* Sólo se reservan los cargadores dados de alta por un particular o una
       empresa. Los de la red UTE no los opera E-Find, así que no se pueden
       reservar ni cobrar por adelantado. La ficha ya oculta el botón, pero
       eso es comodidad de la interfaz: la restricción se decide acá, porque
       de otro modo alcanza con escribir la dirección de reserva a mano. */
    if ($p['fuente'] === 'ute' || $p['acceso'] === 'publico')
        throw new Exception('Este cargador no admite reserva: sólo se reservan los cargadores privados dados de alta en E-Find.', 403);

    /* El dueño no puede pagarse a sí mismo: inflaría su facturación y la comisión. */
    if ($p['propietario_id'] !== null && (int)$p['propietario_id'] === (int)$u['id'])
        throw new Exception('No podés reservar tu propio cargador.', 403);
    if ($p['estado'] === 'sin_servicio')
        throw new Exception('El cargador está fuera de servicio.', 409);

    /* El conector tiene que pertenecer a esta estación, no a otra cualquiera. */
    $stmt = $db->prepare("SELECT potencia_kw, estado FROM conectores
                          WHERE id = :cid AND punto_carga_id = :pcid");
    $stmt->execute([':cid' => $cid, ':pcid' => $pcid]);
    $con = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$con) throw new Exception('El conector no corresponde a este punto de carga.', 404);
    if ($con['estado'] === 'sin_servicio') throw new Exception('El conector está fuera de servicio.', 409);

    $pot = (float)$con['potencia_kw'];
    if ($pot <= 0) throw new Exception('El conector no tiene una potencia válida.', 422);

    /* Cálculo, con la misma fórmula del estimador del cliente: el tiempo sale de
       la energía sin redondear, y se redondea el total en minutos antes de separar
       horas y minutos (si no, aparecía «5 h 60 min»). */
    $ener  = $cap * (1 - $soc / 100);
    $kwh   = round($ener, 1);
    $horas = $ener / ($pot * RENDIMIENTO_CARGA);
    $min = (int)round($horas * 60); $h = intdiv($min, 60); $m = $min % 60;
    $tiempo = $horas < 1/60 ? 'Batería completa' : ($h == 0 ? "$m min" : ($m == 0 ? "$h h" : "$h h $m min"));

    $subtotal = round($kwh * (float)$p['costo_kwh'], 2);
    $comision = round($subtotal * COMISION, 2);
    /* random_bytes en vez de rand(): el recibo identifica la operación ante el
       usuario y no debe poder repetirse ni adivinarse. El índice único de la
       migración 003 cierra la carrera que esto solo no cierra. */
    $recibo   = 'EF-' . time() . strtoupper(bin2hex(random_bytes(3)));

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

} catch (Throwable $e) {
    responder_error($e);
}
