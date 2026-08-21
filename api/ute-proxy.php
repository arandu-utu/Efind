<?php
/**
 * ute-proxy.php
 *
 * Proxy server-side para la API de cargadores UTE.
 *
 * El endpoint público de UTE (movilidad.ute.com.uy) no incluye cabeceras
 * CORS, por lo que el browser bloquea las peticiones desde dominios distintos.
 * Este script actúa como intermediario: el browser llama a E-Find (mismo
 * origen) y este script consulta la API de UTE desde el servidor.
 *
 * @endpoint   GET /api/ute-proxy.php
 * @returns    application/json  (respuesta directa de la API de UTE)
 */

// ── Configuración ──────────────────────────────────────────────
const UTE_API_URL = 'https://movilidad.ute.com.uy/api/v1/station/status/map';
const CACHE_TTL   = 300;  // segundos — 5 minutos de caché en disco

// ── Cache simple en /tmp ───────────────────────────────────────
$cacheFile = sys_get_temp_dir() . '/efind_ute_stations.json';

if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < CACHE_TTL) {
    $body = file_get_contents($cacheFile);
} else {
    // Fetch desde el servidor (sin restricciones CORS)
    $ctx  = stream_context_create([
        'http' => [
            'method'  => 'GET',
            'header'  => "Accept: application/json\r\nUser-Agent: EFind/1.0\r\n",
            'timeout' => 10,
        ],
        'ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
        ],
    ]);

    $body = @file_get_contents(UTE_API_URL, false, $ctx);

    if ($body === false || empty($body)) {
        http_response_code(502);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'No se pudo contactar la API de UTE.']);
        exit;
    }

    // Validar que sea JSON antes de cachear
    if (json_decode($body) === null) {
        http_response_code(502);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Respuesta inválida de la API de UTE.']);
        exit;
    }

    file_put_contents($cacheFile, $body);
}

// ── Respuesta al browser ───────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=' . CACHE_TTL);
header('Access-Control-Allow-Origin: *');   // seguro: datos públicos de UTE
echo $body;
