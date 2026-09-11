<?php
/**
 * E-Find — Historial de transacciones del usuario
 * GET   /api/reservas.php        → lista las propias (para perfil.html)
 * PATCH /api/reservas.php {id}   → marca una transacción propia como calificada
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../includes/db.php';
require_once '../includes/auth.php';

try {
    $db = db_connect();
    requiere_login();
    $u = usuario_actual();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $db->prepare("
            SELECT t.id, t.recibo, t.kwh, t.tiempo, t.monto_total, t.comision, t.fecha,
                   t.calificado, t.propietario_id, p.nombre AS cargador_nombre
            FROM   transacciones t
            JOIN   puntos_carga p ON p.id = t.punto_carga_id
            WHERE  t.usuario_id = :uid
            ORDER  BY t.id DESC
        ");
        $stmt->execute([':uid' => $u['id']]);
        echo json_encode(['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $id   = (int)($body['id'] ?? 0);
        if (!$id) throw new Exception('Falta el id.');
        $db->prepare("UPDATE transacciones SET calificado = 1 WHERE id = :id AND usuario_id = :uid")
           ->execute([':id' => $id, ':uid' => $u['id']]);
        echo json_encode(['ok' => true]);

    } else {
        http_response_code(405); echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
