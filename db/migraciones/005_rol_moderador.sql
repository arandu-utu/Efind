/* ===========================================================================
   E-Find — Migración 005: el rol Moderador
   ---------------------------------------------------------------------------
   La letra del proyecto define cuatro perfiles y el Moderador era el único sin
   rol propio: sus tareas (moderar reseñas, resolver reportes, ocultar
   cargadores del mapa y suspender cuentas) las ejercía el administrador.

   Sólo agrega el rol. Quién lo recibe lo decide el administrador desde la
   gestión de usuarios, así que no crea ninguna cuenta.

       mysql -u root -p efind < db/migraciones/005_rol_moderador.sql

   Va antes de desplegar el código: usuarios.php ya permite asignar el rol 4 y,
   sin esta fila, la clave foránea rechazaría la asignación. Es idempotente.
   =========================================================================== */

SELECT DATABASE() AS base_de_destino;

INSERT IGNORE INTO roles (id, nombre) VALUES (4, 'moderador');

SELECT id, nombre FROM roles ORDER BY id;
