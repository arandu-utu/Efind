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
| `/api/login.php` | POST | — | Inicio de sesión |
| `/api/registro.php` | POST | — | Registro de usuario |
| `/api/estaciones.php` | GET | — | Lista cargadores (público, con conectores) |
| `/api/estacion.php` | GET | — | Detalle de un cargador |
| `/api/stats.php` | GET | Admin | KPIs del dashboard |
| `/api/reportes.php` | GET / POST / PATCH | Login / Admin | Reportes de cargadores |
| `/api/resenas.php` | GET / POST / PATCH | Login / Admin | Reseñas de cargadores |
| `/api/usuarios.php` | GET / PATCH | Admin | Gestión de usuarios |

## Funcionalidades

| Módulo | Estado | Descripción |
|---|---|---|
| 🗺️ Mapa interactivo | ✅ Conectado a DB | Leaflet.js, cargadores reales, filtros por estado y tipo |
| 🔐 Autenticación | ✅ PHP + sesiones | Registro, login, roles (particular, propietario, admin) |
| 🛡️ Panel admin | ✅ API real | KPIs, gráfico mensual, donut de roles, moderación de reseñas y reportes |
| 👥 Gestión de usuarios | ✅ API real | Lista, cambio de rol, suspensión/activación |
| ⚡ Detalle de cargador | ✅ API real | Conectores, estado, reseñas |
| ⭐ Reseñas | ✅ API real | Creación y moderación (pendiente → aprobada/rechazada) |
| 📢 Reportes | ✅ API real | Creación y resolución por admin |
| 👤 Perfil de usuario | 🔧 En desarrollo | Historial, vehículos, cargadores propios |
| 📅 Reservas | 🔧 En desarrollo | Estimador físico-matemático de tiempo y costo |

## Estructura del proyecto

```
efind-frontend/
├── index.html          # Mapa principal
├── login.html          # Autenticación
├── registro.html       # Registro de usuarios
├── cargador.html       # Detalle de cargador (API real)
├── reservar.html       # Flujo de reserva
├── agregar.html        # Alta de nuevo cargador
├── reportar.html       # Reporte de problemas
├── perfil.html         # Perfil del usuario
├── admin.html          # Panel admin (API real)
├── usuarios.html       # Gestión de usuarios (API real)
├── api/
│   ├── login.php
│   ├── registro.php
│   ├── estaciones.php
│   ├── estacion.php
│   ├── stats.php
│   ├── reportes.php
│   ├── resenas.php
│   └── usuarios.php
├── includes/           # En el servidor (fuera del repo)
│   ├── db.php          # db_connect() + PDO
│   └── auth.php        # requiere_login(), requiere_rol(), usuario_actual()
├── css/
│   └── style.css       # Sistema de diseño completo
├── js/
│   ├── auth.js         # Navbar/footer + Auth object (localStorage)
│   ├── icons.js        # Set de íconos SVG custom
│   ├── mock-data.js    # Datos mock legacy (en desuso progresivo)
│   └── estimador.js    # Modelo físico de estimación de carga
└── img/                # Logos E-Find y Arandú
```

## Esquema de base de datos (tablas principales)

| Tabla | Descripción |
|---|---|
| `usuarios` | id, nombre, email, password_hash, rol_id, activo, creado_en |
| `puntos_carga` | id, nombre, estado, acceso, lat, lng |
| `conectores` | id, punto_carga_id, tipo_conector_id, potencia_kw, estado |
| `tipos_conector` | id, nombre (CCS2, CHAdeMO, Type 2, Schuko…), carga_rapida |
| `resenas` | id, punto_carga_id, usuario_id, estrellas, texto, estado, fecha_creacion |
| `reportes` | id, punto_carga_id, usuario_id, tipo, descripcion, resuelto, creado_en |
| `roles` | id, nombre |

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
