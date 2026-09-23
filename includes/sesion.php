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

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $es_https,
        'httponly' => true,   // inaccesible desde JavaScript
        'samesite' => 'Lax',  // no viaja en peticiones POST de otros sitios
    ]);

    session_start();
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
