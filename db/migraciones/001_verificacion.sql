/* ===========================================================================
   E-Find — Verificación previa a la migración 001
   ---------------------------------------------------------------------------
   SÓLO LECTURA sobre los datos: no crea, no borra y no modifica ninguna fila.
   (Usa una tabla temporal de sesión únicamente para armar el veredicto.)

   Este archivo NO elige la base: se la indica quien lo ejecuta. La primera
   consulta devuelve el nombre de la base de destino justamente para poder
   comprobarlo antes de aplicar nada.

       mysql -u root -p efind < db/migraciones/001_verificacion.sql

   Toda la columna `bloqueantes` debe dar 0. Si algo no da 0, NO se aplica la
   migración: primero hay que entender por qué existen esos datos.
   =========================================================================== */

SELECT DATABASE() AS base_de_destino, VERSION() AS version_del_servidor;

CREATE TEMPORARY TABLE _verificacion (control VARCHAR(60), bloqueantes INT);

INSERT INTO _verificacion
SELECT 'usuarios.ci_rut duplicado', COUNT(*) FROM (
    SELECT ci_rut FROM usuarios WHERE ci_rut IS NOT NULL
    GROUP BY ci_rut HAVING COUNT(*) > 1) d
UNION ALL SELECT 'calificaciones.transaccion_id duplicado', COUNT(*) FROM (
    SELECT transaccion_id FROM calificaciones WHERE transaccion_id IS NOT NULL
    GROUP BY transaccion_id HAVING COUNT(*) > 1) d
UNION ALL SELECT 'transacciones.usuario_id huerfano', COUNT(*)
    FROM transacciones t LEFT JOIN usuarios u ON u.id = t.usuario_id WHERE u.id IS NULL
UNION ALL SELECT 'transacciones.punto_carga_id huerfano', COUNT(*)
    FROM transacciones t LEFT JOIN puntos_carga p ON p.id = t.punto_carga_id WHERE p.id IS NULL
UNION ALL SELECT 'transacciones.propietario_id huerfano', COUNT(*)
    FROM transacciones t LEFT JOIN usuarios u ON u.id = t.propietario_id
    WHERE t.propietario_id IS NOT NULL AND u.id IS NULL
UNION ALL SELECT 'calificaciones.transaccion_id huerfano', COUNT(*)
    FROM calificaciones c LEFT JOIN transacciones t ON t.id = c.transaccion_id
    WHERE c.transaccion_id IS NOT NULL AND t.id IS NULL
UNION ALL SELECT 'calificaciones.de_usuario_id huerfano', COUNT(*)
    FROM calificaciones c LEFT JOIN usuarios u ON u.id = c.de_usuario_id WHERE u.id IS NULL
UNION ALL SELECT 'calificaciones.para_usuario_id huerfano', COUNT(*)
    FROM calificaciones c LEFT JOIN usuarios u ON u.id = c.para_usuario_id WHERE u.id IS NULL
UNION ALL SELECT 'resenas.punto_carga_id huerfano', COUNT(*)
    FROM resenas r LEFT JOIN puntos_carga p ON p.id = r.punto_carga_id WHERE p.id IS NULL
UNION ALL SELECT 'resenas.usuario_id huerfano', COUNT(*)
    FROM resenas r LEFT JOIN usuarios u ON u.id = r.usuario_id WHERE u.id IS NULL
UNION ALL SELECT 'password_resets.usuario_id huerfano', COUNT(*)
    FROM password_resets pr LEFT JOIN usuarios u ON u.id = pr.usuario_id WHERE u.id IS NULL
/* Valores negativos o cero: las columnas pasan a INT UNSIGNED y no podrían
   almacenarlos. No debería haber ninguno, porque son ids autoincrementales. */
UNION ALL SELECT 'ids no positivos', COUNT(*) FROM (
    SELECT id FROM transacciones WHERE id <= 0 OR usuario_id <= 0 OR punto_carga_id <= 0
    UNION ALL SELECT id FROM calificaciones WHERE id <= 0 OR de_usuario_id <= 0 OR para_usuario_id <= 0
    UNION ALL SELECT id FROM resenas WHERE id <= 0 OR punto_carga_id <= 0 OR usuario_id <= 0
    UNION ALL SELECT id FROM password_resets WHERE id <= 0 OR usuario_id <= 0) d;

SELECT * FROM _verificacion;

SELECT CASE WHEN SUM(bloqueantes) = 0
            THEN 'APTA — se puede aplicar 001_integridad_up.sql'
            ELSE CONCAT('NO APTA — ', SUM(bloqueantes),
                        ' fila(s) bloqueante(s). Revisar el detalle de arriba.')
       END AS veredicto
FROM _verificacion;

DROP TEMPORARY TABLE _verificacion;
