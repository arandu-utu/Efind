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
 * Renderiza estrellas como HTML.
 * @param {number|null} promedio  — valor 0-5 o null
 * @param {number}      total     — cantidad de calificaciones
 */
function renderStars(promedio, total) {
  if (promedio === null || total === 0) return `<span class="user-rating user-rating--new">Nuevo</span>`;
  const filled = Math.round(promedio);
  const soles  = Array.from({length: 5}, (_, i) =>
    `<span style="color:${i < filled ? '#F5B800' : '#C8D3DF'}">${solSVG(15)}</span>`
  ).join('');
  return `<span class="user-soles" title="${promedio.toFixed(1)} / 5 (${total} calificaciones)"
               style="display:inline-flex;align-items:center;gap:1px;vertical-align:middle">${soles}</span
         ><span class="user-rating-count">${promedio.toFixed(1)}</span>`;
}
