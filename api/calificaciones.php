<?php
/**
 * E-Find — Calificaciones entre usuarios (cliente ↔ propietario)
 * GET  /api/calificaciones.php               → recibidas por el usuario logueado + promedio
 * POST /api/calificaciones.php { para_usuario_id, transaccion_id, puntos, comentario }
 */
require_once '../includes/sesion.php';
iniciar_sesion_segura();
header('Content-Type: application/json; charset=utf-8');
require_once '../includes/db.php';
require_once '../includes/auth.php';

try {
    $db = db_connect();
    requiere_login();
    $u = usuario_actual();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $db->prepare("
            SELECT c.puntos, c.comentario, c.fecha, c.tipo, u.nombre AS de_nombre
            FROM   calificaciones c JOIN usuarios u ON u.id = c.de_usuario_id
            WHERE  c.para_usuario_id = :uid ORDER BY c.fecha DESC
        ");
        $stmt->execute([':uid' => $u['id']]);
        $cals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $prom = count($cals) ? round(array_sum(array_column($cals, 'puntos')) / count($cals), 1) : null;
        echo json_encode(['ok' => true, 'data' => ['promedio' => $prom, 'total' => count($cals), 'calificaciones' => $cals]]);
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false,'error'=>'Método no permitido.']); exit; }

    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $para = (int)($body['para_usuario_id'] ?? 0);
    $tx   = (int)($body['transaccion_id'] ?? 0);
    $pts  = (int)($body['puntos'] ?? 0);
    $com  = trim($body['comentario'] ?? '');

    if (!$tx)                   throw new Exception('Falta la transacción que se califica.');
    if ($pts < 1 || $pts > 5)   throw new Exception('La puntuación debe estar entre 1 y 5.');

    /* Una calificación sólo es válida si respalda una carga real del usuario.
       Sin esta verificación, el cliente podía enviar cualquier destinatario y
       repetir el envío, porque el endpoint insertaba lo que le mandaran. */
    $stmt = $db->prepare("SELECT propietario_id, calificado FROM transacciones
                          WHERE id = :id AND usuario_id = :uid");
    $stmt->execute([':id' => $tx, ':uid' => $u['id']]);
    $t = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$t)                                throw new Exception('La transacción no existe o no te pertenece.');
    if ((int)$t['calificado'] === 1)        throw new Exception('Esa carga ya fue calificada.');
    if ($t['propietario_id'] === null)      throw new Exception('Esa carga no tiene un propietario a calificar.');
    if ($para !== (int)$t['propietario_id']) throw new Exception('El destinatario no corresponde a esa transacción.');
    if ($para === (int)$u['id'])            throw new Exception('No podés calificarte a vos mismo.');

    /* Dos escrituras que deben ir juntas: si se registra la calificación pero
       la transacción no queda marcada, se podría calificar otra vez. */
    $db->beginTransaction();
    $db->prepare("INSERT INTO calificaciones (de_usuario_id, para_usuario_id, transaccion_id, puntos, comentario, tipo, fecha)
                  VALUES (:de, :para, :tx, :pts, :com, 'cliente_a_propietario', CURDATE())")
       ->execute([':de'=>$u['id'], ':para'=>$para, ':tx'=>$tx, ':pts'=>$pts, ':com'=>$com]);
    $db->prepare("UPDATE transacciones SET calificado = 1 WHERE id = :id AND usuario_id = :uid")
       ->execute([':id' => $tx, ':uid' => $u['id']]);
    $db->commit();

    echo json_encode(['ok' => true]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
