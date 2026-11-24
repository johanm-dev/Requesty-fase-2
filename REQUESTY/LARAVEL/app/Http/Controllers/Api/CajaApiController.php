<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DomainValidationException;
use App\Exceptions\ForbiddenException;
use App\Http\Controllers\Controller;
use App\Models\Caja;
use App\Models\Cliente;
use App\Models\Reporte;
use App\Services\ActivityLogger;
use App\Services\DtoValidator;
use App\Services\FileRateLimiter;
use App\Services\PgEncryptionContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CajaApiController extends Controller
{
    public function caja(): JsonResponse
    {
        return response()->json(['success' => true, 'cajas' => (new Caja())->getResumen()]);
    }

    public function cajaAbrir(Request $request): JsonResponse
    {
        $user = $request->attributes->get('requesty_user');
        $input = DtoValidator::parseJsonInput($request, ['monto_apertura', 'observaciones']);
        $caja = (new Caja())->abrir(
            (int) $user['id'],
            DtoValidator::requirePositiveAmount($input, 'monto_apertura', true),
            DtoValidator::optionalString($input, 'observaciones', 500)
        );
        ActivityLogger::record((int) $user['id'], "Apertura de caja #{$caja['id_caja']}", 'CAJA', $request);

        return response()->json(['success' => true, 'caja' => $caja], 201);
    }

    public function cajaCerrar(Request $request): JsonResponse
    {
        $user = $request->attributes->get('requesty_user');
        $input = DtoValidator::parseJsonInput($request, ['id_caja', 'efectivo_contado', 'observaciones']);
        $idCaja = DtoValidator::requireInt($input, 'id_caja');

        $cajaModel = new Caja();
        $actual = $cajaModel->findHeaderById($idCaja);
        if (!$actual) {
            throw new DomainValidationException('Caja no encontrada.');
        }
        if ($user['rol'] !== 'ADMIN' && (int) $actual['id_cajero'] !== (int) $user['id']) {
            throw new ForbiddenException('Solo el cajero propietario o ADMIN puede cerrar esta caja.');
        }

        $cerrada = $cajaModel->cerrar(
            $idCaja,
            DtoValidator::requirePositiveAmount($input, 'efectivo_contado', true),
            DtoValidator::optionalString($input, 'observaciones', 500)
        );
        ActivityLogger::record((int) $user['id'], "Cierre de caja #{$idCaja}", 'CAJA', $request);

        return response()->json(['success' => true, 'caja' => $cerrada]);
    }

    public function pagos(Request $request): JsonResponse
    {
        $user = $request->attributes->get('requesty_user');
        $cajaModel = new Caja();
        $method = strtoupper($request->getMethod());

        if ($method === 'GET') {
            $idCaja = !empty($request->query('id_caja')) ? (int) $request->query('id_caja') : null;
            $idPed = !empty($request->query('id_pedido')) ? (int) $request->query('id_pedido') : null;

            return response()->json(['success' => true, 'pagos' => $cajaModel->getPagos($idCaja, $idPed)]);
        }

        if ($method === 'PUT' || $method === 'PATCH') {
            $input = DtoValidator::parseJsonInput($request, ['id_pago', 'estado']);
            $idPago = DtoValidator::requireInt($input, 'id_pago');
            DtoValidator::requireEnum($input, 'estado', ['REEMBOLSADO']);
            $res = $cajaModel->reembolsarPago($idPago);
            ActivityLogger::record((int) $user['id'], "Reembolso de pago #{$idPago}", 'PAGOS', $request);

            return response()->json(['success' => true, 'pago' => $res]);
        }

        if ($method !== 'POST') {
            throw new DomainValidationException('Metodo HTTP no permitido.', 400);
        }

        FileRateLimiter::check($request, 'api_write:' . (int) $user['id'], 60, 60);
        $input = DtoValidator::parseJsonInput($request, ['id_pedido', 'id_caja', 'metodo_pago', 'valor', 'estado', 'referencia', 'propina', 'distribuir_mesero']);

        $idCaja = DtoValidator::requireInt($input, 'id_caja');
        $caja = $cajaModel->findHeaderById($idCaja);
        if (!$caja) {
            throw new DomainValidationException('Caja no encontrada.');
        }
        if ($user['rol'] !== 'ADMIN' && (int) $caja['id_cajero'] !== (int) $user['id']) {
            throw new ForbiddenException('No puede registrar pagos en la caja de otro cajero.');
        }

        $pago = $cajaModel->registrarPago([
            'id_pedido' => DtoValidator::requireInt($input, 'id_pedido'),
            'id_caja' => $idCaja,
            'metodo_pago' => DtoValidator::requireEnum($input, 'metodo_pago', ['EFECTIVO', 'TARJETA_CREDITO', 'TARJETA_DEBITO', 'TRANSFERENCIA', 'NEQUI', 'DAVIPLATA', 'OTRO']),
            'valor' => DtoValidator::requirePositiveAmount($input, 'valor', false),
            'estado' => !empty($input['estado']) ? DtoValidator::requireEnum($input, 'estado', ['PENDIENTE', 'COMPLETADO']) : 'COMPLETADO',
            'referencia' => DtoValidator::optionalString($input, 'referencia', 100),
            'propina' => isset($input['propina']) ? DtoValidator::requirePositiveAmount($input, 'propina', true) : 0,
            'distribuir_mesero' => !empty($input['distribuir_mesero']),
        ]);
        ActivityLogger::record((int) $user['id'], "Registro pago #{$pago['id_pago']} pedido #{$pago['id_pedido']}", 'PAGOS', $request);

        return response()->json(['success' => true, 'pago' => $pago], 201);
    }

    public function propinasDistribuir(Request $request): JsonResponse
    {
        $user = $request->attributes->get('requesty_user');
        $input = DtoValidator::parseJsonInput($request, ['id_propina', 'asignaciones']);
        $idPropina = DtoValidator::requireInt($input, 'id_propina');
        if (empty($input['asignaciones']) || !is_array($input['asignaciones'])) {
            throw new DomainValidationException('Debe indicar al menos una asignacion de propina.');
        }
        $res = (new Caja())->distribuirPropina($idPropina, $input['asignaciones']);
        ActivityLogger::record((int) $user['id'], "Distribucion propina #{$idPropina}", 'PROPINAS', $request);

        return response()->json(['success' => true, 'propina' => $res]);
    }

    public function facturas(Request $request): JsonResponse
    {
        $user = $request->attributes->get('requesty_user');
        $cajaModel = new Caja();
        $method = strtoupper($request->getMethod());

        if ($method === 'POST') {
            if (!in_array($user['rol'], ['ADMIN', 'CAJERO'], true)) {
                throw new ForbiddenException('Solo ADMIN o CAJERO pueden emitir facturas.');
            }
            $input = DtoValidator::parseJsonInput($request, ['id_pedido', 'numero_factura', 'xml', 'aceptar', 'cufe']);
            $factura = $cajaModel->emitirFactura([
                'id_pedido' => DtoValidator::requireInt($input, 'id_pedido'),
                'numero_factura' => DtoValidator::optionalString($input, 'numero_factura', 50) ?? '',
                'xml' => DtoValidator::optionalString($input, 'xml', 65535),
                'aceptar' => !empty($input['aceptar']),
                'cufe' => DtoValidator::optionalString($input, 'cufe', 128),
            ]);
            ActivityLogger::record((int) $user['id'], "Emision factura {$factura['numero_factura']}", 'FACTURACION', $request);

            return response()->json(['success' => true, 'factura' => $factura], 201);
        }

        if ($method === 'PUT' || $method === 'PATCH') {
            if (!in_array($user['rol'], ['ADMIN', 'CAJERO'], true)) {
                throw new ForbiddenException('Solo ADMIN o CAJERO pueden actualizar estado DIAN.');
            }
            $input = DtoValidator::parseJsonInput($request, ['id_factura', 'estado_dian', 'cufe', 'xml']);
            $idFac = DtoValidator::requireInt($input, 'id_factura');
            $est = DtoValidator::requireEnum($input, 'estado_dian', ['ACEPTADA', 'RECHAZADA', 'ANULADA']);
            $res = $cajaModel->actualizarFacturaEstado(
                $idFac,
                $est,
                DtoValidator::optionalString($input, 'cufe', 128),
                DtoValidator::optionalString($input, 'xml', 65535)
            );
            ActivityLogger::record((int) $user['id'], "Factura #{$idFac} -> {$est}", 'FACTURACION', $request);

            return response()->json(['success' => true, 'factura' => $res]);
        }

        $filterMesero = ($user['rol'] === 'MESERO') ? (int) $user['id'] : null;
        $filterCliente = null;
        if ($user['rol'] === 'CLIENTE') {
            $cli = (new Cliente())->findByEmail((string) $user['correo']);
            $filterCliente = $cli ? (int) $cli['id_cliente'] : -1;
        }
        [$limit, $offset] = DtoValidator::extractPagination($request);

        return response()->json([
            'success' => true,
            'facturas' => $cajaModel->getFacturas($filterMesero, $filterCliente, $limit, $offset),
            'pagination' => ['limit' => $limit, 'offset' => $offset],
        ]);
    }

    public function facturaAnular(Request $request): JsonResponse
    {
        $user = $request->attributes->get('requesty_user');
        $input = DtoValidator::parseJsonInput($request, ['id_factura']);
        $res = (new Caja())->anularFactura(DtoValidator::requireInt($input, 'id_factura'));
        ActivityLogger::record((int) $user['id'], "Anulacion factura #{$res['id_factura']}", 'FACTURACION', $request);

        return response()->json(['success' => true, 'factura' => $res]);
    }

    public function descuentos(Request $request): JsonResponse
    {
        $user = $request->attributes->get('requesty_user');
        $pdo = PgEncryptionContext::getPdo();
        if (strtoupper($request->getMethod()) === 'POST') {
            if ($user['rol'] !== 'ADMIN') {
                throw new ForbiddenException('Solo ADMIN puede crear descuentos.');
            }
            $input = DtoValidator::parseJsonInput($request, ['nombre', 'porcentaje', 'motivo']);
            $stmt = $pdo->prepare(
                'INSERT INTO descuento (nombre, porcentaje, motivo, autorizado_por) VALUES (:n, :p, :m, :a) RETURNING id_descuento'
            );
            $stmt->execute([
                ':n' => DtoValidator::requireString($input, 'nombre', 2, 100),
                ':p' => DtoValidator::requirePositiveAmount($input, 'porcentaje', false),
                ':m' => DtoValidator::optionalString($input, 'motivo', 255),
                ':a' => (int) $user['id'],
            ]);
            $row = $stmt->fetch();
            ActivityLogger::record((int) $user['id'], "Creacion descuento #{$row['id_descuento']}", 'DESCUENTOS', $request);

            return response()->json(['success' => true, 'id_descuento' => (int) $row['id_descuento']], 201);
        }

        $stmt = $pdo->query('SELECT * FROM descuento ORDER BY id_descuento DESC');

        return response()->json(['success' => true, 'descuentos' => $stmt->fetchAll()]);
    }

    public function reportes(Request $request): JsonResponse
    {
        $user = $request->attributes->get('requesty_user');
        $repModel = new Reporte();
        if (strtoupper($request->getMethod()) === 'POST') {
            $input = DtoValidator::parseJsonInput($request, ['fecha_inicio', 'fecha_fin', 'tipo']);
            $fi = DtoValidator::requireString($input, 'fecha_inicio', 10, 10);
            $ff = DtoValidator::requireString($input, 'fecha_fin', 10, 10);
            $tipo = DtoValidator::optionalString($input, 'tipo', 100) ?? 'VENTAS_PERIODO';
            $res = $repModel->generarResumen($fi, $ff, (int) $user['id'], $tipo);
            ActivityLogger::record((int) $user['id'], "Generacion reporte {$tipo} ({$fi}..{$ff})", 'REPORTES', $request);

            return response()->json(['success' => true, 'reporte' => $res], 201);
        }

        $hoy = date('Y-m-d');
        $fi = !empty($request->query('fecha_inicio')) ? (string) $request->query('fecha_inicio') : date('Y-m-d', strtotime('-7 days'));
        $ff = !empty($request->query('fecha_fin')) ? (string) $request->query('fecha_fin') : $hoy;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fi) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $ff) || $fi > $ff) {
            throw new DomainValidationException('Rango de fechas invalido (YYYY-MM-DD).');
        }

        return response()->json([
            'success' => true,
            'reporte' => $repModel->generarResumen($fi, $ff, (int) $user['id'], null),
        ]);
    }
}
