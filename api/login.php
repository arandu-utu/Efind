<?php
/* Cookie de sesión: Secure solo si la conexión real es HTTPS (túnel),
   para no romper el acceso directo por HTTP en red local */
$es_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
         || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $es_https,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']); exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$email = trim($data['email'] ?? '');
$pass  = $data['password'] ?? '';

if (!$email || !$pass) {
    echo json_encode(['ok' => false, 'error' => 'Email y contraseña requeridos']); exit;
}

$pdo = db_connect();

/* ── Rate limiting: máx. 5 intentos fallidos en 15 minutos por IP+email ── */
function obtener_ip_real() {
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) return $_SERVER['HTTP_CF_CONNECTING_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $partes = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($partes[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'desconocida';
}

$ip = obtener_ip_real();

$pdo->exec("CREATE TABLE IF NOT EXISTS login_intentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    email VARCHAR(150) NOT NULL,
    intentos INT NOT NULL DEFAULT 1,
    ultimo_intento DATETIME NOT NULL,
    UNIQUE KEY ip_email (ip, email)
)");

$LIMITE_INTENTOS = 5;
$VENTANA_MINUTOS = 15;

$stmtCheck = $pdo->prepare("SELECT intentos, ultimo_intento FROM login_intentos WHERE ip = ? AND email = ?");
$stmtCheck->execute([$ip, $email]);
$intento = $stmtCheck->fetch();

if ($intento) {
    $minutosDesdeUltimo = (time() - strtotime($intento['ultimo_intento'])) / 60;

    if ($intento['intentos'] >= $LIMITE_INTENTOS && $minutosDesdeUltimo < $VENTANA_MINUTOS) {
        $espera = (int)ceil($VENTANA_MINUTOS - $minutosDesdeUltimo);
        http_response_code(429);
        echo json_encode(['ok' => false, 'error' => "Demasiados intentos fallidos. Probá de nuevo en $espera minuto(s)."]);
        exit;
    }

    if ($minutosDesdeUltimo >= $VENTANA_MINUTOS) {
        $pdo->prepare("UPDATE login_intentos SET intentos = 0 WHERE ip = ? AND email = ?")->execute([$ip, $email]);
    }
}

$stmt = $pdo->prepare("SELECT id, nombre, password_hash, rol_id, activo FROM usuarios WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !$user['activo'] || !password_verify($pass, $user['password_hash'])) {
    $pdo->prepare("
        INSERT INTO login_intentos (ip, email, intentos, ultimo_intento)
        VALUES (?, ?, 1, NOW())
        ON DUPLICATE KEY UPDATE intentos = intentos + 1, ultimo_intento = NOW()
    ")->execute([$ip, $email]);

    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Credenciales incorrectas']); exit;
}

/* Login correcto: limpiar contador de intentos */
$pdo->prepare("DELETE FROM login_intentos WHERE ip = ? AND email = ?")->execute([$ip, $email]);

$_SESSION['usuario_id'] = $user['id'];
$_SESSION['nombre']     = $user['nombre'];
$_SESSION['rol_id']     = $user['rol_id'];

echo json_encode(['ok' => true, 'data' => [
    'id'     => $user['id'],
    'nombre' => $user['nombre'],
    'rol_id' => $user['rol_id'],
]]);
