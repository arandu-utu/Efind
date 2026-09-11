<?php
/**
 * api/registro.php
 * POST — registro de nuevo usuario.
 * Tipo de cuenta "particular" -> rol_id 2, valida CI uruguaya.
 * Tipo de cuenta "empresa"    -> rol_id 3 (propietario), valida RUT + razón social.
 */
require_once '../includes/db.php';
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok' => false, 'error' => 'Método no permitido']); exit; }

function normalizar_documento($doc) {
    return preg_replace('/[.\-\s]/', '', $doc ?? '');
}

function validar_ci($ci) {
    $ci = str_pad(normalizar_documento($ci), 8, '0', STR_PAD_LEFT);
    if (!preg_match('/^\d{8}$/', $ci)) return false;
    $pesos = [2, 9, 8, 7, 6, 3, 4];
    $suma = 0;
    for ($i = 0; $i < 7; $i++) $suma += (int)$ci[$i] * $pesos[$i];
    return (10 - $suma % 10) % 10 === (int)$ci[7];
}

function validar_rut($rut) {
    return (bool)preg_match('/^\d{12}$/', normalizar_documento($rut));
}

$body    = json_decode(file_get_contents('php://input'), true);
$nombre  = trim($body['nombre']   ?? '');
$email   = trim($body['email']    ?? '');
$password = $body['password']     ?? '';
$rol     = ($body['rol'] ?? 'particular') === 'empresa' ? 'empresa' : 'particular';
$ci      = trim($body['ci']       ?? '');
$empresa = trim($body['empresa']  ?? '');

if (!$nombre || !$email || !$password || !$ci) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Datos incompletos']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'error' => 'Email inválido']);
    exit;
}
if (strlen($password) < 8) {
    echo json_encode(['ok' => false, 'error' => 'La contraseña debe tener al menos 8 caracteres']);
    exit;
}
if ($rol === 'empresa') {
    if (!validar_rut($ci)) {
        echo json_encode(['ok' => false, 'error' => 'RUT inválido (12 dígitos)']);
        exit;
    }
    if (!$empresa) {
        echo json_encode(['ok' => false, 'error' => 'Ingresá el nombre de la empresa']);
        exit;
    }
    $ciNormalizado = normalizar_documento($ci);
} else {
    if (!validar_ci($ci)) {
        echo json_encode(['ok' => false, 'error' => 'CI inválida']);
        exit;
    }
    $ciNormalizado = str_pad(normalizar_documento($ci), 8, '0', STR_PAD_LEFT);
    $empresa = null;
}

$pdo = db_connect();

// Verificar email duplicado
$stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    echo json_encode(['ok' => false, 'error' => 'El email ya está registrado']);
    exit;
}

$rolId = $rol === 'empresa' ? 3 : 2;

// Crear usuario con bcrypt cost 12
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
$stmt = $pdo->prepare(
    "INSERT INTO usuarios (nombre, email, password_hash, ci_rut, empresa, rol_id, activo)
     VALUES (?, ?, ?, ?, ?, ?, 1)"
);
$stmt->execute([$nombre, $email, $hash, $ciNormalizado, $empresa, $rolId]);
$nuevo_id = $pdo->lastInsertId();

// Iniciar sesión automáticamente
$_SESSION['usuario_id'] = $nuevo_id;
$_SESSION['nombre']     = $nombre;
$_SESSION['rol_id']     = $rolId;

// Devolver datos para localStorage (navbar)
echo json_encode([
    'ok'   => true,
    'data' => [
        'id'      => (int)$nuevo_id,
        'nombre'  => $nombre,
        'email'   => $email,
        'ci_rut'  => $ciNormalizado,
        'empresa' => $empresa,
        'rol_id'  => $rolId,
    ],
]);
