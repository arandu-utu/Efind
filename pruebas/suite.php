<?php
/**
 * E-Find — Batería de pruebas de integración.
 * Ejecuta peticiones HTTP reales contra los endpoints, con base de datos real.
 */
require __DIR__ . '/comun.php';

$sufijo = substr((string)time(), -6);
$A    = new Cliente('a');
$B    = new Cliente('b');
$ADM  = new Cliente('adm');
$ANON = new Cliente('anon');
$MOD  = new Cliente('mod');

/* La base y las credenciales salen del entorno, para que esto corra en otra
   máquina sin editar el archivo. Los valores por defecto son los de un XAMPP
   recién instalado. Nunca apuntar esto a la base de producción: la batería
   crea y modifica datos. */
$BASE_PRUEBAS = getenv('EFIND_TEST_DB')   ?: 'efind_test';
$BASE_USUARIO = getenv('EFIND_TEST_USER') ?: 'root';
$BASE_CLAVE   = getenv('EFIND_TEST_PASS') ?: '';

if ($BASE_PRUEBAS === 'efind') {
    fwrite(STDERR, "La batería no se ejecuta contra la base de producción.
");
    exit(1);
}

$sql = new PDO("mysql:host=127.0.0.1;dbname=$BASE_PRUEBAS;charset=utf8mb4", $BASE_USUARIO, $BASE_CLAVE, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

/* Cédulas uruguayas generadas por corrida: el dígito verificador se calcula
   con los mismos pesos que usa el sistema, para que cada ejecución use
   documentos nuevos y no choque con los de la corrida anterior. */
function ciValida(int $base): string {
    $s = str_pad((string)($base % 10000000), 7, '0', STR_PAD_LEFT);
    $pesos = [2, 9, 8, 7, 6, 3, 4];
    $suma = 0;
    for ($i = 0; $i < 7; $i++) $suma += (int)$s[$i] * $pesos[$i];
    return $s . ((10 - $suma % 10) % 10);
}
$CI_ANA = ciValida((int)$sufijo);
$CI_ADM = ciValida((int)$sufijo + 7);
$CI_LIB = ciValida((int)$sufijo + 13);
$CI_MOD = ciValida((int)$sufijo + 19);
$RUT_B  = '12345' . str_pad($sufijo, 7, '0', STR_PAD_LEFT);

/* ══════════════════════════════════ REGISTRO ══════════════════════════════ */
grupo('REGISTRO Y DOCUMENTO DUPLICADO');

$r = $A->pedir('POST', '/api/registro.php', ['acepta' => true, 'nombre' => 'Ana Cliente', 'email' => "ana$sufijo@test.com",
    'password' => 'contrasena1', 'rol' => 'particular', 'ci' => $CI_ANA]);
caso('registro', 'Alta de usuario particular con cedula valida', ($r['cuerpo']['ok'] ?? false) === true, json_encode($r['cuerpo']));
$idA = $r['cuerpo']['data']['id'] ?? 0;

$T = new Cliente('descarte');
$r = $T->pedir('POST', '/api/registro.php', ['acepta' => true, 'nombre' => 'Otro', 'email' => "otro$sufijo@test.com",
    'password' => 'contrasena1', 'rol' => 'particular', 'ci' => $CI_ANA]);
caso('registro', 'La misma cedula en otro formato es rechazada', ($r['cuerpo']['ok'] ?? true) === false, $r['cuerpo']['error'] ?? '');

$r = $T->pedir('POST', '/api/registro.php', ['acepta' => true, 'nombre' => 'Otro', 'email' => "ana$sufijo@test.com",
    'password' => 'contrasena1', 'rol' => 'particular', 'ci' => $CI_LIB]);
caso('registro', 'Correo duplicado es rechazado', ($r['cuerpo']['ok'] ?? true) === false);

$r = $T->pedir('POST', '/api/registro.php', ['acepta' => true, 'nombre' => 'Malo', 'email' => "malo$sufijo@test.com",
    'password' => 'contrasena1', 'rol' => 'particular', 'ci' => '12345678']);
caso('registro', 'Cedula con digito verificador invalido es rechazada', ($r['cuerpo']['ok'] ?? true) === false);

$r = $T->pedir('POST', '/api/registro.php', ['acepta' => true, 'nombre' => 'Corto', 'email' => "corto$sufijo@test.com",
    'password' => 'abc', 'rol' => 'particular', 'ci' => $CI_LIB]);
caso('registro', 'Contrasena de menos de 8 caracteres rechazada', ($r['cuerpo']['ok'] ?? true) === false);

$r = $B->pedir('POST', '/api/registro.php', ['acepta' => true, 'nombre' => 'Beto Propietario', 'email' => "beto$sufijo@test.com",
    'password' => 'contrasena1', 'rol' => 'empresa', 'ci' => $RUT_B, 'empresa' => 'Cargas SRL']);
caso('registro', 'Alta de empresa con RUT valido', ($r['cuerpo']['ok'] ?? false) === true, json_encode($r['cuerpo']));
$idB = $r['cuerpo']['data']['id'] ?? 0;

$r = $T->pedir('POST', '/api/registro.php', ['acepta' => true, 'nombre' => 'Clon', 'email' => "clon$sufijo@test.com",
    'password' => 'contrasena1', 'rol' => 'empresa', 'ci' => $RUT_B, 'empresa' => 'Otra SRL']);
caso('registro', 'RUT duplicado es rechazado', ($r['cuerpo']['ok'] ?? true) === false, $r['cuerpo']['error'] ?? '');

$r = $T->pedir('POST', '/api/registro.php', ['nombre' => 'Sin Aceptar', 'email' => "noacepta$sufijo@test.com",
    'password' => 'contrasena1', 'rol' => 'particular', 'ci' => $CI_LIB, 'acepta' => 'si']);
$guardado = $sql->prepare('SELECT COUNT(*) FROM usuarios WHERE email = ?');
$guardado->execute(["noacepta$sufijo@test.com"]);
caso('registro', 'Sin aceptar los terminos no se crea la cuenta',
     ($r['cuerpo']['ok'] ?? true) === false && (int)$guardado->fetchColumn() === 0, $r['cuerpo']['error'] ?? '');
$guardado = $sql->prepare('SELECT terminos_aceptados_en FROM usuarios WHERE id = ?');
$guardado->execute([$idA]);
caso('registro', 'La cuenta guarda la fecha de aceptacion', $guardado->fetchColumn() !== null);

/* ══════════════════════════════════ SESION ════════════════════════════════ */
grupo('SESION Y COOKIE');

$r = $A->pedir('POST', '/api/login.php', ['email' => "ana$sufijo@test.com", 'password' => 'contrasena1']);
caso('sesion', 'Ingreso con credenciales correctas', ($r['cuerpo']['ok'] ?? false) === true);
caso('sesion', 'La cookie de sesion lleva HttpOnly', stripos($r['cabeceras'], 'HttpOnly') !== false);
caso('sesion', 'La cookie de sesion lleva SameSite=Lax', stripos($r['cabeceras'], 'SameSite=Lax') !== false);
caso('sesion', 'Sin HTTPS la cookie no se marca Secure', stripos($r['cabeceras'], 'secure') === false);
caso('sesion', 'Se emite un identificador de sesion nuevo al ingresar', substr_count($r['cabeceras'], 'Set-Cookie: PHPSESSID') >= 1);

$r = $A->pedir('POST', '/api/login.php', ['email' => "ana$sufijo@test.com", 'password' => 'incorrecta']);
caso('sesion', 'Credenciales incorrectas son rechazadas con 401', $r['codigo'] === 401);

/* Freno de fuerza bruta. Se usa un correo propio del caso, porque al activarse
   el freno esa combinacion de IP y correo queda bloqueada quince minutos y
   arruinaria los casos siguientes. El control estuvo inactivo mucho tiempo: la
   ventana se medía restando el reloj de PHP contra la marca que escribe la base,
   y con zonas horarias distintas la diferencia daba horas. */
$F = new Cliente('freno');
$correoFreno = "freno$sufijo@test.com";
$codigos = [];
for ($i = 1; $i <= 6; $i++) {
    $rr = $F->pedir('POST', '/api/login.php', ['email' => $correoFreno, 'password' => 'incorrecta']);
    $codigos[] = $rr['codigo'];
}
caso('sesion', 'Los primeros cinco intentos fallidos devuelven 401',
     array_slice($codigos, 0, 5) === [401, 401, 401, 401, 401], implode(',', $codigos));
caso('sesion', 'El sexto intento queda frenado con 429',
     $codigos[5] === 429, 'codigo=' . $codigos[5]);

/* ═════════════════════════════════ CARGADORES ═════════════════════════════ */
grupo('ALTA DE CARGADORES: VALIDACION DE ENTRADA');

$ra = $ADM->pedir('POST', '/api/registro.php', ['acepta' => true, 'nombre' => 'Admin', 'email' => "adm$sufijo@test.com",
    'password' => 'contrasena1', 'rol' => 'particular', 'ci' => $CI_ADM]);
$idADM = $ra['cuerpo']['data']['id'] ?? 0;
$sql->prepare('UPDATE usuarios SET rol_id = 1 WHERE id = ?')->execute([$idADM]);
$ADM->pedir('POST', '/api/login.php', ['email' => "adm$sufijo@test.com", 'password' => 'contrasena1']);

$base = ['nombre' => 'Cargador Beto', 'direccion' => 'Ruta 9 km 200', 'lat' => -34.4, 'lng' => -54.3,
         'acceso' => 'publico', 'costo_kwh' => 12, 'conectores' => [['tipo' => 'Type 2', 'potencia' => 22]]];

$r = $B->pedir('POST', '/api/cargadores.php', $base);
caso('cargadores', 'Alta valida', ($r['cuerpo']['ok'] ?? false) === true, json_encode($r['cuerpo']));
$idCargador = $r['cuerpo']['data']['id'] ?? 0;

$r = $B->pedir('POST', '/api/cargadores.php', array_merge($base, ['costo_kwh' => 0]));
caso('cargadores', 'Precio cero es valido, es un cargador gratuito', ($r['cuerpo']['ok'] ?? false) === true, json_encode($r['cuerpo']));

/* Los pagos van sobre un cargador privado: solo se reservan los dados de alta
   por un particular o una empresa, no los de acceso publico ni los de UTE. */
$r = $B->pedir('POST', '/api/cargadores.php', array_merge($base, ['acceso' => 'privado', 'nombre' => 'Privado de prueba']));
caso('cargadores', 'Alta de cargador privado', ($r['cuerpo']['ok'] ?? false) === true, json_encode($r['cuerpo']));
$idPrivado = $r['cuerpo']['data']['id'] ?? 0;

$invalidos = [
    ['Precio negativo rechazado',               ['costo_kwh' => -100]],
    ['Precio no numerico rechazado',            ['costo_kwh' => 'gratis']],
    ['Latitud fuera de rango rechazada',        ['lat' => 500]],
    ['Longitud fuera de rango rechazada',       ['lng' => -900]],
    ['Coordenada no numerica rechazada',        ['lat' => 'norte']],
    ['Potencia cero rechazada',                 ['conectores' => [['tipo' => 'Type 2', 'potencia' => 0]]]],
    ['Potencia negativa rechazada',             ['conectores' => [['tipo' => 'Type 2', 'potencia' => -50]]]],
    ['Potencia desmedida rechazada',            ['conectores' => [['tipo' => 'Type 2', 'potencia' => 99999]]]],
    ['Tipo de conector inexistente rechazado',  ['conectores' => [['tipo' => 'Inventado', 'potencia' => 22]]]],
    ['Sin conectores rechazado',                ['conectores' => []]],
    ['Acceso no permitido rechazado',           ['acceso' => 'secreto']],
];
foreach ($invalidos as [$nombre, $cambio]) {
    $r = $B->pedir('POST', '/api/cargadores.php', array_merge($base, $cambio));
    caso('cargadores', $nombre, ($r['cuerpo']['ok'] ?? true) === false, $r['cuerpo']['error'] ?? '');
}

$r = $B->pedir('POST', '/api/cargadores.php', array_merge($base, ['conectores' => [['tipo' => 'Type 2', 'potencia' => 350]]]));
caso('cargadores', 'Potencia de 350 kW aceptada, no se impone el tope del formulario', ($r['cuerpo']['ok'] ?? false) === true);

/* ══════════════════════════════════ PAGOS ═════════════════════════════════ */
grupo('PAGOS: EL IMPORTE SE RECONSTRUYE EN EL SERVIDOR');

$A->pedir('POST', '/api/login.php', ['email' => "ana$sufijo@test.com", 'password' => 'contrasena1']);
$rv  = $A->pedir('POST', '/api/vehiculos.php', ['marca' => 'Renault', 'modelo' => 'Zoe', 'capacidad_kwh' => 52, 'tipo_conector' => 'Type 2']);
$idVeh = $rv['cuerpo']['data']['id'] ?? 0;
$rvb = $B->pedir('POST', '/api/vehiculos.php', ['marca' => 'BYD', 'modelo' => 'Dolphin', 'capacidad_kwh' => 44.9, 'tipo_conector' => 'Type 2']);
$idVehB = $rvb['cuerpo']['data']['id'] ?? 0;

$conector       = $sql->query("SELECT id FROM conectores WHERE punto_carga_id = $idPrivado LIMIT 1")->fetch()['id'];
$conectorAjeno  = $sql->query("SELECT id FROM conectores WHERE punto_carga_id <> $idPrivado LIMIT 1")->fetch()['id'];

/* Regla de negocio: un cargador de acceso publico no se reserva ni se cobra.
   La ficha oculta el boton, pero la restriccion tiene que estar en el servidor:
   de otro modo alcanza con escribir la direccion de reserva a mano. */
$conectorPublico = $sql->query("SELECT id FROM conectores WHERE punto_carga_id = $idCargador LIMIT 1")->fetch()['id'];
$r = $A->pedir('POST', '/api/pagos.php', ['punto_carga_id' => $idCargador, 'vehiculo_id' => $idVeh, 'conector_id' => $conectorPublico, 'soc_pct' => 20]);
caso('pagos', 'Cargador publico no se puede reservar', ($r['cuerpo']['ok'] ?? true) === false, $r['cuerpo']['error'] ?? '');

$r = $A->pedir('POST', '/api/pagos.php', ['punto_carga_id' => $idPrivado, 'vehiculo_id' => $idVeh, 'conector_id' => $conector, 'soc_pct' => 20]);
$d = $r['cuerpo']['data'] ?? [];
caso('pagos', 'Pago valido genera comprobante', ($r['cuerpo']['ok'] ?? false) === true, json_encode($r['cuerpo']));
caso('pagos', 'La energia sale de la capacidad del vehiculo: 52 kWh al 20 por ciento da 41,6', (float)($d['kwh'] ?? 0) === 41.6, 'kwh=' . ($d['kwh'] ?? '?'));
caso('pagos', 'El importe usa el precio del cargador: 41,6 por 12 da 499,20', (float)($d['monto_total'] ?? 0) === 499.20, 'monto=' . ($d['monto_total'] ?? '?'));
caso('pagos', 'La comision es el 10 por ciento', (float)($d['comision'] ?? 0) === 49.92, 'comision=' . ($d['comision'] ?? '?'));
caso('pagos', 'El tiempo usa la potencia del conector con el rendimiento', ($d['tiempo'] ?? '') === '2 h 3 min', 'tiempo=' . ($d['tiempo'] ?? '?'));
$idTx = $d['id'] ?? 0;

$r = $A->pedir('POST', '/api/pagos.php', ['punto_carga_id' => $idPrivado, 'vehiculo_id' => $idVehB, 'conector_id' => $conector, 'soc_pct' => 20]);
caso('pagos', 'Vehiculo de otro usuario rechazado', ($r['cuerpo']['ok'] ?? true) === false, $r['cuerpo']['error'] ?? '');

$r = $A->pedir('POST', '/api/pagos.php', ['punto_carga_id' => $idPrivado, 'vehiculo_id' => 999999, 'conector_id' => $conector, 'soc_pct' => 20]);
caso('pagos', 'Vehiculo inexistente rechazado', ($r['cuerpo']['ok'] ?? true) === false);

$r = $A->pedir('POST', '/api/pagos.php', ['punto_carga_id' => $idPrivado, 'vehiculo_id' => $idVeh, 'conector_id' => $conectorAjeno, 'soc_pct' => 20]);
caso('pagos', 'Conector de otro cargador rechazado', ($r['cuerpo']['ok'] ?? true) === false, $r['cuerpo']['error'] ?? '');

$r = $A->pedir('POST', '/api/pagos.php', ['punto_carga_id' => $idPrivado, 'conector_id' => $conector, 'soc_pct' => 20]);
caso('pagos', 'Sin vehiculo no se genera pago', ($r['cuerpo']['ok'] ?? true) === false);

$r = $ANON->pedir('POST', '/api/pagos.php', ['punto_carga_id' => $idCargador, 'vehiculo_id' => $idVeh, 'conector_id' => $conector, 'soc_pct' => 20]);
caso('pagos', 'Sin sesion no se genera pago', $r['codigo'] === 401);

/* Controles del servidor y códigos HTTP: un aviso al usuario no es un error 500. */
$r = $B->pedir('POST', '/api/pagos.php', ['punto_carga_id' => $idPrivado, 'vehiculo_id' => $idVehB, 'conector_id' => $conector, 'soc_pct' => 20]);
caso('pagos', 'El dueno no puede reservar su propio cargador (403)', $r['codigo'] === 403 && ($r['cuerpo']['ok'] ?? true) === false, "codigo={$r['codigo']}");

$r = $A->pedir('POST', '/api/pagos.php', ['punto_carga_id' => $idPrivado, 'vehiculo_id' => 999999, 'conector_id' => $conector, 'soc_pct' => 20]);
caso('pagos', 'Vehiculo inexistente responde 404, no 500', $r['codigo'] === 404, "codigo={$r['codigo']}");

$r = $A->pedir('POST', '/api/pagos.php', ['punto_carga_id' => $idPrivado, 'vehiculo_id' => $idVeh, 'conector_id' => $conector, 'soc_pct' => 'abc']);
caso('pagos', 'Estado de carga no numerico responde 400', $r['codigo'] === 400, "codigo={$r['codigo']}");

$r = $A->pedir('POST', '/api/pagos.php', ['punto_carga_id' => $idPrivado, 'vehiculo_id' => $idVeh, 'conector_id' => $conector, 'soc_pct' => ['x']]);
caso('pagos', 'Estado de carga como arreglo no provoca error 500', $r['codigo'] === 400, "codigo={$r['codigo']}");

$sql->exec("UPDATE conectores SET estado = 'sin_servicio' WHERE id = $conector");
$r = $A->pedir('POST', '/api/pagos.php', ['punto_carga_id' => $idPrivado, 'vehiculo_id' => $idVeh, 'conector_id' => $conector, 'soc_pct' => 20]);
caso('pagos', 'Conector fuera de servicio no se cobra (409)', $r['codigo'] === 409, "codigo={$r['codigo']}");
$sql->exec("UPDATE conectores SET estado = 'disponible' WHERE id = $conector");

/* 40,4 kWh a 22 kW con rendimiento 0,92 son 119,76 minutos: el servidor dejaba
   «1 h 60 min» al redondear solo la fraccion; tiene que dar «2 h». */
$rv40 = $A->pedir('POST', '/api/vehiculos.php', ['marca' => 'Nissan', 'modelo' => 'Leaf', 'capacidad_kwh' => 40.4, 'tipo_conector' => 'Type 2']);
$r = $A->pedir('POST', '/api/pagos.php', ['punto_carga_id' => $idPrivado, 'vehiculo_id' => $rv40['cuerpo']['data']['id'] ?? 0, 'conector_id' => $conector, 'soc_pct' => 0]);
caso('pagos', 'El tiempo nunca sale como "60 min": 119,76 minutos dan 2 h', ($r['cuerpo']['data']['tiempo'] ?? '') === '2 h', 'tiempo=' . ($r['cuerpo']['data']['tiempo'] ?? '?'));

/* ═══════════════════════════════ CALIFICACIONES ═══════════════════════════ */
grupo('CALIFICACIONES: SOLO SOBRE CARGAS PROPIAS');

$r = $A->pedir('POST', '/api/calificaciones.php', ['para_usuario_id' => $idB, 'transaccion_id' => $idTx, 'puntos' => 5, 'comentario' => 'Todo bien']);
caso('calificaciones', 'Calificacion legitima aceptada', ($r['cuerpo']['ok'] ?? false) === true, json_encode($r['cuerpo']));

$r = $A->pedir('POST', '/api/calificaciones.php', ['para_usuario_id' => $idB, 'transaccion_id' => $idTx, 'puntos' => 1]);
caso('calificaciones', 'La misma transaccion no se califica dos veces', ($r['cuerpo']['ok'] ?? true) === false, $r['cuerpo']['error'] ?? '');

$r = $A->pedir('POST', '/api/calificaciones.php', ['para_usuario_id' => $idADM, 'transaccion_id' => $idTx, 'puntos' => 1]);
caso('calificaciones', 'Destinatario que no es el propietario rechazado', ($r['cuerpo']['ok'] ?? true) === false, $r['cuerpo']['error'] ?? '');

$r = $A->pedir('POST', '/api/calificaciones.php', ['para_usuario_id' => $idB, 'transaccion_id' => 999999, 'puntos' => 1]);
caso('calificaciones', 'Transaccion inexistente rechazada', ($r['cuerpo']['ok'] ?? true) === false);

$r = $B->pedir('POST', '/api/calificaciones.php', ['para_usuario_id' => $idADM, 'transaccion_id' => $idTx, 'puntos' => 1]);
caso('calificaciones', 'Transaccion ajena rechazada', ($r['cuerpo']['ok'] ?? true) === false, $r['cuerpo']['error'] ?? '');

$r = $A->pedir('POST', '/api/calificaciones.php', ['para_usuario_id' => $idB, 'transaccion_id' => $idTx, 'puntos' => 9]);
caso('calificaciones', 'Puntuacion fuera de 1 a 5 rechazada', ($r['cuerpo']['ok'] ?? true) === false);

$r = $ANON->pedir('POST', '/api/calificaciones.php', ['para_usuario_id' => $idB, 'transaccion_id' => $idTx, 'puntos' => 1]);
caso('calificaciones', 'Sin sesion no se puede calificar', $r['codigo'] === 401);

/* ═══════════════════════════════ ESTADO Y COLA ════════════════════════════ */
grupo('ESTADO Y COLA EN VIVO');

$r = $A->pedir('PATCH', '/api/estado.php', ['punto_carga_id' => $idCargador, 'estado' => 'ocupado']);
caso('estado', 'Reporte de estado valido', ($r['cuerpo']['ok'] ?? false) === true);

$r = $A->pedir('PATCH', '/api/estado.php', ['punto_carga_id' => $idCargador, 'estado' => 'ocupado']);
caso('estado', 'Repetir el mismo estado sigue siendo valido', ($r['cuerpo']['ok'] ?? false) === true, $r['cuerpo']['error'] ?? '');

$r = $A->pedir('PATCH', '/api/estado.php', ['punto_carga_id' => 999999, 'estado' => 'ocupado']);
caso('estado', 'Cargador inexistente da error claro', ($r['cuerpo']['ok'] ?? true) === false, $r['cuerpo']['error'] ?? '');

$r = $A->pedir('PATCH', '/api/estado.php', ['punto_carga_id' => $idCargador, 'estado' => 'inventado']);
caso('estado', 'Estado no permitido rechazado', ($r['cuerpo']['ok'] ?? true) === false);

$r = $A->pedir('PATCH', '/api/cola.php', ['punto_carga_id' => $idCargador, 'cola' => 2]);
caso('cola', 'Reporte de cola valido', ($r['cuerpo']['ok'] ?? false) === true);

$r = $A->pedir('PATCH', '/api/cola.php', ['punto_carga_id' => 999999, 'cola' => 2]);
caso('cola', 'Cargador inexistente da error claro', ($r['cuerpo']['ok'] ?? true) === false);

$r = $A->pedir('PATCH', '/api/cola.php', ['punto_carga_id' => $idCargador, 'cola' => 9]);
caso('cola', 'Valor de cola fuera de rango rechazado', ($r['cuerpo']['ok'] ?? true) === false);

/* ════════════════════════════ RESENAS Y REPORTES ══════════════════════════ */
grupo('RESENAS Y REPORTES');

$r = $A->pedir('POST', '/api/resenas.php', ['punto_carga_id' => $idCargador, 'estrellas' => 4, 'texto' => 'Anduvo bien']);
caso('resenas', 'Resena sobre cargador existente aceptada', ($r['cuerpo']['ok'] ?? false) === true, json_encode($r['cuerpo']));

$r = $A->pedir('POST', '/api/resenas.php', ['punto_carga_id' => 999999, 'estrellas' => 4, 'texto' => 'Fantasma']);
caso('resenas', 'Resena sobre cargador inexistente rechazada', ($r['cuerpo']['ok'] ?? true) === false, $r['cuerpo']['error'] ?? '');

$r = $A->pedir('POST', '/api/reportes.php', ['punto_carga_id' => $idCargador, 'tipo' => 'fuera_servicio', 'descripcion' => 'No carga']);
caso('reportes', 'Reporte sobre cargador existente aceptado', ($r['cuerpo']['ok'] ?? false) === true, json_encode($r['cuerpo']));

$r = $A->pedir('POST', '/api/reportes.php', ['punto_carga_id' => 999999, 'tipo' => 'fuera_servicio']);
caso('reportes', 'Reporte sobre cargador inexistente rechazado', ($r['cuerpo']['ok'] ?? true) === false);

/* ═════════════════════════════ OCULTAR (HU-05) ════════════════════════════ */
grupo('OCULTAR CARGADOR DEL MAPA PUBLICO (HU-05)');

$r = $A->pedir('PATCH', '/api/cargadores.php', ['id' => $idCargador]);
caso('ocultar', 'Un usuario comun no puede ocultar', $r['codigo'] === 403, 'HTTP ' . $r['codigo']);

$r = $ANON->pedir('PATCH', '/api/cargadores.php', ['id' => $idCargador]);
caso('ocultar', 'Sin sesion no se puede ocultar', $r['codigo'] === 401, 'HTTP ' . $r['codigo']);

$r = $ADM->pedir('PATCH', '/api/cargadores.php', ['id' => $idCargador]);
caso('ocultar', 'El administrador puede ocultar', ($r['cuerpo']['ok'] ?? false) === true, json_encode($r['cuerpo']));

$activo = $sql->query("SELECT activo FROM puntos_carga WHERE id = $idCargador")->fetch()['activo'];
caso('ocultar', 'Queda con activo igual a cero en la base', (int)$activo === 0, "activo=$activo");

$lista = json_decode(file_get_contents(BASE . '/api/estaciones.php'), true);
caso('ocultar', 'Desaparece del listado publico del mapa', !in_array($idCargador, array_column($lista['data'] ?? [], 'id')));

$r = $A->pedir('POST', '/api/pagos.php', ['punto_carga_id' => $idCargador, 'vehiculo_id' => $idVeh, 'conector_id' => $conector, 'soc_pct' => 20]);
caso('ocultar', 'Oculto, ya no puede pagarse', ($r['cuerpo']['ok'] ?? true) === false, $r['cuerpo']['error'] ?? '');

$r = $A->pedir('POST', '/api/resenas.php', ['punto_carga_id' => $idCargador, 'estrellas' => 5, 'texto' => 'x']);
caso('ocultar', 'Oculto, ya no puede resenarse', ($r['cuerpo']['ok'] ?? true) === false);

$r = $A->pedir('POST', '/api/reportes.php', ['punto_carga_id' => $idCargador, 'tipo' => 'fuera_servicio']);
caso('ocultar', 'Oculto, ya no puede reportarse', ($r['cuerpo']['ok'] ?? true) === false);

$r = $A->pedir('PATCH', '/api/estado.php', ['punto_carga_id' => $idCargador, 'estado' => 'disponible']);
caso('ocultar', 'Oculto, ya no admite reporte de estado', ($r['cuerpo']['ok'] ?? true) === false);

$r = $ADM->pedir('PATCH', '/api/cargadores.php', ['id' => $idCargador]);
caso('ocultar', 'Ocultar dos veces informa que ya estaba oculto', ($r['cuerpo']['ok'] ?? true) === false, $r['cuerpo']['error'] ?? '');

/* ═════════════════════════ MODERADOR (HU-04 a HU-06) ══════════════════════ */
grupo('ROL MODERADOR (HU-04 A HU-06)');

/* Se arma igual que el administrador: alta por la API y rol por SQL. */
$rm = $MOD->pedir('POST', '/api/registro.php', ['acepta' => true, 'nombre' => 'Moderador', 'email' => "mod$sufijo@test.com",
    'password' => 'contrasena1', 'rol' => 'particular', 'ci' => $CI_MOD]);
$idMOD = $rm['cuerpo']['data']['id'] ?? 0;
$sql->prepare('UPDATE usuarios SET rol_id = 4 WHERE id = ?')->execute([$idMOD]);
$MOD->pedir('POST', '/api/login.php', ['email' => "mod$sufijo@test.com", 'password' => 'contrasena1']);

/* Material para moderar: un cargador visible con una reseña y un reporte. */
$idVis = $B->pedir('POST', '/api/cargadores.php', $base)['cuerpo']['data']['id'] ?? 0;
$idRes = $A->pedir('POST', '/api/resenas.php', ['punto_carga_id' => $idVis, 'estrellas' => 4, 'texto' => 'Bien'])['cuerpo']['data']['id'] ?? 0;
$idRep = $A->pedir('POST', '/api/reportes.php', ['punto_carga_id' => $idVis, 'tipo' => 'fuera_servicio'])['cuerpo']['data']['id'] ?? 0;

/* Lo que el moderador puede hacer */
$r = $MOD->pedir('GET', '/api/resenas.php');
caso('moderador', 'Lista las resenas pendientes', ($r['cuerpo']['ok'] ?? false) === true, 'HTTP ' . $r['codigo']);

$r = $MOD->pedir('PATCH', '/api/resenas.php', ['id' => $idRes, 'estado' => 'aprobada']);
caso('moderador', 'Aprueba una resena', ($r['cuerpo']['ok'] ?? false) === true, json_encode($r['cuerpo']));

$r = $MOD->pedir('PATCH', '/api/reportes.php', ['id' => $idRep]);
caso('moderador', 'Resuelve un reporte', ($r['cuerpo']['ok'] ?? false) === true, json_encode($r['cuerpo']));

$r = $MOD->pedir('PATCH', '/api/cargadores.php', ['id' => $idVis]);
caso('moderador', 'Oculta un cargador del mapa', ($r['cuerpo']['ok'] ?? false) === true, json_encode($r['cuerpo']));

$r = $MOD->pedir('PATCH', '/api/usuarios.php', ['id' => $idA, 'activo' => false]);
caso('moderador', 'Suspende una cuenta comun', ($r['cuerpo']['ok'] ?? false) === true, json_encode($r['cuerpo']));
$MOD->pedir('PATCH', '/api/usuarios.php', ['id' => $idA, 'activo' => true]);       // se repone

/* Lo que queda reservado al administrador */
$r = $MOD->pedir('PATCH', '/api/usuarios.php', ['id' => $idA, 'rol_id' => 4]);
caso('moderador', 'No puede reasignar roles', $r['codigo'] === 403, 'HTTP ' . $r['codigo']);

$r = $MOD->pedir('PATCH', '/api/usuarios.php', ['id' => $idADM, 'activo' => false]);
caso('moderador', 'No puede suspender al administrador', $r['codigo'] === 403, 'HTTP ' . $r['codigo']);

$r = $MOD->pedir('GET', '/api/stats.php');
caso('moderador', 'No accede a las estadisticas', $r['codigo'] === 403, 'HTTP ' . $r['codigo']);

$r = $MOD->pedir('POST', '/api/ute-sync.php');
caso('moderador', 'No dispara la sincronizacion con UTE', $r['codigo'] === 403, 'HTTP ' . $r['codigo']);

/* Y el administrador puede otorgar el rol nuevo */
$r = $ADM->pedir('PATCH', '/api/usuarios.php', ['id' => $idA, 'rol_id' => 4]);
caso('moderador', 'El administrador asigna el rol Moderador', ($r['cuerpo']['ok'] ?? false) === true, json_encode($r['cuerpo']));
$ADM->pedir('PATCH', '/api/usuarios.php', ['id' => $idA, 'rol_id' => 2]);            // se repone

/* ═══════════════════ ACTUALIZACION GENERAL: BLOQUES NUEVOS ═══════════════════ */

/* Cada usuario nuevo trae su propio cliente, y con él su propia IP: los límites
   de uso de un caso no consumen el cupo de otro. El rol se fija por SQL antes
   de ingresar, igual que con el administrador y el moderador. */
function nuevoUsuario(string $etiqueta, int $rol = 2): array {
    global $sql, $sufijo;
    static $n = 0;
    $n++;
    $c = new Cliente("n$etiqueta$n");
    $email = "$etiqueta{$n}_$sufijo@test.com";
    $r = $c->pedir('POST', '/api/registro.php', ['acepta' => true, 'nombre' => "Usuario $etiqueta", 'email' => $email,
        'password' => 'contrasena1', 'rol' => 'particular', 'ci' => ciValida(random_int(1000000, 9999999))]);
    $id = $r['cuerpo']['data']['id'] ?? 0;
    if ($rol !== 2) $sql->prepare('UPDATE usuarios SET rol_id = ? WHERE id = ?')->execute([$rol, $id]);
    $c->pedir('POST', '/api/login.php', ['email' => $email, 'password' => 'contrasena1']);
    return ['c' => $c, 'id' => $id, 'email' => $email];
}
function es4xx(array $r): bool { return $r['codigo'] >= 400 && $r['codigo'] < 500; }
function sin500(array $r): bool { return $r['codigo'] < 500; }
function largo(int $n): string { return str_repeat('a', $n); }
function ids(array $r): array { return array_column($r['cuerpo']['data'] ?? [], 'id'); }

/* ═════════════════════════ SESION REVALIDADA EN CADA PETICION ═════════════════ */
grupo('SESION REVALIDADA: SUSPENSION, CAMBIO DE ROL Y CIERRE');

/* Una suspensión rige en la petición siguiente, sin esperar a que la persona salga. */
$S = nuevoUsuario('susp');
$r = $S['c']->pedir('GET', '/api/vehiculos.php');
caso('sesion', 'Usuario activo con sesion abierta accede (200)', $r['codigo'] === 200, "codigo={$r['codigo']}");
$r = $ADM->pedir('PATCH', '/api/usuarios.php', ['id' => $S['id'], 'activo' => false]);
caso('sesion', 'El administrador suspende al usuario', ($r['cuerpo']['ok'] ?? false) === true, json_encode($r['cuerpo']));
$r = $S['c']->pedir('GET', '/api/vehiculos.php');
caso('sesion', 'Suspendido, su sesion abierta da 401 de inmediato', $r['codigo'] === 401, "codigo={$r['codigo']}");
$r = $S['c']->pedir('POST', '/api/login.php', ['email' => $S['email'], 'password' => 'contrasena1']);
caso('sesion', 'Un suspendido no puede iniciar sesion', es4xx($r) && ($r['cuerpo']['ok'] ?? true) === false, "codigo={$r['codigo']}");
$ADM->pedir('PATCH', '/api/usuarios.php', ['id' => $S['id'], 'activo' => true]);
$r = $S['c']->pedir('POST', '/api/login.php', ['email' => $S['email'], 'password' => 'contrasena1']);
caso('sesion', 'Repuesto, el usuario vuelve a ingresar (200)', $r['codigo'] === 200, "codigo={$r['codigo']}");

/* El rol vigente sale de la base, no del momento del ingreso. */
$R = nuevoUsuario('rol');
$r = $R['c']->pedir('GET', '/api/usuarios.php');
caso('sesion', 'Antes del cambio de rol, un usuario comun no lista usuarios (403)', $r['codigo'] === 403, "codigo={$r['codigo']}");
$ADM->pedir('PATCH', '/api/usuarios.php', ['id' => $R['id'], 'rol_id' => 4]);
$r = $R['c']->pedir('GET', '/api/usuarios.php');
caso('sesion', 'El rol nuevo rige en la peticion siguiente, sin reingresar (200)', $r['codigo'] === 200, "codigo={$r['codigo']}");
$ADM->pedir('PATCH', '/api/usuarios.php', ['id' => $R['id'], 'rol_id' => 2]);
$r = $R['c']->pedir('GET', '/api/usuarios.php');
caso('sesion', 'Al quitarle el rol, el permiso se pierde en la peticion siguiente (403)', $r['codigo'] === 403, "codigo={$r['codigo']}");

/* Cerrar sesión modifica estado: solo por POST. */
$L = nuevoUsuario('salida');
$r = $L['c']->pedir('GET', '/api/logout.php');
caso('sesion', 'El cierre de sesion por GET da 405', $r['codigo'] === 405, "codigo={$r['codigo']}");
$r = $L['c']->pedir('GET', '/api/vehiculos.php');
caso('sesion', 'Tras el GET rechazado la sesion sigue abierta', $r['codigo'] === 200, "codigo={$r['codigo']}");
$r = $L['c']->pedir('POST', '/api/logout.php');
caso('sesion', 'El cierre de sesion por POST responde 200', $r['codigo'] === 200 && ($r['cuerpo']['ok'] ?? false) === true, "codigo={$r['codigo']}");
$r = $L['c']->pedir('GET', '/api/vehiculos.php');
caso('sesion', 'Tras cerrar sesion el acceso da 401', $r['codigo'] === 401, "codigo={$r['codigo']}");

/* ═══════════════════════════════ PERMISOS POR ROL ═════════════════════════════ */
grupo('PERMISOS POR ROL Y AUTOMODIFICACION');

$C = nuevoUsuario('comun');
foreach ([
    ['Usuario comun no lista usuarios',                  'GET',   '/api/usuarios.php', null],
    ['Usuario comun no accede a las estadisticas',       'GET',   '/api/stats.php', null],
    ['Usuario comun no dispara la sincronizacion UTE',   'POST',  '/api/ute-sync.php', []],
    ['Usuario comun no modera resenas',                  'PATCH', '/api/resenas.php', ['id' => $idRes, 'estado' => 'aprobada']],
    ['Usuario comun no resuelve reportes',               'PATCH', '/api/reportes.php', ['id' => $idRep]],
    ['Usuario comun no cambia roles ni suspende',        'PATCH', '/api/usuarios.php', ['id' => $idB, 'activo' => false]],
] as [$nombre, $m, $ruta, $cuerpo]) {
    $r = $C['c']->pedir($m, $ruta, $cuerpo);
    caso('permisos', "$nombre (403)", $r['codigo'] === 403, "codigo={$r['codigo']}");
}
$r = $ANON->pedir('GET', '/api/stats.php');
caso('permisos', 'Sin sesion las estadisticas dan 401', $r['codigo'] === 401, "codigo={$r['codigo']}");
$r = $ADM->pedir('GET', '/api/stats.php');
caso('permisos', 'El administrador accede a las estadisticas (200)', $r['codigo'] === 200, "codigo={$r['codigo']}");

$r = $ADM->pedir('PATCH', '/api/usuarios.php', ['id' => $idADM, 'rol_id' => 2]);
caso('permisos', 'Nadie se cambia el propio rol (409)', $r['codigo'] === 409, "codigo={$r['codigo']}");
$r = $ADM->pedir('PATCH', '/api/usuarios.php', ['id' => $idADM, 'activo' => false]);
caso('permisos', 'El administrador no se desactiva a si mismo (409)', $r['codigo'] === 409, "codigo={$r['codigo']}");
$r = $MOD->pedir('PATCH', '/api/usuarios.php', ['id' => $idMOD, 'activo' => false]);
caso('permisos', 'El moderador no se desactiva a si mismo (409)', $r['codigo'] === 409, "codigo={$r['codigo']}");
$q = $sql->prepare('SELECT rol_id, activo FROM usuarios WHERE id = ?');
$q->execute([$idADM]);
$fila = $q->fetch();
caso('permisos', 'La cuenta del administrador queda intacta', (int)$fila['rol_id'] === 1 && (int)$fila['activo'] === 1);
$r = $ADM->pedir('GET', '/api/stats.php');
caso('permisos', 'El administrador sigue pudiendo operar tras los intentos', $r['codigo'] === 200, "codigo={$r['codigo']}");

/* ══════════════════════ REGLA DE UTE Y CARGADORES AJENOS ═════════════════════ */
grupo('REGLA DE UTE: NO SE RESERVA NI SE PAGA');

/* Propietario nuevo: un cargador publico y otro privado de particular. */
$P = nuevoUsuario('prop');
$idPubP  = $P['c']->pedir('POST', '/api/cargadores.php', array_merge($base, ['nombre' => "Publico P $sufijo"]))['cuerpo']['data']['id'] ?? 0;
$idPrivP = $P['c']->pedir('POST', '/api/cargadores.php', array_merge($base, ['nombre' => "Privado P $sufijo", 'acceso' => 'privado']))['cuerpo']['data']['id'] ?? 0;

/* Estaciones de UTE puestas por SQL, como las dejaria la sincronizacion. Una es
   publica y otra privada: la segunda aisla la fuente del tipo de acceso. */
$tipo2 = $sql->query("SELECT id FROM tipos_conector WHERE nombre = 'Type 2'")->fetchColumn();
$altaUte = function (string $acceso) use ($sql, $tipo2, $sufijo): array {
    $sql->prepare("INSERT INTO puntos_carga (nombre, direccion, lat, lng, acceso, estado, fuente, activo) VALUES (?, 'Dir UTE', -34.9, -56.1, ?, 'disponible', 'ute', 1)")
        ->execute(["UTE $acceso $sufijo", $acceso]);
    $id = (int)$sql->lastInsertId();
    $sql->prepare('INSERT INTO conectores (punto_carga_id, tipo_conector_id, potencia_kw) VALUES (?, ?, 22)')->execute([$id, $tipo2]);
    return [$id, (int)$sql->query("SELECT id FROM conectores WHERE punto_carga_id = $id")->fetchColumn()];
};
[$idUteP, $conUteP] = $altaUte('publico');
[$idUteR, $conUteR] = $altaUte('privado');
$conPubP  = $sql->query("SELECT id FROM conectores WHERE punto_carga_id = $idPubP LIMIT 1")->fetchColumn();
$conPrivP = $sql->query("SELECT id FROM conectores WHERE punto_carga_id = $idPrivP LIMIT 1")->fetchColumn();

foreach ([
    ['Estacion de UTE publica',                              $idUteP, $conUteP],
    ['Estacion de UTE aunque figure como privada',           $idUteR, $conUteR],
    ['Cargador publico de un particular',                    $idPubP, $conPubP],
] as [$nombre, $pc, $con]) {
    $r = $A->pedir('POST', '/api/pagos.php', ['punto_carga_id' => $pc, 'vehiculo_id' => $idVeh, 'conector_id' => $con, 'soc_pct' => 20]);
    caso('ute', "$nombre no admite pago (403)", $r['codigo'] === 403 && ($r['cuerpo']['ok'] ?? true) === false, "codigo={$r['codigo']}");
}
$r = $A->pedir('POST', '/api/pagos.php', ['punto_carga_id' => $idPrivP, 'vehiculo_id' => $idVeh, 'conector_id' => $conPrivP, 'soc_pct' => 20]);
caso('ute', 'Control: el cargador privado de un particular si se paga (200)', $r['codigo'] === 200 && ($r['cuerpo']['ok'] ?? false) === true, "codigo={$r['codigo']}");
$cuenta = $sql->query("SELECT COUNT(*) FROM transacciones WHERE punto_carga_id IN ($idUteP, $idUteR, $idPubP)")->fetchColumn();
caso('ute', 'Los pagos rechazados no dejan transacciones', (int)$cuenta === 0, "filas=$cuenta");

/* El estado de una estacion de UTE solo lo cambia un reporte de la comunidad.
   La parte de la sincronizacion (que no pise ese estado) necesita llamar a la
   API de UTE y no se puede ejercitar sin red: queda sin caso a proposito. */
$r = $C['c']->pedir('PATCH', '/api/estado.php', ['punto_carga_id' => $idUteP, 'estado' => 'ocupado']);
$est = $sql->query("SELECT estado FROM puntos_carga WHERE id = $idUteP")->fetchColumn();
caso('ute', 'La comunidad cambia el estado de una estacion de UTE', $r['codigo'] === 200 && $est === 'ocupado', "codigo={$r['codigo']} estado=$est");

/* ═══════════════════════ ESTADO Y COLA DE CARGADORES PRIVADOS ═════════════════ */
grupo('ESTADO Y COLA: QUIEN PUEDE CAMBIARLOS');

$estado = fn(int $id) => $sql->query("SELECT estado FROM puntos_carga WHERE id = $id")->fetchColumn();
$colaDe = fn(int $id) => (int)$sql->query("SELECT cola FROM puntos_carga WHERE id = $id")->fetchColumn();

$r = $C['c']->pedir('PATCH', '/api/estado.php', ['punto_carga_id' => $idPrivP, 'estado' => 'sin_servicio']);
caso('estado', 'Un tercero no cambia el estado de un cargador privado ajeno (403)', $r['codigo'] === 403 && $estado($idPrivP) === 'disponible', "codigo={$r['codigo']}");
$r = $C['c']->pedir('PATCH', '/api/cola.php', ['punto_carga_id' => $idPrivP, 'cola' => 3]);
caso('cola', 'Un tercero no cambia la cola de un cargador privado ajeno (403)', $r['codigo'] === 403 && $colaDe($idPrivP) === 0, "codigo={$r['codigo']}");
$r = $ANON->pedir('PATCH', '/api/estado.php', ['punto_carga_id' => $idPrivP, 'estado' => 'ocupado']);
caso('estado', 'Sin sesion el estado no cambia (401)', $r['codigo'] === 401, "codigo={$r['codigo']}");

$r = $P['c']->pedir('PATCH', '/api/estado.php', ['punto_carga_id' => $idPrivP, 'estado' => 'ocupado']);
caso('estado', 'El propietario cambia el estado de su cargador privado', $r['codigo'] === 200 && $estado($idPrivP) === 'ocupado', "codigo={$r['codigo']}");
$r = $P['c']->pedir('PATCH', '/api/cola.php', ['punto_carga_id' => $idPrivP, 'cola' => 2]);
caso('cola', 'El propietario cambia la cola de su cargador privado', $r['codigo'] === 200 && $colaDe($idPrivP) === 2, "codigo={$r['codigo']}");
$r = $MOD->pedir('PATCH', '/api/estado.php', ['punto_carga_id' => $idPrivP, 'estado' => 'disponible']);
caso('estado', 'El moderador cambia el estado de un cargador privado', $r['codigo'] === 200 && $estado($idPrivP) === 'disponible', "codigo={$r['codigo']}");
$r = $MOD->pedir('PATCH', '/api/cola.php', ['punto_carga_id' => $idPrivP, 'cola' => 1]);
caso('cola', 'El moderador cambia la cola de un cargador privado', $r['codigo'] === 200 && $colaDe($idPrivP) === 1, "codigo={$r['codigo']}");

$r = $C['c']->pedir('PATCH', '/api/estado.php', ['punto_carga_id' => $idPubP, 'estado' => 'ocupado']);
caso('estado', 'En un cargador publico la comunidad si cambia el estado', $r['codigo'] === 200 && $estado($idPubP) === 'ocupado', "codigo={$r['codigo']}");
$r = $C['c']->pedir('PATCH', '/api/cola.php', ['punto_carga_id' => $idPubP, 'cola' => 3]);
caso('cola', 'En un cargador publico la comunidad si cambia la cola', $r['codigo'] === 200 && $colaDe($idPubP) === 3, "codigo={$r['codigo']}");

/* ══════════════════════════════════ VEHICULOS ════════════════════════════════ */
grupo('VEHICULOS: VALIDACION, PROPIEDAD Y TOPE');

$V = nuevoUsuario('veh');
$vehiculo = ['marca' => 'Tesla', 'modelo' => 'Model 3', 'capacidad_kwh' => 60, 'tipo_conector' => 'Type 2'];
$r = $V['c']->pedir('POST', '/api/vehiculos.php', $vehiculo);
caso('vehiculos', 'Alta valida de vehiculo', $r['codigo'] === 200 && ($r['cuerpo']['ok'] ?? false) === true, json_encode($r['cuerpo']));
$idV = $r['cuerpo']['data']['id'] ?? 0;

foreach ([
    ['Capacidad cero rechazada',              ['capacidad_kwh' => 0]],
    ['Capacidad negativa rechazada',          ['capacidad_kwh' => -40]],
    ['Capacidad no numerica rechazada',       ['capacidad_kwh' => 'grande']],
    ['Capacidad desmedida rechazada',         ['capacidad_kwh' => 99999]],
    ['Tipo de conector inexistente rechazado', ['tipo_conector' => 'Inventado']],
    ['Marca de mas de 40 caracteres',         ['marca' => largo(41)]],
    ['Modelo de mas de 40 caracteres',        ['modelo' => largo(41)]],
] as [$nombre, $cambio]) {
    $r = $V['c']->pedir('POST', '/api/vehiculos.php', array_merge($vehiculo, $cambio));
    caso('vehiculos', "$nombre (400 o 422)", in_array($r['codigo'], [400, 422], true) && ($r['cuerpo']['ok'] ?? true) === false, "codigo={$r['codigo']}");
}

$W = nuevoUsuario('veh');
$r = $W['c']->pedir('DELETE', "/api/vehiculos.php?id=$idV");
$activo = $sql->query("SELECT activo FROM vehiculos WHERE id = $idV")->fetchColumn();
caso('vehiculos', 'No se da de baja un vehiculo ajeno (403 o 404)', in_array($r['codigo'], [403, 404], true) && (int)$activo === 1, "codigo={$r['codigo']}");
$r = $V['c']->pedir('DELETE', "/api/vehiculos.php?id=$idV");
caso('vehiculos', 'La baja del vehiculo propio funciona', $r['codigo'] === 200, "codigo={$r['codigo']}");

/* El tope de diez activos se prepara por SQL para no gastar el cupo diario de altas. */
$tope = nuevoUsuario('tope');
$ins = $sql->prepare('INSERT INTO vehiculos (usuario_id, marca, modelo, capacidad_bateria_kwh, tipo_conector_id) VALUES (?, ?, ?, 50, ?)');
for ($i = 1; $i <= 10; $i++) $ins->execute([$tope['id'], 'Marca', "Modelo $i", $tipo2]);
$r = $tope['c']->pedir('POST', '/api/vehiculos.php', $vehiculo);
caso('vehiculos', 'El undecimo vehiculo activo es rechazado (409)', $r['codigo'] === 409, "codigo={$r['codigo']}");
$unoActivo = $tope['c']->pedir('GET', '/api/vehiculos.php')['cuerpo']['data'][0]['id'] ?? 0;
$tope['c']->pedir('DELETE', "/api/vehiculos.php?id=$unoActivo");
$r = $tope['c']->pedir('POST', '/api/vehiculos.php', $vehiculo);
caso('vehiculos', 'Dando de baja uno, vuelve a poder agregar (200)', $r['codigo'] === 200, "codigo={$r['codigo']}");

/* ══════════════════════════════════ RESERVAS ═════════════════════════════════ */
grupo('HISTORIAL DE RESERVAS: SOLO LAS PROPIAS');

$r = $A->pedir('GET', '/api/reservas.php');
caso('reservas', 'El usuario ve su propia transaccion', $r['codigo'] === 200 && in_array($idTx, ids($r)), "codigo={$r['codigo']}");
$r = $B->pedir('GET', '/api/reservas.php');
caso('reservas', 'Otro usuario no ve transacciones ajenas', $r['codigo'] === 200 && !in_array($idTx, ids($r)));
$r = $ANON->pedir('GET', '/api/reservas.php');
caso('reservas', 'Sin sesion el historial da 401', $r['codigo'] === 401, "codigo={$r['codigo']}");
$r = $B->pedir('PATCH', '/api/reservas.php', ['id' => $idTx]);
caso('reservas', 'Marcar una transaccion ajena da 404', $r['codigo'] === 404, "codigo={$r['codigo']}");
$r = $A->pedir('PATCH', '/api/reservas.php', ['id' => 999999999]);
caso('reservas', 'Marcar una transaccion inexistente da 404', $r['codigo'] === 404, "codigo={$r['codigo']}");
$r = $A->pedir('PATCH', '/api/reservas.php', ['id' => $idTx]);
caso('reservas', 'Marcar una transaccion propia funciona (200)', $r['codigo'] === 200 && ($r['cuerpo']['ok'] ?? false) === true, "codigo={$r['codigo']}");

/* ═══════════════════════════════ LIMITES DE USO ══════════════════════════════ */
grupo('LIMITES DE USO (429)');

/* Cada caso usa un cliente con IP propia o usuarios nuevos. Los intentos
   invalidos no cuentan: solo las operaciones que llegan a escribir. */
$LR = new Cliente('limreg');
$codigos = [];
for ($i = 1; $i <= 11; $i++)
    $codigos[] = $LR->pedir('POST', '/api/registro.php', ['acepta' => true, 'nombre' => "Lim $i", 'email' => "lim{$i}_$sufijo@test.com",
        'password' => 'contrasena1', 'rol' => 'particular', 'ci' => ciValida(random_int(1000000, 9999999))])['codigo'];
caso('limites', 'Diez registros desde una IP en una hora son aceptados', count(array_filter(array_slice($codigos, 0, 10), fn($c) => $c === 200)) === 10, implode(',', $codigos));
caso('limites', 'El undecimo registro desde la misma IP da 429', $codigos[10] === 429, 'codigo=' . $codigos[10]);

/* Reseñas, reportes y altas: se llega al tope con un usuario nuevo sobre el cargador publico de P. */
$u = nuevoUsuario('limres');
$codigos = [];
for ($i = 1; $i <= 6; $i++) $codigos[] = $u['c']->pedir('POST', '/api/resenas.php', ['punto_carga_id' => $idPubP, 'estrellas' => 4, 'texto' => "Resena $i"])['codigo'];
caso('limites', 'Cinco resenas por hora son aceptadas', $codigos[4] === 200 && !in_array(429, array_slice($codigos, 0, 5), true), implode(',', $codigos));
caso('limites', 'La sexta resena en una hora da 429', $codigos[5] === 429, 'codigo=' . $codigos[5]);

$u = nuevoUsuario('limrep');
$codigos = [];
for ($i = 1; $i <= 11; $i++) $codigos[] = $u['c']->pedir('POST', '/api/reportes.php', ['punto_carga_id' => $idPubP, 'tipo' => 'fuera_servicio', 'descripcion' => "Reporte $i"])['codigo'];
caso('limites', 'Diez reportes por hora son aceptados', $codigos[9] === 200 && !in_array(429, array_slice($codigos, 0, 10), true), implode(',', $codigos));
caso('limites', 'El reporte numero once en una hora da 429', $codigos[10] === 429, 'codigo=' . $codigos[10]);

$u = nuevoUsuario('limcar');
$codigos = [];
for ($i = 1; $i <= 6; $i++) $codigos[] = $u['c']->pedir('POST', '/api/cargadores.php', array_merge($base, ['nombre' => "Limite $i"]))['codigo'];
caso('limites', 'Cinco altas de cargador en 24 h son aceptadas', $codigos[4] === 200 && !in_array(429, array_slice($codigos, 0, 5), true), implode(',', $codigos));
caso('limites', 'La sexta alta de cargador en 24 h da 429', $codigos[5] === 429, 'codigo=' . $codigos[5]);

/* ═══════════════════════════════ VALIDACION DE ENTRADA ════════════════════════ */
grupo('VALIDACION: TOPES, RANGOS Y ENTRADAS HOSTILES');

$H = nuevoUsuario('hostil');
$TR = new Cliente('hostilreg');
$cuerpoReg = ['acepta' => true, 'nombre' => 'Hostil', 'email' => "hostil_$sufijo@test.com", 'password' => 'contrasena1', 'rol' => 'particular', 'ci' => ciValida(random_int(1000000, 9999999))];

/* Longitudes máximas: pasarse es 422 y no escribe nada. */
foreach ([
    ['nombre de mas de 120',        ['nombre' => largo(121)]],
    ['direccion de mas de 200',     ['direccion' => largo(201)]],
    ['ciudad de mas de 80',         ['ciudad' => largo(81)]],
    ['departamento de mas de 80',   ['departamento' => largo(81)]],
    ['horario de mas de 60',        ['horario' => largo(61)]],
    ['descripcion de mas de 1000',  ['descripcion' => largo(1001)]],
    ['costo_kwh de 10000',          ['costo_kwh' => 10000]],
    ['costo_kwh negativo',          ['costo_kwh' => -0.01]],
] as [$nombre, $cambio]) {
    $r = $H['c']->pedir('POST', '/api/cargadores.php', array_merge($base, $cambio));
    caso('validacion', "Cargador con $nombre da 422", $r['codigo'] === 422, "codigo={$r['codigo']}");
}
$r = $H['c']->pedir('POST', '/api/cargadores.php', array_merge($base, ['costo_kwh' => 9999.99]));
caso('validacion', 'costo_kwh de 9999,99 (el maximo de la columna) es aceptado', $r['codigo'] === 200, "codigo={$r['codigo']}");

$r = $H['c']->pedir('POST', '/api/resenas.php', ['punto_carga_id' => $idPubP, 'estrellas' => 4, 'texto' => largo(1001)]);
caso('validacion', 'Resena de mas de 1000 caracteres da 422', $r['codigo'] === 422, "codigo={$r['codigo']}");
$r = $H['c']->pedir('POST', '/api/reportes.php', ['punto_carga_id' => $idPubP, 'tipo' => 'fuera_servicio', 'descripcion' => largo(1001)]);
caso('validacion', 'Descripcion de reporte de mas de 1000 caracteres da 422', $r['codigo'] === 422, "codigo={$r['codigo']}");

/* Una calificación con comentario largo necesita una transacción propia sin calificar. */
$pago = $A->pedir('POST', '/api/pagos.php', ['punto_carga_id' => $idPrivado, 'vehiculo_id' => $idVeh, 'conector_id' => $conector, 'soc_pct' => 50]);
$tx2 = $pago['cuerpo']['data']['id'] ?? 0;
$r = $A->pedir('POST', '/api/calificaciones.php', ['para_usuario_id' => $idB, 'transaccion_id' => $tx2, 'puntos' => 4, 'comentario' => largo(501)]);
caso('validacion', 'Comentario de calificacion de mas de 500 caracteres da 422', $r['codigo'] === 422, "codigo={$r['codigo']}");
$r = $A->pedir('POST', '/api/calificaciones.php', ['para_usuario_id' => $idB, 'transaccion_id' => $tx2, 'puntos' => 4, 'comentario' => largo(500)]);
caso('validacion', 'Comentario de exactamente 500 caracteres es aceptado', $r['codigo'] === 200, "codigo={$r['codigo']}");

/* Contraseñas: el tope de 72 es de bytes (límite de bcrypt), no de caracteres. */
$r = $TR->pedir('POST', '/api/registro.php', array_merge($cuerpoReg, ['password' => str_repeat('a', 73)]));
caso('validacion', 'Contrasena de 73 bytes da 422', $r['codigo'] === 422, "codigo={$r['codigo']}");
$r = $TR->pedir('POST', '/api/registro.php', array_merge($cuerpoReg, ['password' => str_repeat('ñ', 37)]));
caso('validacion', 'Contrasena de 37 caracteres y 74 bytes da 422', $r['codigo'] === 422, "codigo={$r['codigo']}");
$r = $TR->pedir('POST', '/api/registro.php', array_merge($cuerpoReg, ['password' => str_repeat('a', 72)]));
caso('validacion', 'Contrasena de 72 bytes es aceptada', $r['codigo'] === 200, "codigo={$r['codigo']}");

/* Entradas hostiles: arreglos u objetos donde va texto. Ninguna puede dar 500;
   donde el dato es obligatorio el rechazo es 4xx. */
$obligatorios = [
    ['registro con nombre arreglo',        $TR, '/api/registro.php',   'POST', array_merge($cuerpoReg, ['nombre' => ['x'], 'email' => "h1_$sufijo@test.com"])],
    ['registro con correo arreglo',        $TR, '/api/registro.php',   'POST', array_merge($cuerpoReg, ['email' => ['x']])],
    ['registro con contrasena arreglo',    $TR, '/api/registro.php',   'POST', array_merge($cuerpoReg, ['password' => ['x'], 'email' => "h2_$sufijo@test.com"])],
    ['registro con documento arreglo',     $TR, '/api/registro.php',   'POST', array_merge($cuerpoReg, ['ci' => ['x'], 'email' => "h3_$sufijo@test.com"])],
    ['login con correo arreglo',           $TR, '/api/login.php',      'POST', ['email' => ['x'], 'password' => 'contrasena1']],
    ['login con contrasena arreglo',       $TR, '/api/login.php',      'POST', ['email' => "hostil_$sufijo@test.com", 'password' => ['x']]],
    ['cargador con nombre arreglo',        $H['c'], '/api/cargadores.php', 'POST', array_merge($base, ['nombre' => ['x']])],
    ['cargador con acceso arreglo',        $H['c'], '/api/cargadores.php', 'POST', array_merge($base, ['acceso' => ['privado']])],
    ['cargador con precio arreglo',        $H['c'], '/api/cargadores.php', 'POST', array_merge($base, ['costo_kwh' => ['1']])],
    ['cargador con latitud arreglo',       $H['c'], '/api/cargadores.php', 'POST', array_merge($base, ['lat' => ['1']])],
    ['cargador con conectores como texto', $H['c'], '/api/cargadores.php', 'POST', array_merge($base, ['conectores' => 'Type 2'])],
    ['cargador con tipo de conector arreglo', $H['c'], '/api/cargadores.php', 'POST', array_merge($base, ['conectores' => [['tipo' => ['x'], 'potencia' => 22]]])],
    ['cargador con potencia arreglo',      $H['c'], '/api/cargadores.php', 'POST', array_merge($base, ['conectores' => [['tipo' => 'Type 2', 'potencia' => [22]]]])],
    ['vehiculo con marca arreglo',         $H['c'], '/api/vehiculos.php',  'POST', array_merge($vehiculo, ['marca' => ['x']])],
    ['vehiculo con tipo de conector arreglo', $H['c'], '/api/vehiculos.php', 'POST', array_merge($vehiculo, ['tipo_conector' => ['x']])],
    ['reporte con tipo arreglo',           $H['c'], '/api/reportes.php',   'POST', ['punto_carga_id' => $idPubP, 'tipo' => ['x']]],
    ['estado con valor arreglo',           $H['c'], '/api/estado.php',     'PATCH', ['punto_carga_id' => $idPubP, 'estado' => ['ocupado']]],
    ['usuarios con activo arreglo',        $ADM, '/api/usuarios.php',      'PATCH', ['id' => $H['id'], 'activo' => []]],
    ['usuarios con activo no booleano',    $ADM, '/api/usuarios.php',      'PATCH', ['id' => $H['id'], 'activo' => 'quizas']],
];
foreach ($obligatorios as [$nombre, $cli, $ruta, $m, $cuerpo]) {
    $r = $cli->pedir($m, $ruta, $cuerpo);
    caso('validacion', "Hostil, $nombre: 4xx y nunca 500", es4xx($r) && ($r['cuerpo']['ok'] ?? true) === false, "codigo={$r['codigo']}");
}

/* Campos de texto opcionales con basura: puede aceptarse o rechazarse, pero no fallar. */
$opcionales = [
    ['resena con texto arreglo',           $H['c'], '/api/resenas.php',        'POST', ['punto_carga_id' => $idPubP, 'estrellas' => 4, 'texto' => ['x']]],
    ['resena con estrellas arreglo',       $H['c'], '/api/resenas.php',        'POST', ['punto_carga_id' => $idPubP, 'estrellas' => [4], 'texto' => 'x']],
    ['reporte con descripcion arreglo',    $H['c'], '/api/reportes.php',       'POST', ['punto_carga_id' => $idPubP, 'tipo' => 'fuera_servicio', 'descripcion' => ['x']]],
    ['calificacion con comentario arreglo', $A,     '/api/calificaciones.php', 'POST', ['para_usuario_id' => $idB, 'transaccion_id' => $tx2, 'puntos' => 3, 'comentario' => ['x']]],
    ['calificacion con puntos arreglo',    $A,      '/api/calificaciones.php', 'POST', ['para_usuario_id' => $idB, 'transaccion_id' => $tx2, 'puntos' => [3]]],
    ['vehiculo con capacidad arreglo',     $H['c'], '/api/vehiculos.php',      'POST', array_merge($vehiculo, ['capacidad_kwh' => [60]])],
    ['cola con valor arreglo',             $H['c'], '/api/cola.php',           'PATCH', ['punto_carga_id' => $idPubP, 'cola' => [1]]],
    ['punto_carga_id arreglo en estado',   $H['c'], '/api/estado.php',         'PATCH', ['punto_carga_id' => [1], 'estado' => 'ocupado']],
    ['recuperar con correo arreglo',       $TR,     '/api/recuperar.php',      'POST', ['email' => ['x']]],
    ['restablecer con token arreglo',      $TR,     '/api/restablecer.php',    'POST', ['token' => ['x'], 'password' => 'contrasena1']],
    ['restablecer con contrasena arreglo', $TR,     '/api/restablecer.php',    'POST', ['token' => 'abc', 'password' => ['x']]],
    ['cuerpo que es una lista, no un objeto', $H['c'], '/api/resenas.php',     'POST', [1, 2, 3]],
    ['cuerpo vacio en cargadores',         $H['c'], '/api/cargadores.php',     'POST', []],
];
foreach ($opcionales as [$nombre, $cli, $ruta, $m, $cuerpo]) {
    $r = $cli->pedir($m, $ruta, $cuerpo);
    caso('validacion', "Hostil, $nombre: no da 500", sin500($r), "codigo={$r['codigo']}");
}

/* Parámetros de URL hostiles y paginación fuera de rango: se corrigen, no fallan. */
foreach ([
    ['usuarios con pagina negativa y por_pagina enorme', $ADM, '/api/usuarios.php?pagina=-5&por_pagina=999999'],
    ['usuarios con pagina no numerica',                  $ADM, '/api/usuarios.php?pagina=abc&por_pagina=xyz'],
    ['usuarios con pagina desmedida',                    $ADM, '/api/usuarios.php?pagina=99999999999999999999'],
    ['usuarios con q arreglo',                           $ADM, '/api/usuarios.php?q[]=x'],
    ['usuarios con rol arreglo',                         $ADM, '/api/usuarios.php?rol[]=1'],
    ['resenas pendientes con paginacion fuera de rango', $MOD, '/api/resenas.php?pagina=-1&por_pagina=0'],
    ['reportes pendientes con paginacion fuera de rango', $MOD, '/api/reportes.php?pagina=-1&por_pagina=100000'],
] as [$nombre, $cli, $ruta]) {
    $r = $cli->pedir('GET', $ruta);
    caso('paginacion', "$nombre da 200", $r['codigo'] === 200, "codigo={$r['codigo']}");
}
foreach ([
    ['limit negativo y offset negativo',  "/api/resenas.php?punto_carga_id=$idPubP&limit=-1&offset=-5"],
    ['limit y offset no numericos',       "/api/resenas.php?punto_carga_id=$idPubP&limit=abc&offset=xyz"],
    ['offset desmedido',                  "/api/resenas.php?punto_carga_id=$idPubP&offset=99999999999999999999"],
    ['punto_carga_id arreglo',            '/api/resenas.php?punto_carga_id[]=1'],
    ['estacion con id arreglo',           '/api/estacion.php?id[]=1'],
    ['estaciones con parametros basura',  '/api/estaciones.php?limit=-1&offset=-1&q[]=x'],
] as [$nombre, $ruta]) {
    $r = $ANON->pedir('GET', $ruta);
    caso('paginacion', "Publico, $nombre: no da 500", sin500($r), "codigo={$r['codigo']}");
}

/* usuarios.php: activo es booleano estricto; "false" cuenta como falso. */
$X = nuevoUsuario('activo');
$activoDe = fn() => (int)$sql->query("SELECT activo FROM usuarios WHERE id = {$X['id']}")->fetchColumn();
$r = $ADM->pedir('PATCH', '/api/usuarios.php', ['id' => $X['id'], 'activo' => 'false']);
caso('usuarios', 'activo con el texto "false" desactiva la cuenta', $r['codigo'] === 200 && $activoDe() === 0, "codigo={$r['codigo']}");
$r = $ADM->pedir('PATCH', '/api/usuarios.php', ['id' => $X['id'], 'activo' => 1]);
caso('usuarios', 'activo igual a 1 reactiva la cuenta', $r['codigo'] === 200 && $activoDe() === 1, "codigo={$r['codigo']}");
$r = $ADM->pedir('PATCH', '/api/usuarios.php', ['id' => $X['id'], 'activo' => 'quizas']);
caso('usuarios', 'activo con un valor que no es booleano da 400 y no cambia nada', $r['codigo'] === 400 && $activoDe() === 1, "codigo={$r['codigo']}");

resumen();
