/* ===========================================================================
   E-Find — Migración 007: límites de uso, rangos válidos e índices
   ---------------------------------------------------------------------------
   1. limites_uso: contador de eventos por clave (IP o usuario) que usa
      includes/limite.php. Sin esta tabla el límite no bloquea a nadie.
   2. transacciones.creado_en: momento exacto del cobro, con un índice que
      permite detectar cobros repetidos (mismo usuario y estación en
      segundos). La columna `fecha` sólo guarda el día.
   3. Restricciones CHECK de rango. Antes de crearlas se listan las filas que
      las violarían: si alguna consulta devuelve filas, el ALTER correspondiente
      falla sin escribir nada y hay que revisar esos datos, no forzarlos.
   4. resenas.estado deja de admitir NULL.
   5. Índices que las consultas de api/ necesitan.

       mysql -u root -p efind < db/migraciones/007_endurecimiento_y_limites.sql

   Va antes de desplegar el código. Requiere MariaDB 10.4 o superior.
   Es idempotente: puede repetirse sin errores ni cambios.
   =========================================================================== */

SELECT DATABASE() AS base_de_destino;

-- ---------------------------------------------------------------------------
-- 1. Límites de uso
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS limites_uso (
  id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  clave   VARCHAR(80)  NOT NULL,
  momento TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_clave_momento (clave, momento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 2. Momento del cobro
-- Las filas existentes quedan con la hora de esta migración: no se inventa un
-- instante a partir de `fecha`, que sólo tiene el día.
-- ---------------------------------------------------------------------------
ALTER TABLE transacciones
  ADD COLUMN IF NOT EXISTS creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

CREATE INDEX IF NOT EXISTS idx_tx_cobro_repetido ON transacciones (usuario_id, punto_carga_id, creado_en);

-- ---------------------------------------------------------------------------
-- 3. Datos que se corrigen porque la corrección es segura
-- ---------------------------------------------------------------------------
-- Una reseña sin estado nunca se mostraría ni se moderaría: queda pendiente.
UPDATE resenas SET estado = 'pendiente' WHERE estado IS NULL;
-- Potencia nula = desconocida. Un 0 o un negativo no es una potencia.
UPDATE conectores SET potencia_kw = NULL WHERE potencia_kw <= 0;

-- ---------------------------------------------------------------------------
-- 3b. Verificación (sólo lectura). Todas deben devolver cero filas; si no,
--     corregir a mano esas filas antes de seguir.
-- ---------------------------------------------------------------------------
SELECT 'calificaciones.puntos' AS regla, id FROM calificaciones WHERE puntos NOT BETWEEN 1 AND 5
UNION ALL SELECT 'resenas.estrellas',        id FROM resenas        WHERE estrellas NOT BETWEEN 1 AND 5
UNION ALL SELECT 'puntos_carga.lat',         id FROM puntos_carga   WHERE lat NOT BETWEEN -90 AND 90
UNION ALL SELECT 'puntos_carga.lng',         id FROM puntos_carga   WHERE lng NOT BETWEEN -180 AND 180
UNION ALL SELECT 'puntos_carga.costo_kwh',   id FROM puntos_carga   WHERE costo_kwh < 0
UNION ALL SELECT 'transacciones.kwh',        id FROM transacciones  WHERE kwh < 0
UNION ALL SELECT 'transacciones.monto_total', id FROM transacciones WHERE monto_total < 0
UNION ALL SELECT 'transacciones.comision',   id FROM transacciones  WHERE comision < 0 OR comision > monto_total;

-- ---------------------------------------------------------------------------
-- 4. resenas.estado obligatorio
-- ---------------------------------------------------------------------------
ALTER TABLE resenas
  MODIFY COLUMN estado ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente';

-- ---------------------------------------------------------------------------
-- 5. Restricciones de rango
-- ---------------------------------------------------------------------------
ALTER TABLE calificaciones
  ADD CONSTRAINT IF NOT EXISTS ck_calificaciones_puntos CHECK (puntos BETWEEN 1 AND 5);
ALTER TABLE resenas
  ADD CONSTRAINT IF NOT EXISTS ck_resenas_estrellas CHECK (estrellas BETWEEN 1 AND 5);
ALTER TABLE puntos_carga
  ADD CONSTRAINT IF NOT EXISTS ck_pc_lat   CHECK (lat BETWEEN -90 AND 90),
  ADD CONSTRAINT IF NOT EXISTS ck_pc_lng   CHECK (lng BETWEEN -180 AND 180),
  ADD CONSTRAINT IF NOT EXISTS ck_pc_costo CHECK (costo_kwh >= 0);
ALTER TABLE conectores
  ADD CONSTRAINT IF NOT EXISTS ck_conectores_potencia CHECK (potencia_kw IS NULL OR potencia_kw > 0);
ALTER TABLE transacciones
  ADD CONSTRAINT IF NOT EXISTS ck_tx_kwh      CHECK (kwh >= 0),
  ADD CONSTRAINT IF NOT EXISTS ck_tx_monto    CHECK (monto_total >= 0),
  ADD CONSTRAINT IF NOT EXISTS ck_tx_comision CHECK (comision >= 0 AND comision <= monto_total);

-- ---------------------------------------------------------------------------
-- 6. Índices según las consultas de api/
-- ---------------------------------------------------------------------------
-- stats.php y reportes.php: reportes abiertos, más recientes primero.
CREATE INDEX IF NOT EXISTS idx_reportes_resuelto ON reportes (resuelto, creado_en);
-- resenas.php: aprobadas por estación y cola de pendientes.
CREATE INDEX IF NOT EXISTS idx_resenas_pc_estado ON resenas (punto_carga_id, estado);
CREATE INDEX IF NOT EXISTS idx_resenas_estado    ON resenas (estado);
-- ute-sync.php: estaciones de UTE por nombre.
CREATE INDEX IF NOT EXISTS idx_pc_fuente_nombre  ON puntos_carga (fuente, nombre);
-- vehiculos.php: vehículos activos del usuario.
CREATE INDEX IF NOT EXISTS idx_veh_usuario_activo ON vehiculos (usuario_id, activo);
-- Purga de includes/limite.php.
CREATE INDEX IF NOT EXISTS idx_li_ultimo_intento ON login_intentos (ultimo_intento);
CREATE INDEX IF NOT EXISTS idx_pr_expira         ON password_resets (expira_en);
-- limites_uso se purga por antigüedad sobre una tabla chica: no necesita más índice.

-- ---------------------------------------------------------------------------
-- 7. Opcional: purga diaria. Requiere event_scheduler=ON en el servidor
--    (SET GLOBAL event_scheduler = ON y persistirlo en la configuración).
--    includes/limite.php ya purga de forma ocasional, así que no es necesario.
-- ---------------------------------------------------------------------------
-- CREATE EVENT IF NOT EXISTS ev_purga_diaria
--   ON SCHEDULE EVERY 1 DAY STARTS CURRENT_DATE + INTERVAL 1 DAY + INTERVAL 3 HOUR
--   DO BEGIN
--     DELETE FROM limites_uso     WHERE momento < NOW() - INTERVAL 2 DAY;
--     DELETE FROM login_intentos  WHERE ultimo_intento < NOW() - INTERVAL 1 DAY;
--     DELETE FROM password_resets WHERE expira_en < NOW() - INTERVAL 7 DAY;
--   END;

-- Resumen
SELECT
  (SELECT COUNT(*) FROM information_schema.TABLES      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'limites_uso')                      AS limites_uso,
  (SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME LIKE 'ck\_%')           AS checks,
  (SELECT COUNT(*) FROM resenas WHERE estado IS NULL)                                                                                         AS resenas_sin_estado;
