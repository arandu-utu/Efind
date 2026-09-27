/* ===========================================================================
   E-Find — Migración 002: liberar el estado de las estaciones de UTE
   ---------------------------------------------------------------------------
   La API de UTE devuelve statusDetail = 'Busy' en todos sus conectores, en
   todas las corridas. Es un valor constante que no informa ocupación real, y
   al mapearlo como 'ocupado' el mapa quedaba con casi todas las estaciones en
   naranja. A partir de ahora una estación de UTE queda 'disponible' salvo que
   la comunidad reporte otra cosa, y el sincronizador ya no pisa ese reporte.

   Esta migración corrige las filas que quedaron congeladas por las corridas
   anteriores. No cambia el esquema: sólo datos.

       mysql -u root -p efind < db/migraciones/002_estado_ute.sql

   ATENCIÓN: se ejecuta UNA SOLA VEZ. Después de aplicarla, un 'ocupado' en una
   estación de UTE ya no viene del sincronizador sino de un reporte real de la
   comunidad, y volver a correr esto lo borraría.
   =========================================================================== */

SELECT DATABASE() AS base_de_destino;

/* Antes: cuántas filas se van a tocar. */
SELECT estado, COUNT(*) AS estaciones
FROM   puntos_carga
WHERE  fuente = 'ute'
GROUP  BY estado;

UPDATE puntos_carga
SET    estado = 'disponible'
WHERE  fuente = 'ute'
  AND  estado = 'ocupado';

/* Los conectores acompañan: el mismo valor constante los marcaba ocupados. */
UPDATE conectores c
JOIN   puntos_carga p ON p.id = c.punto_carga_id
SET    c.estado = 'disponible'
WHERE  p.fuente = 'ute'
  AND  c.estado = 'ocupado';

SELECT estado, COUNT(*) AS estaciones_despues
FROM   puntos_carga
WHERE  fuente = 'ute'
GROUP  BY estado;

SELECT 'Migración 002 aplicada' AS resultado;
