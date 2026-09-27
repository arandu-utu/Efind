/* ===========================================================================
   E-Find — Migración 003: el número de recibo no puede repetirse
   ---------------------------------------------------------------------------
   El recibo identifica la operación ante el usuario y se muestra en su
   historial. Se armaba con la hora más un número al azar de tres cifras, o sea
   900 valores por segundo, y la columna no tenía índice único: dos pagos en el
   mismo segundo podían quedar con el mismo comprobante sin que nada avisara.

   El código ya genera el recibo con random_bytes. El índice cierra la carrera
   que la generación por sí sola no cierra, con el mismo criterio que
   uq_transaccion en la migración 001.

       mysql -u root -p efind < db/migraciones/003_recibo_unico.sql

   Es idempotente. Si hubiera recibos repetidos de antes, el ALTER falla y no
   escribe nada: en ese caso hay que revisarlos antes, no forzar el índice.
   =========================================================================== */

SELECT DATABASE() AS base_de_destino;

/* Control previo: tiene que dar 0. */
SELECT COUNT(*) AS recibos_repetidos FROM (
    SELECT recibo FROM transacciones GROUP BY recibo HAVING COUNT(*) > 1
) d;

ALTER TABLE transacciones ADD UNIQUE INDEX IF NOT EXISTS uq_recibo (recibo);

SELECT 'Migración 003 aplicada' AS resultado;
