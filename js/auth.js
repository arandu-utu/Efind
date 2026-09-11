/**
 * E-Find — Sesión de usuario (login/registro reales vía API; esto solo
 * lee/escribe la copia en localStorage que usa la navbar).
 */

const Auth = {
  KEY: 'efind_session',

  /* Usuario actual (objeto) o null */
  get() {
    const raw = localStorage.getItem(this.KEY);
    return raw ? JSON.parse(raw) : null;
  },

  logout() {
    localStorage.removeItem(this.KEY);
    // Destruir sesión PHP en el servidor (fire & forget)
    fetch('/api/logout.php').catch(() => {});
  },

  is(role) {
    const u = this.get();
    if (!u) return false;
    if (role === 'auth') return true;
    // Soporta tanto rol_id numérico (API real) como string (mock legacy)
    if (role === 'admin')       return u.rol_id === 1 || u.rol === 'admin' || u.rol === 'moderador';
    if (role === 'propietario') return u.rol_id === 3 || u.rol === 'propietario';
    return u.rol === role;
  },

  isAny(...roles) {
    const u = this.get();
    return u ? roles.some(r => this.is(r)) : false;
  },
};

/* ── Fetch a /api/*.php con body JSON, devuelve la respuesta ya parseada ── */
function apiFetch(url, method = 'GET', body) {
  const opts = { method };
  if (body !== undefined) {
    opts.headers = { 'Content-Type': 'application/json' };
    opts.body = JSON.stringify(body);
  }
  return fetch(url, opts).then(r => r.json());
}

/* ── Muestra un <div class="alert"> ya existente en la página ────────
   ocultarMs > 0 lo vuelve a esconder solo pasado ese tiempo. */
function showAlert(id, msg, tipo = 'error', ocultarMs = 0) {
  const el = document.getElementById(id);
  el.className = `alert alert-${tipo}`;
  el.innerHTML = msg;
  el.style.display = 'flex';
  if (ocultarMs) setTimeout(() => el.style.display = 'none', ocultarMs);
}

/* ── Placeholder mientras se espera la respuesta de una API ──────────
   Traduce con autoT en vez de I18N.apply(): apply() emite 'i18n:change',
   y hay páginas suscritas que re-renderizan la lista y borrarían esto. */
function mostrarCargando(id, texto = 'Cargando…') {
  const el = document.getElementById(id);
  if (!el) return;
  el.innerHTML = `<div style="display:flex;align-items:center;justify-content:center;gap:.6rem;
       padding:2rem 1rem;color:var(--soft);font-size:.85rem">
       <span class="spinner"></span>${escapeHtml(I18N.autoT(texto))}</div>`;
}

/* ── Confirmación en un modal propio, en lugar del confirm() del navegador.
   Devuelve una promesa que resuelve a true si el usuario acepta. */
function confirmar(mensaje, { titulo = 'Confirmar', aceptar = 'Aceptar', peligro = false } = {}) {
  return new Promise(resolve => {
    const t = s => escapeHtml(I18N.autoT(s));
    const ov = document.createElement('div');
    ov.className = 'modal-overlay center';
    ov.innerHTML = `
      <div class="modal" role="dialog" aria-modal="true" style="max-width:400px">
        <div class="modal__title">${t(titulo)}</div>
        <p style="font-size:.9rem;color:var(--soft);line-height:1.5">${t(mensaje)}</p>
        <div style="display:flex;gap:.5rem;justify-content:flex-end;margin-top:1.25rem">
          <button class="btn btn--ghost btn--sm" data-r="0">${t('Cancelar')}</button>
          <button class="btn btn--${peligro ? 'warn' : 'primary'} btn--sm" data-r="1">${t(aceptar)}</button>
        </div>
      </div>`;

    const cerrar = valor => {
      document.removeEventListener('keydown', alTeclear);
      ov.remove();
      resolve(valor);
    };
    const alTeclear = e => { if (e.key === 'Escape') cerrar(false); };

    ov.addEventListener('click', e => {
      if (e.target === ov) return cerrar(false);
      const btn = e.target.closest('[data-r]');
      if (btn) cerrar(btn.dataset.r === '1');
    });
    document.addEventListener('keydown', alTeclear);
    document.body.appendChild(ov);
    ov.querySelector('[data-r="1"]').focus();
  });
}

/* ── Escapar HTML antes de insertar texto de usuario con innerHTML ──── */
function escapeHtml(str) {
  const d = document.createElement('div');
  d.textContent = str ?? '';
  return d.innerHTML;
}

/* ── Validaciones uruguayas ──────────────────────────────────── */
function validarCI(ci) {
  ci = ci.replace(/[.\-\s]/g, '').padStart(8, '0');
  if (!/^\d{8}$/.test(ci)) return false;
  const pesos = [2, 9, 8, 7, 6, 3, 4];
  let suma = 0;
  for (let i = 0; i < 7; i++) suma += +ci[i] * pesos[i];
  return (10 - suma % 10) % 10 === +ci[7];
}

function validarRUT(rut) {
  return /^\d{12}$/.test(rut.replace(/[.\-\s]/g, ''));
}

/* ── Inyección dinámica de navbar/footer ─────────────────────── */
function renderNavbar() {
  const u = Auth.get();
  const isAdmin = Auth.is('admin');

  const nav = document.getElementById('navbar');
  if (!nav) return;

  const t = (k) => I18N.t(k);
  const lang = I18N.get();
  const langToggle = `
    <div class="lang-toggle">
      <button class="lang-btn${lang === 'es' ? ' active' : ''}" data-lang="es" onclick="I18N.set('es')">ES</button>
      <button class="lang-btn${lang === 'en' ? ' active' : ''}" data-lang="en" onclick="I18N.set('en')">EN</button>
    </div>`;

  nav.innerHTML = `
    <div class="navbar__inner">
      <a href="index.html" class="nav-logo">
        <img src="img/efind-nav.png" alt="E-Find" height="38" style="display:block">
      </a>
      <div class="navbar__links">
        <a href="index.html" class="nav-link" id="nl-mapa">${t('nav_mapa')}</a>
        ${isAdmin ? `<a href="admin.html" class="nav-link" id="nl-admin">${t('nav_admin')}</a>` : ''}
        ${u ? `
          <a href="agregar.html" class="btn btn--green btn--sm">${t('nav_agregar')}</a>
          <div class="navbar__user" id="user-menu">
            <button type="button" class="user-chip" aria-haspopup="true" aria-expanded="false">
              <div class="avatar">${u.avatar || u.nombre[0].toUpperCase()}</div>
              ${u.nombre.split(' ')[0]}
            </button>
            <div class="dropdown">
              <a href="perfil.html">${ICONS.user(15)} ${t('nav_perfil')}</a>
              ${isAdmin ? `<a href="admin.html">${ICONS.settings(15)} ${t('nav_administracion')}</a>` : ''}
              <div class="sep"></div>
              <button onclick="Auth.logout(); location.href='login.html'">${ICONS.logout(15)} ${t('nav_logout')}</button>
            </div>
          </div>
        ` : `
          <a href="login.html"    class="btn btn--outline-nav btn--sm">${t('nav_login')}</a>
          <a href="registro.html" class="btn btn--primary btn--sm">${t('nav_registro')}</a>
        `}
        ${langToggle}
      </div>
    </div>
  `;

  /* Marcar link activo */
  const page = location.pathname.split('/').pop() || 'index.html';
  const linkMap = { 'index.html': 'nl-mapa', 'admin.html': 'nl-admin' };
  const active = document.getElementById(linkMap[page]);
  if (active) active.classList.add('active');

  wireUserMenu();
}

function renderFooter() {
  const f = document.getElementById('footer');
  if (!f) return;
  const t = (k) => I18N.t(k);
  f.innerHTML = `
    <div class="footer-inner">
      <div class="footer-brand">
        <img src="img/efind-nav.png" alt="E-Find" height="28">
        <span style="color:rgba(255,255,255,.55);font-size:.8rem">${t('footer_tagline')}</span>
      </div>
      <span style="color:rgba(255,255,255,.4);font-size:.8rem">${t('footer_proyecto')}</span>
      <div class="footer-brand">
        <span style="color:rgba(255,255,255,.4);font-size:.75rem">${t('footer_dev')}</span>
        <img src="img/arandu-dark.png" alt="Arandú" height="28">
      </div>
    </div>
  `;
}

/* ── Guard: redirige si no está autenticado ──────────────────── */
function requireAuth(redirectTo = 'login.html') {
  if (!Auth.get()) {
    sessionStorage.setItem('redirect_after_login', location.href);
    location.href = redirectTo;
    return false;
  }
  return true;
}

/* ── Guard: redirige si no es admin/moderador ────────────────── */
function requireAdmin(redirectTo = 'index.html') {
  if (!Auth.is('admin')) { location.href = redirectTo; return false; }
  return true;
}

/* ── Init automático ─────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  renderNavbar();
  renderFooter();
});

/* Se llama cada vez que renderNavbar() reconstruye el navbar (carga inicial
   y cambio de idioma), porque el chip/dropdown son elementos nuevos cada vez
   y pierden los listeners anteriores. El listener de "click afuera" se
   engancha en document una sola vez (buscando .user-menu en el momento del
   click) para no ir acumulando uno por cada reconstrucción. */
function wireUserMenu() {
  const menu = document.getElementById('user-menu');
  if (!menu) return;

  const chip = menu.querySelector('.user-chip');
  if (chip) {
    chip.onclick = (e) => {
      e.stopPropagation();
      chip.setAttribute('aria-expanded', menu.classList.toggle('open'));
    };
  }

  const dropdown = menu.querySelector('.dropdown');
  if (dropdown) dropdown.onclick = (e) => e.stopPropagation();

  const cerrarMenu = () => {
    const m = document.getElementById('user-menu');
    m?.classList.remove('open');
    m?.querySelector('.user-chip')?.setAttribute('aria-expanded', 'false');
  };

  if (!window.__userMenuOutsideClickWired) {
    window.__userMenuOutsideClickWired = true;
    document.addEventListener('click', cerrarMenu);
    document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrarMenu(); });
  }
}
