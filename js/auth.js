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
            <div class="user-chip">
              <div class="avatar">${u.avatar || u.nombre[0].toUpperCase()}</div>
              ${u.nombre.split(' ')[0]}
            </div>
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
      menu.classList.toggle('open');
    };
  }

  const dropdown = menu.querySelector('.dropdown');
  if (dropdown) dropdown.onclick = (e) => e.stopPropagation();

  if (!window.__userMenuOutsideClickWired) {
    window.__userMenuOutsideClickWired = true;
    document.addEventListener('click', () => {
      document.getElementById('user-menu')?.classList.remove('open');
    });
  }
}
