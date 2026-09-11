/**
 * E-Find — Utilidades de UI compartidas (ícono Sol de Mayo, estrellas, URL)
 * El resto de este archivo (datos mock, getMock/setMock) se eliminó al
 * terminar de migrar todas las páginas a las APIs reales.
 */
function cargadorIdFromURL() {
  return new URLSearchParams(location.search).get('id') || 1;
}

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
 * Fila de 5 soles de sólo lectura, coloreados con estilo inline.
 * @param {number} n   — cuántos van encendidos (0-5)
 * @param {number} px  — tamaño de cada sol
 */
function solesHTML(n, px = 15) {
  return Array.from({length: 5}, (_, i) =>
    `<span style="color:${i < n ? '#F5B800' : '#C8D3DF'}">${solSVG(px)}</span>`
  ).join('');
}

/**
 * Renderiza estrellas como HTML.
 * @param {number|null} promedio  — valor 0-5 o null
 * @param {number}      total     — cantidad de calificaciones
 */
function renderStars(promedio, total) {
  if (promedio === null || total === 0) return `<span class="user-rating user-rating--new">Nuevo</span>`;
  return `<span class="user-soles" title="${promedio.toFixed(1)} / 5 (${total} calificaciones)"
               style="display:inline-flex;align-items:center;gap:1px;vertical-align:middle">${solesHTML(Math.round(promedio))}</span
         ><span class="user-rating-count">${promedio.toFixed(1)}</span>`;
}

/**
 * Picker de soles interactivo (reseñas en cargador.html, calificación en reservar.html).
 * Rellena el contenedor y devuelve un objeto cuyo `.valor` refleja la selección actual.
 * @param {string} contenedorId  — id del div que aloja los soles
 * @param {string} clase         — clase de cada sol (la define el CSS de cada página)
 * @param {number} px            — tamaño de cada sol
 */
function crearSolPicker(contenedorId, clase, px) {
  const cont = document.getElementById(contenedorId);
  if (!cont) return null;
  const api = { valor: 0 };

  const pintar = (hasta, color, conEscala) => cont.querySelectorAll('.' + clase).forEach(s => {
    const activo = +s.dataset.v <= hasta;
    s.style.color = activo ? color : '#C8D3DF';
    if (conEscala) s.style.transform = activo ? 'scale(1.08)' : 'scale(1)';
  });

  cont.innerHTML = [1, 2, 3, 4, 5].map(v =>
    `<span class="${clase}" data-v="${v}" style="color:#C8D3DF">${solSVG(px)}</span>`
  ).join('');

  cont.querySelectorAll('.' + clase).forEach(s => {
    s.onclick      = () => { api.valor = +s.dataset.v; pintar(api.valor, '#F5B800', true); };
    s.onmouseenter = () => pintar(+s.dataset.v, '#FFCC30', false);
    s.onmouseleave = () => pintar(api.valor, '#F5B800', false);
  });
  return api;
}
