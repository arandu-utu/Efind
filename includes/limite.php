<?php
/* Límite de uso por clave (IP o usuario) sobre la tabla limites_uso (migración 007).
   Si la tabla todavía no existe no se bloquea a nadie: el código puede desplegarse
   antes que la migración. */

/* Detrás del túnel de Cloudflare la dirección real llega en CF-Connecting-IP.
   Sólo se confía en esa cabecera si la conexión viene del propio equipo
   (el túnel, o las pruebas); un cliente directo podría falsificarla. */
function ip_cliente(): string {
    $r = $_SERVER['REMOTE_ADDR'] ?? '';
    if (in_array($r, ['127.0.0.1', '::1'], true) && !empty($_SERVER['HTTP_CF_CONNECTING_IP']))
        return substr($_SERVER['HTTP_CF_CONNECTING_IP'], 0, 45);
    return $r ?: 'desconocida';
}

/* Registra un uso de $clave y rechaza con 429 si ya hubo $max en los últimos
   $segundos. Ejemplo: limitar($db, 'resena:u' . $uid, 5, 3600); */
function limitar(PDO $db, string $clave, int $max, int $segundos): void {
    try {
        if (random_int(1, 50) === 1) {   /* limpieza ocasional, sin depender de un cron */
            $db->exec("DELETE FROM limites_uso WHERE momento < NOW() - INTERVAL 2 DAY");
            $db->exec("DELETE FROM login_intentos WHERE ultimo_intento < NOW() - INTERVAL 1 DAY");
            $db->exec("DELETE FROM password_resets WHERE expira_en < NOW() - INTERVAL 7 DAY");
        }
        $s = $db->prepare("SELECT COUNT(*) FROM limites_uso WHERE clave = :k AND momento > NOW() - INTERVAL " . (int)$segundos . " SECOND");
        $s->execute([':k' => substr($clave, 0, 80)]);
        if ((int)$s->fetchColumn() >= $max) throw new Exception('Demasiadas solicitudes. Probá de nuevo en unos minutos.', 429);
        $db->prepare("INSERT INTO limites_uso (clave) VALUES (:k)")->execute([':k' => substr($clave, 0, 80)]);
    } catch (PDOException $e) {
        error_log('limitar(): ' . $e->getMessage());
    }
}
