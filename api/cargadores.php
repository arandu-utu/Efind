<?php
/**
 * E-Find — Alta de puntos de carga
 * POST /api/cargadores.php  → crear cargador + sus conectores (usuario autenticado)
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../includes/db.php';
require_once '../includes/auth.php';

try {
    $db     = db_connect();
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
        exit;
    }

    requiere_login();
    $u = usuario_actual();

    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $nombre       = trim($body['nombre'] ?? '');
    $direccion    = trim($body['direccion'] ?? '');
    $ciudad       = trim($body['ciudad'] ?? '') ?: null;
    $departamento = trim($body['departamento'] ?? '') ?: null;
    $descripcion  = trim($body['descripcion'] ?? '') ?: null;
    $horario      = trim($body['horario'] ?? '') ?: null;
    $costo_kwh    = (float)($body['costo_kwh'] ?? 0);
    $lat          = $body['lat'] ?? null;
    $lng          = $body['lng'] ?? null;
    $acceso       = $body['acceso'] ?? 'publico';
    $conectores   = $body['conectores'] ?? [];

    if ($nombre === '' || $direccion === '' || $lat === null || $lng === null)
        throw new Exception('Faltan datos obligatorios: nombre, dirección y ubicación.');

    if (!in_array($acceso, ['publico', 'privado'], true))
        throw new Exception('Acceso inválido: debe ser "publico" o "privado".');

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
        $conectoresResueltos[] = [
            'tipo_conector_id' => $tiposMap[$tipoNombre],
            'potencia_kw'      => (float)($c['potencia'] ?? 0),
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
