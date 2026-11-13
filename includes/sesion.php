<?php
/**
 * E-Find — Inicio de la sesión PHP con la cookie endurecida.
 *
 * Los atributos de la cookie tienen que fijarse antes de session_start(), y
 * sólo tienen efecto en la petición que la crea. Por eso no alcanza con
 * configurarlos en el login: si la cookie la creó antes cualquier otro
 * endpoint con los valores por defecto, esos atributos son los que quedan.
 * Todos los endpoints inician la sesión desde acá para que eso no ocurra.
 *
 * No contiene credenciales, por lo que sí forma parte del repositorio.
 */

function iniciar_sesion_segura(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;

    /* Secure sólo cuando la conexión real es HTTPS. El sitio se sirve por el
       túnel con TLS, pero también se accede por HTTP en la red local, y una
       cookie Secure no viajaría en ese caso. */
    $es_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
             || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    /* Sin esto Ubuntu descarta las sesiones inactivas a los 24 minutos; el valor del
       servidor (php.ini) también debe ser SESION_INACTIVIDAD para la limpieza programada. */
    ini_set('session.gc_maxlifetime', (string)SESION_INACTIVIDAD);

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $es_https,
        'httponly' => true,   // inaccesible desde JavaScript
        'samesite' => 'Lax',  // no viaja en peticiones POST de otros sitios
    ]);

    session_start();
    if (isset($_SESSION['usuario_id'])) revalidar_sesion();
}

const SESION_INACTIVIDAD = 8 * 3600;   // segundos sin actividad antes de pedir ingreso de nuevo

/**
 * Comprueba en cada petición que la cuenta sigue activa y toma el rol vigente
 * de la base. Así una suspensión o un cambio de rol rige de inmediato, en lugar
 * de esperar a que la persona cierre sesión, y una sesión abandonada vence.
 * Si la base no responde se conserva la sesión: el endpoint fallará por su cuenta.
 */
function revalidar_sesion(): void
{
    if (time() - ($_SESSION['actividad'] ?? time()) > SESION_INACTIVIDAD) { vaciar_sesion(); return; }
    $_SESSION['actividad'] = time();
    try {
        require_once __DIR__ . '/db.php';
        $s = db_connect()->prepare('SELECT rol_id, activo FROM usuarios WHERE id = :id');
        $s->execute([':id' => $_SESSION['usuario_id']]);
        $u = $s->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { return; }
    if (!$u || !(int)$u['activo']) { vaciar_sesion(); return; }
    $_SESSION['rol_id'] = (int)$u['rol_id'];
}

/* Borra los datos y renueva el identificador, dejando la sesión abierta y vacía
   (así un ingreso posterior en la misma petición sigue funcionando). */
function vaciar_sesion(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

/**
 * Renueva el identificador de sesión conservando sus datos.
 *
 * Se llama justo después de autenticar. Si un atacante consigue fijar un
 * identificador de sesión en el navegador de la víctima antes del ingreso,
 * al renovarlo el identificador que él conoce deja de servir.
 */
function renovar_sesion(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
}
