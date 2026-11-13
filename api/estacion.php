<?php
/**
 * E-Find — Detalle de una estación
 * GET /api/estacion.php?id=N
 */
header('Content-Type: application/json');
require_once '../includes/db.php';
require_once '../includes/errores.php';

try {
    $id = (int)($_GET['id'] ?? 0);
    if ($id < 1) throw new Exception('ID requerido', 400);

    $pdo  = db_connect();
    $stmt = $pdo->prepare("SELECT id, nombre, descripcion, direccion, ciudad, departamento, horario, costo_kwh,
                lat, lng, acceso, estado, cola, fuente, propietario_id
         FROM puntos_carga WHERE id = ? AND activo = 1");
    $stmt->execute([$id]);
    $estacion = $stmt->fetch();

    if (!$estacion) throw new Exception('Estación no encontrada', 404);

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

} catch (Throwable $e) {
    responder_error($e);
}
