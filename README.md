# E-Find ⚡

> Plataforma de crowdsourcing para cargadores de vehículos eléctricos en Uruguay.  
> Proyecto de egreso 2026 — Equipo Arandú · Tecnicatura en Informática

---

## ¿Qué es E-Find?

E-Find permite a los usuarios de vehículos eléctricos en Uruguay **encontrar, reportar y reservar** puntos de carga cercanos, con un sistema de reputación que da credibilidad a los reportes de la comunidad.

## Stack técnico

| Capa | Tecnología |
|---|---|
| Frontend | HTML5 / CSS3 / JavaScript puro (sin frameworks) |
| Mapa | Leaflet.js |
| Backend | PHP 8 (LAMP, sin frameworks) |
| Base de datos | MariaDB |
| Servidor | Apache 2 en VM Ubuntu |
| Auth | Sesiones PHP + localStorage para estado de navbar |

## Arquitectura

```
Navegador  →  HTML/JS  →  /api/*.php  →  MariaDB
```

- Todas las respuestas de API siguen el formato `{ ok: true/false, data/error: ... }`
- Autenticación dual: sesión PHP en servidor + `localStorage['efind_session']` para la navbar
- RBAC: `rol_id` 1 = admin · 2 = particular · 3 = propietario

## APIs implementadas

| Endpoint | Métodos | Auth | Descripción |
|---|---|---|---|
| `/api/login.php` | POST | — | Inicio de sesión (rate limit por IP+email) |
| `/api/registro.php` | POST | — | Registro de usuario (particular → rol 2, empresa → rol 3 propietario) |
| `/api/logout.php` | GET | — | Cierre de sesión (solo en servidor) |
| `/api/estaciones.php` | GET | — | Lista cargadores (público, con conectores) |
| `/api/estacion.php` | GET | — | Detalle de un cargador (solo en servidor) |
| `/api/cargadores.php` | POST | Login | Alta de un cargador propio + sus conectores |
| `/api/stats.php` | GET | Admin | KPIs del dashboard |
| `/api/reportes.php` | GET / POST / PATCH | Login / Admin | Reportes de cargadores |
| `/api/resenas.php` | GET / POST / PATCH | Login / Admin | Reseñas de cargadores |
| `/api/usuarios.php` | GET / PATCH | Admin | Gestión de usuarios |
| `/api/estado.php` | PATCH | Login | Estado del cargador en vivo (crowdsourced) |
| `/api/cola.php` | PATCH | Login | Cola de espera en vivo (crowdsourced) |
| `/api/ute-sync.php` | POST | Admin | Sincroniza cargadores públicos de UTE a la base propia |
| `/api/vehiculos.php` | GET / POST / DELETE | Login | Vehículos propios del usuario |
| `/api/reservas.php` | GET / PATCH | Login | Historial de transacciones propias |
| `/api/pagos.php` | POST | Login | Procesa el pago simulado y emite el recibo |
| `/api/calificaciones.php` | GET / POST | Login | Calificaciones entre usuarios (recibidas + nueva) |

## Funcionalidades

| Módulo | Estado | Descripción |
|---|---|---|
| 🗺️ Mapa interactivo | ✅ Conectado a DB | Leaflet.js, cargadores reales + UTE, filtros por estado y tipo |
| 🔐 Autenticación | ✅ PHP + sesiones | Registro, login, roles (particular, propietario, admin) |
| 🛡️ Panel admin | ✅ API real | KPIs, gráfico mensual, donut de roles, moderación de reseñas y reportes |
| 👥 Gestión de usuarios | ✅ API real | Lista, cambio de rol, suspensión/activación |
| ⚡ Detalle de cargador | ✅ API real | Conectores, estado, reseñas, reporte y estado/cola en vivo |
| ⭐ Reseñas | ✅ API real | Creación y moderación (pendiente → aprobada/rechazada) |
| 📢 Reportes | ✅ API real | Creación y resolución por admin |
| 👤 Perfil de usuario | ✅ API real | Datos personales, vehículos, cargadores propios, historial, calificaciones |
| 📅 Reservas | ✅ API real | Estimador físico-matemático + pago simulado + calificación post-pago |

## Estructura del proyecto

```
efind-frontend/
├── index.html          # Mapa principal
├── login.html          # Autenticación
├── registro.html       # Registro de usuarios
├── cargador.html       # Detalle de cargador (API real, incluye modal de reporte)
├── reservar.html       # Flujo de reserva
├── agregar.html        # Alta de nuevo cargador
├── perfil.html         # Perfil del usuario
├── admin.html          # Panel admin (API real)
├── usuarios.html       # Gestión de usuarios (API real)
├── api/
│   ├── login.php / registro.php* / logout.php*
│   ├── estaciones.php / estacion.php* / cargadores.php
│   ├── estado.php / cola.php          # crowdsourcing en vivo
│   ├── reportes.php / resenas.php     # requieren moderación de admin
│   ├── vehiculos.php / reservas.php / pagos.php / calificaciones.php
│   ├── stats.php / usuarios.php       # panel admin
│   └── ute-sync.php                   # sincronización manual con UTE
├── includes/           # En el servidor (fuera del repo, gitignored)
│   ├── db.php          # db_connect() + PDO
│   └── auth.php        # requiere_login(), requiere_rol(), usuario_actual()
├── css/
│   └── style.css       # Sistema de diseño completo
├── js/
│   ├── auth.js          # Navbar/footer, Auth (sesión), escapeHtml()
│   ├── i18n.js           # Toggle ES/EN
│   ├── icons.js          # Set de íconos SVG custom
│   ├── report-types.js   # Tipos de reporte (fuente única admin/cargador)
│   ├── mock-data.js      # Solo utilidades: solSVG(), renderStars(), cargadorIdFromURL()
│   └── estimador.js       # Modelo físico de estimación de carga
└── img/                # Logos E-Find y Arandú
```
\* Solo existen en el servidor, no en este checkout — ver "Local repo gaps" en `CLAUDE.md`.

## Esquema de base de datos (tablas principales)

| Tabla | Descripción |
|---|---|
| `usuarios` | id, nombre, email, password_hash, ci_rut, empresa, rol_id, activo, creado_en |
| `puntos_carga` | id, nombre, estado, acceso, fuente, lat, lng, cola, propietario_id, costo_kwh |
| `conectores` | id, punto_carga_id, tipo_conector_id, potencia_kw, estado |
| `tipos_conector` | id, nombre (CCS2, CHAdeMO, Type 2, Schuko…), carga_rapida |
| `resenas` | id, punto_carga_id, usuario_id, estrellas, texto, estado, fecha_creacion |
| `reportes` | id, punto_carga_id, usuario_id, tipo, descripcion, resuelto, creado_en |
| `roles` | id, nombre |
| `vehiculos` | id, usuario_id, marca, modelo, capacidad_kwh, tipo_conector |
| `transacciones` | id, usuario_id, punto_carga_id, propietario_id, recibo, kwh, monto_total, comision, calificado |
| `calificaciones` | id, de_usuario_id, para_usuario_id, transaccion_id, puntos, comentario, tipo |
| `login_intentos` | id, ip, email, intentos, ultimo_intento (rate limit de login) |

Las últimas cuatro se auto-crean (`CREATE TABLE IF NOT EXISTS`) la primera vez que el endpoint correspondiente se usa — no requieren migración manual.

## Identidad visual

- **Sol de Mayo** uruguayo como ícono del sistema de calificaciones
- Set de íconos SVG custom (`js/icons.js`)
- Marcador de mapa E-Find con rayo SVG embebido
- Paleta: azul profundo `#1A2E44` · azul acento `#2D7DD2` · verde `#3BB273`

## Despliegue (VM local)

```bash
# Copiar archivos al servidor
scp archivo.php administrador@192.168.56.103:~/
ssh -t administrador@192.168.56.103 "sudo mv ~/archivo.php /var/www/efind/efind-frontend/api/"

# Raíz web
/var/www/efind/efind-frontend/

# Includes (fuera del webroot público)
/var/www/efind/efind-frontend/includes/
```

## Equipo

**Arandú** · Tecnicatura en Informática · 2026
