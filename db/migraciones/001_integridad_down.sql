/* ===========================================================================
   E-Find — Migración 001 (down): revertir la integridad referencial
   ---------------------------------------------------------------------------
   Deja las cuatro tablas como estaban antes de 001_integridad_up.sql. No borra
   ni modifica ninguna fila: sólo quita restricciones y vuelve los enteros a INT
   con signo.

       mysql -u root -p efind < db/migraciones/001_integridad_down.sql

   Es idempotente. El orden importa: primero las claves foráneas, porque
   mientras existan bloquean tanto el borrado de su índice como el cambio de
   tipo de la columna.

   Advertencia: revertir vuelve a permitir dos calificaciones sobre la misma
   transacción y dos usuarios con el mismo documento. Sólo tiene sentido como
   marcha atrás inmediata si la migración rompiera algo en producción.
   =========================================================================== */

SELECT DATABASE() AS base_de_destino;

/* ── 1. Claves foráneas ─────────────────────────────────────────────────── */

ALTER TABLE password_resets DROP FOREIGN KEY IF EXISTS fk_password_resets_usuario;

ALTER TABLE resenas
    DROP FOREIGN KEY IF EXISTS fk_resenas_punto_carga,
    DROP FOREIGN KEY IF EXISTS fk_resenas_usuario;

ALTER TABLE calificaciones
    DROP FOREIGN KEY IF EXISTS fk_calificaciones_transaccion,
    DROP FOREIGN KEY IF EXISTS fk_calificaciones_autor,
    DROP FOREIGN KEY IF EXISTS fk_calificaciones_destinatario;

ALTER TABLE transacciones
    DROP FOREIGN KEY IF EXISTS fk_transacciones_usuario,
    DROP FOREIGN KEY IF EXISTS fk_transacciones_punto_carga,
    DROP FOREIGN KEY IF EXISTS fk_transacciones_propietario;

/* ── 2. Índices ─────────────────────────────────────────────────────────────
   InnoDB crea un índice por cada clave foránea, con el nombre de la
   restricción. Quitar la clave no lo quita, así que se borran aparte. */

ALTER TABLE password_resets DROP INDEX IF EXISTS fk_password_resets_usuario;

ALTER TABLE resenas
    DROP INDEX IF EXISTS fk_resenas_punto_carga,
    DROP INDEX IF EXISTS fk_resenas_usuario;

ALTER TABLE calificaciones
    DROP INDEX IF EXISTS fk_calificaciones_transaccion,
    DROP INDEX IF EXISTS fk_calificaciones_autor,
    DROP INDEX IF EXISTS fk_calificaciones_destinatario,
    DROP INDEX IF EXISTS uq_transaccion;

ALTER TABLE transacciones
    DROP INDEX IF EXISTS fk_transacciones_usuario,
    DROP INDEX IF EXISTS fk_transacciones_punto_carga,
    DROP INDEX IF EXISTS fk_transacciones_propietario;

ALTER TABLE usuarios DROP INDEX IF EXISTS uq_ci_rut;

/* ── 3. Tipos ───────────────────────────────────────────────────────────── */

ALTER TABLE transacciones
    MODIFY id             INT NOT NULL AUTO_INCREMENT,
    MODIFY usuario_id     INT NOT NULL,
    MODIFY punto_carga_id INT NOT NULL,
    MODIFY propietario_id INT NULL;

ALTER TABLE calificaciones
    MODIFY id              INT NOT NULL AUTO_INCREMENT,
    MODIFY de_usuario_id   INT NOT NULL,
    MODIFY para_usuario_id INT NOT NULL,
    MODIFY transaccion_id  INT NULL;

ALTER TABLE resenas
    MODIFY id             INT NOT NULL AUTO_INCREMENT,
    MODIFY punto_carga_id INT NOT NULL,
    MODIFY usuario_id     INT NOT NULL;

ALTER TABLE password_resets
    MODIFY id         INT NOT NULL AUTO_INCREMENT,
    MODIFY usuario_id INT NOT NULL;

ALTER TABLE login_intentos
    MODIFY id INT NOT NULL AUTO_INCREMENT;

SELECT 'Migración 001 revertida' AS resultado;
