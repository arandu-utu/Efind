<?php
/**
 * E-Find — Listado de estaciones propias
 * GET /api/estaciones.php
 */
header('Content-Type: application/json; charset=utf-8');
require_once '../includes/db.php';

try {
    $db    = db_connect();
    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 200) : 50;
    /* LIMIT inline (int ya validado, no hay riesgo de inyección) */
    $rows = $db->query("
        SELECT p.id, p.nombre, p.estado, p.acceso, p.lat, p.lng,
               GROUP_CONCAT(DISTINCT t.nombre ORDER BY t.nombre SEPARATOR ', ') AS tipos_conectores
        FROM   puntos_carga p
        LEFT JOIN conectores     c ON c.punto_carga_id  = p.id
        LEFT JOIN tipos_conector t ON t.id              = c.tipo_conector_id
        GROUP  BY p.id
        ORDER  BY p.id DESC
        LIMIT  $limit
    ")->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['ok' => true, 'data' => $rows]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
