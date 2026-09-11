<?php
/**
 * E-Find — Cliente SMTP mínimo sobre sockets.
 *
 * No usa librerías externas (PHPMailer y compañía quedan descartadas por la
 * restricción de stack del proyecto), sólo stream_socket_client.
 * Soporta los dos modos habituales:
 *   - 'tls' → puerto 587, conexión en claro y luego STARTTLS.
 *   - 'ssl' → puerto 465, TLS desde el primer byte.
 *
 * Las credenciales NO viven acá: se leen de includes/mail_config.php, que está
 * fuera del repositorio. Ver smtp_config().
 */

/** Lee una respuesta completa del servidor (puede venir en varias líneas). */
function _smtp_leer($fp) {
    $respuesta = '';
    while (($linea = fgets($fp, 515)) !== false) {
        $respuesta .= $linea;
        /* En la última línea el código va seguido de un espacio; en las
           intermedias, de un guion (ej. "250-STARTTLS" vs "250 OK"). */
        if (isset($linea[3]) && $linea[3] === ' ') break;
    }
    return $respuesta;
}

/** Envía un comando y verifica que el código de respuesta sea el esperado. */
function _smtp_cmd($fp, $comando, $esperados) {
    if ($comando !== null) fwrite($fp, $comando . "\r\n");
    $respuesta = _smtp_leer($fp);
    $codigo    = (int)substr($respuesta, 0, 3);
    if (!in_array($codigo, (array)$esperados, true)) {
        throw new Exception('SMTP: respuesta inesperada del servidor (' . trim($respuesta) . ')');
    }
    return $respuesta;
}

/** Codifica un encabezado con acentos según RFC 2047. */
function _smtp_encabezado($texto) {
    return '=?UTF-8?B?' . base64_encode($texto) . '?=';
}

/**
 * Devuelve la configuración SMTP, o null si el servidor no la tiene cargada.
 * includes/mail_config.php debe devolver un array con las claves:
 * host, puerto, seguridad ('tls'|'ssl'), usuario, password, desde, desde_nombre.
 */
function smtp_config() {
    $ruta = __DIR__ . '/mail_config.php';
    if (!is_readable($ruta)) return null;
    $cfg = require $ruta;
    return is_array($cfg) ? $cfg : null;
}

/**
 * Envía un correo HTML. Lanza Exception si algo falla.
 * @param string $para    destinatario (se valida para evitar inyección de cabeceras)
 * @param string $asunto  asunto en texto plano
 * @param string $html    cuerpo del mensaje
 */
function smtp_enviar($para, $asunto, $html) {
    $cfg = smtp_config();
    if (!$cfg) throw new Exception('SMTP: falta includes/mail_config.php en el servidor.');

    if (!filter_var($para, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $para)) {
        throw new Exception('SMTP: dirección de destino inválida.');
    }

    $seguridad = $cfg['seguridad'] ?? 'tls';
    $destino   = ($seguridad === 'ssl' ? 'ssl://' : '') . $cfg['host'] . ':' . (int)$cfg['puerto'];

    $contexto = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $fp = @stream_socket_client($destino, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $contexto);
    if (!$fp) throw new Exception("SMTP: no se pudo conectar a $destino ($errstr)");
    stream_set_timeout($fp, 20);

    try {
        $dominio = $cfg['dominio'] ?? 'efindapp.com';

        _smtp_cmd($fp, null, 220);
        _smtp_cmd($fp, "EHLO $dominio", 250);

        if ($seguridad === 'tls') {
            _smtp_cmd($fp, 'STARTTLS', 220);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new Exception('SMTP: no se pudo activar TLS.');
            }
            _smtp_cmd($fp, "EHLO $dominio", 250);   // hay que repetirlo ya cifrado
        }

        _smtp_cmd($fp, 'AUTH LOGIN', 334);
        _smtp_cmd($fp, base64_encode($cfg['usuario']), 334);
        _smtp_cmd($fp, base64_encode($cfg['password']), 235);

        _smtp_cmd($fp, 'MAIL FROM:<' . $cfg['desde'] . '>', 250);
        _smtp_cmd($fp, "RCPT TO:<$para>", [250, 251]);
        _smtp_cmd($fp, 'DATA', 354);

        $remitente = _smtp_encabezado($cfg['desde_nombre'] ?? 'E-Find') . ' <' . $cfg['desde'] . '>';
        $cabeceras = [
            'From: ' . $remitente,
            'To: <' . $para . '>',
            'Subject: ' . _smtp_encabezado($asunto),
            'Date: ' . date('r'),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $dominio . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];

        /* Normalizar saltos de línea y aplicar dot-stuffing: una línea que
           empieza con "." cerraría el DATA antes de tiempo (RFC 5321 §4.5.2). */
        $cuerpo = preg_replace('/\r\n|\r|\n/', "\r\n", $html);
        $cuerpo = preg_replace('/^\./m', '..', $cuerpo);

        fwrite($fp, implode("\r\n", $cabeceras) . "\r\n\r\n" . $cuerpo . "\r\n.\r\n");
        _smtp_cmd($fp, null, 250);
        _smtp_cmd($fp, 'QUIT', 221);
    } finally {
        fclose($fp);
    }
    return true;
}
