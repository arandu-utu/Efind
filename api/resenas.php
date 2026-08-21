<?php
/**
 * E-Find — Reseñas de puntos de carga
 * GET   /api/resenas.php              → lista pendientes (admin)
 * POST  /api/resenas.php              → crear reseña (usuario autenticado)
 * PATCH /api/resenas.php              → moderar reseña (admin)
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../includes/db.php';
require_once '../includes/auth.php';

try {
    $db = db_connect();

    /* Crear tabla si no existe (sin FK para evitar problemas de charset/engine) */
    $db->exec("CREATE TABLE IF NOT EXISTS resenas (
        id             INT AUTO_INCREMENT PRIMARY KEY,
        punto_carga_id INT NOT NULL,
        usuario_id     INT NOT NULL,
        usuario_nombre VARCHAR(100) NOT NULL,
        estrellas      TINYINT NOT NULL,
        texto          TEXT,
        estado         ENUM('pendiente','aprobada','rechazada') DEFAULT 'pendiente',
        fecha_creacion DATE DEFAULT NULL
    )");

    $method = $_SERVER['REQUEST_METHOD'];

    /* ── GET: listar pendientes (admin) ──────────────────────── */
    if ($method === 'GET') {
        requiere_rol(1);
        $stmt = $db->query("
            SELECT r.*, p.nombre AS cargador_nombre
            FROM   resenas r
            JOIN   puntos_carga p ON p.id = r.punto_carga_id
            WHERE  r.estado = 'pendiente'
            ORDER  BY r.fecha_creacion DESC
        ");
        echo json_encode(['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

    /* ── POST: crear reseña ──────────────────────────────────── */
    } elseif ($method === 'POST') {
        requiere_login();
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $punto_carga_id = (int)($body['punto_carga_id'] ?? 0);
        $estrellas      = (int)($body['estrellas'] ?? 0);
        $texto          = trim($body['texto'] ?? '');

        if (!$punto_carga_id || $estrellas < 1 || $estrellas > 5)
            throw new Exception('Datos inválidos: falta punto_carga_id o estrellas.');

        $u = usuario_actual();
        $stmt = $db->prepare("
            INSERT INTO resenas (punto_carga_id, usuario_id, usuario_nombre, estrellas, texto, fecha_creacion)
            VALUES (:pcid, :uid, :nombre, :est, :txt, :fecha)
        ");
        $stmt->execute([
            ':pcid'   => $punto_carga_id,
            ':uid'    => $u['id'],
            ':nombre' => $u['nombre'],
            ':est'    => $estrellas,
            ':txt'    => $texto,
            ':fecha'  => date('Y-m-d'),
        ]);
        echo json_encode(['ok' => true, 'data' => ['id' => $db->lastInsertId()]]);

    /* ── PATCH: moderar (admin) ──────────────────────────────── */
    } elseif ($method === 'PATCH' || $method === 'PUT') {
        requiere_rol(1);
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $id     = (int)($body['id'] ?? 0);
        $estado = $body['estado'] ?? '';

        if (!$id || !in_array($estado, ['aprobada', 'rechazada']))
            throw new Exception('Datos inválidos: id y estado requeridos.');

        $stmt = $db->prepare("UPDATE resenas SET estado = :estado WHERE id = :id");
        $stmt->execute([':estado' => $estado, ':id' => $id]);
        echo json_encode(['ok' => true]);

    } else {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
