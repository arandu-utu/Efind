-- =====================================================
--  E-Find — Datos Iniciales (Seed)
--  Ejecutar DESPUÉS de schema.sql
--  Rev. 1.0 — 2026-08-11
-- =====================================================

-- La base de destino la indica quien ejecuta el archivo, no el archivo.

-- -----------------------------------------------------
-- ROLES
-- -----------------------------------------------------
INSERT INTO roles (id, nombre) VALUES
  (1, 'admin'),
  (2, 'usuario'),
  (3, 'propietario');


-- -----------------------------------------------------
-- TIPOS DE CONECTOR
-- -----------------------------------------------------
INSERT INTO tipos_conector (nombre, carga_rapida) VALUES
  ('Schuko',   0),   -- toma doméstica, lenta
  ('Type 2',   0),   -- estándar europeo AC
  ('CCS2',     1),   -- Combined Charging System DC
  ('CHAdeMO',  1),   -- estándar japonés DC
  ('GB/T AC',  0),   -- estándar chino AC
  ('GB/T DC',  1);   -- estándar chino DC rápido


-- -----------------------------------------------------
-- USUARIO ADMIN (password: Admin1234!)
-- Hash bcrypt generado con cost=12
-- Cambiar en producción
-- -----------------------------------------------------
INSERT INTO usuarios (nombre, email, password_hash, rol_id) VALUES
  ('Administrador E-Find',
   'admin@efind.uy',
   '$2y$12$placeholder_hash_cambiar_en_produccion_xxxxxxxxxxxxxxxxxxx',
   1);


-- -----------------------------------------------------
-- PUNTOS DE CARGA DE EJEMPLO (Rocha, Uruguay)
-- -----------------------------------------------------
INSERT INTO puntos_carga
  (nombre, descripcion, direccion, ciudad, departamento, lat, lng, acceso, estado, verificado)
VALUES
  ('Estación E-Find Centro',
   'Cargador público en el centro de Rocha. Disponible las 24 horas.',
   'Rambla Br. Artigas 150', 'Rocha', 'Rocha',
   -34.4826, -54.3306, 'publico', 'disponible', 1),

  ('Parking Shopping Rocha',
   'Cargador en estacionamiento techado del shopping.',
   'Av. Italia 890', 'Rocha', 'Rocha',
   -34.4801, -54.3289, 'publico', 'disponible', 1),

  ('Hotel Palacio — Cargador privado',
   'Solo para huéspedes del hotel.',
   'Calle 25 de Agosto 340', 'Rocha', 'Rocha',
   -34.4835, -54.3315, 'privado', 'disponible', 1),

  ('Terminal de Ómnibus Rocha',
   'Cargador rápido junto a la terminal.',
   'Av. Victorio Viana s/n', 'Rocha', 'Rocha',
   -34.4791, -54.3278, 'publico', 'sin_servicio', 1),

  ('Playa La Paloma — Acceso norte',
   'Cargador estacional, activo de noviembre a marzo.',
   'Ruta 15 km 0', 'La Paloma', 'Rocha',
   -34.6614, -54.1547, 'publico', 'disponible', 1);


-- -----------------------------------------------------
-- CONECTORES DE EJEMPLO
-- -----------------------------------------------------
-- Estación E-Find Centro (id=1): Type 2 + CCS2
INSERT INTO conectores (punto_carga_id, tipo_conector_id, potencia_kw, estado) VALUES
  (1, 2, 22.0,  'disponible'),   -- Type 2 AC
  (1, 3, 50.0,  'disponible');   -- CCS2 DC rápido

-- Parking Shopping (id=2): dos Type 2
INSERT INTO conectores (punto_carga_id, tipo_conector_id, potencia_kw, estado) VALUES
  (2, 2, 11.0, 'disponible'),
  (2, 2, 11.0, 'ocupado');

-- Hotel Palacio (id=3): Schuko + Type 2
INSERT INTO conectores (punto_carga_id, tipo_conector_id, potencia_kw, estado) VALUES
  (3, 1,  3.7, 'disponible'),   -- Schuko lento
  (3, 2, 11.0, 'disponible');   -- Type 2

-- Terminal (id=4): CCS2 fuera de servicio
INSERT INTO conectores (punto_carga_id, tipo_conector_id, potencia_kw, estado) VALUES
  (4, 3, 50.0, 'sin_servicio');

-- La Paloma (id=5): Type 2 + CHAdeMO
INSERT INTO conectores (punto_carga_id, tipo_conector_id, potencia_kw, estado) VALUES
  (5, 2, 22.0, 'disponible'),
  (5, 4, 50.0, 'disponible');   -- CHAdeMO
