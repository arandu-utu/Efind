<?php
/**
 * E-Find — Puntos de carga
 * POST  /api/cargadores.php        → crear cargador + sus conectores (usuario autenticado)
 * PATCH /api/cargadores.php {id}   → ocultar un cargador del mapa público (admin)
 *
 * La ocultación cumple la historia HU-05: retirar de la vista pública los
 * cargadores inexistentes o fraudulentos para que no puedan reservarse. Usa el
 * campo `activo` que ya existía, sin introducir un estado nuevo. No afecta al
 * historial: las transacciones registran cargas ya realizadas.
 */
require_once '../includes/sesion.php';
iniciar_sesion_segura();
header('Content-Type: application/json; charset=utf-8');
require_once '../includes/db.php';
require_once '../includes/auth.php';

try {
    $db     = db_connect();
    $method = $_SERVER['REQUEST_METHOD'];

    /* ── PATCH: ocultar del mapa público (HU-05) ──────────────── */
    if ($method === 'PATCH' || $method === 'PUT') {
        /* Hoy la moderación la ejerce el administrador. Cuando exista el rol
           Moderador como perfil propio, la guarda admite también ese rol. */
        requiere_rol(1);

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $id   = (int)($body['id'] ?? 0);
        if (!$id) throw new Exception('Falta el id del punto de carga.');

        $stmt = $db->prepare("SELECT nombre FROM puntos_carga WHERE id = :id AND activo = 1");
        $stmt->execute([':id' => $id]);
        $pc = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$pc) throw new Exception('El punto de carga no existe o ya estaba oculto.');

        $db->prepare("UPDATE puntos_carga SET activo = 0 WHERE id = :id")->execute([':id' => $id]);
        echo json_encode(['ok' => true, 'data' => ['nombre' => $pc['nombre']]]);
        exit;
    }

    if ($method !== 'POST') { http_response_code(405); echo json_encode(['ok' => false, 'error' => 'Método no permitido.']); exit; }

    requiere_login();
    $u = usuario_actual();

    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $nombre       = trim($body['nombre'] ?? '');
    $direccion    = trim($body['direccion'] ?? '');
    $ciudad       = trim($body['ciudad'] ?? '') ?: null;
    $departamento = trim($body['departamento'] ?? '') ?: null;
    $descripcion  = trim($body['descripcion'] ?? '') ?: null;
    $horario      = trim($body['horario'] ?? '') ?: null;
    $costo_kwh    = $body['costo_kwh'] ?? 0;
    $lat          = $body['lat'] ?? null;
    $lng          = $body['lng'] ?? null;
    $acceso       = $body['acceso'] ?? 'publico';
    $conectores   = $body['conectores'] ?? [];

    if ($nombre === '' || $direccion === '' || $lat === null || $lng === null)
        throw new Exception('Faltan datos obligatorios: nombre, dirección y ubicación.');

    if (!in_array($acceso, ['publico', 'privado'], true))
        throw new Exception('Acceso inválido: debe ser "publico" o "privado".');

    /* El formulario ya acota estos valores, pero eso es sólo comodidad de uso:
       un cliente puede enviar cualquier cosa. Un precio negativo, por ejemplo,
       produciría transacciones con monto y comisión negativos. */
    if (!is_numeric($costo_kwh) || !is_finite((float)$costo_kwh))
        throw new Exception('El precio por kWh debe ser un número.');
    $costo_kwh = round((float)$costo_kwh, 2);
    if ($costo_kwh < 0)
        throw new Exception('El precio por kWh no puede ser negativo.');
    /* Cero es válido y significa cargador gratuito. */

    if (!is_numeric($lat) || !is_numeric($lng) || !is_finite((float)$lat) || !is_finite((float)$lng))
        throw new Exception('Las coordenadas deben ser numéricas.');
    $lat = (float)$lat;
    $lng = (float)$lng;
    if ($lat < -90 || $lat > 90)     throw new Exception('Latitud fuera de rango: debe estar entre -90 y 90.');
    if ($lng < -180 || $lng > 180)   throw new Exception('Longitud fuera de rango: debe estar entre -180 y 180.');

    if (!is_array($conectores) || count($conectores) === 0)
        throw new Exception('Debés agregar al menos un conector.');

    /* Resolver nombre de conector -> tipo_conector_id */
    $tiposStmt = $db->query("SELECT id, nombre FROM tipos_conector");
    $tiposMap  = [];
    foreach ($tiposStmt->fetchAll(PDO::FETCH_ASSOC) as $t) {
        $tiposMap[$t['nombre']] = (int)$t['id'];
    }

    $conectoresResueltos = [];
    foreach ($conectores as $c) {
        $tipoNombre = trim($c['tipo'] ?? '');
        if (!isset($tiposMap[$tipoNombre]))
            throw new Exception("Tipo de conector desconocido: \"$tipoNombre\".");

        /* Se exige que sea positiva y que entre en la columna, que es
           DECIMAL(6,2). No se impone el tope de 350 kW del formulario porque
           no está definido como regla del proyecto en la documentación. */
        $potencia = $c['potencia'] ?? null;
        if (!is_numeric($potencia) || !is_finite((float)$potencia))
            throw new Exception("La potencia del conector \"$tipoNombre\" debe ser un número.");
        $potencia = round((float)$potencia, 2);
        if ($potencia <= 0 || $potencia >= 10000)
            throw new Exception("Potencia inválida para el conector \"$tipoNombre\".");

        $conectoresResueltos[] = [
            'tipo_conector_id' => $tiposMap[$tipoNombre],
            'potencia_kw'      => $potencia,
        ];
    }

    $db->beginTransaction();

    $stmt = $db->prepare("
        INSERT INTO puntos_carga
            (nombre, descripcion, direccion, ciudad, departamento, lat, lng,
             acceso, estado, fuente, propietario_id, verificado, activo, horario, costo_kwh)
        VALUES
            (:nombre, :descripcion, :direccion, :ciudad, :departamento, :lat, :lng,
             :acceso, 'disponible', 'efind', :propietario_id, 0, 1, :horario, :costo_kwh)
    ");
    $stmt->execute([
        ':nombre'         => $nombre,
        ':descripcion'    => $descripcion,
        ':direccion'      => $direccion,
        ':ciudad'         => $ciudad,
        ':departamento'   => $departamento,
        ':lat'            => $lat,
        ':lng'            => $lng,
        ':acceso'         => $acceso,
        ':propietario_id' => $u['id'],
        ':horario'        => $horario,
        ':costo_kwh'      => $costo_kwh,
    ]);

    $puntoCargaId = (int)$db->lastInsertId();

    $stmtConector = $db->prepare("
        INSERT INTO conectores (punto_carga_id, tipo_conector_id, potencia_kw, estado)
        VALUES (:pcid, :tipo_id, :potencia, 'disponible')
    ");
    foreach ($conectoresResueltos as $c) {
        $stmtConector->execute([
            ':pcid'     => $puntoCargaId,
            ':tipo_id'  => $c['tipo_conector_id'],
            ':potencia' => $c['potencia_kw'],
        ]);
    }

    $db->commit();

    echo json_encode(['ok' => true, 'data' => ['id' => $puntoCargaId]]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
