/* Pruebas de js/estimador.js sin dependencias: node pruebas/estimador.test.js
   El archivo declara `const ETA` y funciones globales, así que se evalúa dentro
   de una función y se devuelven las que interesan. */
const fs = require('fs');
const path = require('path');

const fuente = fs.readFileSync(path.join(__dirname, '..', 'js', 'estimador.js'), 'utf8');
const { calcularCarga } = new Function(`${fuente}; return { calcularCarga };`)();

let fallos = 0;
function caso(nombre, obtenido, esperado) {
  const ok = obtenido === esperado;
  if (!ok) fallos++;
  console.log(`  [${ok ? 'OK' : 'FALLA'}]${ok ? '   ' : ' '} ${nombre}${ok ? '' : `  (obtenido ${JSON.stringify(obtenido)}, esperado ${JSON.stringify(esperado)})`}`);
}

console.log('\nESTIMADOR DE TIEMPO DE CARGA\n----------------------------');

/* Casos de referencia: los mismos que comprueba el servidor en pagos.php. */
let r = calcularCarga(52, 20, 22);
caso('52 kWh al 20 % con 22 kW: tiempo', r.texto, '2 h 3 min');
caso('52 kWh al 20 % con 22 kW: energía', r.kwh, 41.6);
caso('40,4 kWh al 0 % con 22 kW: nunca "60 min"', calcularCarga(40.4, 0, 22).texto, '2 h');
caso('60 kWh al 32 % con 7,4 kW: hora exacta', calcularCarga(60, 32, 7.4).texto, '6 h');

/* Extremos de estado de carga. */
r = calcularCarga(60, 100, 22);
caso('SOC 100: batería completa', r.texto, 'Batería completa');
caso('SOC 100: sin energía por cargar', r.kwh, 0);
caso('SOC mayor a 100 se acota a 100', calcularCarga(60, 150, 22).texto, 'Batería completa');
caso('SOC negativo se acota a 0', calcularCarga(40.4, -10, 22).texto, '2 h');

/* Sin datos válidos no hay estimación. */
for (const [nombre, args] of [
  ['potencia cero',              [60, 20, 0]],
  ['potencia negativa',          [60, 20, -7]],
  ['capacidad negativa',         [-60, 20, 22]],
  ['capacidad cero',             [0, 20, 22]],
  ['SOC NaN',                    [60, NaN, 22]],
  ['SOC no numérico',            [60, 'abc', 22]],
  ['SOC indefinido',             [60, undefined, 22]],
  ['potencia indefinida',        [60, 20, undefined]],
]) {
  r = calcularCarga(...args);
  caso(`${nombre}: texto "—"`, r.texto, '—');
  caso(`${nombre}: sin energía ni horas`, r.kwh === 0 && r.horas === 0, true);
}

console.log(fallos ? `\n${fallos} caso(s) fallaron.` : '\nTodos los casos pasaron.');
process.exit(fallos ? 1 : 0);
