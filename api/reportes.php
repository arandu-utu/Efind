<?php
/**
 * E-Find — Reportes de puntos de carga
 * GET   /api/reportes.php             → lista pendientes (admin)
 * POST  /api/reportes.php             → crear reporte (usuario autenticado)
 * PATCH /api/reportes.php             → resolver reporte (admin)
 */
require_once '../includes/sesion.php';
iniciar_sesion_segura();
header('Content-Type: application/json; charset=utf-8');
require_once '../includes/db.php';
require_once '../includes/auth.php';

try {
    $db     = db_connect();
    $method = $_SERVER['REQUEST_METHOD'];

    /* ── GET: listar pendientes (admin) ──────────────────────── */
    if ($method === 'GET') {
        requiere_rol(1);

        $porPagina = min(max((int)($_GET['por_pagina'] ?? 20), 5), 100);
        $pagina    = max((int)($_GET['pagina'] ?? 1), 1);
        $offset    = ($pagina - 1) * $porPagina;

        $total = (int)$db->query("SELECT COUNT(*) FROM reportes WHERE resuelto = 0")->fetchColumn();

        /* Enteros ya acotados arriba: MariaDB no admite placeholders en LIMIT. */
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
            LIMIT  $porPagina OFFSET $offset
        ");

        echo json_encode(['ok' => true,
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'meta' => [
                'pagina'     => $pagina,
                'por_pagina' => $porPagina,
                'total'      => $total,
                'paginas'    => (int)ceil($total / $porPagina),
            ],
        ]);

    /* ── POST: crear reporte (usuario autenticado) ───────────── */
    } elseif ($method === 'POST') {
        requiere_login();
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $punto_carga_id = (int)($body['punto_carga_id'] ?? 0);
        $tipo           = trim($body['tipo'] ?? '');
        $descripcion    = trim($body['descripcion'] ?? '');

        if (!$punto_carga_id || !$tipo)
            throw new Exception('Faltan campos requeridos: punto_carga_id y tipo.');

        /* Los tipos válidos son los del ENUM de la columna, que es la fuente de
           verdad del catálogo. Se leen del esquema en lugar de repetirlos acá,
           para no tener una tercera copia de la lista además de la base y de
           js/report-types.js. Sin esta validación, un tipo inválido llegaba al
           INSERT y el usuario recibía un error SQL crudo. */
        $stmt = $db->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                            WHERE TABLE_SCHEMA = DATABASE()
                              AND TABLE_NAME = 'reportes' AND COLUMN_NAME = 'tipo'");
        $definicion = $stmt->fetchColumn() ?: '';
        preg_match_all("/'([^']+)'/", $definicion, $coincidencias);
        $tiposValidos = $coincidencias[1] ?? [];

        /* Si el catálogo no se puede leer, se corta acá. Dejar pasar el tipo
           haría que la validación dependiera del sql_mode del servidor: con
           STRICT_TRANS_TABLES la base rechaza el valor, pero sin ese modo lo
           trunca en silencio y queda un reporte con el tipo vacío. */
        if (!$tiposValidos)
            throw new Exception('No se pudo leer el catálogo de tipos de reporte.');

        if (!in_array($tipo, $tiposValidos, true))
            throw new Exception('Tipo de reporte inválido. Opciones: ' . implode(', ', $tiposValidos) . '.');

        /* El panel de administración ya contempla reportes huérfanos, porque un
           cargador puede desaparecer después. Lo que no debería poder pasar es
           crear el reporte huérfano de entrada. */
        $stmt = $db->prepare("SELECT id FROM puntos_carga WHERE id = :id AND activo = 1");
        $stmt->execute([':id' => $punto_carga_id]);
        if (!$stmt->fetch()) throw new Exception('El punto de carga no existe o no está disponible.');

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
        http_response_code(405); echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
