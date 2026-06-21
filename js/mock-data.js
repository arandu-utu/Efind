/**
 * E-Find — Datos de prueba (mock)
 * Simula la base de datos para el prototipo front-end.
 */

const MOCK = {

  cargadores: [
    {
      id: 1, nombre: 'ANCAP — Estación Aguada',
      lat: -34.9058, lng: -56.1881,
      direccion: 'Av. 18 de Julio 1150, Montevideo',
      estado: 'disponible', horario: '24 horas', costo_kwh: 12.00,
      conectores: [
        { tipo: 'Tipo 2', potencia: 22 },
        { tipo: 'CCS2',   potencia: 50 },
      ],
      resenas: [
        { usuario: 'Carlos M.', estrellas: 5, texto: 'Excelente, muy rápido y limpio.', fecha: '2026-06-10' },
        { usuario: 'Laura P.',  estrellas: 4, texto: 'Funciona bien, un poco lento a veces.', fecha: '2026-06-01' },
      ],
      propietario_id: 1, cola: 0, publica: true,
    },
    {
      id: 2, nombre: 'UTE — Parque Rodó',
      lat: -34.9162, lng: -56.1573,
      direccion: 'Rambla Wilson 1400, Montevideo',
      estado: 'disponible', horario: '06:00–22:00', costo_kwh: 10.50,
      conectores: [
        { tipo: 'Tipo 2', potencia: 7.4 },
        { tipo: 'CCS2',   potencia: 50 },
        { tipo: 'CHAdeMO',potencia: 50 },
      ],
      resenas: [
        { usuario: 'Rodrigo T.', estrellas: 5, texto: 'Vista al río y carga rápida. Perfecto.', fecha: '2026-06-15' },
      ],
      propietario_id: 1, cola: 1, publica: true,
    },
    {
      id: 3, nombre: 'Shopping Tres Cruces',
      lat: -34.9003, lng: -56.1742,
      direccion: 'Bv. Artigas 1825, Montevideo',
      estado: 'ocupado', horario: '09:00–22:00', costo_kwh: 15.00,
      conectores: [
        { tipo: 'Tipo 2', potencia: 22 },
        { tipo: 'CHAdeMO',potencia: 50 },
      ],
      resenas: [],
      propietario_id: 2, cola: 2, publica: false,
    },
    {
      id: 4, nombre: 'Intendencia de Montevideo',
      lat: -34.9055, lng: -56.1900,
      direccion: 'Av. 18 de Julio 1360, Montevideo',
      estado: 'disponible', horario: 'Lun–Vie 08:00–18:00', costo_kwh: 0,
      conectores: [
        { tipo: 'Tipo 2', potencia: 7.4 },
      ],
      resenas: [
        { usuario: 'Sofía R.', estrellas: 3, texto: 'Solo días hábiles, horario limitado.', fecha: '2026-05-28' },
      ],
      propietario_id: 1, cola: 0, publica: true,
    },
    {
      id: 5, nombre: 'WTC Buceo',
      lat: -34.8928, lng: -56.1436,
      direccion: 'Av. Luis A. de Herrera 1248, Montevideo',
      estado: 'disponible', horario: '24 horas', costo_kwh: 13.00,
      conectores: [
        { tipo: 'CCS2',   potencia: 50 },
        { tipo: 'Tipo 2', potencia: 22 },
      ],
      resenas: [
        { usuario: 'Martín A.', estrellas: 5, texto: 'Excelente servicio, siempre libre.', fecha: '2026-06-18' },
        { usuario: 'Ana V.',    estrellas: 4, texto: 'Buen precio, bien ubicado.', fecha: '2026-06-05' },
      ],
      propietario_id: 3, cola: 0, publica: false,
    },
    {
      id: 6, nombre: 'Cargador particular — Pocitos',
      lat: -34.9008, lng: -56.1562,
      direccion: 'Av. Brasil 2770, Pocitos, Montevideo',
      estado: 'disponible', horario: 'Acuerdo previo', costo_kwh: 8.00,
      conectores: [
        { tipo: 'Schuko', potencia: 3.7 },
        { tipo: 'Tipo 2', potencia: 7.4 },
      ],
      resenas: [],
      propietario_id: 4, cola: 0, publica: false,
    },
  ],

  usuarios: [
    { id: 1, nombre: 'Administrador E-Find', email: 'admin@efind.com.uy',   rol: 'admin',      ci_rut: '49993901',     avatar: 'A', activo: true, calificacion_promedio: 4.8, num_calificaciones: 5 },
    { id: 2, nombre: 'Sofía Rodríguez',      email: 'sofia@empresa.com.uy', rol: 'empresa',    ci_rut: '210572690001', avatar: 'S', activo: true, empresa: 'Shopping Tres Cruces', calificacion_promedio: 4.5, num_calificaciones: 2 },
    { id: 3, nombre: 'Carlos Martínez',      email: 'carlos@gmail.com',     rol: 'particular', ci_rut: '43521089',     avatar: 'C', activo: true, calificacion_promedio: 3.7, num_calificaciones: 3 },
    { id: 4, nombre: 'Laura Pérez',          email: 'laura@gmail.com',       rol: 'particular', ci_rut: '52184763',     avatar: 'L', activo: true, calificacion_promedio: 5.0, num_calificaciones: 1 },
    { id: 5, nombre: 'Moderador UTU',        email: 'mod@efind.com.uy',     rol: 'moderador',  ci_rut: '31045872',     avatar: 'M', activo: true, calificacion_promedio: null, num_calificaciones: 0 },
  ],

  /* Calificaciones entre usuarios (post-transacción) */
  calificaciones: [
    { id: 1, de_usuario_id: 3, para_usuario_id: 1, transaccion_id: 1, puntos: 5, comentario: 'Cargador impecable, propietario muy atento.', fecha: '2026-06-15', tipo: 'cliente_a_propietario' },
    { id: 2, de_usuario_id: 1, para_usuario_id: 3, transaccion_id: 1, puntos: 4, comentario: 'Cliente puntual y respetuoso del lugar.', fecha: '2026-06-15', tipo: 'propietario_a_cliente' },
    { id: 3, de_usuario_id: 4, para_usuario_id: 1, transaccion_id: 2, puntos: 5, comentario: 'Todo perfecto, muy recomendable.', fecha: '2026-06-10', tipo: 'cliente_a_propietario' },
    { id: 4, de_usuario_id: 1, para_usuario_id: 4, transaccion_id: 2, puntos: 5, comentario: 'Excelente clienta, muy educada.', fecha: '2026-06-10', tipo: 'propietario_a_cliente' },
    { id: 5, de_usuario_id: 3, para_usuario_id: 2, transaccion_id: null, puntos: 3, comentario: 'El cargador no estaba como se describía.', fecha: '2026-06-01', tipo: 'cliente_a_propietario' },
  ],

  vehiculos: [
    { id: 1, usuario_id: 3, marca: 'Tesla',      modelo: 'Model 3 SR+', capacidad_kwh: 57.5, conector: 'CCS2' },
    { id: 2, usuario_id: 3, marca: 'Renault',    modelo: 'Zoe ZE50',   capacidad_kwh: 52.0, conector: 'Tipo 2' },
    { id: 3, usuario_id: 4, marca: 'BYD',        modelo: 'Atto 3',     capacidad_kwh: 60.5, conector: 'CCS2' },
    { id: 4, usuario_id: 2, marca: 'Volkswagen', modelo: 'ID.4',       capacidad_kwh: 77.0, conector: 'CCS2' },
  ],

  transacciones: [
    { id: 1, usuario_id: 3, cargador_id: 1, duracion_h: 1.5, kwh: 33,   monto_total: 396,   comision: 39.6,  fecha: '2026-06-15', recibo: 'EF-20260615-001', calificado: true  },
    { id: 2, usuario_id: 4, cargador_id: 2, duracion_h: 2,   kwh: 14.8, monto_total: 155.4, comision: 15.54, fecha: '2026-06-10', recibo: 'EF-20260610-002', calificado: true  },
  ],

  resenas_pendientes: [
    { id: 10, usuario: 'Diego F.',     cargador_id: 3, cargador_nombre: 'Shopping Tres Cruces', estrellas: 2, texto: 'Estaba roto y nadie lo reportó.', fecha: '2026-06-20', estado: 'pendiente' },
    { id: 11, usuario: 'Valentina S.', cargador_id: 5, cargador_nombre: 'WTC Buceo',            estrellas: 5, texto: 'Increíble, el mejor de Mvd.',      fecha: '2026-06-21', estado: 'pendiente' },
  ],

  reportes: [
    { id: 1, cargador_id: 3, cargador_nombre: 'Shopping Tres Cruces', tipo: 'Fuera de servicio', usuario: 'Laura Pérez',    usuario_id: 4, fecha: '2026-06-19', estado: 'pendiente' },
    { id: 2, cargador_id: 4, cargador_nombre: 'Intendencia de Mvd.',  tipo: 'Precio incorrecto',  usuario: 'Carlos Martínez', usuario_id: 3, fecha: '2026-06-18', estado: 'resuelto' },
  ],

  stats: {
    cargadores_total: 6,
    usuarios_total: 5,
    resenas_pendientes: 2,
    reportes_abiertos: 1,
    cargadores_por_mes: [1, 0, 2, 1, 0, 1, 1, 0, 0, 0, 0, 0],
    meses: ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'],
  },
};

/* ── Persistencia en localStorage ─────────────────────────────── */
function getMock(key) {
  const raw = localStorage.getItem('efind_' + key);
  return raw ? JSON.parse(raw) : MOCK[key];
}
function setMock(key, val) {
  localStorage.setItem('efind_' + key, JSON.stringify(val));
}

/* ── Helpers de búsqueda ───────────────────────────────────────── */
function getCargador(id) {
  return getMock('cargadores').find(c => c.id === +id) || null;
}
function getUsuario(id) {
  return getMock('usuarios').find(u => u.id === +id) || null;
}
function cargadorIdFromURL() {
  return new URLSearchParams(location.search).get('id') || 1;
}

/* ── Helpers de calificación ───────────────────────────────────── */

/**
 * Sol de Mayo — ícono identitario uruguayo para el sistema de calificaciones.
 * Usa currentColor para que CSS controle el color de filled/empty.
 * 16 rayos: 8 rectos (lines) + 8 ondulados (S-curves), alternados a 22.5°.
 * @param {number} px  — tamaño en px (width/height del SVG)
 */
function solSVG(px = 16) {
  return `<svg viewBox="0 0 32 32" width="${px}" height="${px}" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;vertical-align:middle;flex-shrink:0;transition:transform .15s ease">
    <!-- Rayos rectos: 0°, 45°, 90°, 135°, 180°, 225°, 270°, 315° -->
    <line x1="16" y1="8" x2="16" y2="1.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" transform="rotate(0 16 16)"/>
    <line x1="16" y1="8" x2="16" y2="1.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" transform="rotate(45 16 16)"/>
    <line x1="16" y1="8" x2="16" y2="1.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" transform="rotate(90 16 16)"/>
    <line x1="16" y1="8" x2="16" y2="1.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" transform="rotate(135 16 16)"/>
    <line x1="16" y1="8" x2="16" y2="1.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" transform="rotate(180 16 16)"/>
    <line x1="16" y1="8" x2="16" y2="1.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" transform="rotate(225 16 16)"/>
    <line x1="16" y1="8" x2="16" y2="1.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" transform="rotate(270 16 16)"/>
    <line x1="16" y1="8" x2="16" y2="1.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" transform="rotate(315 16 16)"/>
    <!-- Rayos ondulados: 22.5°, 67.5°, 112.5°, 157.5°, 202.5°, 247.5°, 292.5°, 337.5° -->
    <path d="M16 8 C18.8 6.5 13.2 4.5 16 1.5" stroke="currentColor" stroke-width="1.9" fill="none" stroke-linecap="round" transform="rotate(22.5 16 16)"/>
    <path d="M16 8 C18.8 6.5 13.2 4.5 16 1.5" stroke="currentColor" stroke-width="1.9" fill="none" stroke-linecap="round" transform="rotate(67.5 16 16)"/>
    <path d="M16 8 C18.8 6.5 13.2 4.5 16 1.5" stroke="currentColor" stroke-width="1.9" fill="none" stroke-linecap="round" transform="rotate(112.5 16 16)"/>
    <path d="M16 8 C18.8 6.5 13.2 4.5 16 1.5" stroke="currentColor" stroke-width="1.9" fill="none" stroke-linecap="round" transform="rotate(157.5 16 16)"/>
    <path d="M16 8 C18.8 6.5 13.2 4.5 16 1.5" stroke="currentColor" stroke-width="1.9" fill="none" stroke-linecap="round" transform="rotate(202.5 16 16)"/>
    <path d="M16 8 C18.8 6.5 13.2 4.5 16 1.5" stroke="currentColor" stroke-width="1.9" fill="none" stroke-linecap="round" transform="rotate(247.5 16 16)"/>
    <path d="M16 8 C18.8 6.5 13.2 4.5 16 1.5" stroke="currentColor" stroke-width="1.9" fill="none" stroke-linecap="round" transform="rotate(292.5 16 16)"/>
    <path d="M16 8 C18.8 6.5 13.2 4.5 16 1.5" stroke="currentColor" stroke-width="1.9" fill="none" stroke-linecap="round" transform="rotate(337.5 16 16)"/>
    <!-- Círculo central -->
    <circle cx="16" cy="16" r="5.8" fill="currentColor"/>
    <!-- Cara expresiva (rgba permite superposición sobre amarillo y gris) -->
    <circle cx="14.2" cy="15.4" r="0.9" fill="rgba(0,0,0,0.38)"/>
    <circle cx="17.8" cy="15.4" r="0.9" fill="rgba(0,0,0,0.38)"/>
    <path d="M13.6 17.8 Q16 20.2 18.4 17.8" stroke="rgba(0,0,0,0.38)" stroke-width="1.05" fill="none" stroke-linecap="round"/>
    <path d="M12.9 13.9 Q14.2 12.5 15.5 13.7" stroke="rgba(0,0,0,0.35)" stroke-width="0.85" fill="none" stroke-linecap="round"/>
    <path d="M16.5 13.7 Q17.8 12.5 19.1 13.9" stroke="rgba(0,0,0,0.35)" stroke-width="0.85" fill="none" stroke-linecap="round"/>
  </svg>`;
}

/**
 * Renderiza estrellas como HTML.
 * @param {number|null} promedio  — valor 0-5 o null
 * @param {number}      total     — cantidad de calificaciones
 * @param {string}      size      — 'sm' | 'md'
 */
function renderStars(promedio, total, size = 'sm') {
  if (promedio === null || total === 0) {
    return `<span class="user-rating user-rating--new">Nuevo</span>`;
  }
  const filled = Math.round(promedio);
  const px     = size === 'md' ? 22 : 15;
  const soles  = Array.from({length: 5}, (_, i) =>
    `<span style="color:${i < filled ? '#F5B800' : '#C8D3DF'}">${solSVG(px)}</span>`
  ).join('');
  return `<span class="user-soles" title="${promedio.toFixed(1)} / 5 (${total} calificaciones)"
               style="display:inline-flex;align-items:center;gap:1px;vertical-align:middle">${soles}</span
         ><span class="user-rating-count">${promedio.toFixed(1)}</span>`;
}

/**
 * Badge de credibilidad para reportes.
 * Usado en admin.html al listar reportes pendientes.
 */
function credibilidadBadge(usuario_id) {
  const u = getUsuario(usuario_id);
  if (!u) return '';
  const p = u.calificacion_promedio;
  const n = u.num_calificaciones;
  if (n === 0 || p === null)
    return `<span class="badge badge--gray" title="Sin calificaciones aún">Nuevo usuario</span>`;
  if (p >= 4.5)
    return `<span class="badge badge--green" title="${p.toFixed(1)} — ${n} cal." style="display:inline-flex;align-items:center;gap:3px"><span style="color:#F5B800">${solSVG(13)}</span> Confiable</span>`;
  if (p >= 3.0)
    return `<span class="badge badge--blue"  title="${p.toFixed(1)} — ${n} cal." style="display:inline-flex;align-items:center;gap:3px"><span style="color:#F5B800">${solSVG(13)}</span>${p.toFixed(1)}</span>`;
  return `<span class="badge badge--orange" title="${p.toFixed(1)} — ${n} cal.">Baja credibilidad</span>`;
}

/**
 * Recalcula y guarda el promedio de calificaciones de un usuario.
 */
function recalcularCalificacion(usuario_id) {
  const cals = getMock('calificaciones').filter(c => c.para_usuario_id === +usuario_id);
  const usuarios = getMock('usuarios');
  const idx = usuarios.findIndex(u => u.id === +usuario_id);
  if (idx === -1) return;
  usuarios[idx].calificacion_promedio = cals.length
    ? parseFloat((cals.reduce((s, c) => s + c.puntos, 0) / cals.length).toFixed(1))
    : null;
  usuarios[idx].num_calificaciones = cals.length;
  setMock('usuarios', usuarios);
}
