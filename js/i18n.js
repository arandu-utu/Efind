/**
 * E-Find — Internacionalización básica (Español / English)
 * Toggle persistido en localStorage. Traduce elementos marcados con
 * data-i18n (textContent) y data-i18n-placeholder (atributo placeholder).
 * Los textos generados dinámicamente por JS (navbar, listas, popups)
 * deben llamar a I18N.t('clave') directamente en su template.
 */
window.I18N = {
  KEY: 'efind_lang',

  dict: {
    es: {
      nav_mapa: 'Mapa',
      nav_admin: 'Panel Admin',
      nav_agregar: '+ Agregar',
      nav_perfil: 'Mi perfil',
      nav_administracion: 'Administración',
      nav_logout: 'Cerrar sesión',
      nav_login: 'Iniciar sesión',
      nav_registro: 'Registrarse',
      footer_tagline: 'Cargadores VE en Uruguay',
      footer_proyecto: 'Proyecto de egreso © 2026',
      footer_dev: 'Desarrollado por',
      buscar_placeholder: 'Buscar cargador o dirección…',
      tipo_conector: 'Tipo de conector',
      potencia_minima: 'Potencia mínima',
      potencia_cualquiera: 'Cualquiera',
      estado: 'Estado',
      estado_disponible: 'Disponible',
      estado_ocupado: 'Ocupado',
      estado_sinservicio: 'Sin servicio',
      acceso: 'Acceso',
      acceso_publico: 'Público',
      acceso_privado: 'Privado / Alquiler',
      fuente: 'Fuente',
      red_ute: 'Red UTE',
      leyenda_efind_publico: 'E-Find público',
      leyenda_efind_privado: 'E-Find privado',
      aplicar_filtros: 'Aplicar filtros',
      sin_cargadores: 'Sin cargadores',
      sin_cargadores_body: 'Probá ajustando los filtros o buscá en otra zona del mapa.',
      ver_detalle: 'Ver detalle',
      como_llegar: 'Cómo llegar',
      reportar: 'Reportar',
      potencia: 'Potencia',
      conectores: 'Conectores',
      horario: 'Horario',
      precio: 'Precio',
      gratuito: 'Gratuito',
      tarifa_ute: 'Consultar tarifa UTE',
      no_especificado: 'No especificado',
      p404_title: 'Cargador no encontrado',
      p404_line1: 'La página que buscas no existe o fue movida.',
      p404_line2: 'Pero seguro hay un cargador cerca de vos.',
      p404_irmapa: 'Ir al mapa',
      p404_volver: 'Volver',
      auth_tagline: 'La red colaborativa de cargadores para vehículos eléctricos en Uruguay.',
      auth_feat1: 'Encontrá cargadores cerca de vos en tiempo real',
      auth_feat2: 'Estimá el tiempo de carga de tu EV',
      auth_feat3: 'Compartí puntos de carga con la comunidad',
      auth_feat4: 'Alquilá tu cargador y generá ingresos',
      label_email: 'Correo electrónico',
      err_email: 'Ingresá un email válido.',
      label_password: 'Contraseña',
      err_password: 'Ingresá tu contraseña.',
      pwd_ver: 'Ver',
      pwd_ocultar: 'Ocultar',
      login_no_account: '¿No tenés cuenta?',
      login_olvide: '¿Olvidaste tu contraseña?',
      login_registrate_gratis: 'Registrate gratis',
      login_success: 'Sesión iniciada. Redirigiendo…',
      login_error_credenciales: 'Credenciales incorrectas',
      login_error_conexion: 'Error de conexión con el servidor',
    },
    en: {
      nav_mapa: 'Map',
      nav_admin: 'Admin Panel',
      nav_agregar: '+ Add',
      nav_perfil: 'My profile',
      nav_administracion: 'Administration',
      nav_logout: 'Log out',
      nav_login: 'Log in',
      nav_registro: 'Sign up',
      footer_tagline: 'EV chargers in Uruguay',
      footer_proyecto: 'Graduation project © 2026',
      footer_dev: 'Developed by',
      buscar_placeholder: 'Search charger or address…',
      tipo_conector: 'Connector type',
      potencia_minima: 'Minimum power',
      potencia_cualquiera: 'Any',
      estado: 'Status',
      estado_disponible: 'Available',
      estado_ocupado: 'In use',
      estado_sinservicio: 'Out of service',
      acceso: 'Access',
      acceso_publico: 'Public',
      acceso_privado: 'Private / Rental',
      fuente: 'Source',
      red_ute: 'UTE Network',
      leyenda_efind_publico: 'E-Find public',
      leyenda_efind_privado: 'E-Find private',
      aplicar_filtros: 'Apply filters',
      sin_cargadores: 'No chargers',
      sin_cargadores_body: 'Try adjusting the filters or search another area of the map.',
      ver_detalle: 'View detail',
      como_llegar: 'Get directions',
      reportar: 'Report',
      potencia: 'Power',
      conectores: 'Connectors',
      horario: 'Hours',
      precio: 'Price',
      gratuito: 'Free',
      tarifa_ute: 'Check UTE rate',
      no_especificado: 'Not specified',
      p404_title: 'Charger not found',
      p404_line1: 'The page you are looking for does not exist or was moved.',
      p404_line2: 'But there is surely a charger near you.',
      p404_irmapa: 'Go to map',
      p404_volver: 'Go back',
      auth_tagline: 'The collaborative network of EV charging points in Uruguay.',
      auth_feat1: 'Find chargers near you in real time',
      auth_feat2: 'Estimate your EV’s charging time',
      auth_feat3: 'Share charging points with the community',
      auth_feat4: 'Rent out your charger and earn income',
      label_email: 'Email',
      err_email: 'Enter a valid email.',
      label_password: 'Password',
      err_password: 'Enter your password.',
      pwd_ver: 'Show',
      pwd_ocultar: 'Hide',
      login_no_account: 'Don’t have an account?',
      login_olvide: 'Forgot your password?',
      login_registrate_gratis: 'Sign up for free',
      login_success: 'Signed in. Redirecting…',
      login_error_credenciales: 'Incorrect credentials',
      login_error_conexion: 'Server connection error',
    },
  },

  /**
   * Traducción automática de texto libre (sin tocar el HTML de cada página).
   * Clave = texto exacto en español tal cual aparece en la página,
   * valor = traducción al inglés. Un TreeWalker recorre el <body> y
   * reemplaza cualquier texto que matchee, sin necesidad de agregar
   * atributos data-i18n en cada página nueva.
   */
  auto: {
    // cargador.html (incluye el modal de reporte, usado también por
    // agregar.html/perfil.html/etc. vía I18N.autoT() en alert/confirm)
    'Volver': 'Back',
    'Fuera de servicio': 'Out of service',
    'Otro': 'Other',
    'Seleccioná un tipo de problema.': 'Select a problem type.',
    'Seleccioná un tipo…': 'Select a type…',
    'Cancelar': 'Cancel',
    'Enviar reporte': 'Send report',
    'Volver al mapa': 'Back to map',
    'Escribir reseña': 'Write a review',
    'Calificación': 'Rating',
    'Comentario (opcional)': 'Comment (optional)',
    'Contá tu experiencia con este cargador…': 'Tell us about your experience with this charger…',
    'Publicar reseña': 'Post review',
    'Reportar problema': 'Report a problem',
    'Tipo de problema': 'Problem type',
    'Vandalismo': 'Vandalism',
    'Información incorrecta (precio, conector, acceso)': 'Incorrect information (price, connector, access)',
    'Descripción (opcional)': 'Description (optional)',
    'Describí el problema…': 'Describe the problem…',
    'ID de cargador no especificado.': 'Charger ID not specified.',
    'Cargador no encontrado.': 'Charger not found.',
    'Error de conexión con el servidor.': 'Server connection error.',
    'Mapa': 'Map',
    'Información técnica': 'Technical information',
    'Horario': 'Hours',
    'Precio': 'Price',
    'Potencia máx.': 'Max power',
    'Tipo de acceso': 'Access type',
    'Público': 'Public',
    'Privado / Alquiler': 'Private / Rental',
    'Fuente': 'Source',
    'Red UTE CargaME': 'UTE CargaME Network',
    'Conectores disponibles': 'Available connectors',
    'Reseñas': 'Reviews',
    '+ Escribir reseña': '+ Write review',
    'Sin reseñas aún. ¡Sé el primero!': 'No reviews yet. Be the first!',
    'Cómo llegar': 'Get directions',
    'Reportar': 'Report',
    'Reservar carga': 'Reserve charge',
    'Iniciar sesión para más opciones': 'Log in for more options',
    'Estimador de carga': 'Charging estimator',
    '📡 Reportar estado en tiempo real': '📡 Report real-time status',
    'Iniciá sesión para reportar.': 'Log in to report.',
    'Disponible': 'Available',
    'Ocupado': 'In use',
    'Cola de espera': 'Waiting queue',
    'Sin cola': 'No queue',
    '1 vehículo': '1 vehicle',
    '2 vehículos': '2 vehicles',
    '3 o más': '3 or more',
    'Actualizar cola': 'Update queue',
    'Sin espera': 'No wait',
    'Seleccioná una calificación.': 'Select a rating.',
    '✓ Reseña enviada. Se va a publicar cuando un admin la apruebe.': '✓ Review sent. It will be published once an admin approves it.',
    'Error al enviar la reseña.': 'Error sending review.',
    'Error de conexión al enviar la reseña.': 'Connection error sending review.',
    '✓ Reporte enviado, gracias por contribuir.': '✓ Report sent, thanks for contributing.',
    'Error al enviar el reporte.': 'Error sending report.',
    'Error de conexión al enviar el reporte.': 'Connection error sending report.',
    'Error al actualizar el estado.': 'Error updating status.',
    'Error de conexión al actualizar el estado.': 'Connection error updating status.',
    'Error al actualizar la cola.': 'Error updating queue.',
    'Error de conexión al actualizar la cola.': 'Connection error updating queue.',

    // registro.html
    'Creá tu cuenta y empezá a contribuir con la comunidad de VE en Uruguay.':
      'Create your account and start contributing to the EV community in Uruguay.',
    'Registro gratuito en menos de 1 minuto': 'Free registration in under 1 minute',
    'Agregá tus propios puntos de carga': 'Add your own charging points',
    'Guardá cargadores favoritos': 'Save favorite chargers',
    'Accedé al estimador de carga personalizado': 'Access the personalized charging estimator',
    'Si sos empresa: reportes financieros de tus terminales': 'If you are a company: financial reports for your terminals',
    'Tipo de cuenta': 'Account type',
    'Particular': 'Individual',
    'CI uruguaya': 'Uruguayan ID',
    'Empresa': 'Company',
    'RUT + razón social': 'Tax ID + company name',
    'Nombre completo *': 'Full name *',
    'Mínimo 3 caracteres.': 'Minimum 3 characters.',
    'Correo electrónico *': 'Email *',
    'Email no válido o ya registrado.': 'Invalid or already registered email.',
    'Cédula de Identidad *': 'ID card *',
    '7 u 8 dígitos, con o sin puntos y guion': '7 or 8 digits, with or without dots and dash',
    'CI inválida.': 'Invalid ID.',
    'Razón social *': 'Company name *',
    'Nombre de la empresa': 'Company name',
    'Ingresá el nombre de la empresa.': 'Enter the company name.',
    'Contraseña *': 'Password *',
    'Mínimo 8 caracteres': 'Minimum 8 characters',
    'Mínimo 8 caracteres.': 'Minimum 8 characters.',
    'Confirmar contraseña *': 'Confirm password *',
    'Repetí la contraseña': 'Repeat the password',
    'Las contraseñas no coinciden.': 'Passwords don’t match.',
    'Crear cuenta': 'Create account',
    '¿Ya tenés cuenta?': 'Already have an account?',
    'Iniciá sesión': 'Log in',
    'RUT de la empresa *': 'Company tax ID *',
    '12 dígitos sin puntos ni guiones': '12 digits without dots or dashes',
    'RUT inválido (12 dígitos).': 'Invalid tax ID (12 digits).',
    'Creando cuenta…': 'Creating account…',
    '✓ ¡Cuenta creada! Redirigiendo…': '✓ Account created! Redirecting…',
    '✗ Error al crear la cuenta': '✗ Error creating account',
    '✗ Error de conexión con el servidor': '✗ Server connection error',

    // agregar.html
    'Agregar punto de carga': 'Add charging point',
    'Ubicación': 'Location',
    'Datos': 'Details',
    'Confirmar': 'Confirm',
    'Paso 1 — Ubicación': 'Step 1 — Location',
    'Buscar dirección': 'Search address',
    'Buscar': 'Search',
    'Hacé clic en el mapa para colocar el marcador con precisión.': 'Click on the map to place the marker precisely.',
    'Latitud': 'Latitude',
    'Longitud': 'Longitude',
    'Dirección completa (editable)': 'Full address (editable)',
    'Siguiente →': 'Next →',
    'Paso 2 — Datos del cargador': 'Step 2 — Charger details',
    'Nombre del punto de carga *': 'Charging point name *',
    'Ej: Mi cargador doméstico': 'E.g.: My home charger',
    'Campo obligatorio.': 'Required field.',
    '24 horas': '24 hours',
    'Precio ($/kWh, 0 = gratis)': 'Price ($/kWh, 0 = free)',
    'Libre acceso': 'Open access',
    'Reserva previa': 'Prior reservation',
    'Paso 3 — Conectores': 'Step 3 — Connectors',
    '+ Agregar conector': '+ Add connector',
    'Paso 4 — Confirmar': 'Step 4 — Confirm',
    'Guardar cargador': 'Save charger',
    '¡Cargador agregado!': 'Charger added!',
    'Tu punto de carga ya está visible en el mapa.': 'Your charging point is now visible on the map.',
    'Ver en el mapa': 'View on map',
    'Dirección no encontrada, hacé clic en el mapa.': 'Address not found, click on the map.',
    'No se pudo buscar la dirección. Hacé clic en el mapa.': 'Could not search the address. Click on the map.',
    'Hacé clic en el mapa para seleccionar la ubicación.': 'Click on the map to select the location.',
    'Nombre': 'Name',
    'Dirección': 'Address',
    'Coordenadas': 'Coordinates',
    'Gratuito': 'Free',
    'Consultar tarifa UTE': 'Check UTE rate',
    'Acceso': 'Access',
    'Guardando…': 'Saving…',
    '✗ No se pudo guardar el cargador.': '✗ Could not save the charger.',
    '✗ Error de conexión con el servidor.': '✗ Server connection error.',

    // perfil.html
    'Perfil': 'Profile',
    'Mis vehículos': 'My vehicles',
    'Mis cargadores': 'My chargers',
    'Historial': 'History',
    'Calificaciones': 'Ratings',
    'Mi reputación': 'My reputation',
    'Datos personales': 'Personal details',
    'Email': 'Email',
    'CI / RUT': 'ID / Tax ID',
    'Rol': 'Role',
    '+ Agregar vehículo': '+ Add vehicle',
    'Marca': 'Make',
    'Modelo': 'Model',
    'Capacidad (kWh)': 'Capacity (kWh)',
    'Capacidad': 'Capacity',
    'Conector': 'Connector',
    'Guardar vehículo': 'Save vehicle',
    'Mis puntos de carga': 'My charging points',
    '+ Agregar cargador': '+ Add charger',
    'Mis transacciones': 'My transactions',
    'Usuario Particular': 'Individual User',
    'Moderador': 'Moderator',
    'Administrador': 'Administrator',
    'Sin calificaciones': 'No ratings',
    'Aún no tenés calificaciones de otros usuarios.': 'You don’t have ratings from other users yet.',
    'Calificación promedio': 'Average rating',
    'Como cliente': 'As customer',
    'Como propietario': 'As owner',
    'No tenés vehículos registrados.': 'You have no registered vehicles.',
    'Completá todos los campos.': 'Fill in all fields.',
    '¿Eliminar este vehículo?': 'Delete this vehicle?',
    'Eliminar vehículo': 'Delete vehicle',
    'Se va a dar de baja este vehículo de tu perfil.': 'This vehicle will be removed from your profile.',
    '✓ Vehículo agregado.': '✓ Vehicle added.',
    '✓ Vehículo eliminado.': '✓ Vehicle deleted.',
    'No se pudo guardar el vehículo.': 'The vehicle could not be saved.',
    'No se pudo eliminar.': 'Could not delete.',
    /* Placeholders de carga y modal de confirmación */
    'Cargando…': 'Loading…',
    'Cargando cargadores…': 'Loading chargers…',
    'Cargando cargador…': 'Loading charger…',
    'Cargando usuarios…': 'Loading users…',
    'Eliminar': 'Delete',
    'Aceptar': 'Accept',
    /* Recuperación de contraseña */
    'Recuperar contraseña': 'Reset password',
    'Escribí el correo con el que te registraste y te mandamos un enlace para elegir una contraseña nueva.':
      'Enter the email you signed up with and we will send you a link to choose a new password.',
    'Enviar enlace': 'Send link',
    'Enviando…': 'Sending…',
    'Volver a iniciar sesión': 'Back to sign in',
    'Si el correo corresponde a una cuenta registrada, te enviamos un enlace para restablecer la contraseña.':
      'If the email matches a registered account, we have sent a link to reset the password.',
    'Elegí una contraseña nueva': 'Choose a new password',
    'Verificando el enlace…': 'Checking the link…',
    'Contraseña nueva': 'New password',
    'Repetir contraseña': 'Repeat password',
    'Guardar contraseña': 'Save password',
    'La contraseña debe tener al menos 8 caracteres.': 'The password must be at least 8 characters long.',
    'El enlace no es válido o ya venció. Pedí uno nuevo.': 'The link is not valid or has expired. Request a new one.',
    'Pedí uno nuevo': 'Request a new one',
    '✓ Listo, tu contraseña quedó cambiada. Ya podés iniciar sesión.':
      '✓ Done, your password has been changed. You can sign in now.',
    'No se pudo cambiar la contraseña.': 'The password could not be changed.',
    'No se pudo procesar el pedido.': 'The request could not be processed.',
    'Ya pediste varios enlaces en la última hora. Esperá un rato antes de volver a intentar.':
      'You have already requested several links in the last hour. Please wait before trying again.',
    /* Etiquetas accesibles (aria-label) */
    'Cerrar': 'Close',
    'Quitar conector': 'Remove connector',
    'Detalle del cargador': 'Charger details',
    'Calificación': 'Rating',
    'No tenés cargadores registrados.': 'You have no registered chargers.',
    'Ver': 'View',
    'Sin historial': 'No history',
    'Tus recargas aparecerán acá una vez que realices tu primera reserva.': 'Your charges will appear here once you make your first reservation.',
    'Recibo': 'Receipt',
    'Cargador': 'Charger',
    'Total': 'Total',
    'Fecha': 'Date',
    'Gratis': 'Free',

    // admin.html
    'Principal': 'Main',
    'Cargadores': 'Chargers',
    'Usuarios': 'Users',
    'Moderación': 'Moderation',
    'Reportes': 'Reports',
    'Sistema': 'System',
    'Ver mapa': 'View map',
    'Cargadores registrados por mes (2026)': 'Chargers registered per month (2026)',
    'Distribución por rol': 'Distribution by role',
    'Reseñas pendientes de moderación': 'Reviews pending moderation',
    'Reportes abiertos': 'Open reports',
    'Últimos cargadores registrados': 'Latest registered chargers',
    'Actualizar cargadores UTE': 'Update UTE chargers',
    'Estado': 'Status',
    'Cargadores totales': 'Total chargers',
    '+1 este mes': '+1 this month',
    'Usuarios registrados': 'Registered users',
    '+2 este mes': '+2 this month',
    'Reseñas pendientes': 'Pending reviews',
    'Propietario': 'Owner',
    'Usuario': 'User',
    'Estrellas': 'Stars',
    'Comentario': 'Comment',
    'Acciones': 'Actions',
    'Aprobar': 'Approve',
    'Rechazar': 'Reject',
    'Sin reseñas pendientes. ✓': 'No pending reviews. ✓',
    'Tipo': 'Type',
    'Descripción': 'Description',
    'Reportado por': 'Reported by',
    'Aplicar estado': 'Apply status',
    'Acción': 'Action',
    'No cambiar': 'Don’t change',
    'Resolver': 'Resolve',
    'Sin reportes pendientes. ✓': 'No pending reports. ✓',
    'Sincronizando…': 'Syncing…',
    '✗ Error al sincronizar.': '✗ Sync error.',

    // usuarios.html
    'Gestión de usuarios': 'User management',
    'Todos los roles': 'All roles',
    'Buscar nombre o email…': 'Search name or email…',
    'Registrado': 'Registered',
    'Sin usuarios': 'No users',
    'No hay usuarios que coincidan con el filtro aplicado.': 'No users match the applied filter.',
    'Activo': 'Active',
    'Suspendido': 'Suspended',
    'Tu cuenta': 'Your account',
    'Suspender': 'Suspend',
    'Activar': 'Activate',

    // reservar.html
    '› Reservar': '› Reserve',
    'Mi vehículo': 'My vehicle',
    '— Seleccioná un vehículo —': '— Select a vehicle —',
    'Estado de carga actual:': 'Current charge level:',
    'Energía a cargar': 'Energy to charge',
    'Tiempo estimado': 'Estimated time',
    'Precio por kWh': 'Price per kWh',
    'Subtotal': 'Subtotal',
    'Comisión plataforma (10%)': 'Platform fee (10%)',
    'Total a pagar': 'Total to pay',
    'Continuar al pago →': 'Continue to payment →',
    '💳 Datos de pago (simulado)': '💳 Payment details (simulated)',
    'Este proceso es una simulación. No se realizará ningún cobro real.': 'This process is a simulation. No real charge will be made.',
    'Número de tarjeta': 'Card number',
    'Nombre en la tarjeta': 'Name on card',
    'Vencimiento': 'Expiration',
    'Confirmar y pagar': 'Confirm and pay',
    '¡Carga reservada!': 'Charge reserved!',
    'Recibo de transacción': 'Transaction receipt',
    'N° de recibo': 'Receipt No.',
    'Energía cargada': 'Energy charged',
    'Total pagado': 'Total paid',
    'Acreditado al propietario (90%)': 'Credited to owner (90%)',
    'Ver mis transacciones': 'View my transactions',
    '¿Cómo fue tu experiencia con el propietario?': 'How was your experience with the owner?',
    'Comentario opcional…': 'Optional comment…',
    'Enviar calificación': 'Submit rating',
    'Omitir': 'Skip',
    'Seleccioná una puntuación antes de enviar.': 'Select a rating before submitting.',
    '✓ ¡Gracias por tu calificación!': '✓ Thank you for your rating!',
    'Completá todos los datos de la tarjeta.': 'Fill in all card details.',
    'Procesando…': 'Processing…',
  },

  get() { return localStorage.getItem(this.KEY) || 'es'; },

  t(key) {
    const lang = this.get();
    if (this.dict[lang] && key in this.dict[lang]) return this.dict[lang][key];
    if (lang !== 'es' && this.auto[key]) return this.auto[key];
    return this.dict.es[key] ?? key;
  },

  /* Traduce texto libre usando `auto`. También sirve para usar dentro de
     alert()/confirm() nativos, que no viven en el DOM. */
  autoT(str) {
    return this.get() === 'es' ? str : (this.auto[str] || str);
  },

  /* Aplica todas las traducciones: data-i18n, placeholders y el resto del
     texto de la página (vía diccionario `auto`, sin tocar el HTML). */
  apply(rebuildNav = true) {
    document.documentElement.lang = this.get();

    document.querySelectorAll('[data-i18n]').forEach(el => {
      el.textContent = this.t(el.getAttribute('data-i18n'));
    });
    document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
      el.setAttribute('placeholder', this.t(el.getAttribute('data-i18n-placeholder')));
    });

    const tw = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, {
      acceptNode(n) {
        const p = n.parentElement;
        if (!p || p.closest('script, style, noscript, [data-i18n]')) return NodeFilter.FILTER_REJECT;
        return n.nodeValue.trim() ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT;
      },
    });
    let n;
    while ((n = tw.nextNode())) {
      if (n.__i18nOrig === undefined) n.__i18nOrig = n.nodeValue;
      const trimmed = n.__i18nOrig.trim();
      if (trimmed) n.nodeValue = n.__i18nOrig.replace(trimmed, this.autoT(trimmed));
    }
    document.querySelectorAll('input[placeholder]:not([data-i18n-placeholder]), textarea[placeholder]:not([data-i18n-placeholder])').forEach(el => {
      if (el.__i18nPh === undefined) el.__i18nPh = el.placeholder;
      el.placeholder = this.autoT(el.__i18nPh);
    });

    /* Los aria-label no son nodos de texto, así que el TreeWalker no los ve. */
    document.querySelectorAll('[aria-label]').forEach(el => {
      if (el.__i18nAria === undefined) el.__i18nAria = el.getAttribute('aria-label');
      el.setAttribute('aria-label', this.autoT(el.__i18nAria));
    });

    document.querySelectorAll('.lang-btn').forEach(b => {
      b.classList.toggle('active', b.dataset.lang === this.get());
    });

    if (rebuildNav) {
      if (typeof renderNavbar === 'function') renderNavbar();
      if (typeof renderFooter === 'function') renderFooter();
    }

    window.dispatchEvent(new Event('i18n:change'));
  },

  set(lang) {
    localStorage.setItem(this.KEY, lang);
    this.apply();
  },

  toggle() { this.set(this.get() === 'es' ? 'en' : 'es'); },
};

document.addEventListener('DOMContentLoaded', () => {
  I18N.apply();
  /* Reintentos cortos para agarrar contenido que llega por fetch (dashboards,
     listas, reseñas) sin necesidad de un observer permanente. No reconstruyen
     navbar/footer para no cerrar menús o interrumpir clics en curso. */
  setTimeout(() => I18N.apply(false), 400);
  setTimeout(() => I18N.apply(false), 1200);
});
