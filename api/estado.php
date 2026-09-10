<?php
/**
 * E-Find — Estado del cargador en tiempo real
 * PATCH /api/estado.php  (usuario autenticado, sin moderación de admin)
 *
 * Igual que cola.php: dato crowdsourced en vivo, se pisa con cada
 * actualización. Distinto de reportes.php, que sí requiere revisión
 * de un admin (para vandalismo, información incorrecta, etc.).
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../includes/db.php';
require_once '../includes/auth.php';

const ESTADOS_VALIDOS = ['disponible', 'ocupado', 'sin_servicio'];

try {
    $db     = db_connect();
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method !== 'PATCH' && $method !== 'PUT') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
        exit;
    }

    requiere_login();

    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $id     = (int)($body['punto_carga_id'] ?? 0);
    $estado = $body['estado'] ?? '';

    if (!$id) throw new Exception('punto_carga_id requerido.');
    if (!in_array($estado, ESTADOS_VALIDOS, true)) throw new Exception('Estado inválido.');

    $stmt = $db->prepare("UPDATE puntos_carga SET estado = :estado WHERE id = :id");
    $stmt->execute([':estado' => $estado, ':id' => $id]);

    echo json_encode(['ok' => true, 'data' => ['estado' => $estado]]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
