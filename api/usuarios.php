<?php
/**
 * E-Find — Gestión de usuarios
 * GET   /api/usuarios.php?pagina=1&por_pagina=20&rol=2&q=texto  → lista paginada (admin)
 * PATCH /api/usuarios.php          → cambiar rol_id o activo (admin)
 *
 * El filtrado y la paginación son del lado del servidor: si se filtrara en el
 * navegador sólo se filtraría la página visible, que es justamente lo que no
 * se quiere cuando hay muchos usuarios.
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

        $porPagina = min(max((int)($_GET['por_pagina'] ?? 20), 5), 100);
        $pagina    = max((int)($_GET['pagina'] ?? 1), 1);
        $offset    = ($pagina - 1) * $porPagina;

        $where = [];
        $params = [];
        if (isset($_GET['rol']) && $_GET['rol'] !== '') {
            $where[] = 'rol_id = :rol';
            $params[':rol'] = (int)$_GET['rol'];
        }
        $q = trim($_GET['q'] ?? '');
        if ($q !== '') {
            /* Se escapan % y _ para que el usuario los busque como texto
               literal en vez de como comodines de LIKE. */
            $where[] = '(nombre LIKE :q OR email LIKE :q)';
            $params[':q'] = '%' . addcslashes($q, '%_\\') . '%';
        }
        $filtro = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $db->prepare("SELECT COUNT(*) FROM usuarios $filtro");
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        /* LIMIT/OFFSET van interpolados porque MariaDB no admite placeholders
           ahí; son enteros ya acotados más arriba, no texto del usuario. */
        $stmt = $db->prepare("
            SELECT id, nombre, email, rol_id, activo, creado_en
            FROM   usuarios
            $filtro
            ORDER  BY creado_en DESC
            LIMIT  $porPagina OFFSET $offset
        ");
        $stmt->execute($params);

        echo json_encode(['ok' => true,
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'meta' => [
                'pagina'     => $pagina,
                'por_pagina' => $porPagina,
                'total'      => $total,
                'paginas'    => (int)ceil($total / $porPagina),
            ],
        ]);

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
        http_response_code(405); echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
