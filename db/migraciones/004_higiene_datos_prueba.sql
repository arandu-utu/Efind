/* ===========================================================================
   E-Find — Limpieza de datos de prueba publicados
   ---------------------------------------------------------------------------
   Durante el desarrollo quedaron publicados en el mapa cargadores creados para
   probar el alta. Tres de ellos tienen un par de coordenadas en el campo de
   dirección, porque el formulario lo autocompletaba así; eso ya se corrigió en
   agregar.html y ahora la dirección se escribe.

   No se borran: se retiran del mapa con activo = 0, igual que hace la HU-05.
   Borrarlos no es posible sin romper el historial, porque transacciones y
   reseñas los referencian con ON DELETE RESTRICT, y tampoco es deseable: una
   carga ya realizada es un registro contable.

       mysql -u root -p efind < db/migraciones/004_higiene_datos_prueba.sql

   REVISAR LA LISTA ANTES DE EJECUTAR. Los identificadores son los de la base
   de producción al 27/09/2026 y no valen para otra instalación.
   =========================================================================== */

SELECT DATABASE() AS base_de_destino;

/* Qué se va a retirar, para poder confirmarlo antes. */
SELECT id, nombre, direccion, propietario_id, activo
FROM   puntos_carga
WHERE  id IN (6, 220, 221, 223, 224);

UPDATE puntos_carga
SET    activo = 0
WHERE  id IN (6, 220, 221, 223, 224);

/* Cuántos quedan publicados, por origen. */
SELECT fuente, COUNT(*) AS publicados
FROM   puntos_carga
WHERE  activo = 1
GROUP  BY fuente;

SELECT 'Limpieza aplicada' AS resultado;
