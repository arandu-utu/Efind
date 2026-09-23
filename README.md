# E-Find

Plataforma de crowdsourcing para cargadores de vehículos eléctricos en Uruguay.
Proyecto de egreso 2026, equipo Arandú, Tecnicatura en Informática.

## Qué es E-Find

E-Find permite a los usuarios de vehículos eléctricos encontrar, reportar y reservar puntos de carga cercanos, con un sistema de reputación que da credibilidad a los reportes de la comunidad. El mapa integra los cargadores cargados por la propia comunidad junto con las estaciones públicas de la red CargaME de UTE.

## Stack técnico

| Capa | Tecnología |
|---|---|
| Frontend | HTML5, CSS3 y JavaScript sin frameworks |
| Mapa | Leaflet.js sobre cartografía de OpenStreetMap |
| Backend | PHP 8.5 sobre LAMP, sin frameworks |
| Base de datos | MariaDB 11.8 |
| Servidor web | Apache 2.4 |
| Autenticación | Sesiones PHP, con espejo en localStorage sólo para la navbar |
| Correo | Cliente SMTP propio sobre sockets (`includes/smtp.php`) |

## Arquitectura

```
Navegador  ->  HTML/JS  ->  /api/*.php  ->  MariaDB
```

- Todas las respuestas de la API siguen el formato `{ ok: true/false, data/error: ... }`.
- Autenticación dual: la sesión PHP del servidor es la frontera de autorización real; `localStorage['efind_session']` sólo alimenta el render de la navbar y no es autoritativo.
- Control de acceso por rol mediante `rol_id`: 1 administrador, 2 particular, 3 propietario.

## APIs implementadas

| Endpoint | Métodos | Acceso | Descripción |
|---|---|---|---|
| `/api/registro.php` | POST | Público | Registro de usuario. Particular valida CI, empresa valida RUT |
| `/api/login.php` | POST | Público | Inicio de sesión con límite de intentos por IP y email |
| `/api/logout.php` | GET | Sesión | Cierre de sesión |
| `/api/recuperar.php` | POST | Público | Solicita el restablecimiento de contraseña y envía el enlace |
| `/api/restablecer.php` | GET / POST | Token | Valida el token y fija la contraseña nueva |
| `/api/estaciones.php` | GET | Público | Lista los cargadores activos con sus conectores |
| `/api/estacion.php` | GET | Público | Detalle de un cargador |
| `/api/cargadores.php` | POST | Sesión | Alta de un cargador propio con sus conectores |
| `/api/estado.php` | PATCH | Sesión | Estado del cargador en vivo, sin moderación |
| `/api/cola.php` | PATCH | Sesión | Cola de espera en vivo, sin moderación |
| `/api/reportes.php` | GET / POST / PATCH | Sesión y admin | Reportes que requieren moderación |
| `/api/resenas.php` | GET / POST / PATCH | Sesión y admin | Reseñas de cargadores con cola de moderación |
| `/api/vehiculos.php` | GET / POST / DELETE | Sesión | Vehículos propios del usuario |
| `/api/pagos.php` | POST | Sesión | Procesa el pago simulado y emite el recibo |
| `/api/reservas.php` | GET / PATCH | Sesión | Historial de transacciones propias |
| `/api/calificaciones.php` | GET / POST | Sesión | Calificaciones entre usuarios |
| `/api/stats.php` | GET | Admin | Indicadores del panel de administración |
| `/api/usuarios.php` | GET / PATCH | Admin | Gestión de usuarios, paginada y con filtros |
| `/api/ute-sync.php` | POST | Admin | Sincroniza las estaciones de UTE a la base propia |

## Funcionalidades

| Módulo | Descripción |
|---|---|
| Mapa interactivo | Cargadores propios y de la red UTE, con filtros por conector, potencia, estado, acceso y fuente |
| Autenticación | Registro con validación de CI o RUT, inicio de sesión y recuperación de contraseña por correo |
| Detalle de cargador | Conectores, estado, horario, reseñas y estimador de tiempo y costo de carga |
| Crowdsourcing en vivo | Reporte de estado y de cola de espera que se aplica sin moderación previa |
| Reportes moderados | Reporte de incidencias que un administrador revisa y resuelve |
| Reseñas | Alta por el usuario y cola de moderación con aprobación o rechazo |
| Alta de cargadores | Formulario por pasos con ubicación en el mapa y búsqueda de dirección |
| Perfil de usuario | Datos personales, vehículos, cargadores propios, historial y calificaciones recibidas |
| Reservas y pagos | Estimación de carga, pago simulado con desglose de comisión y calificación posterior |
| Panel de administración | Indicadores, gráficos, moderación y gestión de usuarios, con paginación en el servidor |
| Interfaz bilingüe | Traducción completa entre español e inglés |

## Estructura del proyecto

```
efind-frontend/
├── .htaccess           # Charset UTF-8 para los .js
├── index.html          # Mapa principal
├── login.html          # Inicio de sesión
├── registro.html       # Registro de usuarios
├── recuperar.html      # Solicitud de recuperación de contraseña
├── restablecer.html    # Definición de la contraseña nueva
├── cargador.html       # Detalle de cargador
├── reservar.html       # Flujo de reserva y pago
├── agregar.html        # Alta de nuevo cargador
├── perfil.html         # Perfil del usuario
├── admin.html          # Panel de administración
├── usuarios.html       # Gestión de usuarios
├── 404.html
├── api/
│   ├── login.php / registro.php / logout.php
│   ├── recuperar.php / restablecer.php
│   ├── estaciones.php / estacion.php / cargadores.php
│   ├── estado.php / cola.php            # crowdsourcing en vivo
│   ├── reportes.php / resenas.php       # requieren moderación
│   ├── vehiculos.php / reservas.php / pagos.php / calificaciones.php
│   ├── stats.php / usuarios.php         # panel de administración
│   └── ute-sync.php                     # sincronización con UTE
├── includes/
│   ├── smtp.php        # Cliente SMTP sobre sockets, sin librerías
│   ├── db.php *        # db_connect() con PDO
│   ├── auth.php *      # requiere_login(), requiere_rol(), usuario_actual()
│   └── mail_config.php * # Credenciales del remitente
├── css/
│   └── style.css       # Hoja de estilos única
├── js/
│   ├── auth.js         # Sesión, navbar, apiFetch(), escapeHtml(), alertas, paginación
│   ├── i18n.js         # Traducción español e inglés
│   ├── icons.js        # Íconos SVG propios
│   ├── report-types.js # Catálogo de tipos de reporte
│   ├── mock-data.js    # Utilidades de interfaz: Sol de Mayo, calificaciones, URL
│   └── estimador.js    # Modelo físico de estimación de carga
├── db/
│   ├── schema.sql      # Esquema relacional completo, para instalación nueva
│   ├── seed.sql        # Datos iniciales
│   └── migraciones/    # Cambios sobre una base que ya existe, con verificación previa
└── img/                # Logos E-Find y Arandú
```

\* Contienen credenciales y existen sólo en el servidor. Están excluidos del repositorio.

## Esquema de base de datos

| Tabla | Descripción |
|---|---|
| `roles` | id, nombre |
| `usuarios` | id, nombre, email, password_hash, ci_rut, empresa, rol_id, activo, creado_en |
| `tipos_conector` | id, nombre, carga_rapida |
| `vehiculos` | id, usuario_id, marca, modelo, capacidad_bateria_kwh, tipo_conector_id, activo |
| `puntos_carga` | id, nombre, estado, acceso, fuente, lat, lng, horario, costo_kwh, cola, propietario_id |
| `conectores` | id, punto_carga_id, tipo_conector_id, potencia_kw, estado |
| `reportes` | id, punto_carga_id, usuario_id, tipo, descripcion, resuelto, creado_en |
| `favoritos` | id, usuario_id, punto_carga_id |
| `resenas` | id, punto_carga_id, usuario_id, estrellas, texto, estado, fecha_creacion |
| `transacciones` | id, usuario_id, punto_carga_id, propietario_id, recibo, kwh, monto_total, comision, calificado |
| `calificaciones` | id, de_usuario_id, para_usuario_id, transaccion_id, puntos, comentario, tipo |
| `login_intentos` | id, ip, email, intentos, ultimo_intento |
| `password_resets` | id, usuario_id, token_hash, expira_en, usado_en, creado_en |

Las tablas `reservas` y `pagos` forman parte del esquema original y se conservan, pero el flujo de reserva implementado utiliza `transacciones`, que unifica la reserva y el pago en un único registro con su recibo.

Las quince tablas están declaradas en `db/schema.sql`. Una base que ya existe se actualiza con los archivos de `db/migraciones/`, que se aplican sobre los datos existentes y llevan al mismo esquema que una instalación nueva. Ningún endpoint crea ni modifica tablas.

## Identidad visual

- Sol de Mayo como ícono del sistema de calificaciones.
- Conjunto de íconos SVG propios en `js/icons.js`.
- Marcador de mapa con rayo SVG embebido, diferenciado por acceso y por fuente.
- Paleta: azul profundo `#1A2E44`, azul de acento `#2D7DD2`, verde `#3BB273`.

## Entornos

El proyecto corre sobre dos entornos equivalentes. La máquina virtual con VirtualBox es el entorno de referencia documentado y reproducible. El servidor físico aloja la instancia publicada, con el túnel de Cloudflare como servicio de systemd para garantizar disponibilidad permanente.

## Despliegue

La raíz web es `/var/www/efind/efind-frontend/`. La copia directa al webroot falla por permisos, así que los archivos se suben a `/tmp` y se mueven con privilegios:

```bash
scp archivo.php administrador@SERVIDOR:/tmp/
ssh administrador@SERVIDOR "sudo mv /tmp/archivo.php /var/www/efind/efind-frontend/api/ && sudo chown administrador:administrador /var/www/efind/efind-frontend/api/archivo.php"
```

Para un despliegue completo, `git archive --format=tar.gz -o /tmp/efind-site.tar.gz HEAD` produce exactamente el árbol desplegable.

Los archivos de `includes/` con credenciales deben pertenecer al grupo `www-data` con permisos `640`, de lo contrario Apache no puede leerlos y los envíos de correo fallan sin mensaje visible.

Cloudflare cachea los `.js` y los `.css` de forma agresiva. Tras desplegar cambios en esos archivos hay que purgar la caché o versionar la URL con `?v=N`.

## Equipo

Arandú, Tecnicatura en Informática, 2026.
