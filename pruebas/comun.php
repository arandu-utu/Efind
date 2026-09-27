<?php
/* Utilidades compartidas por la batería de pruebas de integración. */

define('BASE', getenv('EFIND_TEST_URL') ?: 'http://127.0.0.1:8011');

class Cliente {
    private string $galleta;

    public function __construct(string $nombre) {
        $this->galleta = sys_get_temp_dir() . "/efind_$nombre.txt";
        @unlink($this->galleta);
    }

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
        if ($datos !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($datos));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }
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
