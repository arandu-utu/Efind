/**
 * E-Find — Simulación de autenticación
 * Lee/escribe en localStorage. No hay backend real.
 */

const Auth = {
  KEY: 'efind_session',

  /* Usuario actual (objeto) o null */
  get() {
    const raw = localStorage.getItem(this.KEY);
    return raw ? JSON.parse(raw) : null;
  },

  /* Iniciar sesión con email + contraseña (contraseña ignorada en mock) */
  login(email, _password) {
    const usuarios = getMock('usuarios');
    const u = usuarios.find(u => u.email.toLowerCase() === email.toLowerCase());
    if (!u) return { ok: false, error: 'Email no encontrado.' };
    if (!u.activo) return { ok: false, error: 'Usuario suspendido.' };
    localStorage.setItem(this.KEY, JSON.stringify(u));
    return { ok: true, usuario: u };
  },

  /* Registrar nuevo usuario */
  register(datos) {
    const usuarios = getMock('usuarios');
    if (usuarios.find(u => u.email === datos.email))
      return { ok: false, error: 'El email ya está registrado.' };

    const nuevo = {
      id: Date.now(),
      nombre: datos.nombre,
      email: datos.email,
      rol: datos.rol || 'particular',
      ci_rut: datos.ci_rut.replace(/[.\-\s]/g, ''),
      avatar: datos.nombre[0].toUpperCase(),
      activo: true,
      empresa: datos.empresa || null,
    };
    usuarios.push(nuevo);
    setMock('usuarios', usuarios);
    localStorage.setItem(this.KEY, JSON.stringify(nuevo));
    return { ok: true, usuario: nuevo };
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
    if (role === 'admin')     return u.rol_id === 1 || u.rol === 'admin';
    if (role === 'propietario') return u.rol_id === 3 || u.rol === 'propietario';
    return u.rol === role;
  },

  isAny(...roles) {
    const u = this.get();
    return u ? roles.some(r => this.is(r)) : false;
  },
};

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
  const isAdmin = u && (u.rol_id === 1 || u.rol === 'admin' || u.rol === 'moderador');

  const nav = document.getElementById('navbar');
  if (!nav) return;

  nav.innerHTML = `
    <div class="navbar__inner">
      <a href="index.html" class="nav-logo">
        <img src="img/efind-nav.png" alt="E-Find" height="38" style="display:block">
      </a>
      <div class="navbar__links">
        <a href="index.html" class="nav-link" id="nl-mapa">Mapa</a>
        ${isAdmin ? '<a href="admin.html" class="nav-link" id="nl-admin">Panel Admin</a>' : ''}
        ${u ? `
          <a href="agregar.html" class="btn btn--green btn--sm">+ Agregar</a>
          <div class="navbar__user" id="user-menu">
            <div class="user-chip">
              <div class="avatar">${u.avatar || u.nombre[0].toUpperCase()}</div>
              ${u.nombre.split(' ')[0]}
            </div>
            <div class="dropdown">
              <a href="perfil.html">${ICONS.user(15)} Mi perfil</a>
              ${isAdmin ? `<a href="admin.html">${ICONS.settings(15)} Administración</a>` : ''}
              <div class="sep"></div>
              <button onclick="Auth.logout(); location.href='login.html'">${ICONS.logout(15)} Cerrar sesión</button>
            </div>
          </div>
        ` : `
          <a href="login.html"    class="btn btn--outline-nav btn--sm">Iniciar sesión</a>
          <a href="registro.html" class="btn btn--primary btn--sm">Registrarse</a>
        `}
      </div>
    </div>
  `;

  /* Marcar link activo */
  const page = location.pathname.split('/').pop() || 'index.html';
  const linkMap = { 'index.html': 'nl-mapa', 'admin.html': 'nl-admin' };
  const active = document.getElementById(linkMap[page]);
  if (active) active.classList.add('active');
}

function renderFooter() {
  const f = document.getElementById('footer');
  if (!f) return;
  f.innerHTML = `
    <div class="footer-inner">
      <div class="footer-brand">
        <img src="img/efind-nav.png" alt="E-Find" height="28">
        <span style="color:rgba(255,255,255,.55);font-size:.8rem">Cargadores VE en Uruguay</span>
      </div>
      <span style="color:rgba(255,255,255,.4);font-size:.8rem">Proyecto de egreso &copy; 2026</span>
      <div class="footer-brand">
        <span style="color:rgba(255,255,255,.4);font-size:.75rem">Desarrollado por</span>
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
  const u = Auth.get();
  const isAdmin = u && (u.rol_id === 1 || u.rol === 'admin' || u.rol === 'moderador');
  if (!isAdmin) {
    location.href = redirectTo;
    return false;
  }
  return true;
}

/* ── Init automático ─────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  renderNavbar();
  renderFooter();
  wireUserMenu();
});

function wireUserMenu() {
  const menu = document.getElementById('user-menu');
  if (!menu) return;

  const chip = menu.querySelector('.user-chip');
  if (!chip) return;

  // Toggle on chip click
  chip.addEventListener('click', (e) => {
    e.stopPropagation();
    menu.classList.toggle('open');
  });

  // Close when clicking anywhere outside
  document.addEventListener('click', () => {
    menu.classList.remove('open');
  });

  // Prevent clicks inside dropdown from closing it
  const dropdown = menu.querySelector('.dropdown');
  if (dropdown) {
    dropdown.addEventListener('click', (e) => e.stopPropagation());
  }
}
