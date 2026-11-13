/**
 * E-Find — Estimador de tiempo de carga
 *
 * Fórmula física (sección 9 de la documentación):
 *   E_necesaria = C × (1 − SOC)          [kWh]
 *   t = E_necesaria / (P × η)            [horas]
 *
 * Donde:
 *   C   = capacidad de la batería en kWh
 *   SOC = estado de carga actual (0–1)
 *   P   = potencia del cargador en kW
 *   η   = eficiencia del proceso de carga (0.92 = 92%)
 */

const ETA = 0.92;  // eficiencia estándar de carga AC/DC

/**
 * Calcula el tiempo de carga.
 * @param {number} capacidad_kwh  Capacidad total de la batería
 * @param {number} soc_pct        Estado de carga actual (0-100)
 * @param {number} potencia_kw    Potencia del cargador
 * @returns {{ horas: number, texto: string, kwh: number }}
 */
function calcularCarga(capacidad_kwh, soc_pct, potencia_kw) {
  // Sin datos válidos no hay estimación: evita mostrar «Infinity h» o «NaN».
  if (!(capacidad_kwh > 0) || !(potencia_kw > 0) || !Number.isFinite(soc_pct))
    return { horas: 0, texto: '—', kwh: 0 };

  const soc = Math.max(0, Math.min(100, soc_pct)) / 100;
  const e_necesaria = capacidad_kwh * (1 - soc);          // kWh a cargar
  const t_horas     = e_necesaria / (potencia_kw * ETA);  // tiempo en horas

  // Se redondea el total en minutos y después se separa: redondear sólo la
  // fracción daba «5 h 60 min» cuando los minutos rozaban la hora siguiente.
  const total_min = Math.round(t_horas * 60);
  const h = Math.floor(total_min / 60);
  const m = total_min % 60;

  let texto;
  if (t_horas < 1/60) {
    texto = 'Batería completa';
  } else if (h === 0) {
    texto = `${m} min`;
  } else if (m === 0) {
    texto = `${h} h`;
  } else {
    texto = `${h} h ${m} min`;
  }

  return { horas: t_horas, texto, kwh: +e_necesaria.toFixed(1) };
}

/**
 * Calcula el costo estimado de la carga.
 * @param {number} kwh       Energía a cargar
 * @param {number} costo_kwh Precio por kWh en pesos uruguayos
 * @returns {string}         Monto formateado
 */
function calcularCosto(kwh, costo_kwh) {
  if (!costo_kwh || costo_kwh === 0) return 'Gratuito';
  const total = kwh * costo_kwh;
  return `$${total.toFixed(0)} UYU`;
}

/**
 * Monta un widget estimador completo en el elemento `contenedor`.
 * @param {HTMLElement} contenedor
 * @param {object}      cargador    Objeto del mock con .conectores y .costo_kwh
 * @param {Array}       vehiculos   Lista de vehículos del usuario (puede ser [])
 */
function montarEstimador(contenedor, cargador, vehiculos = []) {
  const conectoresOpts = cargador.conectores
    .map((c, i) => `<option value="${i}">${escapeHtml(c.tipo)} — ${+c.potencia} kW</option>`)
    .join('');

  const vehiculosOpts = vehiculos.length
    ? vehiculos.map(v => `<option value="${+v.capacidad_kwh}">${escapeHtml(v.marca)} ${escapeHtml(v.modelo)} (${+v.capacidad_kwh} kWh)</option>`).join('')
    : '<option value="60">Capacidad manual</option>';

  contenedor.innerHTML = `
    <div class="form-group">
      <label class="form-label" for="est-conector">Conector / Potencia</label>
      <select id="est-conector" class="form-select">${conectoresOpts}</select>
    </div>
    ${vehiculos.length ? `
    <div class="form-group" style="margin-top:.65rem">
      <label class="form-label" for="est-vehiculo">Mi vehículo</label>
      <select id="est-vehiculo" class="form-select">${vehiculosOpts}</select>
    </div>` : `
    <div class="form-group" style="margin-top:.65rem">
      <label class="form-label" for="est-vehiculo">Capacidad batería (kWh)</label>
      <input id="est-vehiculo" type="number" class="form-input" value="60" min="10" max="200" step="1">
    </div>`}
    <div class="form-group" style="margin-top:.65rem">
      <label class="form-label" for="est-soc">
        Estado de carga actual: <strong id="est-soc-val">20%</strong>
      </label>
      <input id="est-soc" type="range" min="0" max="99" value="20" aria-label="Estado de carga actual" style="width:100%;accent-color:var(--accent)">
    </div>
    <div class="estimador-result" id="est-result">
      <div class="estimador-time" id="est-time">—</div>
      <div class="estimador-label" id="est-kwh"></div>
      <div class="estimador-label" id="est-costo"></div>
    </div>
  `;

  const recalc = () => {
    const potencia = cargador.conectores[+document.getElementById('est-conector').value]?.potencia;
    const cap      = parseFloat(document.getElementById('est-vehiculo').value) || 60;
    const soc      = +document.getElementById('est-soc').value;

    document.getElementById('est-soc-val').textContent = soc + '%';

    const r = calcularCarga(cap, soc, potencia);
    /* Los textos fijos pasan por autoT: se calculan en JS y el recorrido de
       I18N.apply() no los vuelve a traducir al cambiar de idioma. */
    const t = s => window.I18N ? I18N.autoT(s) : s;
    document.getElementById('est-time').textContent  = t(r.texto);
    document.getElementById('est-kwh').textContent   = `${r.kwh} ${t('kWh a cargar')}`;
    document.getElementById('est-costo').textContent = t(calcularCosto(r.kwh, cargador.costo_kwh));
  };
  /* Un solo oyente aunque el widget se vuelva a montar. */
  window.removeEventListener('i18n:change', window.__estRecalc);
  window.addEventListener('i18n:change', window.__estRecalc = recalc);

  contenedor.querySelectorAll('select, input').forEach(el => {
    el.addEventListener('input', recalc);
    el.addEventListener('change', recalc);
  });

  recalc();
}
