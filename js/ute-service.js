/**
 * @file       js/ute-service.js
 * @module     UTEService
 * @description
 *   Integración con la API pública de cargadores de la red UTE (Uruguay).
 *
 *   La API de UTE no incluye cabeceras CORS, por lo que las peticiones
 *   cross-origin son bloqueadas por el browser. Este módulo intenta primero
 *   el proxy local (api/ute-proxy.php, mismo origen) y solo en desarrollo
 *   cae en el endpoint directo de UTE si el proxy no existe.
 *
 * @endpoint   GET /api/ute-proxy.php            (producción / LAMP)
 * @endpoint   GET https://movilidad.ute.com.uy/api/v1/station/status/map  (fallback)
 */

'use strict';

/* ══════════════════════════════════════════════════════════════════
   UTEService — módulo IIFE (singleton)
══════════════════════════════════════════════════════════════════ */

const UTEService = (() => {

  // ── Endpoints ────────────────────────────────────────────────

  /**
   * Proxy PHP en el mismo servidor de E-Find.
   * Evita el bloqueo CORS del browser al hacer el fetch server-side.
   * @const {string}
   */
  const PROXY_URL  = 'api/ute-proxy.php';

  /**
   * URL directa de la API de UTE.
   * Solo funciona si el browser no aplica restricciones CORS
   * (ej. extensiones, herramientas de desarrollo, Cordova).
   * @const {string}
   */
  const DIRECT_URL = 'https://movilidad.ute.com.uy/api/v1/station/status/map';

  // ── Mapeo de estados ─────────────────────────────────────────

  /**
   * Normaliza los estados de la API UTE al vocabulario E-Find.
   * La API mezcla español e inglés según el proveedor CargaME.
   * @const {Object.<string, 'disponible'|'ocupado'|'sin_servicio'>}
   */
  const STATUS_MAP = {
    Disponible:     'disponible',
    Available:      'disponible',
    Cargando:       'ocupado',
    Busy:           'ocupado',
    Occupied:       'ocupado',
    FueraServicio:  'sin_servicio',
    OutOfService:   'sin_servicio',
    'Sin servicio': 'sin_servicio',
    Offline:        'sin_servicio',
  };

  // ── Helpers privados ──────────────────────────────────────────

  /** @private */
  const _normalizeStatus = s => STATUS_MAP[s] ?? 'sin_servicio';

  /**
   * Calcula el estado global de la estación desde sus conectores.
   * Lógica: si al menos uno disponible → disponible.
   *         si todos offline → sin_servicio.
   *         en otro caso → ocupado.
   * @private
   */
  function _calcStationStatus(connectors) {
    if (!connectors.length) return 'sin_servicio';
    const states = connectors.map(c => _normalizeStatus(c.statusDetail));
    if (states.some(s => s === 'disponible'))    return 'disponible';
    if (states.every(s => s === 'sin_servicio')) return 'sin_servicio';
    return 'ocupado';
  }

  /**
   * Transforma una estación cruda de la API UTE al formato E-Find.
   * @private
   * @param   {Object} raw
   * @returns {UTEStation}
   */
  function _transform(raw) {
    const conectores = (raw.connectorStatusAcc ?? []).map(c => ({
      tipo:     c.type,
      potencia: c.power,
      cantidad: c.count,
      estado:   _normalizeStatus(c.statusDetail),
    }));
    return {
      fuente:       'UTE',
      nombre:       raw.name,
      direccion:    raw.address,
      ciudad:       raw.city,
      departamento: raw.department,
      lat:          raw.lat,
      lng:          raw.lng,
      estado:       _calcStationStatus(raw.connectorStatusAcc ?? []),
      conectores,
      potenciaMax:  conectores.reduce((max, c) => Math.max(max, c.potencia), 0),
    };
  }

  /**
   * Intenta hacer fetch a una URL y devuelve el JSON parseado.
   * Lanza Error si la respuesta no es OK o no es JSON válido.
   * @private
   */
  async function _fetchJSON(url) {
    const res = await fetch(url, {
      method:  'GET',
      headers: { Accept: 'application/json' },
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const json = await res.json();
    if (!Array.isArray(json?.data)) throw new Error('Formato inesperado');
    return json;
  }

  // ── Tipos JSDoc ───────────────────────────────────────────────

  /**
   * @typedef {Object} UTEConnector
   * @property {string} tipo
   * @property {number} potencia  - kW
   * @property {number} cantidad
   * @property {'disponible'|'ocupado'|'sin_servicio'} estado
   */

  /**
   * @typedef {Object} UTEStation
   * @property {'UTE'}   fuente
   * @property {string}  nombre
   * @property {string}  direccion
   * @property {string}  ciudad
   * @property {string}  departamento
   * @property {number}  lat
   * @property {number}  lng
   * @property {'disponible'|'ocupado'|'sin_servicio'} estado
   * @property {UTEConnector[]} conectores
   * @property {number}  potenciaMax  - kW
   */

  // ── API pública ───────────────────────────────────────────────

  /**
   * Obtiene las estaciones de carga UTE en formato normalizado E-Find.
   *
   * Intenta primero el proxy PHP (mismo origen, sin problemas de CORS).
   * Si falla (ej. en desarrollo sin servidor PHP), intenta la URL directa.
   *
   * @async
   * @returns {Promise<UTEStation[]>}
   * @throws  {Error} Si ningún endpoint responde correctamente.
   *
   * @example
   * const estaciones = await UTEService.fetchEstaciones();
   * console.log(`Red UTE: ${estaciones.length} estaciones`);
   */
  async function fetchEstaciones() {
    let json;

    // 1. Intentar proxy PHP (producción / LAMP)
    try {
      json = await _fetchJSON(PROXY_URL);
      return json.data.map(_transform);
    } catch (proxyErr) {
      console.debug('UTEService: proxy no disponible, intentando URL directa.', proxyErr.message);
    }

    // 2. Fallback: URL directa (puede fallar por CORS en browser)
    try {
      json = await _fetchJSON(DIRECT_URL);
      return json.data.map(_transform);
    } catch (directErr) {
      throw new Error(
        `UTEService: no se pudo obtener datos UTE. ` +
        `Proxy: (ver consola). Directo: ${directErr.message}. ` +
        `Asegurate de servir E-Find desde un servidor PHP (LAMP).`
      );
    }
  }

  return { fetchEstaciones };

})();
