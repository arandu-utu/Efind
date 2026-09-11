<?php
/**
 * E-Find — Listado de estaciones (E-Find + UTE sincronizadas)
 * GET /api/estaciones.php
 * Devuelve todos los puntos de carga activos con sus conectores anidados.
 */
header('Content-Type: application/json; charset=utf-8');
require_once '../includes/db.php';

try {
    $db    = db_connect();
    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 1000) : 500;

    /* LIMIT se aplica sobre estaciones distintas (subquery), no sobre las
       filas planas estación×conector del join de abajo — si se aplicara
       después del join, una estación con varios conectores podía consumir
       el límite ella sola y truncar tanto la cantidad de estaciones
       devueltas como los conectores de la última estación incluida. */
    $rows = $db->query("
        SELECT p.id, p.nombre, p.descripcion, p.direccion, p.ciudad, p.departamento,
               p.lat, p.lng, p.acceso, p.estado, p.fuente, p.horario, p.costo_kwh,
               p.propietario_id,
               c.id AS conector_id, c.tipo_conector_id, c.potencia_kw, c.estado AS conector_estado,
               t.nombre AS conector_nombre
        FROM (
            SELECT id, nombre, descripcion, direccion, ciudad, departamento,
                   lat, lng, acceso, estado, fuente, horario, costo_kwh, propietario_id
            FROM   puntos_carga
            WHERE  activo = 1
            ORDER  BY id DESC
            LIMIT  $limit
        ) p
        LEFT JOIN conectores     c ON c.punto_carga_id  = p.id
        LEFT JOIN tipos_conector t ON t.id              = c.tipo_conector_id
        ORDER  BY p.id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    /* Agrupar filas planas en estaciones con array de conectores */
    $estaciones = [];
    foreach ($rows as $r) {
        $id = (int)$r['id'];
        if (!isset($estaciones[$id])) {
            $estaciones[$id] = [
                'id'             => $id,
                'nombre'         => $r['nombre'],
                'descripcion'    => $r['descripcion'],
                'direccion'      => $r['direccion'],
                'ciudad'         => $r['ciudad'],
                'departamento'   => $r['departamento'],
                'lat'            => (float)$r['lat'],
                'lng'            => (float)$r['lng'],
                'acceso'         => $r['acceso'],
                'estado'         => $r['estado'],
                'fuente'         => $r['fuente'],
                'horario'        => $r['horario'],
                'costo_kwh'      => (float)$r['costo_kwh'],
                'propietario_id' => $r['propietario_id'] !== null ? (int)$r['propietario_id'] : null,
                'conectores'     => [],
            ];
        }
        if ($r['conector_id'] !== null) {
            $estaciones[$id]['conectores'][] = [
                'id'       => (int)$r['conector_id'],
                'tipo'     => $r['conector_nombre'],
                'potencia' => (float)$r['potencia_kw'],
                'estado'   => $r['conector_estado'],
            ];
        }
    }

    echo json_encode(['ok' => true, 'data' => array_values($estaciones)]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
