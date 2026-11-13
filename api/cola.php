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
require_once '../includes/sesion.php';
iniciar_sesion_segura();
header('Content-Type: application/json; charset=utf-8');
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/errores.php';
require_once '../includes/limite.php';

try {
    $db     = db_connect();
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method !== 'PATCH' && $method !== 'PUT') { http_response_code(405); echo json_encode(['ok' => false, 'error' => 'Método no permitido.']); exit; }

    requiere_login();
    $u = usuario_actual();

    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = (int)($body['punto_carga_id'] ?? 0);
    $cola = (int)($body['cola'] ?? -1);

    if (!$id) throw new Exception('punto_carga_id requerido.', 400);
    if ($cola < 0 || $cola > 3) throw new Exception('Valor de cola inválido (0 a 3).', 422);

    /* Igual que en estado.php: la existencia se verifica aparte, porque
       reportar el mismo valor que ya estaba no modifica ninguna fila. */
    $stmt = $db->prepare("SELECT acceso, propietario_id FROM puntos_carga WHERE id = :id AND activo = 1");
    $stmt->execute([':id' => $id]);
    $pc = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$pc) throw new Exception('El punto de carga no existe o no está disponible.', 404);

    /* En un cargador privado lo maneja su dueño o la moderación; en los
       públicos sigue reportando la comunidad, con límite de uso. */
    if ($pc['acceso'] === 'privado' && (int)$pc['propietario_id'] !== (int)$u['id'] && !in_array($u['rol_id'], ROLES_MODERACION))
        throw new Exception('Solo el propietario puede modificar un cargador privado.', 403);
    limitar($db, 'estado:u' . $u['id'], 30, 3600);

    $stmt = $db->prepare("UPDATE puntos_carga SET cola = :cola WHERE id = :id");
    $stmt->execute([':cola' => $cola, ':id' => $id]);

    echo json_encode(['ok' => true, 'data' => ['cola' => $cola]]);

} catch (Throwable $e) {
    responder_error($e);
}
