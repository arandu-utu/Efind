<?php
/**
 * E-Find — Reportes de puntos de carga
 * GET   /api/reportes.php             → lista pendientes (admin)
 * POST  /api/reportes.php             → crear reporte (usuario autenticado)
 * PATCH /api/reportes.php             → resolver reporte (admin)
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../includes/db.php';
require_once '../includes/auth.php';

try {
    $db     = db_connect();
    $method = $_SERVER['REQUEST_METHOD'];

    /* ── GET: listar pendientes (admin) ──────────────────────── */
    if ($method === 'GET') {
        requiere_rol(1);
        $stmt = $db->query("
            SELECT r.id, r.punto_carga_id, r.tipo, r.descripcion,
                   r.resuelto, r.creado_en,
                   p.nombre AS punto_carga_nombre,
                   u.nombre AS usuario_nombre
            FROM   reportes r
            LEFT JOIN puntos_carga p ON p.id = r.punto_carga_id
            LEFT JOIN usuarios     u ON u.id = r.usuario_id
            WHERE  r.resuelto = 0
            ORDER  BY r.creado_en DESC
        ");
        echo json_encode(['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

    /* ── POST: crear reporte (usuario autenticado) ───────────── */
    } elseif ($method === 'POST') {
        requiere_login();
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $punto_carga_id = (int)($body['punto_carga_id'] ?? 0);
        $tipo           = trim($body['tipo'] ?? '');
        $descripcion    = trim($body['descripcion'] ?? '');

        if (!$punto_carga_id || !$tipo)
            throw new Exception('Faltan campos requeridos: punto_carga_id y tipo.');

        $u = usuario_actual();
        $stmt = $db->prepare("
            INSERT INTO reportes (punto_carga_id, usuario_id, tipo, descripcion)
            VALUES (:pcid, :uid, :tipo, :desc)
        ");
        $stmt->execute([
            ':pcid' => $punto_carga_id,
            ':uid'  => $u['id'] ?? null,
            ':tipo' => $tipo,
            ':desc' => $descripcion,
        ]);
        echo json_encode(['ok' => true, 'data' => ['id' => $db->lastInsertId()]]);

    /* ── PATCH: resolver reporte (admin) ─────────────────────── */
    } elseif ($method === 'PATCH' || $method === 'PUT') {
        requiere_rol(1);
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $id  = (int)($body['id'] ?? 0);
        $uid = usuario_actual()['id'] ?? null;

        if (!$id)
            throw new Exception('Datos inválidos: id requerido.');

        $stmt = $db->prepare("UPDATE reportes SET resuelto = 1, resuelto_por = :uid WHERE id = :id");
        $stmt->execute([':uid' => $uid, ':id' => $id]);
        echo json_encode(['ok' => true]);

    } else {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
