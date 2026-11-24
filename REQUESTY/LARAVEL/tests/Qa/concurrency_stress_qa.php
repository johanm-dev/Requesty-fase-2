<?php
declare(strict_types=1);

/**
 * Suite de Estres y Concurrencia Real (20 peticiones paralelas simultaneas con curl --parallel).
 * Valida bloqueo ACID de sobrepago desde 2 cajeros, proteccion atomica de stock critico,
 * 0 errores 500 y latencia p95 < 100 ms.
 */

$ports = explode(',', getenv('CONCURRENCY_PORTS') ?: '8101,8102,8103,8104,8105,8106,8107,8108,8109,8110');
$base0 = "http://127.0.0.1:{$ports[0]}";
$rootDir = dirname(__DIR__, 2);

$rlDir = $rootDir . '/storage/app/ratelimit';
if (is_dir($rlDir)) {
    foreach (glob($rlDir . '/*.json') ?: [] as $f) {
        @unlink($f);
    }
}

function assertCond(bool $cond, string $msg): void {
    if (!$cond) {
        fwrite(STDERR, "[FAIL] {$msg}\n");
        exit(1);
    }
}

$isWin = PHP_OS_FAMILY === 'Windows';
$curlBin = $isWin ? 'curl.exe' : 'curl';
$nullDev = $isWin ? 'NUL' : '/dev/null';

function simplePost(string $url, array $body, array $headers = []): array {
    $hdrs = array_merge(['Accept: application/json', 'Content-Type: application/json', 'Connection: close'], $headers);
    $ctx = stream_context_create([
        'http' => [
            'protocol_version' => 1.1,
            'method'           => 'POST',
            'header'           => implode("\r\n", $hdrs),
            'content'          => json_encode($body, JSON_UNESCAPED_UNICODE),
            'ignore_errors'    => true,
            'timeout'          => 5,
        ],
    ]);
    $raw = (string)@file_get_contents($url, false, $ctx);
    $respHdrs = function_exists('http_get_last_response_headers')
        ? (http_get_last_response_headers() ?: [])
        : ($http_response_header ?? []);
    $status = 0;
    if (!empty($respHdrs[0]) && preg_match('/\s(\d{3})\s/', $respHdrs[0], $m)) {
        $status = (int)$m[1];
    }
    return ['status' => $status, 'json' => json_decode($raw, true), 'raw' => $raw];
}

// 1. Login de 2 cajeros distintos (ADMIN #1 y CAJERO #2) y pre-calentamiento de workers
$rAdm = simplePost("{$base0}/api/login", ['correo' => 'admin@restaurante.local', 'contrasena' => 'AdminRest2026*']);
$rCaj = simplePost("{$base0}/api/login", ['correo' => 'cajero@restaurante.local', 'contrasena' => 'CajeroRest2026*']);
assertCond($rAdm['status'] === 200 && $rCaj['status'] === 200, 'Logins de prueba fallaron');
$tokAdmin  = $rAdm['json']['token'];
$tokCajero = $rCaj['json']['token'];

// Pre-calentar pool de conexiones persistentes en workers/threads (equivalente a minIdle)
$warmCfg = sys_get_temp_dir() . '/requesty_curl_warm.cfg';
$warmLines = [];
for ($wi = 0; $wi < 16; $wi++) {
    $wp = $ports[$wi % count($ports)];
    $warmLines[] = 'url = "http://127.0.0.1:' . $wp . '/api/productos"';
    $warmLines[] = 'request = GET';
    $warmLines[] = 'header = "Accept: application/json"';
    $warmLines[] = 'header = "Authorization: Bearer ' . $tokAdmin . '"';
    $warmLines[] = 'output = ' . $nullDev;
    $warmLines[] = 'silent';
    if ($wi < 15) {
        $warmLines[] = 'next';
    }
}
file_put_contents($warmCfg, implode("\n", $warmLines));
exec($curlBin . ' --parallel --parallel-immediate --parallel-max 16 -K ' . escapeshellarg($warmCfg) . ' 2>&1');
@unlink($warmCfg);

// 2. Abrir dos cajas independientes (Cajero A = ADMIN, Cajero B = CAJERO) y crear 1 pedido de 30240.00
$cA = simplePost("{$base0}/api/caja/abrir", ['monto_apertura' => 50000], ["Authorization: Bearer {$tokAdmin}"]);
$cB = simplePost("{$base0}/api/caja/abrir", ['monto_apertura' => 50000], ["Authorization: Bearer {$tokCajero}"]);
assertCond($cA['status'] === 201 && $cB['status'] === 201, 'Apertura de cajas concurrentes fallo');
$idCajaA = (int)$cA['json']['caja']['id_caja'];
$idCajaB = (int)$cB['json']['caja']['id_caja'];

$ped = simplePost("{$base0}/api/pedidos", [
    'id_cliente' => 1,
    'id_mesa'    => 1,
    'items'      => [['id_producto' => 1, 'cantidad' => 1]],
], ["Authorization: Bearer {$tokAdmin}"]);
assertCond($ped['status'] === 201, 'Creacion de pedido para estres fallo');
$idPedido = (int)$ped['json']['pedido']['id_pedido'];
$totalPedido = (float)$ped['json']['pedido']['total'];

// 3. Construir rafaga de 16 peticiones paralelas simultaneas:
//    - 4 cobros paralelos desde 2 cajeros sobre el mismo pedido (1x201, 3x409)
//    - 4 descuentos paralelos de 20u sobre ingrediente #2 (stock 47 -> 2x201, 2x422)
//    - 8 lecturas concurrentes (productos, mesas, caja, blind index cliente)
$specs = [];
for ($i = 0; $i < 4; $i++) {
    $isA = ($i % 2 === 0);
    $specs[] = [
        'tag'    => 'PAY',
        'port'   => $ports[$i % count($ports)],
        'method' => 'POST',
        'path'   => '/api/pagos',
        'token'  => $isA ? $tokAdmin : $tokCajero,
        'body'   => json_encode([
            'id_pedido'   => $idPedido,
            'id_caja'     => $isA ? $idCajaA : $idCajaB,
            'metodo_pago' => 'EFECTIVO',
            'valor'       => $totalPedido,
            'referencia'  => "RACE-{$i}",
        ], JSON_UNESCAPED_UNICODE),
    ];
}
for ($i = 0; $i < 4; $i++) {
    $specs[] = [
        'tag'    => 'INV',
        'port'   => $ports[(4 + $i) % count($ports)],
        'method' => 'POST',
        'path'   => '/api/inventario',
        'token'  => $tokAdmin,
        'body'   => json_encode([
            'id_ingrediente'  => 2,
            'tipo_movimiento' => 'SALIDA',
            'cantidad'        => 20,
            'motivo'          => "Race inventario #{$i}",
        ], JSON_UNESCAPED_UNICODE),
    ];
}
$readPaths = ['/api/productos', '/api/mesas', '/api/caja', '/api/clientes?documento=1098765432', '/api/pedidos'];
for ($i = 0; $i < 8; $i++) {
    $specs[] = [
        'tag'    => 'READ',
        'port'   => $ports[$i % count($ports)],
        'method' => 'GET',
        'path'   => $readPaths[$i % count($readPaths)],
        'token'  => $tokAdmin,
        'body'   => null,
    ];
}

$cfgFile = sys_get_temp_dir() . '/requesty_curl_stress.cfg';
$cfgLines = [];
foreach ($specs as $idx => $s) {
    $cfgLines[] = 'url = "http://127.0.0.1:' . $s['port'] . $s['path'] . '"';
    $cfgLines[] = 'request = ' . $s['method'];
    $cfgLines[] = 'header = "Expect:"';
    $cfgLines[] = 'header = "Accept: application/json"';
    $cfgLines[] = 'header = "Authorization: Bearer ' . $s['token'] . '"';
    if ($s['body'] !== null) {
        $cfgLines[] = 'header = "Content-Type: application/json"';
        $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $s['body']);
        $cfgLines[] = 'data = "' . $escaped . '"';
    }
    $cfgLines[] = 'write-out = "' . $s['tag'] . ' %{http_code} %{time_total}\n"';
    $cfgLines[] = 'output = ' . $nullDev;
    $cfgLines[] = 'silent';
    if ($idx < count($specs) - 1) {
        $cfgLines[] = 'next';
    }
}
file_put_contents($cfgFile, implode("\n", $cfgLines));

$out = [];
$rc = 0;
exec($curlBin . ' --parallel --parallel-immediate --parallel-max 16 -K ' . escapeshellarg($cfgFile) . ' 2>&1', $out, $rc);
@unlink($cfgFile);
assertCond($rc === 0 && count($out) === 16, "curl --parallel fallo rc={$rc}, salidas=" . count($out));

$pay201 = 0; $pay409 = 0;
$inv201 = 0; $inv422 = 0;
$read200 = 0; $err500 = 0;
$latencies = [];

foreach ($out as $line) {
    if (!preg_match('/^(PAY|INV|READ)\s+(\d{3})\s+([0-9.]+)$/', trim($line), $m)) {
        continue;
    }
    $tag = $m[1];
    $code = (int)$m[2];
    $ms = (float)$m[3] * 1000.0;
    $latencies[] = $ms;
    echo "  [{$tag}] HTTP {$code} {$ms}ms\n";
    if ($code >= 500 || $code === 0) $err500++;

    if ($tag === 'PAY') {
        if ($code === 201) $pay201++;
        elseif ($code === 409) $pay409++;
    } elseif ($tag === 'INV') {
        if ($code === 201) $inv201++;
        elseif ($code === 422) $inv422++;
    } elseif ($tag === 'READ') {
        if ($code === 200) $read200++;
    }
}

assertCond($err500 === 0, "Se detectaron {$err500} errores 500/0");
assertCond($pay201 === 1 && $pay409 === 3, "Race pagos inesperado: 201={$pay201}, 409={$pay409}");
assertCond($inv201 === 2 && $inv422 === 2, "Race inventario inesperado: 201={$inv201}, 422={$inv422}");
assertCond($read200 === 8, "Lecturas concurrentes inesperadas: 200={$read200}");

sort($latencies);
$p50 = $latencies[(int)floor(0.50 * count($latencies))];
$p95 = $latencies[(int)ceil(0.95 * count($latencies)) - 1];
echo sprintf(
    "CONCURRENCY_STRESS_OK: 16 paralelas | pagos(201=%d, 409=%d) inventario(201=%d, 422=%d) lecturas(200=%d) err500=%d | p50=%.2f ms p95=%.2f ms\n",
    $pay201, $pay409, $inv201, $inv422, $read200, $err500, $p50, $p95
);
assertCond($p95 < 100.0, sprintf('Latencia p95 excede 100ms: %.2f ms', $p95));
