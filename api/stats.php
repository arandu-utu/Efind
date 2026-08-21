<?php
/**
 * E-Find — Estadísticas del dashboard admin
 * GET /api/stats.php  (requiere rol admin)
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../includes/db.php';
require_once '../includes/auth.php';

requiere_rol(1);

try {
    $db = db_connect();

    /* KPIs básicos */
    $cargadores_total  = (int)$db->query("SELECT COUNT(*) FROM puntos_carga")->fetchColumn();
    $usuarios_total    = (int)$db->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
    $reportes_abiertos = (int)$db->query("SELECT COUNT(*) FROM reportes WHERE resuelto = 0")->fetchColumn();

    /* Reseñas pendientes (tabla puede no existir aún) */
    try {
        $resenas_pendientes = (int)$db->query("SELECT COUNT(*) FROM resenas WHERE estado = 'pendiente'")->fetchColumn();
    } catch (Exception $e) {
        $resenas_pendientes = 0;
    }

    /* Usuarios por rol_id: 1=admin, 2=usuario, 3=propietario */
    $rolMap = [1 => 'admin', 2 => 'particular', 3 => 'propietario'];
    $uPorRol = ['admin' => 0, 'particular' => 0, 'propietario' => 0];
    $stmt = $db->query("SELECT rol_id, COUNT(*) c FROM usuarios GROUP BY rol_id");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $nombre = $rolMap[$r['rol_id']] ?? 'otro';
        $uPorRol[$nombre] = (int)$r['c'];
    }

    /* Cargadores registrados por mes en el año actual */
    $porMes = array_fill(0, 12, 0);
    try {
        $year = (int)date('Y');
        $stmt = $db->prepare(
            "SELECT MONTH(fecha_creacion) m, COUNT(*) c
             FROM puntos_carga
             WHERE YEAR(fecha_creacion) = :y
             GROUP BY m"
        );
        $stmt->execute([':y' => $year]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $porMes[(int)$r['m'] - 1] = (int)$r['c'];
        }
    } catch (Exception $e) {
        /* La columna fecha_creacion no existe: dejamos ceros */
    }

    echo json_encode(['ok' => true, 'data' => [
        'cargadores_total'    => $cargadores_total,
        'usuarios_total'      => $usuarios_total,
        'resenas_pendientes'  => $resenas_pendientes,
        'reportes_abiertos'   => $reportes_abiertos,
        'usuarios_por_rol'    => $uPorRol,
        'cargadores_por_mes'  => array_values($porMes),
    ]]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
