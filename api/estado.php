<?php
/**
 * E-Find — Estado del cargador en tiempo real
 * PATCH /api/estado.php  (usuario autenticado, sin moderación de admin)
 *
 * Igual que cola.php: dato crowdsourced en vivo, se pisa con cada
 * actualización. Distinto de reportes.php, que sí requiere revisión
 * de un admin (para vandalismo, información incorrecta, etc.).
 */
require_once '../includes/sesion.php';
iniciar_sesion_segura();
header('Content-Type: application/json; charset=utf-8');
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/errores.php';
require_once '../includes/limite.php';

const ESTADOS_VALIDOS = ['disponible', 'ocupado', 'sin_servicio'];

try {
    $db     = db_connect();
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method !== 'PATCH' && $method !== 'PUT') { http_response_code(405); echo json_encode(['ok' => false, 'error' => 'Método no permitido.']); exit; }

    requiere_login();
    $u = usuario_actual();

    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $id     = (int)($body['punto_carga_id'] ?? 0);
    $estado = $body['estado'] ?? '';

    if (!$id) throw new Exception('punto_carga_id requerido.', 400);
    if (!in_array($estado, ESTADOS_VALIDOS, true)) throw new Exception('Estado inválido.', 422);

    /* Se comprueba la existencia antes de actualizar. No alcanza con mirar las
       filas afectadas: reportar el mismo estado que ya tenía devuelve cero
       filas modificadas, que es indistinguible de un cargador inexistente. */
    $stmt = $db->prepare("SELECT acceso, propietario_id FROM puntos_carga WHERE id = :id AND activo = 1");
    $stmt->execute([':id' => $id]);
    $pc = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$pc) throw new Exception('El punto de carga no existe o no está disponible.', 404);

    /* En un cargador privado lo maneja su dueño o la moderación; en los
       públicos sigue reportando la comunidad, con límite de uso. */
    if ($pc['acceso'] === 'privado' && (int)$pc['propietario_id'] !== (int)$u['id'] && !in_array($u['rol_id'], ROLES_MODERACION))
        throw new Exception('Solo el propietario puede modificar un cargador privado.', 403);
    limitar($db, 'estado:u' . $u['id'], 30, 3600);

    $stmt = $db->prepare("UPDATE puntos_carga SET estado = :estado WHERE id = :id");
    $stmt->execute([':estado' => $estado, ':id' => $id]);

    echo json_encode(['ok' => true, 'data' => ['estado' => $estado]]);

} catch (Throwable $e) {
    responder_error($e);
}
