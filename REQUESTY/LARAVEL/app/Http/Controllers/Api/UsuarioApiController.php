<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asistencia;
use App\Models\Usuario;
use App\Services\ActivityLogger;
use App\Services\DtoValidator;
use App\Services\FileRateLimiter;
use App\Services\PgEncryptionContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PDO;

class UsuarioApiController extends Controller
{
    public function usuarios(Request $request): JsonResponse
    {
        $user = $request->attributes->get('requesty_user');
        $userModel = new Usuario();
        $method = strtoupper($request->getMethod());

        if ($method === 'POST') {
            FileRateLimiter::check($request, 'api_write:' . (int) $user['id'], 60, 60);
            $input = DtoValidator::parseJsonInput($request, ['nombre', 'apellido', 'documento', 'correo', 'telefono', 'contrasena', 'rol']);
            $id = $userModel->create([
                'nombre' => DtoValidator::requireString($input, 'nombre', 2, 100),
                'apellido' => DtoValidator::optionalString($input, 'apellido', 100) ?? '',
                'documento' => DtoValidator::requireString($input, 'documento', 4, 30),
                'correo' => DtoValidator::requireEmail($input, 'correo'),
                'telefono' => DtoValidator::optionalString($input, 'telefono', 20),
                'contrasena' => DtoValidator::requireStrongPassword($input, 'contrasena'),
                'rol' => DtoValidator::requireEnum($input, 'rol', ['ADMIN', 'CAJERO', 'MESERO', 'COCINA', 'CLIENTE']),
            ]);
            ActivityLogger::record((int) $user['id'], "Creacion de usuario #{$id}", 'USUARIOS', $request);

            return response()->json([
                'success' => true,
                'id_usuario' => $id,
                'usuario' => $userModel->findById($id),
            ], 201);
        }

        if ($method === 'PUT' || $method === 'PATCH') {
            FileRateLimiter::check($request, 'api_write:' . (int) $user['id'], 60, 60);
            $input = DtoValidator::parseJsonInput($request, ['id_usuario', 'nombre', 'apellido', 'correo', 'telefono', 'contrasena', 'rol', 'estado']);
            $idUsuario = DtoValidator::requireInt($input, 'id_usuario');
            $payload = [];
            if (array_key_exists('nombre', $input)) {
                $payload['nombre'] = DtoValidator::requireString($input, 'nombre', 2, 100);
            }
            if (array_key_exists('apellido', $input)) {
                $payload['apellido'] = DtoValidator::optionalString($input, 'apellido', 100) ?? '';
            }
            if (array_key_exists('correo', $input)) {
                $payload['correo'] = DtoValidator::requireEmail($input, 'correo');
            }
            if (array_key_exists('telefono', $input)) {
                $payload['telefono'] = DtoValidator::optionalString($input, 'telefono', 20);
            }
            if (!empty($input['contrasena'])) {
                $payload['contrasena'] = DtoValidator::requireStrongPassword($input, 'contrasena');
            }
            if (array_key_exists('rol', $input)) {
                $payload['rol'] = DtoValidator::requireEnum($input, 'rol', ['ADMIN', 'CAJERO', 'MESERO', 'COCINA', 'CLIENTE']);
            }
            if (array_key_exists('estado', $input)) {
                $payload['estado'] = DtoValidator::requireEnum($input, 'estado', ['ACTIVO', 'INACTIVO', 'SUSPENDIDO']);
            }
            $updated = $userModel->update($idUsuario, $payload, (int) $user['id']);
            ActivityLogger::record((int) $user['id'], "Actualizacion de usuario #{$idUsuario}", 'USUARIOS', $request);

            return response()->json(['success' => true, 'usuario' => $updated]);
        }

        if ($method === 'DELETE') {
            FileRateLimiter::check($request, 'api_write:' . (int) $user['id'], 60, 60);
            $idParam = filter_var($request->query('id'), FILTER_VALIDATE_INT);
            $idUsuario = $idParam !== false && (int) $idParam > 0
                ? (int) $idParam
                : DtoValidator::requireInt(DtoValidator::parseJsonInput($request, ['id_usuario']), 'id_usuario');
            $updated = $userModel->update($idUsuario, ['estado' => 'INACTIVO'], (int) $user['id']);
            ActivityLogger::record((int) $user['id'], "Inactivacion logica de usuario #{$idUsuario}", 'USUARIOS', $request);

            return response()->json(['success' => true, 'usuario' => $updated]);
        }

        $doc = $request->query('documento');
        if (!empty($doc)) {
            return response()->json(['success' => true, 'usuario' => $userModel->findByDocument((string) $doc)]);
        }
        [$limit, $offset] = DtoValidator::extractPagination($request);

        return response()->json([
            'success' => true,
            'usuarios' => $userModel->getAll($limit, $offset),
            'pagination' => ['limit' => $limit, 'offset' => $offset],
        ]);
    }

    public function asistencia(Request $request): JsonResponse
    {
        $user = $request->attributes->get('requesty_user');
        $asistenciaModel = new Asistencia();
        $method = strtoupper($request->getMethod());

        if ($method === 'POST' || $method === 'PUT') {
            FileRateLimiter::check($request, 'api_write:' . (int) $user['id'], 60, 60);
            $input = DtoValidator::parseJsonInput($request, ['accion', 'id_asistencia', 'id_usuario']);
            $accion = strtoupper(trim((string) ($input['accion'] ?? 'ENTRADA')));
            if ($accion === 'SALIDA') {
                $idAsist = DtoValidator::requireInt($input, 'id_asistencia');
                $onlyOwn = ($user['rol'] !== 'ADMIN') ? (int) $user['id'] : null;
                $res = $asistenciaModel->registrarSalida($idAsist, $onlyOwn);
                ActivityLogger::record((int) $user['id'], "Registro de salida asistencia #{$idAsist}", 'ASISTENCIA', $request);

                return response()->json(['success' => true, 'asistencia' => $res]);
            }

            $targetUser = ($user['rol'] === 'ADMIN' && !empty($input['id_usuario']))
                ? DtoValidator::requireInt($input, 'id_usuario')
                : (int) $user['id'];
            $res = $asistenciaModel->registrarEntrada($targetUser);
            ActivityLogger::record((int) $user['id'], "Registro de entrada usuario #{$targetUser}", 'ASISTENCIA', $request);

            return response()->json(['success' => true, 'asistencia' => $res], 201);
        }

        $filterUser = ($user['rol'] === 'ADMIN') ? null : (int) $user['id'];
        [$limit, $offset] = DtoValidator::extractPagination($request, 200, 200);

        return response()->json([
            'success' => true,
            'asistencia' => $asistenciaModel->getAll($filterUser, $limit, $offset),
            'pagination' => ['limit' => $limit, 'offset' => $offset],
        ]);
    }

    public function actividad(Request $request): JsonResponse
    {
        [$limit, $offset] = DtoValidator::extractPagination($request);
        $pdo = PgEncryptionContext::getPdo();
        $stmt = $pdo->prepare(
            'SELECT a.id_actividad, a.id_usuario, u.nombre AS usuario_nombre, a.accion, a.modulo, a.fecha_hora, a.direccion_ip
             FROM actividad a
             LEFT JOIN vw_usuarios_publico u ON u.id_usuario = a.id_usuario
             ORDER BY a.id_actividad DESC LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return response()->json([
            'success' => true,
            'actividad' => $stmt->fetchAll(),
            'pagination' => ['limit' => $limit, 'offset' => $offset],
        ]);
    }
}
