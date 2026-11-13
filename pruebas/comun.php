<?php
/* Utilidades compartidas por la batería de pruebas de integración. */

define('BASE', getenv('EFIND_TEST_URL') ?: 'http://127.0.0.1:8011');

/* Cada cliente se presenta con su propia IP (cabecera CF-Connecting-IP, que el
   servidor respeta cuando la conexión viene del propio equipo). Los dos octetos
   centrales se sortean una vez por corrida y el último cuenta instancias: así
   los límites de uso no se acumulan entre clientes ni entre corridas. */
class Cliente {
    private string $galleta;
    private string $ip;
    private static string $corrida = '';
    private static int $n = 0;

    public function __construct(string $nombre, ?string $ip = null) {
        $this->galleta = sys_get_temp_dir() . "/efind_$nombre.txt";
        @unlink($this->galleta);
        self::$corrida = self::$corrida ?: random_int(1, 254) . '.' . random_int(1, 254);
        $this->ip = $ip ?? '10.' . self::$corrida . '.' . (++self::$n % 254 + 1);
    }

    public function ip(): string { return $this->ip; }

    public function pedir(string $metodo, string $ruta, ?array $datos = null): array {
        $ch = curl_init(BASE . $ruta);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $metodo,
            CURLOPT_COOKIEJAR      => $this->galleta,
            CURLOPT_COOKIEFILE     => $this->galleta,
            CURLOPT_HEADER         => true,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $cabeceras = ['CF-Connecting-IP: ' . $this->ip];
        if ($datos !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($datos));
            $cabeceras[] = 'Content-Type: application/json';
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $cabeceras);
        $bruto  = curl_exec($ch);
        $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $corte  = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        return [
            'codigo'    => $codigo,
            'cuerpo'    => json_decode(substr($bruto, $corte), true) ?? [],
            'cabeceras' => substr($bruto, 0, $corte),
        ];
    }
}

$GLOBALS['total'] = 0;
$GLOBALS['ok'] = 0;
$GLOBALS['fallos'] = [];

function caso(string $g, string $n, bool $cond, string $det = ''): void {
    $GLOBALS['total']++;
    if ($cond) {
        $GLOBALS['ok']++;
        printf("  [OK]    %s\n", $n);
    } else {
        $GLOBALS['fallos'][] = "$g / $n" . ($det ? "  ($det)" : '');
        printf("  [FALLA] %s  %s\n", $n, $det);
    }
}

function grupo(string $t): void {
    printf("\n%s\n%s\n", $t, str_repeat('-', mb_strlen($t)));
}

function resumen(): void {
    printf("\n%s\nRESULTADO: %d de %d casos\n", str_repeat('=', 62), $GLOBALS['ok'], $GLOBALS['total']);
    if ($GLOBALS['fallos']) {
        echo "\nCasos fallidos:\n";
        foreach ($GLOBALS['fallos'] as $f) echo "  - $f\n";
    }
}
