<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ForbiddenException;
use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Notificacion;
use App\Models\Pqrs;
use App\Services\ActivityLogger;
use App\Services\DtoValidator;
use App\Services\FileRateLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClienteApiController extends Controller
{
    public function clientes(Request $request): JsonResponse
    {
        $user = $request->attributes->get('requesty_user');
        $clienteModel = new Cliente();

        if (strtoupper($request->getMethod()) === 'POST') {
            FileRateLimiter::check($request, 'api_write:' . (int) $user['id'], 60, 60);
            $input = DtoValidator::parseJsonInput($request, ['tipo_documento', 'documento', 'nombre', 'telefono', 'correo']);
            $id = $clienteModel->create([
                'tipo_documento' => DtoValidator::optionalString($input, 'tipo_documento', 10) ?? 'CC',
                'documento' => DtoValidator::optionalString($input, 'documento', 30),
                'nombre' => DtoValidator::requireString($input, 'nombre', 2, 100),
                'telefono' => DtoValidator::optionalString($input, 'telefono', 20),
                'correo' => !empty($input['correo']) ? DtoValidator::requireEmail($input, 'correo') : null,
            ]);
            ActivityLogger::record((int) $user['id'], "Registro cliente #{$id}", 'CLIENTES', $request);

            return response()->json(['success' => true, 'id_cliente' => $id], 201);
        }

        $doc = $request->query('documento');
        if (!empty($doc)) {
            return response()->json(['success' => true, 'cliente' => $clienteModel->findByDocument((string) $doc)]);
        }
        [$limit, $offset] = DtoValidator::extractPagination($request);

        return response()->json([
            'success' => true,
            'clientes' => $clienteModel->getAll($limit, $offset),
            'pagination' => ['limit' => $limit, 'offset' => $offset],
        ]);
    }

    public function pqrs(Request $request): JsonResponse
    {
        $user = $request->attributes->get('requesty_user');
        $pqrsModel = new Pqrs();
        $method = strtoupper($request->getMethod());

        if ($method === 'POST') {
            $input = DtoValidator::parseJsonInput($request, ['tipo', 'descripcion', 'id_cliente', 'id_pedido']);
            $idCliente = DtoValidator::optionalInt($input, 'id_cliente');
            if ($idCliente === null) {
                $cli = (new Cliente())->findOrCreateByEmail((string) $user['nombre'], (string) $user['correo']);
                $idCliente = (int) $cli['id_cliente'];
            }
            $id = $pqrsModel->create([
                'tipo' => DtoValidator::requireEnum($input, 'tipo', ['PETICION', 'QUEJA', 'RECLAMO', 'SUGERENCIA']),
                'descripcion' => DtoValidator::requireString($input, 'descripcion', 5, 2000),
                'id_cliente' => $idCliente,
                'id_pedido' => DtoValidator::optionalInt($input, 'id_pedido'),
            ]);
            ActivityLogger::record((int) $user['id'], "Registro PQRS #{$id}", 'PQRS', $request);

            return response()->json(['success' => true, 'id_pqrs' => $id, 'pqrs' => $pqrsModel->getById($id)], 201);
        }

        if ($method === 'PUT' || $method === 'PATCH') {
            if ($user['rol'] !== 'ADMIN') {
                throw new ForbiddenException('Solo ADMIN puede actualizar el estado de PQRS.');
            }
            $input = DtoValidator::parseJsonInput($request, ['id_pqrs', 'estado']);
            $idPqrs = DtoValidator::requireInt($input, 'id_pqrs');
            $estado = DtoValidator::requireEnum($input, 'estado', ['ABIERTA', 'EN_PROCESO', 'RESUELTA', 'CERRADA']);
            $updated = $pqrsModel->updateEstado($idPqrs, $estado);
            ActivityLogger::record((int) $user['id'], "PQRS #{$idPqrs} -> {$estado}", 'PQRS', $request);

            return response()->json(['success' => true, 'pqrs' => $updated]);
        }

        $filterCliente = null;
        if ($user['rol'] === 'CLIENTE') {
            $cli = (new Cliente())->findByEmail((string) $user['correo']);
            $filterCliente = $cli ? (int) $cli['id_cliente'] : -1;
        }
        [$limit, $offset] = DtoValidator::extractPagination($request);

        return response()->json([
            'success' => true,
            'pqrs' => $pqrsModel->getAll($filterCliente, $limit, $offset),
            'pagination' => ['limit' => $limit, 'offset' => $offset],
        ]);
    }

    public function notificaciones(Request $request): JsonResponse
    {
        $user = $request->attributes->get('requesty_user');
        $notifModel = new Notificacion();
        $method = strtoupper($request->getMethod());

        if ($method === 'PUT' || $method === 'PATCH' || $method === 'POST') {
            $input = DtoValidator::parseJsonInput($request, ['id_notificacion', 'todas']);
            if (!empty($input['todas'])) {
                $notifModel->marcarTodasLeidas((int) $user['id']);
            } else {
                $idNotif = DtoValidator::requireInt($input, 'id_notificacion');
                $notifModel->marcarLeida($idNotif, (int) $user['id']);
            }

            return response()->json(['success' => true]);
        }

        $onlyUnread = !empty($request->query('no_leidas'));

        return response()->json([
            'success' => true,
            'notificaciones' => $notifModel->getForUser((int) $user['id'], $onlyUnread),
        ]);
    }
}
