<?php
/* Utilidades comunes de la API: respuesta de error y lectura segura de texto. */

/* Un Exception con código 4xx se muestra con ese código; un Exception común
   (las validaciones de negocio) sale como 400 con su mensaje. Cualquier otra
   falla (SQL, TypeError) se registra en el servidor y sale sin detalles. */
function responder_error(Throwable $e): void {
    $c = $e->getCode();
    $aviso = is_int($c) && $c >= 400 && $c < 500;
    if (!$aviso && get_class($e) === 'Exception') { $aviso = true; $c = 400; }
    if (!$aviso) error_log($e);
    http_response_code($aviso ? $c : 500);
    echo json_encode(['ok' => false, 'error' => $aviso ? $e->getMessage() : 'Error interno. Intentá de nuevo en unos minutos.']);
}

/* El cuerpo JSON puede traer un array o un número donde se espera texto:
   trim() sobre un array lanza TypeError. Devuelve '' si no es una cadena. */
function campo_texto($valor): string {
    return is_string($valor) ? trim($valor) : '';
}
