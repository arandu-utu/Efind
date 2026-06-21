# E-Find ⚡

> Plataforma de crowdsourcing para cargadores de vehículos eléctricos en Uruguay.  
> Proyecto de egreso 2026 — Equipo Arandú · Tecnicatura en Informática

🌐 **[Ver demo en vivo](https://lrtduy-creator.github.io/Efind)**

---

## ¿Qué es E-Find?

E-Find permite a los usuarios de vehículos eléctricos en Uruguay **encontrar, reportar y reservar** puntos de carga cercanos, con un sistema de reputación que da credibilidad a los reportes de la comunidad.

## Funcionalidades del prototipo

| Módulo | Descripción |
|---|---|
| 🗺️ Mapa interactivo | Visualización de cargadores con Leaflet.js, filtros por estado y tipo |
| 🔐 Autenticación | Registro e inicio de sesión con roles (particular, empresa, moderador, admin) |
| ⚡ Reserva de carga | Estimador físico-matemático de tiempo y costo según vehículo y potencia |
| ⭐ Calificaciones | Sistema bilateral cliente ↔ propietario con Sol de Mayo uruguayo |
| 📢 Reportes | Crowdsourcing de problemas con credibilidad del reportero visible al moderador |
| 🛡️ Panel admin | Dashboard con KPIs, moderación de reseñas y gestión de reportes |
| 👤 Perfil de usuario | Historial, vehículos, cargadores propios y calificaciones recibidas |

## Identidad visual

- **Sol de Mayo** uruguayo como ícono del sistema de calificaciones
- Set de íconos SVG sólidos custom (`js/icons.js`)
- Marcador de mapa E-Find con rayo SVG embebido
- Paleta: azul profundo `#1A2E44` · azul acento `#2D7DD2` · verde `#3BB273`

## Tecnologías

- HTML5 / CSS3 / JavaScript puro (sin frameworks)
- [Leaflet.js](https://leafletjs.com/) para el mapa interactivo
- `localStorage` como capa de persistencia (prototipo mock)
- CI/RUT uruguayo con validación por dígito verificador

## Estructura del proyecto

```
efind-frontend/
├── index.html          # Mapa principal
├── login.html          # Autenticación
├── registro.html       # Registro de usuarios
├── cargador.html       # Detalle de cargador
├── reservar.html       # Flujo de reserva y pago
├── agregar.html        # Alta de nuevo cargador
├── reportar.html       # Reporte de problemas
├── perfil.html         # Perfil del usuario
├── admin.html          # Panel de administración
├── usuarios.html       # Gestión de usuarios (admin)
├── css/
│   └── style.css       # Sistema de diseño completo
├── js/
│   ├── mock-data.js    # Datos simulados + helpers
│   ├── auth.js         # Autenticación + navbar/footer
│   ├── icons.js        # Set de íconos SVG custom
│   └── estimador.js    # Modelo físico de estimación de carga
└── img/                # Logos E-Find y Arandú
```

## Equipo

**Arandú** · Tecnicatura en Informática · 2026

---

*Este prototipo utiliza datos simulados (mock) para demostración. No se realizan cobros reales ni se almacena información en servidores.*
