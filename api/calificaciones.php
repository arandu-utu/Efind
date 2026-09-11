<?php
/**
 * E-Find — Calificaciones entre usuarios (cliente ↔ propietario)
 * GET  /api/calificaciones.php               → recibidas por el usuario logueado + promedio
 * POST /api/calificaciones.php { para_usuario_id, transaccion_id, puntos, comentario }
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../includes/db.php';
require_once '../includes/auth.php';

try {
    $db = db_connect();
    $db->exec("CREATE TABLE IF NOT EXISTS calificaciones (
        id INT AUTO_INCREMENT PRIMARY KEY, de_usuario_id INT NOT NULL, para_usuario_id INT NOT NULL,
        transaccion_id INT, puntos TINYINT NOT NULL, comentario TEXT,
        tipo VARCHAR(30) NOT NULL, fecha DATE NOT NULL
    )");
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
    if (!$para || $pts < 1 || $pts > 5) throw new Exception('Datos inválidos: falta destinatario o puntuación.');

    $db->prepare("INSERT INTO calificaciones (de_usuario_id, para_usuario_id, transaccion_id, puntos, comentario, tipo, fecha)
                  VALUES (:de, :para, :tx, :pts, :com, 'cliente_a_propietario', CURDATE())")
       ->execute([':de'=>$u['id'], ':para'=>$para, ':tx'=>$tx ?: null, ':pts'=>$pts, ':com'=>$com]);

    echo json_encode(['ok' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
