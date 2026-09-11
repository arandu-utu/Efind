<?php
/**
 * E-Find — Vehículos del usuario logueado
 * GET    /api/vehiculos.php          → lista los vehículos propios
 * POST   /api/vehiculos.php          → agrega un vehículo propio
 * DELETE /api/vehiculos.php?id=N     → elimina un vehículo propio
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../includes/db.php';
require_once '../includes/auth.php';

try {
    $db = db_connect();

    /* Sin FK, mismo criterio que resenas.php (evita problemas de charset/engine) */
    $db->exec("CREATE TABLE IF NOT EXISTS vehiculos (
        id             INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id     INT NOT NULL,
        marca          VARCHAR(60) NOT NULL,
        modelo         VARCHAR(60) NOT NULL,
        capacidad_kwh  DECIMAL(6,2) NOT NULL,
        tipo_conector  VARCHAR(30) NOT NULL,
        creado_en      DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    requiere_login();
    $u      = usuario_actual();
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $stmt = $db->prepare("
            SELECT id, marca, modelo, capacidad_kwh, tipo_conector
            FROM   vehiculos
            WHERE  usuario_id = :uid
            ORDER  BY id DESC
        ");
        $stmt->execute([':uid' => $u['id']]);
        echo json_encode(['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

    } elseif ($method === 'POST') {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $marca = trim($body['marca']  ?? '');
        $modelo = trim($body['modelo'] ?? '');
        $cap    = (float)($body['capacidad_kwh'] ?? 0);
        $tipo   = trim($body['tipo_conector'] ?? '');

        if (!$marca || !$modelo || !$tipo || $cap < 1 || $cap > 300)
            throw new Exception('Datos inválidos: marca, modelo, capacidad (1-300 kWh) y tipo de conector son obligatorios.');

        $stmt = $db->prepare("
            INSERT INTO vehiculos (usuario_id, marca, modelo, capacidad_kwh, tipo_conector)
            VALUES (:uid, :marca, :modelo, :cap, :tipo)
        ");
        $stmt->execute([
            ':uid'    => $u['id'],
            ':marca'  => $marca,
            ':modelo' => $modelo,
            ':cap'    => $cap,
            ':tipo'   => $tipo,
        ]);
        echo json_encode(['ok' => true, 'data' => ['id' => (int)$db->lastInsertId()]]);

    } elseif ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) throw new Exception('Falta el id del vehículo.');

        $stmt = $db->prepare("DELETE FROM vehiculos WHERE id = :id AND usuario_id = :uid");
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
