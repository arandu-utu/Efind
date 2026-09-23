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
