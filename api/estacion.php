<?php
/**
 * E-Find — Detalle de una estación
 * GET /api/estacion.php?id=N
 */
header('Content-Type: application/json');
require_once '../includes/db.php';

try {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { echo json_encode(['ok' => false, 'error' => 'ID requerido']); exit; }

    $pdo  = db_connect();
    $stmt = $pdo->prepare("SELECT * FROM puntos_carga WHERE id = ? AND activo = 1");
    $stmt->execute([$id]);
    $estacion = $stmt->fetch();

    if (!$estacion) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Estación no encontrada']); exit;
    }

    $stmt = $pdo->prepare(
        "SELECT c.id, c.estado, c.potencia_kw,
                tc.nombre AS tipo, tc.carga_rapida
         FROM conectores c
         JOIN tipos_conector tc ON tc.id = c.tipo_conector_id
         WHERE c.punto_carga_id = ?"
    );
    $stmt->execute([$id]);
    $estacion['conectores'] = $stmt->fetchAll();

    echo json_encode(['ok' => true, 'data' => $estacion]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
