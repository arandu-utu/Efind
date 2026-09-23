<?php
require_once '../includes/sesion.php';
iniciar_sesion_segura();
require_once '../includes/db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok' => false, 'error' => 'Método no permitido']); exit; }

$data = json_decode(file_get_contents('php://input'), true);
$email = trim($data['email'] ?? '');
$pass  = $data['password'] ?? '';

if (!$email || !$pass) {
    echo json_encode(['ok' => false, 'error' => 'Email y contraseña requeridos']); exit;
}

$pdo = db_connect();

/* ── Rate limiting: máx. 5 intentos fallidos en 15 minutos por IP+email ── */
function obtener_ip_real() {
    /* CF-Connecting-IP lo pone el edge de Cloudflare y no se puede spoofear
       en el camino público (túnel). X-Forwarded-For, en cambio, lo puede
       fijar cualquier cliente que le pegue directo a la VM (red local),
       así que no se usa como fuente del rate limit. */
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) return $_SERVER['HTTP_CF_CONNECTING_IP'];
    return $_SERVER['REMOTE_ADDR'] ?? 'desconocida';
}

$ip = obtener_ip_real();

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

$stmt = $pdo->prepare("SELECT id, nombre, email, password_hash, ci_rut, empresa, rol_id, activo FROM usuarios WHERE email = ?");
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

/* Identificador nuevo antes de guardar los datos del usuario, para que un
   identificador fijado de antemano por un tercero deje de ser válido. */
renovar_sesion();

$_SESSION['usuario_id'] = $user['id'];
$_SESSION['nombre']     = $user['nombre'];
$_SESSION['rol_id']     = $user['rol_id'];

echo json_encode(['ok' => true, 'data' => [
    'id'      => $user['id'],
    'nombre'  => $user['nombre'],
    'email'   => $user['email'],
    'ci_rut'  => $user['ci_rut'],
    'empresa' => $user['empresa'],
    'rol_id'  => $user['rol_id'],
]]);
