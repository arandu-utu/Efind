<?php
require_once '../includes/sesion.php';
iniciar_sesion_segura();
require_once '../includes/db.php';
require_once '../includes/errores.php';
require_once '../includes/limite.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok' => false, 'error' => 'Método no permitido']); exit; }

$data  = json_decode(file_get_contents('php://input'), true) ?? [];
$email = campo_texto($data['email'] ?? null);
$pass  = is_string($data['password'] ?? null) ? $data['password'] : '';

if (!$email || !$pass) {
    http_response_code(400); echo json_encode(['ok' => false, 'error' => 'Email y contraseña requeridos']); exit;
}

try {
    $pdo = db_connect();

    /* ── Rate limiting: máx. 5 intentos fallidos en 15 minutos por IP+email ── */
    /* ip_cliente() sólo cree en CF-Connecting-IP si la conexión viene del túnel local. */
    $ip = ip_cliente();

    /* Freno general por IP, además del de IP+correo: frena probar muchos correos desde un mismo origen. */
    limitar($pdo, 'login:ip:' . $ip, 100, 900);

    $LIMITE_INTENTOS = 5;
    $VENTANA_MINUTOS = 15;

    /* La diferencia la calcula la propia base. Antes se restaba time() de PHP contra
       la marca que escribe MariaDB con NOW(): si las zonas horarias de los dos no
       coinciden —y en Debian y Ubuntu date.timezone viene vacío, con lo que PHP cae
       en UTC— la resta da horas y el freno no se activa nunca. Comparando dentro del
       motor, los dos valores salen del mismo reloj y el control deja de depender de
       la configuración del servidor. */
    $stmtCheck = $pdo->prepare("
        SELECT intentos, TIMESTAMPDIFF(SECOND, ultimo_intento, NOW()) / 60 AS minutos
        FROM   login_intentos
        WHERE  ip = ? AND email = ?
    ");
    $stmtCheck->execute([$ip, $email]);
    $intento = $stmtCheck->fetch();

    if ($intento) {
        $minutosDesdeUltimo = (float)$intento['minutos'];

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
} catch (Throwable $e) {
    responder_error($e);
}
