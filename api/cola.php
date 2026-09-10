<?php
/**
 * E-Find — Cola de espera en tiempo real
 * PATCH /api/cola.php  (usuario autenticado, sin moderación de admin)
 *
 * A diferencia de reportes.php, esto es un dato crowdsourced "en vivo":
 * cualquier usuario logueado lo actualiza y se refleja al instante,
 * sin pasar por revisión. Se pisa con cada actualización (último que
 * reporta, gana) — es una aproximación simple, no un promedio.
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../includes/db.php';
require_once '../includes/auth.php';

try {
    $db     = db_connect();
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method !== 'PATCH' && $method !== 'PUT') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
        exit;
    }

    requiere_login();

    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = (int)($body['punto_carga_id'] ?? 0);
    $cola = (int)($body['cola'] ?? -1);

    if (!$id) throw new Exception('punto_carga_id requerido.');
    if ($cola < 0 || $cola > 3) throw new Exception('Valor de cola inválido (0 a 3).');

    $stmt = $db->prepare("UPDATE puntos_carga SET cola = :cola WHERE id = :id");
    $stmt->execute([':cola' => $cola, ':id' => $id]);

    echo json_encode(['ok' => true, 'data' => ['cola' => $cola]]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
