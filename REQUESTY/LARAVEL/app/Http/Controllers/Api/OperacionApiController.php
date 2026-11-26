<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DomainValidationException;
use App\Exceptions\ForbiddenException;
use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Services\ActivityLogger;
use App\Services\DtoValidator;
use App\Services\FileRateLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperacionApiController extends Controller
{
    public function mesas(Request $request): JsonResponse
    {
        $user = $request->attributes->get('requesty_user');
        $mesaModel = new Mesa();
        $method = strtoupper($request->getMethod());

        if ($method === 'POST' || $method === 'PUT') {
            if (!in_array($user['rol'], ['ADMIN', 'CAJERO', 'MESERO'], true)) {
                throw new ForbiddenException('Acceso denegado: privilegios insuficientes.');
            }
            FileRateLimiter::check($request, 'api_write:' . (int) $user['id'], 60, 60);
            $input = DtoValidator::parseJsonInput($request, [
                'numero_mesa', 'numeros_mesa', 'estado', 'ocupantes', 'id_cliente', 'cliente_nombre', 'cliente_correo', 'cliente_telefono',
            ]);
            $estado = DtoValidator::requireEnum($input, 'estado', ['DISPONIBLE', 'OCUPADA', 'RESERVADA', 'INACTIVA']);

            if (!empty($input['numeros_mesa']) && is_array($input['numeros_mesa'])) {
                $numeros = array_values(array_unique(array_map('intval', $input['numeros_mesa'])));
                $mesaModel->updateStatuses($numeros, $estado);
                ActivityLogger::record((int) $user['id'], 'Mesas #' . implode(',', $numeros) . " cambiadas a {$estado}", 'MESAS', $request);

                return response()->json(['success' => true]);
            }

            $num = DtoValidator::requireInt($input, 'numero_mesa');

            if ($estado === 'DISPONIBLE') {
                $mesa = $mesaModel->release($num);
                ActivityLogger::record((int) $user['id'], "Mesa #{$num} liberada", 'MESAS', $request);

                return response()->json(['success' => true, 'mesa' => $mesa]);
            }

            if ($estado === 'OCUPADA') {
                $idCliente = DtoValidator::optionalInt($input, 'id_cliente');
                if ($idCliente === null && !empty($input['cliente_correo'])) {
                    $cli = (new Cliente())->findOrCreateByEmail(
                        DtoValidator::optionalString($input, 'cliente_nombre', 100) ?? '',
                        DtoValidator::requireEmail($input, 'cliente_correo'),
                        DtoValidator::optionalString($input, 'cliente_telefono', 20)
                    );
                    $idCliente = (int) $cli['id_cliente'];
                }
                $ocupantes = DtoValidator::optionalInt($input, 'ocupantes', 1, 50) ?? 1;
                $mesa = $mesaModel->assign($num, $ocupantes, $idCliente);
                ActivityLogger::record((int) $user['id'], "Mesa #{$num} asignada a {$ocupantes} ocupante(s)", 'MESAS', $request);

                return response()->json(['success' => true, 'mesa' => $mesa]);
            }

            $mesaModel->updateStatus($num, $estado);
            ActivityLogger::record((int) $user['id'], "Mesa #{$num} cambiada a {$estado}", 'MESAS', $request);

            return response()->json(['success' => true]);
        }

        $mesas = $mesaModel->getAll();
        if ($user['rol'] === 'CLIENTE') {
            $correo = strtolower(trim((string) ($user['correo'] ?? '')));
            $mesas = array_values(array_filter(
                $mesas,
                static fn ($m) => $m['estado'] === 'OCUPADA' && strtolower(trim((string) ($m['cliente_correo'] ?? ''))) === $correo
            ));
        }

        return response()->json(['success' => true, 'mesas' => $mesas]);
    }

    public function crearMesa(Request $request): JsonResponse
    {
        $user = $request->attributes->get('requesty_user');
        if ($user['rol'] !== 'ADMIN') {
            throw new ForbiddenException('Solo un administrador puede agregar mesas.');
        }
        FileRateLimiter::check($request, 'api_write:' . (int) $user['id'], 60, 60);
        $input = DtoValidator::parseJsonInput($request, ['capacidad']);
        $capacidad = DtoValidator::requireInt($input, 'capacidad', 1, 12);
        $mesa = (new Mesa())->createMesa($capacidad);
        ActivityLogger::record((int) $user['id'], 'Creacion de mesa #' . (int) $mesa['numero_mesa'], 'MESAS', $request);

        return response()->json(['success' => true, 'mesa' => $mesa], 201);
    }

    public function pedidos(Request $request): JsonResponse
    {
        $user = $request->attributes->get('requesty_user');
        $pedidoModel = new Pedido();
        $method = strtoupper($request->getMethod());

        if ($method === 'POST') {
            FileRateLimiter::check($request, 'api_write:' . (int) $user['id'], 60, 60);
            $decoded = json_decode((string) $request->getContent(), true);
            if (!is_array($decoded)) {
                throw new DomainValidationException('Payload JSON malformado.', 400);
            }

            if (array_key_exists('id_pedido', $decoded)) {
                if (!in_array($user['rol'], ['ADMIN', 'CAJERO', 'MESERO', 'COCINA'], true)) {
                    throw new ForbiddenException('Acceso denegado: privilegios insuficientes.');
                }
                $input = DtoValidator::parseJsonInput($request, ['id_pedido', 'estado']);
                $idPedido = DtoValidator::requireInt($input, 'id_pedido');
                $estado = DtoValidator::requireEnum($input, 'estado', ['EN_PREPARACION', 'LISTO', 'ENTREGADO', 'PAGADO', 'CANCELADO']);

                $pedido = $pedidoModel->getById($idPedido);
                if (!$pedido) {
                    throw new DomainValidationException('Pedido no encontrado.');
                }
                if ($user['rol'] === 'COCINA' && !in_array($estado, ['EN_PREPARACION', 'LISTO'], true)) {
                    throw new ForbiddenException('Cocina solo puede iniciar preparacion o marcar listo.');
                }
                if ($user['rol'] === 'MESERO' && (int) $pedido['id_mesero'] !== (int) $user['id']) {
                    throw new ForbiddenException('No puede modificar pedidos asignados a otro mesero.');
                }
                if ($user['rol'] === 'MESERO' && $estado === 'PAGADO') {
                    throw new ForbiddenException('Solo CAJERO o ADMIN pueden marcar un pedido como PAGADO.');
                }

                $updated = $pedidoModel->updateStatus($idPedido, $estado, (int) $user['id']);
                ActivityLogger::record((int) $user['id'], "Pedido #{$idPedido} -> {$estado}", 'PEDIDOS', $request);

                return response()->json(['success' => true, 'pedido' => $updated]);
            }

            if (!in_array($user['rol'], ['ADMIN', 'CAJERO', 'MESERO'], true)) {
                throw new ForbiddenException('Acceso denegado: privilegios insuficientes.');
            }
            $input = DtoValidator::parseJsonInput($request, [
                'id_cliente', 'id_mesa', 'numero_mesa', 'observaciones', 'items',
                'cliente_nombre', 'cliente_correo',
            ]);
            if (empty($input['items']) || !is_array($input['items'])) {
                throw new DomainValidationException('El pedido debe incluir al menos un item.');
            }

            $idCliente = DtoValidator::optionalInt($input, 'id_cliente');
            if ($idCliente === null && !empty($input['cliente_correo'])) {
                $cli = (new Cliente())->findOrCreateByEmail(
                    DtoValidator::optionalString($input, 'cliente_nombre', 100) ?? '',
                    DtoValidator::requireEmail($input, 'cliente_correo')
                );
                $idCliente = (int) $cli['id_cliente'];
            }

            $idMesa = DtoValidator::optionalInt($input, 'id_mesa');
            if ($idMesa === null && !empty($input['numero_mesa'])) {
                $m = (new Mesa())->findByNumero(DtoValidator::requireInt($input, 'numero_mesa'));
                if (!$m) {
                    throw new DomainValidationException('Numero de mesa no existe.');
                }
                $idMesa = (int) $m['id_mesa'];
            }

            $created = $pedidoModel->createWithItems(
                (int) $user['id'],
                $idCliente,
                $idMesa,
                $input['items'],
                DtoValidator::optionalString($input, 'observaciones', 500)
            );
            ActivityLogger::record((int) $user['id'], "Creacion pedido #{$created['id_pedido']}", 'PEDIDOS', $request);

            return response()->json(['success' => true, 'pedido' => $created], 201);
        }

        if ($method === 'PUT' || $method === 'PATCH') {
            if (!in_array($user['rol'], ['ADMIN', 'CAJERO', 'MESERO'], true)) {
                throw new ForbiddenException('Acceso denegado: privilegios insuficientes.');
            }
            $input = DtoValidator::parseJsonInput($request, ['id_pedido', 'items', 'observaciones', 'id_mesa', 'id_cliente']);
            $idPedido = DtoValidator::requireInt($input, 'id_pedido');
            $pedido = $pedidoModel->getById($idPedido);
            if (!$pedido) {
                throw new DomainValidationException('Pedido no encontrado.');
            }
            if ($user['rol'] === 'MESERO' && (int) $pedido['id_mesero'] !== (int) $user['id']) {
                throw new ForbiddenException('No puede editar pedidos asignados a otro mesero.');
            }
            if (empty($input['items']) || !is_array($input['items'])) {
                throw new DomainValidationException('El pedido debe incluir al menos un item.');
            }
            $updated = $pedidoModel->updateItems(
                $idPedido,
                $input['items'],
                DtoValidator::optionalString($input, 'observaciones', 500),
                DtoValidator::optionalInt($input, 'id_mesa'),
                DtoValidator::optionalInt($input, 'id_cliente')
            );
            ActivityLogger::record((int) $user['id'], "Edicion items pedido #{$idPedido}", 'PEDIDOS', $request);

            return response()->json(['success' => true, 'pedido' => $updated]);
        }

        if ($method === 'DELETE') {
            if (!in_array($user['rol'], ['ADMIN', 'CAJERO', 'MESERO'], true)) {
                throw new ForbiddenException('Acceso denegado: privilegios insuficientes.');
            }
            $idParam = filter_var($request->query('id'), FILTER_VALIDATE_INT);
            $idPedido = $idParam !== false && (int) $idParam > 0
                ? (int) $idParam
                : DtoValidator::requireInt(DtoValidator::parseJsonInput($request, ['id_pedido']), 'id_pedido');
            $pedido = $pedidoModel->getById($idPedido);
            if (!$pedido) {
                throw new DomainValidationException('Pedido no encontrado.');
            }
            if ($user['rol'] === 'MESERO' && (int) $pedido['id_mesero'] !== (int) $user['id']) {
                throw new ForbiddenException('No puede cancelar pedidos de otro mesero.');
            }
            $updated = $pedidoModel->updateStatus($idPedido, 'CANCELADO', (int) $user['id']);
            ActivityLogger::record((int) $user['id'], "Cancelacion logica pedido #{$idPedido}", 'PEDIDOS', $request);

            return response()->json(['success' => true, 'pedido' => $updated]);
        }

        if (!empty($request->query('id'))) {
            $id = filter_var($request->query('id'), FILTER_VALIDATE_INT);
            if ($id === false || (int) $id <= 0) {
                throw new DomainValidationException('Identificador de pedido invalido.');
            }
            $pedido = $pedidoModel->getById((int) $id);
            if (!$pedido) {
                throw new DomainValidationException('Pedido no encontrado.');
            }
            if ($user['rol'] === 'MESERO' && (int) $pedido['id_mesero'] !== (int) $user['id']) {
                throw new ForbiddenException('Acceso denegado: pedido pertenece a otro mesero.');
            }
            if ($user['rol'] === 'CLIENTE') {
                $cli = (new Cliente())->findByEmail((string) $user['correo']);
                if (!$cli || (int) $pedido['id_cliente'] !== (int) $cli['id_cliente']) {
                    throw new ForbiddenException('Acceso denegado: pedido pertenece a otro cliente.');
                }
            }

            return response()->json(['success' => true, 'pedido' => $pedido]);
        }

        [$limit, $offset] = DtoValidator::extractPagination($request);
        if ($user['rol'] === 'CLIENTE') {
            $cli = (new Cliente())->findByEmail((string) $user['correo']);
            $list = $cli ? $pedidoModel->getAll(null, (int) $cli['id_cliente'], $limit, $offset) : [];

            return response()->json([
                'success' => true,
                'pedidos' => $list,
                'pagination' => ['limit' => $limit, 'offset' => $offset],
            ]);
        }

        $filterMesero = ($user['rol'] === 'MESERO') ? (int) $user['id'] : null;

        return response()->json([
            'success' => true,
            'pedidos' => $pedidoModel->getAll($filterMesero, null, $limit, $offset),
            'pagination' => ['limit' => $limit, 'offset' => $offset],
        ]);
    }

    public function pedidoDescuento(Request $request): JsonResponse
    {
        $user = $request->attributes->get('requesty_user');
        $pedidoModel = new Pedido();
        $method = strtoupper($request->getMethod());
        $input = DtoValidator::parseJsonInput($request, ['id_pedido', 'id_descuento']);
        $idPedido = DtoValidator::requireInt($input, 'id_pedido');
        $idDesc = DtoValidator::requireInt($input, 'id_descuento');

        if ($method === 'DELETE') {
            $ped = $pedidoModel->removeDiscount($idPedido, $idDesc);
            ActivityLogger::record((int) $user['id'], "Remocion descuento #{$idDesc} en pedido #{$idPedido}", 'DESCUENTOS', $request);

            return response()->json(['success' => true, 'pedido' => $ped]);
        }

        $ped = $pedidoModel->applyDiscount($idPedido, $idDesc, (int) $user['id']);
        ActivityLogger::record((int) $user['id'], "Aplicacion descuento #{$idDesc} en pedido #{$idPedido}", 'DESCUENTOS', $request);

        return response()->json(['success' => true, 'pedido' => $ped]);
    }
}
