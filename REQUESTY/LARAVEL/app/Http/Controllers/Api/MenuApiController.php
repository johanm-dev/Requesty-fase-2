<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DomainValidationException;
use App\Exceptions\ForbiddenException;
use App\Http\Controllers\Controller;
use App\Models\Inventario;
use App\Models\Producto;
use App\Services\ActivityLogger;
use App\Services\DtoValidator;
use App\Services\FileRateLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuApiController extends Controller
{
    public function categorias(Request $request): JsonResponse
    {
        $user = $request->attributes->get('requesty_user');
        $prodModel = new Producto();
        $method = strtoupper($request->getMethod());

        if ($method === 'POST') {
            if ($user['rol'] !== 'ADMIN') {
                throw new ForbiddenException('Acceso denegado: privilegios insuficientes.');
            }
            $input = DtoValidator::parseJsonInput($request, ['nombre', 'descripcion']);
            $cat = $prodModel->createCategoria(
                DtoValidator::requireString($input, 'nombre', 2, 100),
                DtoValidator::optionalString($input, 'descripcion', 255)
            );

            return response()->json(['success' => true, 'categoria' => $cat], 201);
        }

        if ($method === 'PUT' || $method === 'PATCH') {
            if ($user['rol'] !== 'ADMIN') {
                throw new ForbiddenException('Acceso denegado: privilegios insuficientes.');
            }
            $input = DtoValidator::parseJsonInput($request, ['id_categoria', 'nombre', 'descripcion', 'activa']);
            $idCat = DtoValidator::requireInt($input, 'id_categoria');
            $cat = $prodModel->updateCategoria($idCat, $input);

            return response()->json(['success' => true, 'categoria' => $cat]);
        }

        $onlyActive = ($user['rol'] !== 'ADMIN');

        return response()->json(['success' => true, 'categorias' => $prodModel->getCategorias($onlyActive)]);
    }

    public function productos(Request $request): JsonResponse
    {
        $user = $request->attributes->get('requesty_user');
        $prodModel = new Producto();
        $method = strtoupper($request->getMethod());

        if ($method === 'POST') {
            if ($user['rol'] !== 'ADMIN') {
                throw new ForbiddenException('Acceso denegado: privilegios insuficientes.');
            }
            FileRateLimiter::check($request, 'api_write:' . (int) $user['id'], 60, 60);
            $input = DtoValidator::parseJsonInput($request, [
                'nombre', 'descripcion', 'precio', 'impuesto_porcentaje', 'imagen',
                'disponible', 'tiempo_preparacion', 'id_categoria', 'categoria', 'receta',
            ]);
            $created = $prodModel->create([
                'nombre' => DtoValidator::requireString($input, 'nombre', 2, 150),
                'descripcion' => DtoValidator::optionalString($input, 'descripcion', 255),
                'precio' => DtoValidator::requirePositiveAmount($input, 'precio', true),
                'impuesto_porcentaje' => isset($input['impuesto_porcentaje']) ? DtoValidator::requirePositiveAmount($input, 'impuesto_porcentaje', true) : 8.00,
                'imagen' => DtoValidator::optionalString($input, 'imagen', 255),
                'disponible' => array_key_exists('disponible', $input) ? (bool) $input['disponible'] : true,
                'tiempo_preparacion' => DtoValidator::optionalInt($input, 'tiempo_preparacion', 0, 240) ?? 10,
                'id_categoria' => DtoValidator::optionalInt($input, 'id_categoria'),
                'categoria' => DtoValidator::optionalString($input, 'categoria', 100),
                'receta' => is_array($input['receta'] ?? null) ? $input['receta'] : [],
            ]);
            ActivityLogger::record((int) $user['id'], "Creacion producto #{$created['id_producto']}", 'MENU', $request);

            return response()->json(['success' => true, 'producto' => $created], 201);
        }

        if ($method === 'PUT' || $method === 'PATCH') {
            if ($user['rol'] !== 'ADMIN') {
                throw new ForbiddenException('Acceso denegado: privilegios insuficientes.');
            }
            FileRateLimiter::check($request, 'api_write:' . (int) $user['id'], 60, 60);
            $input = DtoValidator::parseJsonInput($request, [
                'id_producto', 'nombre', 'descripcion', 'precio', 'impuesto_porcentaje',
                'imagen', 'disponible', 'tiempo_preparacion', 'id_categoria', 'categoria', 'receta',
            ]);
            $idProd = DtoValidator::requireInt($input, 'id_producto');
            $updated = $prodModel->update($idProd, $input);
            ActivityLogger::record((int) $user['id'], "Actualizacion producto #{$idProd}", 'MENU', $request);

            return response()->json(['success' => true, 'producto' => $updated]);
        }

        if ($method === 'DELETE') {
            if ($user['rol'] !== 'ADMIN') {
                throw new ForbiddenException('Acceso denegado: privilegios insuficientes.');
            }
            $idParam = filter_var($request->query('id'), FILTER_VALIDATE_INT);
            $idProd = $idParam !== false && (int) $idParam > 0
                ? (int) $idParam
                : DtoValidator::requireInt(DtoValidator::parseJsonInput($request, ['id_producto']), 'id_producto');
            $res = $prodModel->deactivate($idProd);
            ActivityLogger::record((int) $user['id'], "Desactivacion logica producto #{$idProd}", 'MENU', $request);

            return response()->json(['success' => true, 'producto' => $res]);
        }

        $includeAll = ($user['rol'] === 'ADMIN' && !empty($request->query('todos')));

        return response()->json([
            'success' => true,
            'productos' => $prodModel->getAll(!$includeAll),
            'categorias' => $prodModel->getCategorias(!$includeAll),
        ]);
    }

    public function inventario(Request $request): JsonResponse
    {
        $user = $request->attributes->get('requesty_user');
        $invModel = new Inventario();
        $method = strtoupper($request->getMethod());

        if ($method === 'POST') {
            if ($user['rol'] !== 'ADMIN') {
                throw new ForbiddenException('Solo ADMIN puede registrar movimientos o insumos de inventario.');
            }
            FileRateLimiter::check($request, 'api_write:' . (int) $user['id'], 60, 60);
            $decoded = json_decode((string) $request->getContent(), true);
            if (!is_array($decoded)) {
                throw new DomainValidationException('Payload JSON malformado.', 400);
            }

            if (array_key_exists('nombre', $decoded) && !array_key_exists('id_ingrediente', $decoded)) {
                $input = DtoValidator::parseJsonInput($request, ['nombre', 'unidad_medida', 'stock_inicial', 'stock_minimo', 'costo', 'motivo']);
                $ing = $invModel->createIngrediente([
                    'nombre' => DtoValidator::requireString($input, 'nombre', 2, 150),
                    'unidad_medida' => DtoValidator::requireString($input, 'unidad_medida', 1, 30),
                    'stock_inicial' => isset($input['stock_inicial']) ? DtoValidator::requirePositiveAmount($input, 'stock_inicial', true) : 0,
                    'stock_minimo' => isset($input['stock_minimo']) ? DtoValidator::requirePositiveAmount($input, 'stock_minimo', true) : 0,
                    'costo' => isset($input['costo']) ? DtoValidator::requirePositiveAmount($input, 'costo', true) : 0,
                    'motivo' => DtoValidator::optionalString($input, 'motivo', 255),
                ], (int) $user['id']);
                ActivityLogger::record((int) $user['id'], "Creacion ingrediente #{$ing['id_ingrediente']}", 'INVENTARIO', $request);

                return response()->json(['success' => true, 'ingrediente' => $ing], 201);
            }

            $input = DtoValidator::parseJsonInput($request, ['id_ingrediente', 'tipo_movimiento', 'cantidad', 'motivo']);
            $idIng = DtoValidator::requireInt($input, 'id_ingrediente');
            $idMov = $invModel->registrarMovimiento(
                $idIng,
                DtoValidator::requireEnum($input, 'tipo_movimiento', ['ENTRADA', 'SALIDA', 'AJUSTE_POSITIVO', 'AJUSTE_NEGATIVO', 'MERMA']),
                DtoValidator::requirePositiveAmount($input, 'cantidad', false),
                DtoValidator::requireString($input, 'motivo', 3, 255),
                (int) $user['id']
            );
            ActivityLogger::record((int) $user['id'], "Movimiento inventario #{$idMov} ingrediente #{$idIng}", 'INVENTARIO', $request);

            return response()->json(['success' => true, 'id_movimiento' => $idMov], 201);
        }

        if ($method === 'PUT' || $method === 'PATCH') {
            if ($user['rol'] !== 'ADMIN') {
                throw new ForbiddenException('Solo ADMIN puede actualizar ingredientes.');
            }
            $input = DtoValidator::parseJsonInput($request, ['id_ingrediente', 'nombre', 'stock_minimo', 'stock_objetivo', 'costo', 'estado', 'motivo']);
            $idIng = DtoValidator::requireInt($input, 'id_ingrediente');
            $ing = $invModel->updateIngrediente($idIng, $input, (int) $user['id']);
            ActivityLogger::record((int) $user['id'], "Actualizacion ingrediente #{$idIng}", 'INVENTARIO', $request);

            return response()->json(['success' => true, 'ingrediente' => $ing]);
        }

        return response()->json([
            'success' => true,
            'ingredientes' => $invModel->getIngredientes(),
            'movimientos' => $invModel->getMovimientos(),
        ]);
    }
}
