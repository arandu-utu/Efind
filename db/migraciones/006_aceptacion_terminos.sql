/* ===========================================================================
   E-Find — Migración 006: fecha de aceptación de los términos
   ---------------------------------------------------------------------------
   El registro pasa a exigir que el usuario acepte los Términos y condiciones
   y la Política de privacidad. La Ley 18.331 pide consentimiento expreso para
   tratar datos personales, y quien los trata tiene que poder demostrarlo: por
   eso se guarda cuándo se aceptó.

   Las cuentas creadas antes quedan con NULL. No se completa con una fecha
   inventada, porque esas personas no aceptaron nada todavía.

       mysql -u root -p efind < db/migraciones/006_aceptacion_terminos.sql

   Va antes de desplegar el código: registro.php ya escribe la columna y, sin
   ella, ningún alta nueva funcionaría. Es idempotente.
   =========================================================================== */

SELECT DATABASE() AS base_de_destino;

ALTER TABLE usuarios
  ADD COLUMN IF NOT EXISTS terminos_aceptados_en DATETIME NULL AFTER activo;

SELECT COUNT(*) AS cuentas, COUNT(terminos_aceptados_en) AS con_aceptacion FROM usuarios;
