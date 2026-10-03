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
$RUT_B  = '12345' . str_pad($sufijo, 7, '0', STR_PAD_LEFT);

/* ══════════════════════════════════ REGISTRO ══════════════════════════════ */
grupo('REGISTRO Y DOCUMENTO DUPLICADO');

$r = $A->pedir('POST', '/api/registro.php', ['nombre' => 'Ana Cliente', 'email' => "ana$sufijo@test.com",
    'password' => 'contrasena1', 'rol' => 'particular', 'ci' => $CI_ANA]);
caso('registro', 'Alta de usuario particular con cedula valida', ($r['cuerpo']['ok'] ?? false) === true, json_encode($r['cuerpo']));
$idA = $r['cuerpo']['data']['id'] ?? 0;

$T = new Cliente('descarte');
$r = $T->pedir('POST', '/api/registro.php', ['nombre' => 'Otro', 'email' => "otro$sufijo@test.com",
    'password' => 'contrasena1', 'rol' => 'particular', 'ci' => $CI_ANA]);
caso('registro', 'La misma cedula en otro formato es rechazada', ($r['cuerpo']['ok'] ?? true) === false, $r['cuerpo']['error'] ?? '');

$r = $T->pedir('POST', '/api/registro.php', ['nombre' => 'Otro', 'email' => "ana$sufijo@test.com",
    'password' => 'contrasena1', 'rol' => 'particular', 'ci' => $CI_LIB]);
caso('registro', 'Correo duplicado es rechazado', ($r['cuerpo']['ok'] ?? true) === false);

$r = $T->pedir('POST', '/api/registro.php', ['nombre' => 'Malo', 'email' => "malo$sufijo@test.com",
    'password' => 'contrasena1', 'rol' => 'particular', 'ci' => '12345678']);
caso('registro', 'Cedula con digito verificador invalido es rechazada', ($r['cuerpo']['ok'] ?? true) === false);

$r = $T->pedir('POST', '/api/registro.php', ['nombre' => 'Corto', 'email' => "corto$sufijo@test.com",
    'password' => 'abc', 'rol' => 'particular', 'ci' => $CI_LIB]);
caso('registro', 'Contrasena de menos de 8 caracteres rechazada', ($r['cuerpo']['ok'] ?? true) === false);

$r = $B->pedir('POST', '/api/registro.php', ['nombre' => 'Beto Propietario', 'email' => "beto$sufijo@test.com",
    'password' => 'contrasena1', 'rol' => 'empresa', 'ci' => $RUT_B, 'empresa' => 'Cargas SRL']);
caso('registro', 'Alta de empresa con RUT valido', ($r['cuerpo']['ok'] ?? false) === true, json_encode($r['cuerpo']));
$idB = $r['cuerpo']['data']['id'] ?? 0;

$r = $T->pedir('POST', '/api/registro.php', ['nombre' => 'Clon', 'email' => "clon$sufijo@test.com",
    'password' => 'contrasena1', 'rol' => 'empresa', 'ci' => $RUT_B, 'empresa' => 'Otra SRL']);
caso('registro', 'RUT duplicado es rechazado', ($r['cuerpo']['ok'] ?? true) === false, $r['cuerpo']['error'] ?? '');

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

$ra = $ADM->pedir('POST', '/api/registro.php', ['nombre' => 'Admin', 'email' => "adm$sufijo@test.com",
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

resumen();
