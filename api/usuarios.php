<?php
/**
 * E-Find — Gestión de usuarios
 * GET   /api/usuarios.php          → lista todos (admin)
 * PATCH /api/usuarios.php          → cambiar rol_id o activo (admin)
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../includes/db.php';
require_once '../includes/auth.php';

try {
    $db     = db_connect();
    $method = $_SERVER['REQUEST_METHOD'];

    /* ── GET: listar usuarios ──────────────────────────────────── */
    if ($method === 'GET') {
        requiere_rol(1);
        $stmt = $db->query("
            SELECT id, nombre, email, rol_id, activo, creado_en
            FROM   usuarios
            ORDER  BY creado_en DESC
        ");
        echo json_encode(['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

    /* ── PATCH: cambiar rol o estado ──────────────────────────── */
    } elseif ($method === 'PATCH' || $method === 'PUT') {
        requiere_rol(1);
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $id   = (int)($body['id'] ?? 0);

        if (!$id) throw new Exception('id requerido.');

        /* No permitir que el admin se modifique a sí mismo */
        if ($id === (int)usuario_actual()['id'])
            throw new Exception('No podés modificar tu propia cuenta.');

        if (array_key_exists('rol_id', $body)) {
            $rol_id = (int)$body['rol_id'];
            if (!in_array($rol_id, [1, 2, 3]))
                throw new Exception('rol_id inválido.');
            $stmt = $db->prepare("UPDATE usuarios SET rol_id = :r WHERE id = :id");
            $stmt->execute([':r' => $rol_id, ':id' => $id]);
        } elseif (array_key_exists('activo', $body)) {
            $activo = $body['activo'] ? 1 : 0;
            $stmt = $db->prepare("UPDATE usuarios SET activo = :a WHERE id = :id");
            $stmt->execute([':a' => $activo, ':id' => $id]);
        } else {
            throw new Exception('Nada que actualizar: falta rol_id o activo.');
        }

        echo json_encode(['ok' => true]);

    } else {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
