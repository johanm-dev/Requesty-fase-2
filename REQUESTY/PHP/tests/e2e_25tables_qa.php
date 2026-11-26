<?php
declare(strict_types=1);

/**
 * E2E Full-Stack QA: Web Session Cookies + CSRF + RBAC + All 25 PostgreSQL Tables via HTTP
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

function httpCall(string $method, string $url, $body = null, array $headers = [], ?string &$cookieJar = null): array {
    $hdrs = array_merge(['Connection: close'], $headers);
    if ($cookieJar) {
        $hdrs[] = "Cookie: {$cookieJar}";
    }
    $content = '';
    if (is_array($body)) {
        $hdrs[] = 'Content-Type: application/json';
        $hdrs[] = 'Accept: application/json';
        $content = json_encode($body, JSON_UNESCAPED_UNICODE);
    } elseif (is_string($body)) {
        $content = $body;
    }

    $ctx = stream_context_create([
        'http' => [
            'protocol_version' => 1.1,
            'method'           => $method,
            'header'           => implode("\r\n", $hdrs),
            'content'          => $content,
            'ignore_errors'    => true,
            'follow_location'  => 0,
            'timeout'          => 5,
        ],
    ]);

    $raw = (string)@file_get_contents($url, false, $ctx);
    $respHeaders = function_exists('http_get_last_response_headers')
        ? (http_get_last_response_headers() ?: [])
        : ($http_response_header ?? []);

    $status = 0;
    if (!empty($respHeaders[0]) && preg_match('/\s(\d{3})\s/', $respHeaders[0], $m)) {
        $status = (int)$m[1];
    }

    $cookies = [];
    if ($cookieJar) {
        foreach (explode('; ', $cookieJar) as $pair) {
            if (str_contains($pair, '=')) {
                [$ck, $cv] = explode('=', $pair, 2);
                $cookies[$ck] = $cv;
            }
        }
    }
    foreach ($respHeaders as $h) {
        if (stripos($h, 'set-cookie:') === 0) {
            $kv = trim(explode(';', substr($h, 11), 2)[0]);
            if (str_contains($kv, '=')) {
                [$ck, $cv] = explode('=', $kv, 2);
                $cookies[$ck] = $cv;
            }
        }
    }
    if (!empty($cookies) && $cookieJar !== null) {
        $pairs = [];
        foreach ($cookies as $ck => $cv) {
            $pairs[] = "{$ck}={$cv}";
        }
        $cookieJar = implode('; ', $pairs);
    }

    $json = json_decode($raw, true);
    return [
        'status' => $status,
        'raw'    => $raw,
        'json'   => is_array($json) ? $json : null,
    ];
}

function must(bool $cond, string $msg): void {
    if (!$cond) {
        fwrite(STDERR, "[E2E FAIL] {$msg}\n");
        exit(1);
    }
}

$checks = 0;

// 1. Registro publico de cliente (crea usuario CLIENTE + cliente con Bcrypt y AES-256)
$jarReg = '';
$regPage = httpCall('GET', "{$baseUrl}/index.php?page=registro", null, [], $jarReg);
must($regPage['status'] === 200, "GET registro fallo: {$regPage['status']}");
must(preg_match('/name="_csrf"\s+value="([^"]+)"/', $regPage['raw'], $cm) === 1, 'Falta _csrf en registro');
$csrfReg = $cm[1];
$emailCli = 'cliente.e2e.' . random_int(1000, 9999) . '@correo.local';
$docCli = '1099' . random_int(100000, 999999);
$postReg = httpCall(
    'POST',
    "{$baseUrl}/index.php?page=registro",
    http_build_query([
        '_csrf'                => $csrfReg,
        'nombre'               => 'Sofia Martinez',
        'documento'            => $docCli,
        'correo'               => $emailCli,
        'telefono'             => '3112223344',
        'contrasena'           => 'SofiaPass2026!',
        'confirmar_contrasena' => 'SofiaPass2026!',
    ]),
    [
        'Content-Type: application/x-www-form-urlencoded',
        'X-Requested-With: XMLHttpRequest',
        'Accept: application/json',
    ],
    $jarReg
);
must($postReg['status'] === 201 && !empty($postReg['json']['success']), "Registro publico fallo: {$postReg['raw']}");
$checks++;

// 2. Login via Cookie Session Web + CSRF en API mutating calls (bloqueo sin CSRF, exito con X-CSRF-Token)
$jarAdmin = '';
$loginPage = httpCall('GET', "{$baseUrl}/index.php", null, [], $jarAdmin);
must($loginPage['status'] === 200, 'GET login page fallo');
$loginResp = httpCall('POST', "{$baseUrl}/index.php?page=api/login", ['correo' => 'admin@restaurante.local', 'contrasena' => 'AdminRest2026*'], [], $jarAdmin);
must($loginResp['status'] === 200 && !empty($loginResp['json']['csrf_token']), "Web API login fallo: {$loginResp['raw']}");
$csrfAdmin = $loginResp['json']['csrf_token'];

// Intento cookie-session POST sin X-CSRF-Token debe retornar 403
$noCsrf = httpCall('POST', "{$baseUrl}/index.php?page=api/categorias", ['nombre' => 'SinCsrfTest'], [], $jarAdmin);
must($noCsrf['status'] === 403, "Cookie API POST sin X-CSRF-Token debio dar 403, dio {$noCsrf['status']}");

// Con X-CSRF-Token debe funcionar 201
$withCsrf = httpCall('POST', "{$baseUrl}/index.php?page=api/categorias", ['nombre' => 'Postres E2E ' . random_int(10, 99), 'descripcion' => 'Categoria test'], ["X-CSRF-Token: {$csrfAdmin}"], $jarAdmin);
must($withCsrf['status'] === 201, "Cookie API POST con X-CSRF-Token fallo: {$withCsrf['raw']}");
$idCat = (int)$withCsrf['json']['categoria']['id_categoria'];
$checks++;

// 3. Verificar todas las vistas HTML cargan 200 OK para ADMIN (sin localStorage/sessionStorage en HTML)
foreach (['menu', 'mesas', 'pedidos', 'cocina', 'caja', 'pqrs', 'reportes'] as $viewPage) {
    $vp = httpCall('GET', "{$baseUrl}/index.php?page={$viewPage}", null, [], $jarAdmin);
    must($vp['status'] === 200, "Vista {$viewPage} fallo con HTTP {$vp['status']}");
    must(str_contains($vp['raw'], 'data-page="' . $viewPage . '"'), "Vista {$viewPage} no tiene data-page");
    must(!preg_match('/localStorage|sessionStorage/', $vp['raw']), "Vista {$viewPage} aun contiene localStorage/sessionStorage!");
}
$checks++;

// Login Bearer tokens para otros roles
function bearerFor(string $baseUrl, string $correo, string $pass): string {
    $r = httpCall('POST', "{$baseUrl}/api/login", ['correo' => $correo, 'contrasena' => $pass]);
    must($r['status'] === 200 && !empty($r['json']['token']), "Bearer login {$correo} fallo: {$r['raw']}");
    return $r['json']['token'];
}
$tokAdmin   = $loginResp['json']['token'];
$tokMesero  = bearerFor($baseUrl, 'mesero@restaurante.local', 'MeseroRest2026*');
$tokCocina  = bearerFor($baseUrl, 'cocina@restaurante.local', 'CocinaRest2026*');
$tokCajero  = bearerFor($baseUrl, 'cajero@restaurante.local', 'CajeroRest2026*');
$tokCliente = bearerFor($baseUrl, $emailCli, 'SofiaPass2026!');

// 4. Roles, Usuarios CRUD, Asistencia entrada/salida
$rRoles = httpCall('GET', "{$baseUrl}/api/roles", null, ["Authorization: Bearer {$tokAdmin}"]);
must($rRoles['status'] === 200 && count($rRoles['json']['roles'] ?? []) >= 5, 'Roles insuficientes');

$rUsrNew = httpCall('POST', "{$baseUrl}/api/usuarios", [
    'nombre'     => 'Luis',
    'apellido'   => 'Cocinero',
    'documento'  => '1088' . random_int(100000, 999999),
    'correo'     => 'luis.' . random_int(100, 999) . '@restaurante.local',
    'telefono'   => '3009998877',
    'contrasena' => 'LuisCocina2026!',
    'rol'        => 'COCINA',
], ["Authorization: Bearer {$tokAdmin}"]);
must($rUsrNew['status'] === 201, "Crear usuario COCINA fallo: {$rUsrNew['raw']}");
$idLuis = (int)$rUsrNew['json']['id_usuario'];

$rUsrUpd = httpCall('PUT', "{$baseUrl}/api/usuarios", [
    'id_usuario' => $idLuis,
    'nombre'     => 'Luis Alberto',
    'rol'        => 'MESERO',
], ["Authorization: Bearer {$tokAdmin}"]);
must($rUsrUpd['status'] === 200 && ($rUsrUpd['json']['usuario']['rol'] ?? '') === 'MESERO', "Actualizar usuario fallo: {$rUsrUpd['raw']}");

$rAsistIn = httpCall('POST', "{$baseUrl}/api/asistencia", ['accion' => 'ENTRADA'], ["Authorization: Bearer {$tokMesero}"]);
must($rAsistIn['status'] === 201, "Asistencia entrada fallo: {$rAsistIn['raw']}");
$idAsist = (int)$rAsistIn['json']['asistencia']['id_asistencia'];
sleep(1);
$rAsistOut = httpCall('POST', "{$baseUrl}/api/asistencia", ['accion' => 'SALIDA', 'id_asistencia' => $idAsist], ["Authorization: Bearer {$tokMesero}"]);
must($rAsistOut['status'] === 200 && !empty($rAsistOut['json']['asistencia']['hora_salida']), "Asistencia salida fallo: {$rAsistOut['raw']}");
$checks++;

// 5. Inventario (nuevo ingrediente + ajuste) & Producto con receta (producto_ingrediente)
$rIng = httpCall('POST', "{$baseUrl}/api/inventario", [
    'nombre'        => 'Chocolate E2E ' . random_int(10, 99),
    'unidad_medida' => 'g',
    'stock_inicial' => 500,
    'stock_minimo'  => 100,
    'costo'         => 25,
], ["Authorization: Bearer {$tokAdmin}"]);
must($rIng['status'] === 201, "Crear ingrediente fallo: {$rIng['raw']}");
$idIngChoc = (int)$rIng['json']['ingrediente']['id_ingrediente'];

$rProd = httpCall('POST', "{$baseUrl}/api/productos", [
    'nombre'       => 'Brownie Artesanal ' . random_int(10, 99),
    'precio'       => 12000,
    'id_categoria' => $idCat,
    'receta'       => [['id_ingrediente' => $idIngChoc, 'cantidad' => 50]],
], ["Authorization: Bearer {$tokAdmin}"]);
must($rProd['status'] === 201 && !empty($rProd['json']['producto']['disponible_stock']), "Crear producto con receta fallo: {$rProd['raw']}");
$idProdBrownie = (int)$rProd['json']['producto']['id_producto'];
$checks++;

// 6. Flujo Completo Mesa -> Pedido Personalizado -> Cocina (descuento stock + notificacion) -> Entrega -> Descuento -> Caja/Pago/Propina/Distribucion -> Factura -> Cierre -> Liberar Mesa
$rMesa = httpCall('POST', "{$baseUrl}/api/mesas", [
    'numero_mesa'    => 2,
    'estado'         => 'OCUPADA',
    'ocupantes'      => 2,
    'cliente_nombre' => 'Sofia Martinez',
    'cliente_correo' => $emailCli,
], ["Authorization: Bearer {$tokMesero}"]);
must($rMesa['status'] === 200 && ($rMesa['json']['mesa']['estado'] ?? '') === 'OCUPADA', "Asignar mesa 2 fallo: {$rMesa['raw']}");

// Cliente ve su mesa asignada
$rCliMesa = httpCall('GET', "{$baseUrl}/api/mesas", null, ["Authorization: Bearer {$tokCliente}"]);
must($rCliMesa['status'] === 200 && count($rCliMesa['json']['mesas'] ?? []) === 1, "Cliente no vio mesa asignada: {$rCliMesa['raw']}");

// Mesero crea pedido personalizado en Mesa 2
$rPed = httpCall('POST', "{$baseUrl}/api/pedidos", [
    'numero_mesa'    => 2,
    'cliente_correo' => $emailCli,
    'cliente_nombre' => 'Sofia Martinez',
    'observaciones'  => 'Mesa 2 E2E',
    'items'          => [
        [
            'id_producto'            => 1,
            'cantidad'               => 1,
            'termino_coccion'        => 'BIEN ASADA',
            'observaciones'          => 'Sin cebolla, extra queso',
            'ingredientes_excluidos' => ['Cebolla caramelizada'],
            'extras'                 => [['nombre' => 'Queso cheddar', 'cantidad' => 1, 'costo_extra' => 2000]],
        ],
        [
            'id_producto' => $idProdBrownie,
            'cantidad'    => 2,
        ],
    ],
], ["Authorization: Bearer {$tokMesero}"]);
must($rPed['status'] === 201, "Crear pedido personalizado fallo: {$rPed['raw']}");
$idPedido = (int)$rPed['json']['pedido']['id_pedido'];
must(count($rPed['json']['pedido']['items'] ?? []) === 2, 'Detalle de pedido incompleto');

// Cocina inicia preparacion (descuenta chocolate: 500 -> 400 g!) y marca LISTO (notifica mesero!)
$rPrep = httpCall('POST', "{$baseUrl}/api/pedidos", ['id_pedido' => $idPedido, 'estado' => 'EN_PREPARACION'], ["Authorization: Bearer {$tokCocina}"]);
must($rPrep['status'] === 200, "Cocina EN_PREPARACION fallo: {$rPrep['raw']}");

$rInvCheck = httpCall('GET', "{$baseUrl}/api/inventario", null, ["Authorization: Bearer {$tokAdmin}"]);
must($rInvCheck['status'] === 200, "GET inventario fallo: {$rInvCheck['raw']}");
$chocRow = null;
foreach ($rInvCheck['json']['ingredientes'] ?? [] as $ingRow) {
    if ((int)$ingRow['id_ingrediente'] === $idIngChoc) {
        $chocRow = $ingRow;
    }
}
must($chocRow !== null && abs((float)$chocRow['stock_actual'] - 400.0) < 0.01, "Stock chocolate debio descontarse a 400g, actual: " . ($chocRow['stock_actual'] ?? 'null'));

$rListo = httpCall('POST', "{$baseUrl}/api/pedidos", ['id_pedido' => $idPedido, 'estado' => 'LISTO'], ["Authorization: Bearer {$tokCocina}"]);
must($rListo['status'] === 200, "Cocina LISTO fallo: {$rListo['raw']}");

// Mesero lee notificacion y entrega pedido
$rNotif = httpCall('GET', "{$baseUrl}/api/notificaciones?no_leidas=1", null, ["Authorization: Bearer {$tokMesero}"]);
must($rNotif['status'] === 200 && count($rNotif['json']['notificaciones'] ?? []) >= 1, "No llego notificacion de pedido LISTO al mesero");

$rEntregado = httpCall('POST', "{$baseUrl}/api/pedidos", ['id_pedido' => $idPedido, 'estado' => 'ENTREGADO'], ["Authorization: Bearer {$tokMesero}"]);
must($rEntregado['status'] === 200, "Mesero ENTREGADO fallo: {$rEntregado['raw']}");

// Descuento + Caja + Pago + Propina distribuida + Pagado + Factura + Cierre + Liberar Mesa
$rDesc = httpCall('POST', "{$baseUrl}/api/pedidos/descuento", ['id_pedido' => $idPedido, 'id_descuento' => 1], ["Authorization: Bearer {$tokCajero}"]);
must($rDesc['status'] === 200, "Aplicar descuento fallo: {$rDesc['raw']}");
$totalConDesc = (float)$rDesc['json']['pedido']['total'];

$rAbrir = httpCall('POST', "{$baseUrl}/api/caja/abrir", ['monto_apertura' => 50000, 'observaciones' => 'Turno E2E Full'], ["Authorization: Bearer {$tokCajero}"]);
must($rAbrir['status'] === 201, "Abrir caja fallo: {$rAbrir['raw']}");
$idCaja = (int)$rAbrir['json']['caja']['id_caja'];

$rPago = httpCall('POST', "{$baseUrl}/api/pagos", [
    'id_pedido'         => $idPedido,
    'id_caja'           => $idCaja,
    'metodo_pago'       => 'EFECTIVO',
    'valor'             => $totalConDesc,
    'propina'           => 4000,
    'distribuir_mesero' => true,
], ["Authorization: Bearer {$tokCajero}"]);
must($rPago['status'] === 201, "Registrar pago+propina fallo: {$rPago['raw']}");

$rPagado = httpCall('POST', "{$baseUrl}/api/pedidos", ['id_pedido' => $idPedido, 'estado' => 'PAGADO'], ["Authorization: Bearer {$tokCajero}"]);
must($rPagado['status'] === 200, "Marcar PAGADO fallo: {$rPagado['raw']}");

$rFactura = httpCall('POST', "{$baseUrl}/api/facturas", [
    'id_pedido' => $idPedido,
    'aceptar'   => true,
    'cufe'      => 'CUFE-E2E-FULL-' . $idPedido,
    'xml'       => "<Invoice><Pedido>{$idPedido}</Pedido></Invoice>",
], ["Authorization: Bearer {$tokCajero}"]);
must($rFactura['status'] === 201 && ($rFactura['json']['factura']['estado_dian'] ?? '') === 'ACEPTADA', "Emitir factura fallo: {$rFactura['raw']}");

$rCerrar = httpCall('POST', "{$baseUrl}/api/caja/cerrar", [
    'id_caja'          => $idCaja,
    'efectivo_contado' => 50000 + $totalConDesc + 4000,
], ["Authorization: Bearer {$tokCajero}"]);
must($rCerrar['status'] === 200 && abs((float)$rCerrar['json']['caja']['diferencia']) < 0.01, "Cerrar caja fallo: {$rCerrar['raw']}");

$rLibMesa = httpCall('POST', "{$baseUrl}/api/mesas", ['numero_mesa' => 2, 'estado' => 'DISPONIBLE'], ["Authorization: Bearer {$tokMesero}"]);
must($rLibMesa['status'] === 200 && ($rLibMesa['json']['mesa']['estado'] ?? '') === 'DISPONIBLE', "Liberar mesa fallo: {$rLibMesa['raw']}");
$checks++;

// 7. PQRS Cliente + Gestion Admin + Reportes + Auditoria
$rPqrsNew = httpCall('POST', "{$baseUrl}/api/pqrs", [
    'tipo'        => 'SUGERENCIA',
    'descripcion' => 'Muy buen brownie artesanal E2E',
    'id_pedido'   => $idPedido,
], ["Authorization: Bearer {$tokCliente}"]);
must($rPqrsNew['status'] === 201, "Cliente crear PQRS fallo: {$rPqrsNew['raw']}");
$idPqrs = (int)$rPqrsNew['json']['id_pqrs'];

$rPqrsUpd = httpCall('PUT', "{$baseUrl}/api/pqrs", ['id_pqrs' => $idPqrs, 'estado' => 'RESUELTA'], ["Authorization: Bearer {$tokAdmin}"]);
must($rPqrsUpd['status'] === 200 && ($rPqrsUpd['json']['pqrs']['estado'] ?? '') === 'RESUELTA', "Admin actualizar PQRS fallo: {$rPqrsUpd['raw']}");

$hoy = date('Y-m-d');
$rRep = httpCall('POST', "{$baseUrl}/api/reportes", ['fecha_inicio' => $hoy, 'fecha_fin' => $hoy, 'tipo' => 'CIERRE_E2E'], ["Authorization: Bearer {$tokAdmin}"]);
must($rRep['status'] === 201 && (int)($rRep['json']['reporte']['kpis']['pedidos_cobrados'] ?? 0) >= 1, "Generar reporte fallo: {$rRep['raw']}");

$rAct = httpCall('GET', "{$baseUrl}/api/actividad", null, ["Authorization: Bearer {$tokAdmin}"]);
must($rAct['status'] === 200 && count($rAct['json']['actividad'] ?? []) >= 5, "Consultar bitacora actividad fallo");
$checks++;

echo "E2E_25_TABLES_QA_OK: {$checks}/7 suites completas aprobadas (Web Cookie+CSRF + API JWT + 25 tablas PostgreSQL)\n";
