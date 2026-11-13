/* ===========================================================================
   E-Find — Supresión de datos personales de un usuario (Ley 18.331, art. 13)
   ---------------------------------------------------------------------------
   Uso, con la base de destino indicada en la línea de comandos:

       mysql -u root -p efind -e "SET @uid = 123; SOURCE db/procedimientos/anonimizar_usuario.sql"

   El id se recibe en la variable de sesión @uid. El script no elige base: lo
   primero que devuelve es DATABASE() para comprobar el destino. Si el usuario
   no existe o ya fue anonimizado no modifica nada. Todo corre en una única
   transacción: o se aplica completo o no se aplica.

   Qué se elimina
     - usuarios: nombre, correo, cédula/RUT y empresa. La cuenta queda inactiva
       y con una contraseña que ningún ingreso puede igualar.
     - vehiculos, favoritos, password_resets y login_intentos del usuario.
     - Comentarios libres de sus calificaciones y descripciones de sus reportes.
     - Sus cargadores se dan de baja (activo = 0), porque la dirección puede
       ser su domicilio.
     - Sesiones: includes/sesion.php revalida `activo` en cada petición, así
       que una sesión abierta se cierra sola.

   Qué se conserva y por qué
     - transacciones: es el registro contable de la carga. Sin su cédula ni su
       nombre ya no identifica a la persona; sólo quedan el id de la cuenta
       anonimizada, la estación, los montos y el recibo.
     - calificaciones (los puntos) y resenas (texto y estrellas): sostienen la
       reputación de propietarios y estaciones. Quedan atribuidas a
       "Usuario eliminado". El texto de una reseña es contenido publicado y
       moderado; si la persona lo pide expresamente, se borra aparte.
     - reportes: se conservan sin autor (usuario_id = NULL) porque documentan
       el historial de la estación.

   Limitaciones: no actúa sobre copias de seguridad (se eliminan a los 14 días
   por la política de retención) ni sobre registros del servidor web. Anulada
   la cédula, el control de cuentas duplicadas deja de aplicarle a esa persona.
   =========================================================================== */

SELECT DATABASE() AS base_de_destino;

-- Usuario a anonimizar: NULL si no existe o ya fue anonimizado, y entonces
-- todas las sentencias siguientes no tocan ninguna fila.
SET @objetivo = (SELECT id FROM usuarios WHERE id = @uid AND email NOT LIKE 'baja-%@invalid');
SELECT @uid AS solicitado, @objetivo AS se_anonimiza;

START TRANSACTION;

-- Antes de cambiar el correo: login_intentos se enlaza por correo, no por id.
DELETE li FROM login_intentos li JOIN usuarios u ON u.email = li.email WHERE u.id = @objetivo;
DELETE FROM password_resets WHERE usuario_id = @objetivo;
DELETE FROM vehiculos       WHERE usuario_id = @objetivo;
DELETE FROM favoritos       WHERE usuario_id = @objetivo;

UPDATE calificaciones SET comentario = NULL WHERE de_usuario_id = @objetivo;
UPDATE reportes       SET descripcion = NULL, usuario_id = NULL WHERE usuario_id = @objetivo;
UPDATE resenas        SET usuario_nombre = 'Usuario eliminado' WHERE usuario_id = @objetivo;
UPDATE puntos_carga   SET activo = 0 WHERE propietario_id = @objetivo;

UPDATE usuarios
SET nombre        = 'Usuario eliminado',
    email         = CONCAT('baja-', id, '@invalid'),
    ci_rut        = NULL,
    empresa       = NULL,
    password_hash = '!',       -- no es un hash bcrypt válido: password_verify() siempre falla
    activo        = 0
WHERE id = @objetivo;

COMMIT;

SELECT id, nombre, email, ci_rut, empresa, activo FROM usuarios WHERE id = @uid;
