<?php
declare(strict_types=1);

/**
 * Red-Team HTTP QA + E2E Integration Suite contra PHP Server + PostgreSQL 17 (app_restaurante_rw)
 */

$baseUrl = getenv('TEST_BASE_URL') ?: 'http://127.0.0.1:8099';
$rootDir = dirname(__DIR__);

// Limpiar rate limit previo para ejecucion determinista
$rlDir = $rootDir . '/storage/ratelimit';
if (is_dir($rlDir)) {
    foreach (glob($rlDir . '/*.json') ?: [] as $f) {
        @unlink($f);
    }
}

function httpRequest(string $method, string $url, ?array $body = null, array $headers = []): array {
    $hdrs = array_merge(['Accept: application/json', 'Connection: close'], $headers);
    $content = null;
    if ($body !== null) {
        $hdrs[] = 'Content-Type: application/json';
        $content = json_encode($body, JSON_UNESCAPED_UNICODE);
    }

    $ctx = stream_context_create([
        'http' => [
            'protocol_version' => 1.1,
            'method'           => $method,
            'header'           => implode("\r\n", $hdrs),
            'content'          => $content ?? '',
            'ignore_errors'    => true,
            'timeout'          => 5,
        ],
    ]);

    $t0 = hrtime(true);
    $raw = @file_get_contents($url, false, $ctx);
    $latMs = (hrtime(true) - $t0) / 1e6;

    $respHeaders = $http_response_header ?? [];
    $status = 0;
    if (!empty($respHeaders[0]) && preg_match('/\s(\d{3})\s/', $respHeaders[0], $m)) {
        $status = (int)$m[1];
    }

    $headerMap = [];
    foreach ($respHeaders as $h) {
        if (str_contains($h, ':')) {
            [$k, $v] = explode(':', $h, 2);
            $headerMap[strtolower(trim($k))] = trim($v);
        }
    }

    $json = is_string($raw) ? json_decode($raw, true) : null;
    return [
        'status'  => $status,
        'headers' => $headerMap,
        'raw'     => (string)$raw,
        'json'    => is_array($json) ? $json : null,
        'lat_ms'  => $latMs,
    ];
}

function assertTrue(bool $cond, string $msg): void {
    if (!$cond) {
        fwrite(STDERR, "[FAIL] {$msg}\n");
        exit(1);
    }
}

function assertNoLeak(array $res, string $context): void {
    $raw = strtolower($res['raw']);
    foreach (['stack trace', 'pdoexception', 'sqlstate', 'trg_', 'pg_catalog', 'select ', 'insert into', 'update '] as $forbidden) {
        assertTrue(!str_contains($raw, $forbidden), "{$context}: filtro detalle interno '{$forbidden}' -> {$res['raw']}");
    }
}

$passed = 0;
$e2eLatencies = [];

// 1. Verificar cabeceras de seguridad HTTP y CORS estricto
$r = httpRequest('GET', "{$baseUrl}/api/pedidos", null, ['Origin: http://127.0.0.1:8088']);
assertTrue($r['status'] === 401, "Esperado 401 sin token, recibido {$r['status']}");
assertTrue(($r['headers']['x-content-type-options'] ?? '') === 'nosniff', 'Falta X-Content-Type-Options: nosniff');
assertTrue(($r['headers']['x-frame-options'] ?? '') === 'DENY', 'Falta X-Frame-Options: DENY');
assertTrue(str_contains($r['headers']['strict-transport-security'] ?? '', 'max-age='), 'Falta HSTS');
assertTrue(str_contains($r['headers']['content-security-policy'] ?? '', "default-src 'self'"), 'Falta CSP');
assertTrue(($r['headers']['referrer-policy'] ?? '') === 'no-referrer', 'Falta Referrer-Policy');
assertTrue(($r['headers']['access-control-allow-origin'] ?? '') === 'http://127.0.0.1:8088', 'CORS whitelist fallo');
$passed++;

// 2. CORS no permitido en preflight OPTIONS
$r = httpRequest('OPTIONS', "{$baseUrl}/api/pedidos", null, ['Origin: https://evil.attacker.example']);
assertTrue($r['status'] === 403, "CORS origen malicioso debio dar 403, dio {$r['status']}");
$passed++;

// 3. JWT manipulado / invalido -> 401
$r = httpRequest('GET', "{$baseUrl}/api/pedidos", null, ['Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOjEsInJvbCI6IkFETUlOIiwiZXhwIjo5OTk5OTk5OTk5fQ.invalidsig']);
assertTrue($r['status'] === 401, "JWT manipulado debio dar 401, dio {$r['status']}");
$passed++;

// 4. Autenticacion real para ADMIN, CAJERO, MESERO (y latencia)
$tokens = [];
foreach ([
    'ADMIN'  => ['admin@restaurante.local', 'AdminRest2026*'],
    'CAJERO' => ['cajero@restaurante.local', 'CajeroRest2026*'],
    'MESERO' => ['mesero@restaurante.local', 'MeseroRest2026*'],
] as $rol => [$email, $pwd]) {
    $res = httpRequest('POST', "{$baseUrl}/api/login", ['correo' => $email, 'contrasena' => $pwd]);
    assertTrue($res['status'] === 200 && !empty($res['json']['token']), "Login {$rol} fallo: {$res['raw']}");
    $tokens[$rol] = $res['json']['token'];
}
$passed++;

// 5. RBAC Vertical: MESERO bloqueado (403) en caja, anular factura, descuentos, actividad, usuarios
foreach ([
    ['POST', '/api/caja/abrir', ['monto_apertura' => 50000]],
    ['POST', '/api/caja/cerrar', ['id_caja' => 1, 'efectivo_contado' => 235000]],
    ['POST', '/api/facturas/anular', ['id_factura' => 2]],
    ['POST', '/api/descuentos', ['nombre' => 'Hack', 'porcentaje' => 90]],
    ['GET',  '/api/actividad', null],
    ['GET',  '/api/usuarios', null],
] as [$m, $ep, $payload]) {
    $res = httpRequest($m, "{$baseUrl}{$ep}", $payload, ["Authorization: Bearer {$tokens['MESERO']}"]);
    assertTrue($res['status'] === 403, "MESERO en {$m} {$ep} debio recibir 403, recibio {$res['status']}");
}
$passed++;

// 6. Anti-IDOR Horizontal: ADMIN crea pedido #X (id_mesero = 1), MESERO (id = 3) intenta leerlo -> 403
$resOrdAdmin = httpRequest('POST', "{$baseUrl}/api/pedidos", [
    'id_cliente' => 1,
    'id_mesa'    => 1,
    'items'      => [['id_producto' => 1, 'cantidad' => 1]],
], ["Authorization: Bearer {$tokens['ADMIN']}"]);
assertTrue($resOrdAdmin['status'] === 201, "ADMIN crear pedido fallo: {$resOrdAdmin['raw']}");
$adminOrderId = (int)$resOrdAdmin['json']['pedido']['id_pedido'];

$resIdor = httpRequest('GET', "{$baseUrl}/api/pedidos?id={$adminOrderId}", null, ["Authorization: Bearer {$tokens['MESERO']}"]);
assertTrue($resIdor['status'] === 403, "Anti-IDOR MESERO leyendo pedido de otro usuario debio dar 403, dio {$resIdor['status']}");
$passed++;

// 7. Mass Assignment / DTO estricto -> 422 sin stack trace
$resMass = httpRequest('POST', "{$baseUrl}/api/clientes", [
    'nombre' => 'Attacker',
    'rol'    => 'ADMIN',
], ["Authorization: Bearer {$tokens['ADMIN']}"]);
assertTrue($resMass['status'] === 422, "Mass assignment debio dar 422, dio {$resMass['status']}");
assertNoLeak($resMass, 'MassAssignment');
$passed++;

// 8. Anti-XXE en XML de factura -> 422 sin stack trace
$resXxe = httpRequest('POST', "{$baseUrl}/api/facturas", [
    'id_pedido'      => $adminOrderId,
    'numero_factura' => 'FE-XXE-HTTP-1',
    'xml'            => '<?xml version="1.0"?><!DOCTYPE foo [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><Invoice>&xxe;</Invoice>',
], ["Authorization: Bearer {$tokens['CAJERO']}"]);
assertTrue($resXxe['status'] === 422, "Payload XXE debio dar 422, dio {$resXxe['status']}");
assertNoLeak($resXxe, 'XXE');
$passed++;

// 9. SQLSTATE mapping limpio: transicion invalida -> 409, stock insuficiente -> 422 (sin stack trace ni SQL)
$resConflict = httpRequest('POST', "{$baseUrl}/api/pedidos", [
    'id_pedido' => $adminOrderId,
    'estado'    => 'PAGADO',
], ["Authorization: Bearer {$tokens['ADMIN']}"]);
assertTrue($resConflict['status'] === 409, "Transicion invalida debio mapear a 409, dio {$resConflict['status']}: {$resConflict['raw']}");
assertNoLeak($resConflict, 'StateConflict');

$resStock = httpRequest('POST', "{$baseUrl}/api/inventario", [
    'id_ingrediente'  => 2,
    'tipo_movimiento' => 'SALIDA',
    'cantidad'        => 999999,
    'motivo'          => 'RedTeam stock check',
], ["Authorization: Bearer {$tokens['ADMIN']}"]);
assertTrue($resStock['status'] === 422, "Stock insuficiente debio mapear a 422, dio {$resStock['status']}: {$resStock['raw']}");
assertNoLeak($resStock, 'InsufficientStock');
$passed++;

// 10. Flujo E2E completo con cifrado PII + Blind Index + Caja + Pago + Factura + Cierre + Medicion de latencia (< 50ms)
$docDemo = '1055667788';
$rCli = httpRequest('POST', "{$baseUrl}/api/clientes", [
    'tipo_documento' => 'CC',
    'documento'      => $docDemo,
    'nombre'         => 'Cliente RedTeam E2E',
    'telefono'       => '3150001122',
    'correo'         => 'e2e.cliente@correo.local',
], ["Authorization: Bearer {$tokens['MESERO']}"]);
assertTrue($rCli['status'] === 201, "Crear cliente E2E fallo: {$rCli['raw']}");
$cliId = (int)$rCli['json']['id_cliente'];
$e2eLatencies[] = $rCli['lat_ms'];

$rCliGet = httpRequest('GET', "{$baseUrl}/api/clientes?documento={$docDemo}", null, ["Authorization: Bearer {$tokens['MESERO']}"]);
assertTrue($rCliGet['status'] === 200 && ($rCliGet['json']['cliente']['documento'] ?? '') === $docDemo, "Blind index cliente fallo: {$rCliGet['raw']}");
$e2eLatencies[] = $rCliGet['lat_ms'];

$rCajaOpen = httpRequest('POST', "{$baseUrl}/api/caja/abrir", [
    'monto_apertura' => 100000.00,
    'observaciones'  => 'Turno RedTeam E2E',
], ["Authorization: Bearer {$tokens['CAJERO']}"]);
assertTrue($rCajaOpen['status'] === 201, "Abrir caja fallo: {$rCajaOpen['raw']}");
$cajaId = (int)$rCajaOpen['json']['caja']['id_caja'];
$e2eLatencies[] = $rCajaOpen['lat_ms'];

$rPed = httpRequest('POST', "{$baseUrl}/api/pedidos", [
    'id_cliente'    => $cliId,
    'id_mesa'       => 1,
    'observaciones' => 'Pedido E2E HTTP',
    'items'         => [['id_producto' => 1, 'cantidad' => 1]],
], ["Authorization: Bearer {$tokens['MESERO']}"]);
assertTrue($rPed['status'] === 201, "Crear pedido E2E fallo: {$rPed['raw']}");
$pedId = (int)$rPed['json']['pedido']['id_pedido'];
$pedTotal = (float)$rPed['json']['pedido']['total'];
assertTrue(abs($pedTotal - 30240.00) < 0.01, "Total esperado 30240.00, recibido {$pedTotal}");
$e2eLatencies[] = $rPed['lat_ms'];

foreach (['EN_PREPARACION', 'LISTO', 'ENTREGADO'] as $st) {
    $rSt = httpRequest('POST', "{$baseUrl}/api/pedidos", [
        'id_pedido' => $pedId,
        'estado'    => $st,
    ], ["Authorization: Bearer {$tokens['MESERO']}"]);
    assertTrue($rSt['status'] === 200, "Cambio estado {$st} fallo: {$rSt['raw']}");
    $e2eLatencies[] = $rSt['lat_ms'];
}

$rPago = httpRequest('POST', "{$baseUrl}/api/pagos", [
    'id_pedido'   => $pedId,
    'id_caja'     => $cajaId,
    'metodo_pago' => 'EFECTIVO',
    'valor'       => $pedTotal,
    'referencia'  => 'E2E-EF-01',
], ["Authorization: Bearer {$tokens['CAJERO']}"]);
assertTrue($rPago['status'] === 201, "Pago E2E fallo: {$rPago['raw']}");
$e2eLatencies[] = $rPago['lat_ms'];

$rPagado = httpRequest('POST', "{$baseUrl}/api/pedidos", [
    'id_pedido' => $pedId,
    'estado'    => 'PAGADO',
], ["Authorization: Bearer {$tokens['CAJERO']}"]);
assertTrue($rPagado['status'] === 200, "Marcar PAGADO fallo: {$rPagado['raw']}");
$e2eLatencies[] = $rPagado['lat_ms'];

$rFac = httpRequest('POST', "{$baseUrl}/api/facturas", [
    'id_pedido'      => $pedId,
    'numero_factura' => 'FE-E2E-0001',
    'xml'            => '<Invoice><ID>FE-E2E-0001</ID></Invoice>',
    'aceptar'        => true,
    'cufe'           => 'CUFE-E2E-0001-CERT',
], ["Authorization: Bearer {$tokens['CAJERO']}"]);
assertTrue($rFac['status'] === 201 && ($rFac['json']['factura']['estado_dian'] ?? '') === 'ACEPTADA', "Factura E2E fallo: {$rFac['raw']}");
$e2eLatencies[] = $rFac['lat_ms'];

$rCajaClose = httpRequest('POST', "{$baseUrl}/api/caja/cerrar", [
    'id_caja'          => $cajaId,
    'efectivo_contado' => 100000.00 + $pedTotal,
], ["Authorization: Bearer {$tokens['CAJERO']}"]);
assertTrue($rCajaClose['status'] === 200 && (float)$rCajaClose['json']['caja']['diferencia'] === 0.0, "Cerrar caja fallo: {$rCajaClose['raw']}");
$e2eLatencies[] = $rCajaClose['lat_ms'];

$rCajaList = httpRequest('GET', "{$baseUrl}/api/caja", null, ["Authorization: Bearer {$tokens['CAJERO']}"]);
assertTrue($rCajaList['status'] === 200, "Consultar caja fallo");
$e2eLatencies[] = $rCajaList['lat_ms'];

$avgMs = array_sum($e2eLatencies) / count($e2eLatencies);
assertTrue($avgMs < 50.0, sprintf('Latencia promedio E2E excede 50ms: %.2f ms', $avgMs));
$passed++;

// 11. Logout + revocacion de JWT (token revocado debe dar 401)
$rOut = httpRequest('POST', "{$baseUrl}/api/logout", [], ["Authorization: Bearer {$tokens['CAJERO']}"]);
assertTrue($rOut['status'] === 200, "Logout fallo: {$rOut['raw']}");
$rAfterOut = httpRequest('GET', "{$baseUrl}/api/caja", null, ["Authorization: Bearer {$tokens['CAJERO']}"]);
assertTrue($rAfterOut['status'] === 401, "Token revocado tras logout debio dar 401, dio {$rAfterOut['status']}");
$passed++;

// 12. Vistas Web: Cookie SameSite=Strict + HttpOnly, token CSRF en HTML, bloqueo CSRF sin token (403) y bloqueo de rutas internas
$rWebLogin = httpRequest('GET', "{$baseUrl}/login");
assertTrue($rWebLogin['status'] === 200, "GET /login fallo: {$rWebLogin['status']}");
assertTrue(str_contains($rWebLogin['raw'], 'name="_csrf"'), 'Falta token CSRF oculto en formulario /login');
$setCookie = strtolower($rWebLogin['headers']['set-cookie'] ?? '');
assertTrue(str_contains($setCookie, 'httponly') && str_contains($setCookie, 'samesite=strict'), "Cookie de sesion sin HttpOnly+SameSite=Strict: {$setCookie}");

$ctxForm = stream_context_create([
    'http' => [
        'method'        => 'POST',
        'header'        => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content'       => 'usuario=admin%40restaurante.local&contrase%C3%B1a=AdminRest2026*',
        'ignore_errors' => true,
        'timeout'       => 5,
    ],
]);
@file_get_contents("{$baseUrl}/login", false, $ctxForm);
$csrfStatus = 0;
if (!empty($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $cm)) {
    $csrfStatus = (int)$cm[1];
}
assertTrue($csrfStatus === 403, "POST web /login sin token CSRF debio dar 403, dio {$csrfStatus}");

foreach (['/.env.example', '/app/config/Database.php', '/tests/redteam_http_qa.php', '/database/requesty_database.sql'] as $blockedPath) {
    $rBlk = httpRequest('GET', "{$baseUrl}{$blockedPath}");
    assertTrue($rBlk['status'] === 403, "Ruta sensible {$blockedPath} debio dar 403, dio {$rBlk['status']}");
}
$passed++;

// 13. Rate Limiting en /api/login (fuerza bruta -> 429 Too Many Requests)
$got429 = false;
for ($i = 0; $i < 6; $i++) {
    $rBrute = httpRequest('POST', "{$baseUrl}/api/login", ['correo' => 'admin@restaurante.local', 'contrasena' => 'WrongPass99']);
    if ($rBrute['status'] === 429) {
        $got429 = true;
        break;
    }
}
assertTrue($got429, 'Fuerza bruta en /api/login no disparo 429 Too Many Requests');
$passed++;

echo sprintf("REDTEAM_HTTP_QA_OK: %d/%d suites aprobadas (latencia promedio E2E: %.2f ms)\n", $passed, 13, $avgMs);
