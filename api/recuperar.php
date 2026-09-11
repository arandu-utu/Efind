<?php
/**
 * E-Find — Solicitud de recuperación de contraseña
 * POST /api/recuperar.php   body: { email }
 *
 * Genera un token de un solo uso y lo manda por correo. La respuesta es
 * siempre la misma exista o no la cuenta: si contestáramos distinto, esto
 * serviría para averiguar qué direcciones están registradas.
 *
 * En la tabla se guarda sólo el SHA-256 del token, nunca el token en claro:
 * si alguien llegara a leer la base, no puede usar los tokens pendientes.
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../includes/db.php';
require_once '../includes/smtp.php';

const RESET_VALIDEZ_MINUTOS = 60;
const RESET_MAX_POR_HORA    = 3;

$RESPUESTA_GENERICA = ['ok' => true, 'data' => [
    'mensaje' => 'Si el correo corresponde a una cuenta registrada, te enviamos un enlace para restablecer la contraseña.',
]];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
        exit;
    }

    $db    = db_connect();
    $body  = json_decode(file_get_contents('php://input'), true) ?? [];
    $email = trim($body['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Ingresá un correo válido.']);
        exit;
    }

    /* Tabla propia: no colisiona con ninguna de db/schema.sql. */
    $db->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id  INT NOT NULL,
        token_hash  CHAR(64) NOT NULL,
        expira_en   DATETIME NOT NULL,
        usado_en    DATETIME NULL,
        creado_en   DATETIME NOT NULL,
        INDEX idx_token (token_hash),
        INDEX idx_usuario (usuario_id)
    )");

    $stmt = $db->prepare("SELECT id, nombre FROM usuarios WHERE email = :email AND activo = 1");
    $stmt->execute([':email' => $email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    /* Cuenta inexistente o dada de baja: cortamos acá, con la misma respuesta. */
    if (!$usuario) { echo json_encode($RESPUESTA_GENERICA); exit; }

    /* Límite por cuenta, para que nadie use esto como fuente de spam. */
    $stmt = $db->prepare("SELECT COUNT(*) FROM password_resets
                          WHERE usuario_id = :uid AND creado_en > (NOW() - INTERVAL 1 HOUR)");
    $stmt->execute([':uid' => $usuario['id']]);
    if ((int)$stmt->fetchColumn() >= RESET_MAX_POR_HORA) {
        http_response_code(429);
        echo json_encode(['ok' => false, 'error' => 'Ya pediste varios enlaces en la última hora. Esperá un rato antes de volver a intentar.']);
        exit;
    }

    /* Un pedido nuevo invalida los anteriores que sigan vivos. */
    $db->prepare("UPDATE password_resets SET usado_en = NOW()
                  WHERE usuario_id = :uid AND usado_en IS NULL")
       ->execute([':uid' => $usuario['id']]);

    $token = bin2hex(random_bytes(32));
    /* El intervalo va interpolado y no como parámetro: MariaDB no acepta un
       placeholder dentro de INTERVAL. Es una constante entera del archivo,
       no entra nada del usuario. */
    $db->prepare("INSERT INTO password_resets (usuario_id, token_hash, expira_en, creado_en)
                  VALUES (:uid, :hash, (NOW() + INTERVAL " . RESET_VALIDEZ_MINUTOS . " MINUTE), NOW())")
       ->execute([
           ':uid'  => $usuario['id'],
           ':hash' => hash('sha256', $token),
       ]);

    $cfg     = smtp_config() ?? [];
    $base    = rtrim($cfg['base_url'] ?? 'https://efindapp.com', '/');
    $enlace  = "$base/restablecer.html?token=$token";
    $nombre  = htmlspecialchars($usuario['nombre'], ENT_QUOTES, 'UTF-8');
    $enlaceE = htmlspecialchars($enlace, ENT_QUOTES, 'UTF-8');

    $html = "<!DOCTYPE html>
<html lang=\"es\"><body style=\"margin:0;padding:24px;background:#F4F6F9;font-family:'Segoe UI',system-ui,sans-serif;color:#1C2433\">
  <div style=\"max-width:520px;margin:0 auto;background:#fff;border:1px solid #DEE3EA;border-radius:14px;padding:28px\">
    <div style=\"font-size:22px;font-weight:900;color:#1A2E44;margin-bottom:18px\">E-Find</div>
    <p style=\"font-size:15px;line-height:1.6;margin:0 0 14px\">Hola $nombre,</p>
    <p style=\"font-size:15px;line-height:1.6;margin:0 0 20px\">
      Recibimos un pedido para restablecer la contraseña de tu cuenta.
      El enlace vale por " . RESET_VALIDEZ_MINUTOS . " minutos y se puede usar una sola vez.
    </p>
    <p style=\"margin:0 0 22px\">
      <a href=\"$enlaceE\" style=\"display:inline-block;background:#2D7DD2;color:#fff;text-decoration:none;
         font-weight:600;font-size:15px;padding:12px 24px;border-radius:8px\">Restablecer contraseña</a>
    </p>
    <p style=\"font-size:13px;line-height:1.6;color:#5C6B82;margin:0 0 6px\">
      Si el botón no funciona, copiá y pegá esta dirección en el navegador:
    </p>
    <p style=\"font-size:12px;line-height:1.5;color:#5C6B82;word-break:break-all;margin:0 0 20px\">$enlaceE</p>
    <p style=\"font-size:13px;line-height:1.6;color:#5C6B82;margin:0;border-top:1px solid #DEE3EA;padding-top:16px\">
      Si no pediste esto, podés ignorar el mensaje: tu contraseña actual sigue siendo válida.
    </p>
  </div>
</body></html>";

    try {
        smtp_enviar($email, 'Restablecer tu contraseña de E-Find', $html);
    } catch (Exception $e) {
        /* No se lo contamos al cliente para no filtrar si la cuenta existe,
           pero queda en el log de Apache para poder diagnosticarlo. */
        error_log('E-Find recuperar.php — fallo al enviar correo: ' . $e->getMessage());
    }

    echo json_encode($RESPUESTA_GENERICA);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
