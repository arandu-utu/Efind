# Migraciones de base de datos

`db/schema.sql` describe la base **desde cero**. Esta carpeta contiene los
cambios que hay que aplicar sobre una base **que ya existe y tiene datos**,
como la de producción, para llevarla al mismo estado.

Los dos caminos convergen: una instalación nueva desde `schema.sql` y una base
existente con la migración 001 aplicada quedan con el esquema idéntico.

## Regla de oro

Ningún archivo de esta carpeta elige la base de datos. La indica quien lo
ejecuta, en la línea de comandos. Todos empiezan devolviendo `DATABASE()` para
que se pueda comprobar el destino antes de seguir.

```bash
mysql -u root -p efind < db/migraciones/001_verificacion.sql
```

## 001 — Integridad de las tablas autocreadas

Durante el desarrollo, cinco tablas (`transacciones`, `calificaciones`,
`resenas`, `password_resets`, `login_intentos`) se creaban solas desde los
endpoints con `CREATE TABLE IF NOT EXISTS`. Quedaron sin claves foráneas y con
los enteros en `INT` con signo, mientras el resto del esquema usa
`INT UNSIGNED`. Además, cinco columnas que el código usa (`usuarios.ci_rut`,
`usuarios.empresa`, `puntos_carga.horario`, `puntos_carga.costo_kwh`,
`puntos_carga.cola`) se habían agregado con `ALTER` sobre el servidor y nunca
volvieron al esquema versionado.

La migración reconcilia las dos cosas. Los endpoints ya no crean tablas: el
esquema es responsabilidad de `schema.sql` y de esta carpeta.

### Orden de ejecución

```bash
# 1. Copia de seguridad. Sin esto no se sigue.
mysqldump -u root -p efind > backups/efind_$(date +%F).sql

# 2. Verificación. Sólo lectura. Toda la columna `bloqueantes` debe dar 0
#    y el veredicto debe decir APTA.
mysql -u root -p efind < db/migraciones/001_verificacion.sql

# 3. Migración.
mysql -u root -p efind < db/migraciones/001_integridad_up.sql
```

Si la verificación no da APTA, **no se aplica la migración**. Un duplicado o un
huérfano es un dato que hay que entender antes de tocarlo; la migración no
corrige datos históricos por su cuenta.

### Marcha atrás

```bash
mysql -u root -p efind < db/migraciones/001_integridad_down.sql
```

Quita las restricciones y vuelve los tipos a `INT` con signo. No borra filas ni
columnas: las cinco columnas de la sección 1 se conservan, porque eliminarlas
destruiría datos reales y su ausencia era un defecto del esquema, no un estado
al que valga la pena volver.

### Qué se probó

Sobre una réplica de producción (esquema original + las tablas con sus
definiciones viejas + datos con casos límite: `propietario_id` nulo y una
calificación sin transacción asociada):

- La verificación detecta duplicados, huérfanos e ids no positivos.
- Sin la conversión de tipos, cualquier clave foránea falla con errno 150.
- `up` aplicado dos veces no produce error ni cambios.
- `down` deja la base como estaba; `up` vuelve a aplicarse después.
- Las restricciones bloquean lo que deben: transacción con usuario inexistente,
  segunda calificación sobre la misma carga, segundo usuario con el mismo
  documento, borrado de un usuario con historial contable.
- `login_intentos` sigue aceptando intentos contra correos que no son de ningún
  usuario, que es justamente su función.
- Los 65 casos de integración dan 65/65 antes y después de migrar.

---

## 002 — Estado de las estaciones de UTE

La API de UTE devuelve `statusDetail = 'Busy'` en todos sus conectores, en todas
las corridas. Es un valor constante que no informa ocupación real, y al mapearlo
como `ocupado` el mapa quedaba con casi todas las estaciones en naranja.

A partir de ahora una estación de UTE queda `disponible` salvo que la comunidad
reporte otra cosa, y el sincronizador ya no pisa ese reporte. Esta migración
corrige las filas que quedaron congeladas por las corridas anteriores.

```bash
mysql -u root -p efind < db/migraciones/002_estado_ute.sql
```

**Se ejecuta una sola vez.** Después de aplicarla, un `ocupado` en una estación
de UTE ya no viene del sincronizador sino de un reporte real de la comunidad, y
volver a correrla lo borraría.

Probada sobre una réplica de producción: 212 estaciones liberadas, los
cargadores de E-Find sin tocar, y un reporte de la comunidad sobrevive a dos
sincronizaciones seguidas contra el feed real.

## 003 — El número de recibo no puede repetirse

El recibo identifica la operación ante el usuario. Se armaba con la hora más un
número al azar de tres cifras, o sea 900 valores por segundo, y la columna no
tenía índice único: dos pagos en el mismo segundo podían quedar con el mismo
comprobante sin que nada avisara.

```bash
mysql -u root -p efind < db/migraciones/003_recibo_unico.sql
```

Idempotente. Si hubiera recibos repetidos de antes, el `ALTER` falla y no
escribe nada: en ese caso hay que revisarlos, no forzar el índice.

## 004 — Retirar del mapa los cargadores de prueba

Durante el desarrollo quedaron publicados cargadores creados para probar el
alta, tres de ellos con un par de coordenadas en el campo de dirección. No se
borran: se retiran con `activo = 0`, igual que hace la HU-05, porque
`transacciones` y `resenas` los referencian con `ON DELETE RESTRICT` y una carga
realizada es un registro contable.

```bash
mysql -u root -p efind < db/migraciones/004_higiene_datos_prueba.sql
```

**Los identificadores son los de producción al 27/09/2026 y no valen para otra
instalación.** Revisar la lista que imprime antes de confirmar.

## Orden de aplicación

Sobre una base existente: 001, 002, 003, 004. Las tres primeras van **antes** de
desplegar el código que las acompaña; la 004 puede ir después.

Una instalación nueva desde `db/schema.sql` ya incluye todo lo estructural de
001 y 003, así que sólo necesita el esquema y la semilla.
