<?php
/**
 * E-Find — Vehículos del usuario logueado
 * GET    /api/vehiculos.php          → lista los vehículos propios
 * POST   /api/vehiculos.php          → agrega un vehículo propio
 * DELETE /api/vehiculos.php?id=N     → da de baja un vehículo propio (soft delete)
 *
 * Usa la tabla `vehiculos` real del esquema (db/schema.sql):
 * columnas capacidad_bateria_kwh y tipo_conector_id (FK a tipos_conector),
 * no las que este archivo asumía originalmente. El JSON que expone sigue
 * llamándose capacidad_kwh/tipo_conector para no tocar el frontend.
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../includes/db.php';
require_once '../includes/auth.php';

try {
    $db = db_connect();
    requiere_login();
    $u      = usuario_actual();
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $stmt = $db->prepare("
            SELECT v.id, v.marca, v.modelo,
                   v.capacidad_bateria_kwh AS capacidad_kwh,
                   tc.nombre AS tipo_conector
            FROM   vehiculos v
            LEFT JOIN tipos_conector tc ON tc.id = v.tipo_conector_id
            WHERE  v.usuario_id = :uid AND v.activo = 1
            ORDER  BY v.id DESC
        ");
        $stmt->execute([':uid' => $u['id']]);
        echo json_encode(['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

    } elseif ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $marca  = trim($body['marca']  ?? '');
        $modelo = trim($body['modelo'] ?? '');
        $cap    = (float)($body['capacidad_kwh'] ?? 0);
        $tipo   = trim($body['tipo_conector'] ?? '');

        if (!$marca || !$modelo || !$tipo || $cap < 1 || $cap > 300)
            throw new Exception('Datos inválidos: marca, modelo, capacidad (1-300 kWh) y tipo de conector son obligatorios.');

        $stmt = $db->prepare("SELECT id FROM tipos_conector WHERE nombre = :nombre");
        $stmt->execute([':nombre' => $tipo]);
        $tipoRow = $stmt->fetch();
        if (!$tipoRow) throw new Exception("Tipo de conector desconocido: \"$tipo\".");

        $stmt = $db->prepare("
            INSERT INTO vehiculos (usuario_id, marca, modelo, capacidad_bateria_kwh, tipo_conector_id)
            VALUES (:uid, :marca, :modelo, :cap, :tipo_id)
        ");
        $stmt->execute([
            ':uid'     => $u['id'],
            ':marca'   => $marca,
            ':modelo'  => $modelo,
            ':cap'     => $cap,
            ':tipo_id' => $tipoRow['id'],
        ]);
        echo json_encode(['ok' => true, 'data' => ['id' => (int)$db->lastInsertId()]]);

    } elseif ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) throw new Exception('Falta el id del vehículo.');

        $stmt = $db->prepare("UPDATE vehiculos SET activo = 0 WHERE id = :id AND usuario_id = :uid");
        $stmt->execute([':id' => $id, ':uid' => $u['id']]);

        if ($stmt->rowCount() === 0)
            throw new Exception('Vehículo no encontrado.');

        echo json_encode(['ok' => true]);

    } else {
        http_response_code(405); echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
