# Batería de pruebas de integración

69 casos que ejercitan los endpoints reales por HTTP, contra una base de datos
real. No son pruebas unitarias con dobles: levantan el sitio, hacen peticiones
con sesión y cookies como lo haría un navegador, y comprueban tanto la
respuesta como el estado en que queda la base.

Se usan como criterio de aceptación: cualquier cambio en `api/` o en el
esquema tiene que dejarlas en 69 de 69 antes de desplegar.

## Qué cubren

| Grupo | Casos | Qué comprueba |
|---|---|---|
| Registro | 11 | Cédula con dígito verificador, RUT, documento y correo repetidos, campos faltantes |
| Sesiones | 9 | Login, credenciales inválidas, cierre de sesión, acceso sin sesión |
| Cargadores | 12 | Alta válida, precio cero, precio negativo, coordenadas fuera de rango, conector desconocido |
| Estado y cola | 7 | Reporte válido, cargador inexistente, valores fuera de rango |
| Pagos | 9 | Importe reconstruido en el servidor, vehículo ajeno, conector de otra estación, cargador público rechazado |
| Calificaciones | 7 | Transacción propia, ya calificada, destinatario que no corresponde, autocalificación |
| Reseñas y reportes | 4 | Cargador existente e inexistente |
| Ocultar del mapa (HU-05) | 10 | Permisos, efecto en la base, y que un cargador oculto no se pueda pagar, reseñar ni reportar |

## Cómo se corren

Hace falta PHP con cURL y PDO, y MariaDB. **Nunca contra la base de
producción**: la batería crea y modifica datos, y el propio script se niega a
arrancar si la base se llama `efind`.

```bash
mysql -u root -p -e "DROP DATABASE IF EXISTS efind_test; CREATE DATABASE efind_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p efind_test < db/schema.sql
mysql -u root -p efind_test < db/seed.sql
```

En una terminal, el servidor. El enrutador está para que el servidor embebido
de PHP resuelva los `require '../includes/...'` igual que Apache, que ejecuta
cada script con el directorio de trabajo puesto en su propia carpeta:

```bash
php -S 127.0.0.1:8011 -t . pruebas/router.php
```

En otra, la batería:

```bash
php pruebas/suite.php
```

Imprime cada caso y termina con el total. Si algo falla, lista los casos
fallidos con el detalle de lo que devolvió el endpoint.

## Configuración

Los valores por defecto son los de un XAMPP recién instalado. Se cambian por
entorno, sin editar los archivos:

| Variable | Por defecto |
|---|---|
| `EFIND_TEST_URL` | `http://127.0.0.1:8011` |
| `EFIND_TEST_DB` | `efind_test` |
| `EFIND_TEST_USER` | `root` |
| `EFIND_TEST_PASS` | vacío |

## Notas

Cada corrida genera cédulas y correos nuevos a partir de la hora, así que se
puede ejecutar varias veces seguidas sin reconstruir la base. Aun así conviene
partir de una base limpia cuando se comprueba un cambio de esquema: algunos
casos cuentan filas.

`includes/db.php` y `includes/auth.php` no están en el repositorio porque
tienen credenciales. Para correr la batería en una máquina de desarrollo hay
que crearlos apuntando a `efind_test`.

## Lo que la batería NO cubre

Se deja constancia explícita para que la cobertura no se dé por mayor de lo que es.

- **El envío real de correo.** Requiere credenciales del servidor, que no están en el
  repositorio.
- **La interfaz.** La batería ejercita los endpoints, no el navegador. Las
  comprobaciones de interfaz, responsive y accesibilidad se hacen a mano y están
  registradas en el plan de pruebas del documento de entrega.
