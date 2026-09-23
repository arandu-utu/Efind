/* ===========================================================================
   E-Find — Migración 001 (up): integridad de las tablas autocreadas
   ---------------------------------------------------------------------------
   `transacciones`, `calificaciones`, `resenas` y `password_resets` nacieron de
   un CREATE TABLE IF NOT EXISTS dentro de los endpoints, así que quedaron sin
   claves foráneas y con los enteros en INT con signo, mientras que el esquema
   original usa INT UNSIGNED. Esta migración las alinea con el resto de la base.

   NO ejecutar sin haber corrido antes 001_verificacion.sql y obtenido APTA.
   Este archivo no elige la base de destino; se la indica quien lo ejecuta:

       mysql -u root -p efind < db/migraciones/001_integridad_up.sql

   Es idempotente: volver a aplicarla no produce error ni cambios.
   Reversible con 001_integridad_down.sql.
   =========================================================================== */

SELECT DATABASE() AS base_de_destino;

/* ── 1. Columnas ausentes en schema.sql ─────────────────────────────────────
   Estas cinco columnas se agregaron con ALTER sobre el servidor durante el
   desarrollo y nunca volvieron al esquema versionado. El código las usa en
   nueve endpoints, así que una base reconstruida desde db/schema.sql fallaba
   en el registro, en el alta de cargadores y en la cola. Se agregan acá para
   que la migración sirva también sobre esas bases; sobre producción, que ya
   las tiene, no hacen nada. db/schema.sql queda corregido por separado.

   Las definiciones y las posiciones son las que tiene el servidor, tomadas del
   volcado de produccion, no deducidas. Sobre una base que ya las tiene, cada
   ADD COLUMN IF NOT EXISTS no hace nada: no reordena ni redefine la columna
   existente. El AFTER solo decide donde caen en una instalacion que aun no las
   tiene, para que termine con la misma estructura que el servidor. */

ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS ci_rut  VARCHAR(20)  NULL AFTER email,
    ADD COLUMN IF NOT EXISTS empresa VARCHAR(150) NULL AFTER ci_rut;

ALTER TABLE puntos_carga
    ADD COLUMN IF NOT EXISTS horario   VARCHAR(60)  NULL                 AFTER departamento,
    ADD COLUMN IF NOT EXISTS costo_kwh DECIMAL(6,2) NOT NULL DEFAULT 0.00 AFTER horario,
    ADD COLUMN IF NOT EXISTS cola      TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER estado;

/* ── 2. Tipos ───────────────────────────────────────────────────────────────
   MariaDB exige que la columna que referencia y la referenciada tengan el
   mismo tipo exacto. `usuarios.id` y `puntos_carga.id` son INT UNSIGNED, así
   que sin este paso cualquier FK falla con errno 150. La conversión no pierde
   datos: son ids autoincrementales, siempre positivos, lo que comprueba el
   control 'ids no positivos' de la verificación. Se conserva la nulabilidad de
   cada columna tal como estaba. */

ALTER TABLE transacciones
    MODIFY id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    MODIFY usuario_id     INT UNSIGNED NOT NULL,
    MODIFY punto_carga_id INT UNSIGNED NOT NULL,
    MODIFY propietario_id INT UNSIGNED NULL;

ALTER TABLE calificaciones
    MODIFY id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    MODIFY de_usuario_id   INT UNSIGNED NOT NULL,
    MODIFY para_usuario_id INT UNSIGNED NOT NULL,
    MODIFY transaccion_id  INT UNSIGNED NULL;

ALTER TABLE resenas
    MODIFY id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    MODIFY punto_carga_id INT UNSIGNED NOT NULL,
    MODIFY usuario_id     INT UNSIGNED NOT NULL;

ALTER TABLE password_resets
    MODIFY id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    MODIFY usuario_id INT UNSIGNED NOT NULL;

/* Sin clave foranea, pero se alinea igual para que una base migrada y una
   instalacion nueva desde schema.sql queden identicas. */
ALTER TABLE login_intentos
    MODIFY id INT UNSIGNED NOT NULL AUTO_INCREMENT;

/* ── 3. Unicidad ────────────────────────────────────────────────────────────
   uq_transaccion impide calificar dos veces la misma carga a nivel de base. La
   validación equivalente ya está en calificaciones.php; el índice cierra la
   carrera entre dos peticiones simultáneas, que la comprobación en PHP no puede
   cerrar por sí sola.

   uq_ci_rut respalda el control de documento repetido de registro.php. Sobre
   una columna nullable, UNIQUE admite varios NULL: la obligatoriedad del
   documento la sigue imponiendo la aplicación, no el índice. */

ALTER TABLE calificaciones ADD UNIQUE INDEX IF NOT EXISTS uq_transaccion (transaccion_id);
ALTER TABLE usuarios       ADD UNIQUE INDEX IF NOT EXISTS uq_ci_rut (ci_rut);

/* ── 4. Claves foráneas ─────────────────────────────────────────────────────
   Las reglas siguen la convención del esquema original: CASCADE para datos
   accesorios, SET NULL donde la fila sobrevive sin su referencia, y RESTRICT
   donde el borrado destruiría historial.

   La aplicación no borra usuarios, puntos de carga, transacciones, reseñas ni
   calificaciones: la HU-05 oculta con activo = 0, no borra. Estas restricciones
   no cambian, entonces, ningún comportamiento del sistema. Existen para que un
   borrado manual sobre la base no pueda dejar registros contables o de
   reputación apuntando al vacío.

   `login_intentos` queda deliberadamente sin FK: registra intentos fallidos
   contra direcciones de correo que muchas veces no corresponden a ningún
   usuario, que es precisamente su función. */

ALTER TABLE transacciones
    ADD CONSTRAINT fk_transacciones_usuario
        FOREIGN KEY IF NOT EXISTS (usuario_id)     REFERENCES usuarios(id)     ON DELETE RESTRICT,
    ADD CONSTRAINT fk_transacciones_punto_carga
        FOREIGN KEY IF NOT EXISTS (punto_carga_id) REFERENCES puntos_carga(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_transacciones_propietario
        FOREIGN KEY IF NOT EXISTS (propietario_id) REFERENCES usuarios(id)     ON DELETE SET NULL;

ALTER TABLE calificaciones
    ADD CONSTRAINT fk_calificaciones_transaccion
        FOREIGN KEY IF NOT EXISTS (transaccion_id)  REFERENCES transacciones(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_calificaciones_autor
        FOREIGN KEY IF NOT EXISTS (de_usuario_id)   REFERENCES usuarios(id)      ON DELETE RESTRICT,
    ADD CONSTRAINT fk_calificaciones_destinatario
        FOREIGN KEY IF NOT EXISTS (para_usuario_id) REFERENCES usuarios(id)      ON DELETE RESTRICT;

ALTER TABLE resenas
    ADD CONSTRAINT fk_resenas_punto_carga
        FOREIGN KEY IF NOT EXISTS (punto_carga_id) REFERENCES puntos_carga(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_resenas_usuario
        FOREIGN KEY IF NOT EXISTS (usuario_id)     REFERENCES usuarios(id)     ON DELETE RESTRICT;

/* Un token de recuperación no tiene sentido sin su usuario: acá sí corresponde
   CASCADE, porque es limpieza y no historial. */
ALTER TABLE password_resets
    ADD CONSTRAINT fk_password_resets_usuario
        FOREIGN KEY IF NOT EXISTS (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE;

/* ── 5. Índices ─────────────────────────────────────────────────────────────
   No se agregan índices adicionales. InnoDB ya crea uno por cada clave foránea,
   y eso cubre todas las columnas por las que la aplicación filtra o une estas
   tablas. Agregar más sin una consulta lenta que lo justifique sólo encarecería
   las escrituras. */

SELECT 'Migración 001 aplicada' AS resultado;
