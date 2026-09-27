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
   '$2y$12$iTxeBt8rUDl9pXhhM4LYy.Mv4ERyjhfKYsKMCAvYvcQZn.tC92mA.',
   1);

-- -----------------------------------------------------
-- USUARIOS DE EJEMPLO (password de los dos: Usuario1234!)
-- Existen para que una instalación limpia permita recorrer el sistema:
-- registrarse no hace falta, y hay un propietario con cargadores propios.
-- -----------------------------------------------------
INSERT INTO usuarios (nombre, email, password_hash, ci_rut, empresa, rol_id) VALUES
  ('Lucía Ferreira', 'lucia@ejemplo.uy', '$2y$12$8x7h9V1s3VKkFJ3AcZfkDeljEA86KTb0RuBjL5qPTwgW5ASx.Yj3C', '12345672', NULL, 2),
  ('Cargas del Este S.R.L.', 'empresa@ejemplo.uy', '$2y$12$8x7h9V1s3VKkFJ3AcZfkDeljEA86KTb0RuBjL5qPTwgW5ASx.Yj3C', '210001234567', 'Cargas del Este S.R.L.', 3);


-- -----------------------------------------------------
-- PUNTOS DE CARGA DE EJEMPLO (Rocha, Uruguay)
-- -----------------------------------------------------
INSERT INTO puntos_carga
  (nombre, descripcion, direccion, ciudad, departamento, lat, lng, acceso, estado, verificado,
   horario, costo_kwh, propietario_id)
VALUES
  ('Estación E-Find Centro',
   'Cargador público en el centro de Rocha. Disponible las 24 horas.',
   'Rambla Br. Artigas 150', 'Rocha', 'Rocha',
   -34.4826, -54.3306, 'publico', 'disponible', 1,
   '24 horas', 0, NULL),

  ('Parking Shopping Rocha',
   'Cargador en estacionamiento techado del shopping.',
   'Av. Italia 890', 'Rocha', 'Rocha',
   -34.4801, -54.3289, 'publico', 'disponible', 1,
   'Lun a sáb 9 a 22', 14.50, 3),

  ('Hotel Palacio — Cargador privado',
   'Solo para huéspedes del hotel.',
   'Calle 25 de Agosto 340', 'Rocha', 'Rocha',
   -34.4835, -54.3315, 'privado', 'disponible', 1,
   '24 horas', 18.00, 3),

  ('Terminal de Ómnibus Rocha',
   'Cargador rápido junto a la terminal.',
   'Av. Victorio Viana s/n', 'Rocha', 'Rocha',
   -34.4791, -54.3278, 'publico', 'sin_servicio', 1,
   '24 horas', 0, NULL),

  ('Playa La Paloma — Acceso norte',
   'Cargador estacional, activo de noviembre a marzo.',
   'Ruta 15 km 0', 'La Paloma', 'Rocha',
   -34.6614, -54.1547, 'publico', 'disponible', 1,
   'Nov a mar, 8 a 20', 12.00, NULL),

  /* Privado y con precio: es el que permite ejercitar reserva, pago,
     comisión y calificación en una instalación recién hecha. */
  ('Cochera Pocitos — Carga nocturna',
   'Cargador particular en cochera, disponible de noche.',
   'Bulevar España 2410', 'Montevideo', 'Montevideo',
   -34.9120, -56.1560, 'privado', 'disponible', 1,
   'Todos los días 20 a 8', 16.00, 2);


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
  (5, 4, 50.0, 'disponible');

-- Cochera Pocitos (id=6): Type 2. Sin conectores, un cargador no se ve en el mapa.
INSERT INTO conectores (punto_carga_id, tipo_conector_id, potencia_kw, estado) VALUES
  (6, 1, 7.40, 'disponible');   -- CHAdeMO


-- -----------------------------------------------------
-- DATOS DE ACTIVIDAD
-- Sin esto, una instalación limpia muestra el panel de administración vacío
-- y no hay forma de ver el historial, la cola de moderación ni las
-- calificaciones sin generarlos a mano.
-- -----------------------------------------------------

-- Vehículo de Lucía (tipo_conector_id 1 = Type 2, según el seed de arriba)
INSERT INTO vehiculos (usuario_id, marca, modelo, anio, capacidad_bateria_kwh, autonomia_km, tipo_conector_id) VALUES
  (2, 'Renault', 'Zoe', 2022, 52.00, 395.0, 1);

-- Una carga ya realizada en el cargador privado de la empresa (id 3, 18 $/kWh).
-- 41,60 kWh * 18 = 748,80; la comisión es el 10 % de ese importe.
INSERT INTO transacciones
  (usuario_id, punto_carga_id, propietario_id, recibo, kwh, tiempo, monto_total, comision, calificado, fecha) VALUES
  (2, 3, 3, 'EF-EJEMPLO01', 41.60, '2 h 3 min', 748.80, 74.88, 1, CURDATE() - INTERVAL 3 DAY);

-- La calificación de esa carga, del cliente al propietario
INSERT INTO calificaciones (de_usuario_id, para_usuario_id, transaccion_id, puntos, comentario, tipo, fecha) VALUES
  (2, 3, 1, 5, 'Cargador impecable y el dueño muy atento.', 'cliente_a_propietario', CURDATE() - INTERVAL 3 DAY);

-- Una reseña publicada y otra esperando moderación, para que la cola del
-- panel de administración tenga algo que mostrar
INSERT INTO resenas (punto_carga_id, usuario_id, usuario_nombre, estrellas, texto, estado, fecha_creacion) VALUES
  (1, 2, 'Lucía Ferreira', 4, 'Bien ubicado y siempre libre cuando pasé.', 'aprobada',  CURDATE() - INTERVAL 5 DAY),
  (2, 2, 'Lucía Ferreira', 2, 'El conector estaba flojo, costó que enganchara.', 'pendiente', CURDATE() - INTERVAL 1 DAY);

-- Un reporte sin resolver, para la bandeja del administrador
INSERT INTO reportes (punto_carga_id, usuario_id, tipo, descripcion) VALUES
  (4, 2, 'fuera_servicio', 'La pantalla no enciende y el cable está cortado.');
