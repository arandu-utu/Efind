<?php
/**
 * E-Find — Sincronización manual de cargadores UTE
 * POST /api/ute-sync.php  (solo admin)
 *
 * Trae el listado público de UTE y lo guarda como filas reales en
 * puntos_carga/conectores (fuente='ute'), para dejar de depender de
 * una consulta en vivo cada vez que alguien abre el mapa.
 *
 * Es idempotente: vuelve a correrla cuando quieras y actualiza
 * (no duplica) las estaciones UTE ya guardadas, matcheando por nombre.
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../includes/db.php';
require_once '../includes/auth.php';

const UTE_API_URL = 'https://movilidad.ute.com.uy/api/v1/station/status/map';

/* UTE usa nombres propios para los tipos de conector; los mapeamos
   a los nombres reales de tipos_conector. GB/T sin distinción AC/DC
   la tratamos como DC (son cargadores rápidos públicos). */
const TIPO_MAP = [
    'CCS2'   => 'CCS2',
    'Tipo 2' => 'Type 2',
    'GB/T'   => 'GB/T DC',
];

const STATUS_MAP = [
    'Disponible'    => 'disponible',
    'Available'     => 'disponible',
    'Cargando'      => 'ocupado',
    'Busy'          => 'ocupado',
    'Occupied'      => 'ocupado',
    'FueraServicio' => 'sin_servicio',
    'OutOfService'  => 'sin_servicio',
    'Sin servicio'  => 'sin_servicio',
    'Offline'       => 'sin_servicio',
];

function normalizarEstado($s) {
    return STATUS_MAP[$s] ?? 'sin_servicio';
}

function calcularEstadoEstacion($conectores) {
    if (!count($conectores)) return 'sin_servicio';
    $estados = array_map(fn($c) => normalizarEstado($c['statusDetail'] ?? ''), $conectores);
    if (in_array('disponible', $estados, true)) return 'disponible';
    if (count(array_unique($estados)) === 1 && $estados[0] === 'sin_servicio') return 'sin_servicio';
    return 'ocupado';
}

try {
    $db = db_connect();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok' => false, 'error' => 'Método no permitido.']); exit; }

    requiere_rol(1);

    $ctx = stream_context_create([
        'http' => ['method' => 'GET', 'header' => "Accept: application/json\r\nUser-Agent: EFind/1.0\r\n", 'timeout' => 15],
        'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $body = @file_get_contents(UTE_API_URL, false, $ctx);
    if ($body === false) throw new Exception('No se pudo contactar la API de UTE.');

    $json = json_decode($body, true);
    if (!is_array($json['data'] ?? null)) throw new Exception('Respuesta inválida de la API de UTE.');

    $creados = 0;
    $actualizados = 0;
    $omitidos = 0;

    $stmtInsert = $db->prepare("
        INSERT INTO puntos_carga
            (nombre, descripcion, direccion, ciudad, departamento, lat, lng,
             acceso, estado, fuente, propietario_id, verificado, activo)
        VALUES
            (:nombre, NULL, :direccion, :ciudad, :departamento, :lat, :lng,
             'publico', :estado, 'ute', NULL, 1, 1)
    ");
    $stmtUpdate = $db->prepare("
        UPDATE puntos_carga
        SET direccion = :direccion, ciudad = :ciudad, departamento = :departamento,
            lat = :lat, lng = :lng, estado = :estado
        WHERE id = :id
    ");
    $stmtBorrarConectores = $db->prepare("DELETE FROM conectores WHERE punto_carga_id = :id");
    $stmtInsertConector    = $db->prepare("
        INSERT INTO conectores (punto_carga_id, tipo_conector_id, potencia_kw, estado)
        VALUES (:pcid, :tipo_id, :potencia, :estado)
    ");

    $tiposStmt = $db->query("SELECT id, nombre FROM tipos_conector");
    $tiposMap  = [];
    foreach ($tiposStmt->fetchAll(PDO::FETCH_ASSOC) as $t) {
        $tiposMap[$t['nombre']] = (int)$t['id'];
    }

    /* Precargar estaciones UTE existentes (nombre -> id) para no hacer un
       SELECT por estación dentro del foreach de abajo. */
    $existentesStmt = $db->query("SELECT id, nombre FROM puntos_carga WHERE fuente = 'ute'");
    $existentesMap  = [];
    foreach ($existentesStmt->fetchAll(PDO::FETCH_ASSOC) as $e) {
        $existentesMap[$e['nombre']] = (int)$e['id'];
    }

    $db->beginTransaction();

    foreach ($json['data'] as $raw) {
        $nombre = trim($raw['name'] ?? '');
        if ($nombre === '' || !isset($raw['lat'], $raw['lng'])) { $omitidos++; continue; }

        $conectoresRaw = $raw['connectorStatusAcc'] ?? [];
        $estado = calcularEstadoEstacion($conectoresRaw);

        $puntoCargaId = $existentesMap[$nombre] ?? null;

        if ($puntoCargaId !== null) {
            $stmtUpdate->execute([
                ':direccion'    => $raw['address'] ?? '',
                ':ciudad'       => $raw['city'] ?? null,
                ':departamento' => $raw['department'] ?? null,
                ':lat'          => $raw['lat'],
                ':lng'          => $raw['lng'],
                ':estado'       => $estado,
                ':id'           => $puntoCargaId,
            ]);
            $stmtBorrarConectores->execute([':id' => $puntoCargaId]);
            $actualizados++;
        } else {
            $stmtInsert->execute([
                ':nombre'       => $nombre,
                ':direccion'    => $raw['address'] ?? '',
                ':ciudad'       => $raw['city'] ?? null,
                ':departamento' => $raw['department'] ?? null,
                ':lat'          => $raw['lat'],
                ':lng'          => $raw['lng'],
                ':estado'       => $estado,
            ]);
            $puntoCargaId = (int)$db->lastInsertId();
            $creados++;
        }

        foreach ($conectoresRaw as $con) {
            $tipoUte = $con['type'] ?? '';
            $tipoNombre = TIPO_MAP[$tipoUte] ?? null;
            if ($tipoNombre === null || !isset($tiposMap[$tipoNombre])) continue; // tipo desconocido, se ignora
            $cantidad = max(1, (int)($con['count'] ?? 1));
            $estadoConector = normalizarEstado($con['statusDetail'] ?? '');
            for ($i = 0; $i < $cantidad; $i++) {
                $stmtInsertConector->execute([
                    ':pcid'     => $puntoCargaId,
                    ':tipo_id'  => $tiposMap[$tipoNombre],
                    ':potencia' => (float)($con['power'] ?? 0),
                    ':estado'   => $estadoConector,
                ]);
            }
        }
    }

    $db->commit();

    echo json_encode(['ok' => true, 'data' => [
        'creados'      => $creados,
        'actualizados' => $actualizados,
        'omitidos'     => $omitidos,
        'total_ute'    => count($json['data']),
    ]]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
