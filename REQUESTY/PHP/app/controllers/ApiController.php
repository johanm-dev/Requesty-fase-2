<?php
/**
 * Controlador REST API completo conectado 100% a PostgreSQL 17 (25 tablas, AppSec, RBAC, Anti-IDOR).
 */
class ApiController extends Controller {

    public function sesion(): void {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'POST') {
            RateLimiter::check('auth_login', 5, 60);
            $input = DtoValidator::parseJsonInput(['correo', 'contrasena']);
            $email = DtoValidator::requireEmail($input, 'correo');
            $pass  = DtoValidator::requireString($input, 'contrasena', 1, 128);

            /** @var Usuario $userModel */
            $userModel = $this->model('Usuario');
            $user = $userModel->authenticate($email, $pass);
            if (!$user) {
                throw new AuthException('Credenciales invalidas o cuenta inactiva.');
            }

            if (session_status() === PHP_SESSION_ACTIVE) {
                session_regenerate_id(true);
                CsrfService::rotate();
                $_SESSION['usuario'] = [
                    'id'     => (int)$user['id_usuario'],
                    'nombre' => (string)$user['nombre'],
                    'correo' => (string)$user['correo'],
                    'rol'    => strtoupper((string)$user['rol']),
                ];
            }

            $this->registrarActividad((int)$user['id_usuario'], 'Inicio de sesion API', 'AUTH');
            $token = JwtService::issue($user);
            $this->json([
                'success'    => true,
                'token'      => $token,
                'csrf_token' => CsrfService::token(),
                'usuario'    => [
                    'id_usuario' => (int)$user['id_usuario'],
                    'nombre'     => $user['nombre'],
                    'correo'     => $user['correo'],
                    'rol'        => strtoupper((string)$user['rol']),
                ],
            ]);
        }

        $user = $this->requireApiAuth();
        $rolKey = strtolower(trim((string)($user['rol'] ?? '')));
        $this->json([
            'autenticado' => true,
            'usuario'     => $user,
            'permisos'    => PERMISOS_POR_ROL[$rolKey] ?? [],
            'csrf_token'  => CsrfService::token(),
        ]);
    }

    public function logout(): void {
        $token = $this->extractBearerToken();
        if ($token !== null) {
            $claims = JwtService::verify($token);
            if ($claims !== null) {
                JwtService::revoke((string)$claims['jti'], (int)$claims['exp']);
            }
        }
        if (isset($_SESSION['usuario']['id'])) {
            $this->registrarActividad((int)$_SESSION['usuario']['id'], 'Cierre de sesion', 'AUTH');
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
        $this->json(['success' => true, 'message' => 'Sesion cerrada y token revocado.']);
    }

    public function roles(): void {
        $this->requireApiAuth();
        /** @var Usuario $userModel */
        $userModel = $this->model('Usuario');
        $this->json(['success' => true, 'roles' => $userModel->getRoles()]);
    }

    public function usuarios(): void {
        $user = $this->requireApiRole(['ADMIN']);
        /** @var Usuario $userModel */
        $userModel = $this->model('Usuario');
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($method === 'POST') {
            RateLimiter::check('api_write:' . (int)$user['id'], 60, 60);
            $input = DtoValidator::parseJsonInput(['nombre', 'apellido', 'documento', 'correo', 'telefono', 'contrasena', 'rol']);
            $id = $userModel->create([
                'nombre'     => DtoValidator::requireString($input, 'nombre', 2, 100),
                'apellido'   => DtoValidator::optionalString($input, 'apellido', 100) ?? '',
                'documento'  => DtoValidator::requireString($input, 'documento', 4, 30),
                'correo'     => DtoValidator::requireEmail($input, 'correo'),
                'telefono'   => DtoValidator::optionalString($input, 'telefono', 20),
                'contrasena' => DtoValidator::requireString($input, 'contrasena', 6, 128),
                'rol'        => DtoValidator::requireEnum($input, 'rol', ['ADMIN', 'CAJERO', 'MESERO', 'COCINA', 'CLIENTE']),
            ]);
            $this->registrarActividad((int)$user['id'], "Creacion de usuario #{$id}", 'USUARIOS');
            $this->json(['success' => true, 'id_usuario' => $id, 'usuario' => $userModel->findById($id)], 201);
        }

        if ($method === 'PUT' || $method === 'PATCH') {
            RateLimiter::check('api_write:' . (int)$user['id'], 60, 60);
            $input = DtoValidator::parseJsonInput(['id_usuario', 'nombre', 'apellido', 'correo', 'telefono', 'contrasena', 'rol', 'estado']);
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
                $payload['contrasena'] = DtoValidator::requireString($input, 'contrasena', 6, 128);
            }
            if (array_key_exists('rol', $input)) {
                $payload['rol'] = DtoValidator::requireEnum($input, 'rol', ['ADMIN', 'CAJERO', 'MESERO', 'COCINA', 'CLIENTE']);
            }
            if (array_key_exists('estado', $input)) {
                $payload['estado'] = DtoValidator::requireEnum($input, 'estado', ['ACTIVO', 'INACTIVO', 'SUSPENDIDO']);
            }
            $updated = $userModel->update($idUsuario, $payload, (int)$user['id']);
            $this->registrarActividad((int)$user['id'], "Actualizacion de usuario #{$idUsuario}", 'USUARIOS');
            $this->json(['success' => true, 'usuario' => $updated]);
        }

        if ($method === 'DELETE') {
            RateLimiter::check('api_write:' . (int)$user['id'], 60, 60);
            $idParam = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
            $idUsuario = $idParam !== false && (int)$idParam > 0
                ? (int)$idParam
                : DtoValidator::requireInt(DtoValidator::parseJsonInput(['id_usuario']), 'id_usuario');
            $updated = $userModel->update($idUsuario, ['estado' => 'INACTIVO'], (int)$user['id']);
            $this->registrarActividad((int)$user['id'], "Inactivacion logica de usuario #{$idUsuario}", 'USUARIOS');
            $this->json(['success' => true, 'usuario' => $updated]);
        }

        if (!empty($_GET['documento'])) {
            $found = $userModel->findByDocument((string)$_GET['documento']);
            $this->json(['success' => true, 'usuario' => $found]);
        }
        $this->json(['success' => true, 'usuarios' => $userModel->getAll()]);
    }

    public function asistencia(): void {
        $user = $this->requireApiRole(['ADMIN', 'CAJERO', 'MESERO', 'COCINA']);
        /** @var Asistencia $asistenciaModel */
        $asistenciaModel = $this->model('Asistencia');
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($method === 'POST' || $method === 'PUT') {
            RateLimiter::check('api_write:' . (int)$user['id'], 60, 60);
            $input = DtoValidator::parseJsonInput(['accion', 'id_asistencia', 'id_usuario']);
            $accion = strtoupper(trim((string)($input['accion'] ?? 'ENTRADA')));
            if ($accion === 'SALIDA') {
                $idAsist = DtoValidator::requireInt($input, 'id_asistencia');
                $onlyOwn = ($user['rol'] !== 'ADMIN') ? (int)$user['id'] : null;
                $res = $asistenciaModel->registrarSalida($idAsist, $onlyOwn);
                $this->registrarActividad((int)$user['id'], "Registro de salida asistencia #{$idAsist}", 'ASISTENCIA');
                $this->json(['success' => true, 'asistencia' => $res]);
            }

            $targetUser = ($user['rol'] === 'ADMIN' && !empty($input['id_usuario']))
                ? DtoValidator::requireInt($input, 'id_usuario')
                : (int)$user['id'];
            $res = $asistenciaModel->registrarEntrada($targetUser);
            $this->registrarActividad((int)$user['id'], "Registro de entrada usuario #{$targetUser}", 'ASISTENCIA');
            $this->json(['success' => true, 'asistencia' => $res], 201);
        }

        $filterUser = ($user['rol'] === 'ADMIN') ? null : (int)$user['id'];
        $this->json(['success' => true, 'asistencia' => $asistenciaModel->getAll($filterUser)]);
    }

    public function clientes(): void {
        $user = $this->requireApiRole(['ADMIN', 'CAJERO', 'MESERO']);
        /** @var Cliente $clienteModel */
        $clienteModel = $this->model('Cliente');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            RateLimiter::check('api_write:' . (int)$user['id'], 60, 60);
            $input = DtoValidator::parseJsonInput(['tipo_documento', 'documento', 'nombre', 'telefono', 'correo']);
            $id = $clienteModel->create([
                'tipo_documento' => DtoValidator::optionalString($input, 'tipo_documento', 10) ?? 'CC',
                'documento'      => DtoValidator::optionalString($input, 'documento', 30),
                'nombre'         => DtoValidator::requireString($input, 'nombre', 2, 100),
                'telefono'       => DtoValidator::optionalString($input, 'telefono', 20),
                'correo'         => !empty($input['correo']) ? DtoValidator::requireEmail($input, 'correo') : null,
            ]);
            $this->registrarActividad((int)$user['id'], "Registro cliente #{$id}", 'CLIENTES');
            $this->json(['success' => true, 'id_cliente' => $id], 201);
        }

        if (!empty($_GET['documento'])) {
            $found = $clienteModel->findByDocument((string)$_GET['documento']);
            $this->json(['success' => true, 'cliente' => $found]);
        }
        $this->json(['success' => true, 'clientes' => $clienteModel->getAll()]);
    }

    public function mesas(): void {
        $user = $this->requireApiAuth();
        /** @var Mesa $mesaModel */
        $mesaModel = $this->model('Mesa');
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($method === 'POST' || $method === 'PUT') {
            $this->requireApiRole(['ADMIN', 'CAJERO', 'MESERO']);
            RateLimiter::check('api_write:' . (int)$user['id'], 60, 60);
            $input = DtoValidator::parseJsonInput([
                'numero_mesa', 'estado', 'ocupantes', 'id_cliente', 'cliente_nombre', 'cliente_correo', 'cliente_telefono'
            ]);
            $num = DtoValidator::requireInt($input, 'numero_mesa');
            $estado = DtoValidator::requireEnum($input, 'estado', ['DISPONIBLE', 'OCUPADA', 'RESERVADA', 'INACTIVA']);

            if ($estado === 'DISPONIBLE') {
                $mesa = $mesaModel->release($num);
                $this->registrarActividad((int)$user['id'], "Mesa #{$num} liberada", 'MESAS');
                $this->json(['success' => true, 'mesa' => $mesa]);
            }

            if ($estado === 'OCUPADA') {
                $idCliente = DtoValidator::optionalInt($input, 'id_cliente');
                if ($idCliente === null && !empty($input['cliente_correo'])) {
                    /** @var Cliente $clienteModel */
                    $clienteModel = $this->model('Cliente');
                    $cli = $clienteModel->findOrCreateByEmail(
                        DtoValidator::optionalString($input, 'cliente_nombre', 100) ?? '',
                        DtoValidator::requireEmail($input, 'cliente_correo'),
                        DtoValidator::optionalString($input, 'cliente_telefono', 20)
                    );
                    $idCliente = (int)$cli['id_cliente'];
                }
                $ocupantes = DtoValidator::optionalInt($input, 'ocupantes', 1, 50) ?? 1;
                $mesa = $mesaModel->assign($num, $ocupantes, $idCliente);
                $this->registrarActividad((int)$user['id'], "Mesa #{$num} asignada a {$ocupantes} ocupante(s)", 'MESAS');
                $this->json(['success' => true, 'mesa' => $mesa]);
            }

            $mesaModel->updateStatus($num, $estado);
            $this->registrarActividad((int)$user['id'], "Mesa #{$num} cambiada a {$estado}", 'MESAS');
            $this->json(['success' => true]);
        }

        $mesas = $mesaModel->getAll();
        if ($user['rol'] === 'CLIENTE') {
            $correo = strtolower(trim((string)($user['correo'] ?? '')));
            $mesas = array_values(array_filter(
                $mesas,
                static fn($m) => $m['estado'] === 'OCUPADA' && strtolower(trim((string)($m['cliente_correo'] ?? ''))) === $correo
            ));
        }
        $this->json(['success' => true, 'mesas' => $mesas]);
    }

    public function categorias(): void {
        $user = $this->requireApiAuth();
        /** @var Producto $prodModel */
        $prodModel = $this->model('Producto');
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($method === 'POST') {
            $this->requireApiRole(['ADMIN']);
            $input = DtoValidator::parseJsonInput(['nombre', 'descripcion']);
            $cat = $prodModel->createCategoria(
                DtoValidator::requireString($input, 'nombre', 2, 100),
                DtoValidator::optionalString($input, 'descripcion', 255)
            );
            $this->json(['success' => true, 'categoria' => $cat], 201);
        }

        if ($method === 'PUT' || $method === 'PATCH') {
            $this->requireApiRole(['ADMIN']);
            $input = DtoValidator::parseJsonInput(['id_categoria', 'nombre', 'descripcion', 'activa']);
            $idCat = DtoValidator::requireInt($input, 'id_categoria');
            $cat = $prodModel->updateCategoria($idCat, $input);
            $this->json(['success' => true, 'categoria' => $cat]);
        }

        $onlyActive = ($user['rol'] !== 'ADMIN');
        $this->json(['success' => true, 'categorias' => $prodModel->getCategorias($onlyActive)]);
    }

    public function productos(): void {
        $user = $this->requireApiAuth();
        /** @var Producto $prodModel */
        $prodModel = $this->model('Producto');
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($method === 'POST') {
            $this->requireApiRole(['ADMIN']);
            RateLimiter::check('api_write:' . (int)$user['id'], 60, 60);
            $input = DtoValidator::parseJsonInput([
                'nombre', 'descripcion', 'precio', 'impuesto_porcentaje', 'imagen',
                'disponible', 'tiempo_preparacion', 'id_categoria', 'categoria', 'receta'
            ]);
            $created = $prodModel->create([
                'nombre'              => DtoValidator::requireString($input, 'nombre', 2, 150),
                'descripcion'         => DtoValidator::optionalString($input, 'descripcion', 255),
                'precio'              => DtoValidator::requirePositiveAmount($input, 'precio', true),
                'impuesto_porcentaje' => isset($input['impuesto_porcentaje']) ? DtoValidator::requirePositiveAmount($input, 'impuesto_porcentaje', true) : 8.00,
                'imagen'              => DtoValidator::optionalString($input, 'imagen', 255),
                'disponible'          => array_key_exists('disponible', $input) ? (bool)$input['disponible'] : true,
                'tiempo_preparacion'  => DtoValidator::optionalInt($input, 'tiempo_preparacion', 0, 240) ?? 10,
                'id_categoria'        => DtoValidator::optionalInt($input, 'id_categoria'),
                'categoria'           => DtoValidator::optionalString($input, 'categoria', 100),
                'receta'              => is_array($input['receta'] ?? null) ? $input['receta'] : [],
            ]);
            $this->registrarActividad((int)$user['id'], "Creacion producto #{$created['id_producto']}", 'MENU');
            $this->json(['success' => true, 'producto' => $created], 201);
        }

        if ($method === 'PUT' || $method === 'PATCH') {
            $this->requireApiRole(['ADMIN']);
            RateLimiter::check('api_write:' . (int)$user['id'], 60, 60);
            $input = DtoValidator::parseJsonInput([
                'id_producto', 'nombre', 'descripcion', 'precio', 'impuesto_porcentaje',
                'imagen', 'disponible', 'tiempo_preparacion', 'id_categoria', 'categoria', 'receta'
            ]);
            $idProd = DtoValidator::requireInt($input, 'id_producto');
            $updated = $prodModel->update($idProd, $input);
            $this->registrarActividad((int)$user['id'], "Actualizacion producto #{$idProd}", 'MENU');
            $this->json(['success' => true, 'producto' => $updated]);
        }

        if ($method === 'DELETE') {
            $this->requireApiRole(['ADMIN']);
            $idParam = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
            $idProd = $idParam !== false && (int)$idParam > 0
                ? (int)$idParam
                : DtoValidator::requireInt(DtoValidator::parseJsonInput(['id_producto']), 'id_producto');
            $res = $prodModel->deactivate($idProd);
            $this->registrarActividad((int)$user['id'], "Desactivacion logica producto #{$idProd}", 'MENU');
            $this->json(['success' => true, 'producto' => $res]);
        }

        $includeAll = ($user['rol'] === 'ADMIN' && !empty($_GET['todos']));
        $this->json([
            'success'    => true,
            'productos'  => $prodModel->getAll(!$includeAll),
            'categorias' => $prodModel->getCategorias(!$includeAll),
        ]);
    }

    public function ingredientes(): void {
        $this->inventario();
    }

    public function inventario(): void {
        $user = $this->requireApiRole(['ADMIN', 'CAJERO', 'MESERO', 'COCINA']);
        /** @var Inventario $invModel */
        $invModel = $this->model('Inventario');
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($method === 'POST') {
            if ($user['rol'] !== 'ADMIN') {
                throw new ForbiddenException('Solo ADMIN puede registrar movimientos o insumos de inventario.');
            }
            RateLimiter::check('api_write:' . (int)$user['id'], 60, 60);
            $raw = file_get_contents('php://input');
            $decoded = json_decode((string)$raw, true);
            if (!is_array($decoded)) {
                throw new DomainValidationException('Payload JSON malformado.', 400);
            }

            if (array_key_exists('nombre', $decoded) && !array_key_exists('id_ingrediente', $decoded)) {
                $input = DtoValidator::parseJsonInput(['nombre', 'unidad_medida', 'stock_inicial', 'stock_minimo', 'costo', 'motivo']);
                $ing = $invModel->createIngrediente([
                    'nombre'        => DtoValidator::requireString($input, 'nombre', 2, 150),
                    'unidad_medida' => DtoValidator::requireString($input, 'unidad_medida', 1, 30),
                    'stock_inicial' => isset($input['stock_inicial']) ? DtoValidator::requirePositiveAmount($input, 'stock_inicial', true) : 0,
                    'stock_minimo'  => isset($input['stock_minimo']) ? DtoValidator::requirePositiveAmount($input, 'stock_minimo', true) : 0,
                    'costo'         => isset($input['costo']) ? DtoValidator::requirePositiveAmount($input, 'costo', true) : 0,
                    'motivo'        => DtoValidator::optionalString($input, 'motivo', 255),
                ], (int)$user['id']);
                $this->registrarActividad((int)$user['id'], "Creacion ingrediente #{$ing['id_ingrediente']}", 'INVENTARIO');
                $this->json(['success' => true, 'ingrediente' => $ing], 201);
            }

            $input = DtoValidator::parseJsonInput(['id_ingrediente', 'tipo_movimiento', 'cantidad', 'motivo']);
            $idIng = DtoValidator::requireInt($input, 'id_ingrediente');
            $idMov = $invModel->registrarMovimiento(
                $idIng,
                DtoValidator::requireEnum($input, 'tipo_movimiento', ['ENTRADA', 'SALIDA', 'AJUSTE_POSITIVO', 'AJUSTE_NEGATIVO', 'MERMA']),
                DtoValidator::requirePositiveAmount($input, 'cantidad', false),
                DtoValidator::requireString($input, 'motivo', 3, 255),
                (int)$user['id']
            );
            $this->registrarActividad((int)$user['id'], "Movimiento inventario #{$idMov} ingrediente #{$idIng}", 'INVENTARIO');
            $this->json(['success' => true, 'id_movimiento' => $idMov], 201);
        }

        if ($method === 'PUT' || $method === 'PATCH') {
            if ($user['rol'] !== 'ADMIN') {
                throw new ForbiddenException('Solo ADMIN puede actualizar ingredientes.');
            }
            $input = DtoValidator::parseJsonInput(['id_ingrediente', 'nombre', 'stock_minimo', 'stock_objetivo', 'costo', 'estado', 'motivo']);
            $idIng = DtoValidator::requireInt($input, 'id_ingrediente');
            $ing = $invModel->updateIngrediente($idIng, $input, (int)$user['id']);
            $this->registrarActividad((int)$user['id'], "Actualizacion ingrediente #{$idIng}", 'INVENTARIO');
            $this->json(['success' => true, 'ingrediente' => $ing]);
        }

        $this->json([
            'success'      => true,
            'ingredientes' => $invModel->getIngredientes(),
            'movimientos'  => $invModel->getMovimientos(),
        ]);
    }

    public function pedidos(): void {
        $user = $this->requireApiAuth();
        /** @var Pedido $pedidoModel */
        $pedidoModel = $this->model('Pedido');
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($method === 'POST') {
            RateLimiter::check('api_write:' . (int)$user['id'], 60, 60);
            $raw = file_get_contents('php://input');
            $decoded = json_decode((string)$raw, true);
            if (!is_array($decoded)) {
                throw new DomainValidationException('Payload JSON malformado.', 400);
            }

            if (array_key_exists('id_pedido', $decoded)) {
                $this->requireApiRole(['ADMIN', 'CAJERO', 'MESERO', 'COCINA']);
                $input = DtoValidator::parseJsonInput(['id_pedido', 'estado']);
                $idPedido = DtoValidator::requireInt($input, 'id_pedido');
                $estado = DtoValidator::requireEnum($input, 'estado', ['EN_PREPARACION', 'LISTO', 'ENTREGADO', 'PAGADO', 'CANCELADO']);

                $pedido = $pedidoModel->getById($idPedido);
                if (!$pedido) {
                    throw new DomainValidationException('Pedido no encontrado.');
                }
                if ($user['rol'] === 'COCINA' && !in_array($estado, ['EN_PREPARACION', 'LISTO'], true)) {
                    throw new ForbiddenException('Cocina solo puede iniciar preparacion o marcar listo.');
                }
                if ($user['rol'] === 'MESERO' && (int)$pedido['id_mesero'] !== (int)$user['id']) {
                    throw new ForbiddenException('No puede modificar pedidos asignados a otro mesero.');
                }
                if ($user['rol'] === 'MESERO' && $estado === 'PAGADO') {
                    throw new ForbiddenException('Solo CAJERO o ADMIN pueden marcar un pedido como PAGADO.');
                }

                $updated = $pedidoModel->updateStatus($idPedido, $estado, (int)$user['id']);
                $this->registrarActividad((int)$user['id'], "Pedido #{$idPedido} -> {$estado}", 'PEDIDOS');
                $this->json(['success' => true, 'pedido' => $updated]);
            }

            $this->requireApiRole(['ADMIN', 'CAJERO', 'MESERO']);
            $input = DtoValidator::parseJsonInput([
                'id_cliente', 'id_mesa', 'numero_mesa', 'observaciones', 'items',
                'cliente_nombre', 'cliente_correo'
            ]);
            if (empty($input['items']) || !is_array($input['items'])) {
                throw new DomainValidationException('El pedido debe incluir al menos un item.');
            }

            $idCliente = DtoValidator::optionalInt($input, 'id_cliente');
            if ($idCliente === null && !empty($input['cliente_correo'])) {
                /** @var Cliente $clienteModel */
                $clienteModel = $this->model('Cliente');
                $cli = $clienteModel->findOrCreateByEmail(
                    DtoValidator::optionalString($input, 'cliente_nombre', 100) ?? '',
                    DtoValidator::requireEmail($input, 'cliente_correo')
                );
                $idCliente = (int)$cli['id_cliente'];
            }

            $idMesa = DtoValidator::optionalInt($input, 'id_mesa');
            if ($idMesa === null && !empty($input['numero_mesa'])) {
                /** @var Mesa $mesaModel */
                $mesaModel = $this->model('Mesa');
                $m = $mesaModel->findByNumero(DtoValidator::requireInt($input, 'numero_mesa'));
                if (!$m) {
                    throw new DomainValidationException('Numero de mesa no existe.');
                }
                $idMesa = (int)$m['id_mesa'];
            }

            $created = $pedidoModel->createWithItems(
                (int)$user['id'],
                $idCliente,
                $idMesa,
                $input['items'],
                DtoValidator::optionalString($input, 'observaciones', 500)
            );
            $this->registrarActividad((int)$user['id'], "Creacion pedido #{$created['id_pedido']}", 'PEDIDOS');
            $this->json(['success' => true, 'pedido' => $created], 201);
        }

        if ($method === 'PUT' || $method === 'PATCH') {
            $this->requireApiRole(['ADMIN', 'CAJERO', 'MESERO']);
            $input = DtoValidator::parseJsonInput(['id_pedido', 'items', 'observaciones', 'id_mesa', 'id_cliente']);
            $idPedido = DtoValidator::requireInt($input, 'id_pedido');
            $pedido = $pedidoModel->getById($idPedido);
            if (!$pedido) {
                throw new DomainValidationException('Pedido no encontrado.');
            }
            if ($user['rol'] === 'MESERO' && (int)$pedido['id_mesero'] !== (int)$user['id']) {
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
            $this->registrarActividad((int)$user['id'], "Edicion items pedido #{$idPedido}", 'PEDIDOS');
            $this->json(['success' => true, 'pedido' => $updated]);
        }

        if ($method === 'DELETE') {
            $this->requireApiRole(['ADMIN', 'CAJERO', 'MESERO']);
            $idParam = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
            $idPedido = $idParam !== false && (int)$idParam > 0
                ? (int)$idParam
                : DtoValidator::requireInt(DtoValidator::parseJsonInput(['id_pedido']), 'id_pedido');
            $pedido = $pedidoModel->getById($idPedido);
            if (!$pedido) {
                throw new DomainValidationException('Pedido no encontrado.');
            }
            if ($user['rol'] === 'MESERO' && (int)$pedido['id_mesero'] !== (int)$user['id']) {
                throw new ForbiddenException('No puede cancelar pedidos de otro mesero.');
            }
            $updated = $pedidoModel->updateStatus($idPedido, 'CANCELADO', (int)$user['id']);
            $this->registrarActividad((int)$user['id'], "Cancelacion logica pedido #{$idPedido}", 'PEDIDOS');
            $this->json(['success' => true, 'pedido' => $updated]);
        }

        if (!empty($_GET['id'])) {
            $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
            if ($id === false || (int)$id <= 0) {
                throw new DomainValidationException('Identificador de pedido invalido.');
            }
            $pedido = $pedidoModel->getById((int)$id);
            if (!$pedido) {
                throw new DomainValidationException('Pedido no encontrado.');
            }
            if ($user['rol'] === 'MESERO' && (int)$pedido['id_mesero'] !== (int)$user['id']) {
                throw new ForbiddenException('Acceso denegado: pedido pertenece a otro mesero.');
            }
            if ($user['rol'] === 'CLIENTE') {
                /** @var Cliente $cliModel */
                $cliModel = $this->model('Cliente');
                $cli = $cliModel->findByEmail((string)$user['correo']);
                if (!$cli || (int)$pedido['id_cliente'] !== (int)$cli['id_cliente']) {
                    throw new ForbiddenException('Acceso denegado: pedido pertenece a otro cliente.');
                }
            }
            $this->json(['success' => true, 'pedido' => $pedido]);
        }

        if ($user['rol'] === 'CLIENTE') {
            /** @var Cliente $cliModel */
            $cliModel = $this->model('Cliente');
            $cli = $cliModel->findByEmail((string)$user['correo']);
            $list = $cli ? $pedidoModel->getAll(null, (int)$cli['id_cliente']) : [];
            $this->json(['success' => true, 'pedidos' => $list]);
        }

        $filterMesero = ($user['rol'] === 'MESERO') ? (int)$user['id'] : null;
        $this->json(['success' => true, 'pedidos' => $pedidoModel->getAll($filterMesero)]);
    }

    public function pedidoDescuento(): void {
        $user = $this->requireApiRole(['ADMIN', 'CAJERO']);
        /** @var Pedido $pedidoModel */
        $pedidoModel = $this->model('Pedido');
        $method = $_SERVER['REQUEST_METHOD'] ?? 'POST';
        $input = DtoValidator::parseJsonInput(['id_pedido', 'id_descuento']);
        $idPedido = DtoValidator::requireInt($input, 'id_pedido');
        $idDesc = DtoValidator::requireInt($input, 'id_descuento');

        if ($method === 'DELETE') {
            $ped = $pedidoModel->removeDiscount($idPedido, $idDesc);
            $this->registrarActividad((int)$user['id'], "Remocion descuento #{$idDesc} en pedido #{$idPedido}", 'DESCUENTOS');
            $this->json(['success' => true, 'pedido' => $ped]);
        }

        $ped = $pedidoModel->applyDiscount($idPedido, $idDesc, (int)$user['id']);
        $this->registrarActividad((int)$user['id'], "Aplicacion descuento #{$idDesc} en pedido #{$idPedido}", 'DESCUENTOS');
        $this->json(['success' => true, 'pedido' => $ped]);
    }

    public function caja(): void {
        $this->requireApiRole(['ADMIN', 'CAJERO']);
        /** @var Caja $cajaModel */
        $cajaModel = $this->model('Caja');
        $this->json(['success' => true, 'cajas' => $cajaModel->getResumen()]);
    }

    public function cajaAbrir(): void {
        $user = $this->requireApiRole(['ADMIN', 'CAJERO']);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            throw new DomainValidationException('Metodo HTTP no permitido.', 400);
        }
        $input = DtoValidator::parseJsonInput(['monto_apertura', 'observaciones']);
        /** @var Caja $cajaModel */
        $cajaModel = $this->model('Caja');
        $caja = $cajaModel->abrir(
            (int)$user['id'],
            DtoValidator::requirePositiveAmount($input, 'monto_apertura', true),
            DtoValidator::optionalString($input, 'observaciones', 500)
        );
        $this->registrarActividad((int)$user['id'], "Apertura de caja #{$caja['id_caja']}", 'CAJA');
        $this->json(['success' => true, 'caja' => $caja], 201);
    }

    public function cajaCerrar(): void {
        $user = $this->requireApiRole(['ADMIN', 'CAJERO']);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            throw new DomainValidationException('Metodo HTTP no permitido.', 400);
        }
        $input = DtoValidator::parseJsonInput(['id_caja', 'efectivo_contado', 'observaciones']);
        $idCaja = DtoValidator::requireInt($input, 'id_caja');

        /** @var Caja $cajaModel */
        $cajaModel = $this->model('Caja');
        $actual = $cajaModel->findHeaderById($idCaja);
        if (!$actual) {
            throw new DomainValidationException('Caja no encontrada.');
        }
        if ($user['rol'] !== 'ADMIN' && (int)$actual['id_cajero'] !== (int)$user['id']) {
            throw new ForbiddenException('Solo el cajero propietario o ADMIN puede cerrar esta caja.');
        }

        $cerrada = $cajaModel->cerrar(
            $idCaja,
            DtoValidator::requirePositiveAmount($input, 'efectivo_contado', true),
            DtoValidator::optionalString($input, 'observaciones', 500)
        );
        $this->registrarActividad((int)$user['id'], "Cierre de caja #{$idCaja}", 'CAJA');
        $this->json(['success' => true, 'caja' => $cerrada]);
    }

    public function pagos(): void {
        $user = $this->requireApiRole(['ADMIN', 'CAJERO']);
        /** @var Caja $cajaModel */
        $cajaModel = $this->model('Caja');
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($method === 'GET') {
            $idCaja = !empty($_GET['id_caja']) ? (int)$_GET['id_caja'] : null;
            $idPed = !empty($_GET['id_pedido']) ? (int)$_GET['id_pedido'] : null;
            $this->json(['success' => true, 'pagos' => $cajaModel->getPagos($idCaja, $idPed)]);
        }

        if ($method === 'PUT' || $method === 'PATCH') {
            $input = DtoValidator::parseJsonInput(['id_pago', 'estado']);
            $idPago = DtoValidator::requireInt($input, 'id_pago');
            $estado = DtoValidator::requireEnum($input, 'estado', ['REEMBOLSADO']);
            $res = $cajaModel->reembolsarPago($idPago);
            $this->registrarActividad((int)$user['id'], "Reembolso de pago #{$idPago}", 'PAGOS');
            $this->json(['success' => true, 'pago' => $res]);
        }

        if ($method !== 'POST') {
            throw new DomainValidationException('Metodo HTTP no permitido.', 400);
        }

        RateLimiter::check('api_write:' . (int)$user['id'], 60, 60);
        $input = DtoValidator::parseJsonInput(['id_pedido', 'id_caja', 'metodo_pago', 'valor', 'estado', 'referencia', 'propina', 'distribuir_mesero']);

        $idCaja = DtoValidator::requireInt($input, 'id_caja');
        $caja = $cajaModel->findHeaderById($idCaja);
        if (!$caja) {
            throw new DomainValidationException('Caja no encontrada.');
        }
        if ($user['rol'] !== 'ADMIN' && (int)$caja['id_cajero'] !== (int)$user['id']) {
            throw new ForbiddenException('No puede registrar pagos en la caja de otro cajero.');
        }

        $pago = $cajaModel->registrarPago([
            'id_pedido'         => DtoValidator::requireInt($input, 'id_pedido'),
            'id_caja'           => $idCaja,
            'metodo_pago'       => DtoValidator::requireEnum($input, 'metodo_pago', ['EFECTIVO', 'TARJETA_CREDITO', 'TARJETA_DEBITO', 'TRANSFERENCIA', 'NEQUI', 'DAVIPLATA', 'OTRO']),
            'valor'             => DtoValidator::requirePositiveAmount($input, 'valor', false),
            'estado'            => !empty($input['estado']) ? DtoValidator::requireEnum($input, 'estado', ['PENDIENTE', 'COMPLETADO']) : 'COMPLETADO',
            'referencia'        => DtoValidator::optionalString($input, 'referencia', 100),
            'propina'           => isset($input['propina']) ? DtoValidator::requirePositiveAmount($input, 'propina', true) : 0,
            'distribuir_mesero' => !empty($input['distribuir_mesero']),
        ]);
        $this->registrarActividad((int)$user['id'], "Registro pago #{$pago['id_pago']} pedido #{$pago['id_pedido']}", 'PAGOS');
        $this->json(['success' => true, 'pago' => $pago], 201);
    }

    public function propinasDistribuir(): void {
        $user = $this->requireApiRole(['ADMIN', 'CAJERO']);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            throw new DomainValidationException('Metodo HTTP no permitido.', 400);
        }
        $input = DtoValidator::parseJsonInput(['id_propina', 'asignaciones']);
        $idPropina = DtoValidator::requireInt($input, 'id_propina');
        if (empty($input['asignaciones']) || !is_array($input['asignaciones'])) {
            throw new DomainValidationException('Debe indicar al menos una asignacion de propina.');
        }
        /** @var Caja $cajaModel */
        $cajaModel = $this->model('Caja');
        $res = $cajaModel->distribuirPropina($idPropina, $input['asignaciones']);
        $this->registrarActividad((int)$user['id'], "Distribucion propina #{$idPropina}", 'PROPINAS');
        $this->json(['success' => true, 'propina' => $res]);
    }

    public function facturas(): void {
        $user = $this->requireApiRole(['ADMIN', 'CAJERO', 'MESERO', 'CLIENTE']);
        /** @var Caja $cajaModel */
        $cajaModel = $this->model('Caja');
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($method === 'POST') {
            if (!in_array($user['rol'], ['ADMIN', 'CAJERO'], true)) {
                throw new ForbiddenException('Solo ADMIN o CAJERO pueden emitir facturas.');
            }
            $input = DtoValidator::parseJsonInput(['id_pedido', 'numero_factura', 'xml', 'aceptar', 'cufe']);
            $factura = $cajaModel->emitirFactura([
                'id_pedido'      => DtoValidator::requireInt($input, 'id_pedido'),
                'numero_factura' => DtoValidator::optionalString($input, 'numero_factura', 50) ?? '',
                'xml'            => DtoValidator::optionalString($input, 'xml', 65535),
                'aceptar'        => !empty($input['aceptar']),
                'cufe'           => DtoValidator::optionalString($input, 'cufe', 128),
            ]);
            $this->registrarActividad((int)$user['id'], "Emision factura {$factura['numero_factura']}", 'FACTURACION');
            $this->json(['success' => true, 'factura' => $factura], 201);
        }

        if ($method === 'PUT' || $method === 'PATCH') {
            if (!in_array($user['rol'], ['ADMIN', 'CAJERO'], true)) {
                throw new ForbiddenException('Solo ADMIN o CAJERO pueden actualizar estado DIAN.');
            }
            $input = DtoValidator::parseJsonInput(['id_factura', 'estado_dian', 'cufe', 'xml']);
            $idFac = DtoValidator::requireInt($input, 'id_factura');
            $est = DtoValidator::requireEnum($input, 'estado_dian', ['ACEPTADA', 'RECHAZADA', 'ANULADA']);
            $res = $cajaModel->actualizarFacturaEstado(
                $idFac,
                $est,
                DtoValidator::optionalString($input, 'cufe', 128),
                DtoValidator::optionalString($input, 'xml', 65535)
            );
            $this->registrarActividad((int)$user['id'], "Factura #{$idFac} -> {$est}", 'FACTURACION');
            $this->json(['success' => true, 'factura' => $res]);
        }

        $filterMesero = ($user['rol'] === 'MESERO') ? (int)$user['id'] : null;
        $filterCliente = null;
        if ($user['rol'] === 'CLIENTE') {
            /** @var Cliente $cliModel */
            $cliModel = $this->model('Cliente');
            $cli = $cliModel->findByEmail((string)$user['correo']);
            $filterCliente = $cli ? (int)$cli['id_cliente'] : -1;
        }
        $this->json(['success' => true, 'facturas' => $cajaModel->getFacturas($filterMesero, $filterCliente)]);
    }

    public function facturaAnular(): void {
        $user = $this->requireApiRole(['ADMIN', 'CAJERO']);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            throw new DomainValidationException('Metodo HTTP no permitido.', 400);
        }
        $input = DtoValidator::parseJsonInput(['id_factura']);
        /** @var Caja $cajaModel */
        $cajaModel = $this->model('Caja');
        $res = $cajaModel->anularFactura(DtoValidator::requireInt($input, 'id_factura'));
        $this->registrarActividad((int)$user['id'], "Anulacion factura #{$res['id_factura']}", 'FACTURACION');
        $this->json(['success' => true, 'factura' => $res]);
    }

    public function descuentos(): void {
        $user = $this->requireApiRole(['ADMIN', 'CAJERO']);
        $db = Database::getConnection();
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'POST') {
            if ($user['rol'] !== 'ADMIN') {
                throw new ForbiddenException('Solo ADMIN puede crear descuentos.');
            }
            $input = DtoValidator::parseJsonInput(['nombre', 'porcentaje', 'motivo']);
            $stmt = $db->prepare(
                'INSERT INTO descuento (nombre, porcentaje, motivo, autorizado_por) VALUES (:n, :p, :m, :a) RETURNING id_descuento'
            );
            $stmt->execute([
                ':n' => DtoValidator::requireString($input, 'nombre', 2, 100),
                ':p' => DtoValidator::requirePositiveAmount($input, 'porcentaje', false),
                ':m' => DtoValidator::optionalString($input, 'motivo', 255),
                ':a' => (int)$user['id'],
            ]);
            $row = $stmt->fetch();
            $this->registrarActividad((int)$user['id'], "Creacion descuento #{$row['id_descuento']}", 'DESCUENTOS');
            $this->json(['success' => true, 'id_descuento' => (int)$row['id_descuento']], 201);
        }
        $stmt = $db->query('SELECT * FROM descuento ORDER BY id_descuento DESC');
        $this->json(['success' => true, 'descuentos' => $stmt->fetchAll()]);
    }

    public function actividad(): void {
        $this->requireApiRole(['ADMIN']);
        $db = Database::getConnection();
        $stmt = $db->query(
            "SELECT a.id_actividad, a.id_usuario, u.nombre AS usuario_nombre, a.accion, a.modulo, a.fecha_hora, a.direccion_ip
             FROM actividad a
             LEFT JOIN vw_usuarios_publico u ON u.id_usuario = a.id_usuario
             ORDER BY a.id_actividad DESC LIMIT 100"
        );
        $this->json(['success' => true, 'actividad' => $stmt->fetchAll()]);
    }

    public function notificaciones(): void {
        $user = $this->requireApiAuth();
        /** @var Notificacion $notifModel */
        $notifModel = $this->model('Notificacion');
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($method === 'PUT' || $method === 'PATCH' || $method === 'POST') {
            $input = DtoValidator::parseJsonInput(['id_notificacion', 'todas']);
            if (!empty($input['todas'])) {
                $notifModel->marcarTodasLeidas((int)$user['id']);
            } else {
                $idNotif = DtoValidator::requireInt($input, 'id_notificacion');
                $notifModel->marcarLeida($idNotif, (int)$user['id']);
            }
            $this->json(['success' => true]);
        }

        $onlyUnread = !empty($_GET['no_leidas']);
        $this->json([
            'success'        => true,
            'notificaciones' => $notifModel->getForUser((int)$user['id'], $onlyUnread),
        ]);
    }

    public function pqrs(): void {
        $user = $this->requireApiAuth();
        /** @var Pqrs $pqrsModel */
        $pqrsModel = $this->model('Pqrs');
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($method === 'POST') {
            $input = DtoValidator::parseJsonInput(['tipo', 'descripcion', 'id_cliente', 'id_pedido']);
            $idCliente = DtoValidator::optionalInt($input, 'id_cliente');
            if ($idCliente === null) {
                /** @var Cliente $cliModel */
                $cliModel = $this->model('Cliente');
                $cli = $cliModel->findOrCreateByEmail((string)$user['nombre'], (string)$user['correo']);
                $idCliente = (int)$cli['id_cliente'];
            }
            $id = $pqrsModel->create([
                'tipo'        => DtoValidator::requireEnum($input, 'tipo', ['PETICION', 'QUEJA', 'RECLAMO', 'SUGERENCIA']),
                'descripcion' => DtoValidator::requireString($input, 'descripcion', 5, 2000),
                'id_cliente'  => $idCliente,
                'id_pedido'   => DtoValidator::optionalInt($input, 'id_pedido'),
            ]);
            $this->registrarActividad((int)$user['id'], "Registro PQRS #{$id}", 'PQRS');
            $this->json(['success' => true, 'id_pqrs' => $id, 'pqrs' => $pqrsModel->getById($id)], 201);
        }

        if ($method === 'PUT' || $method === 'PATCH') {
            $this->requireApiRole(['ADMIN']);
            $input = DtoValidator::parseJsonInput(['id_pqrs', 'estado']);
            $idPqrs = DtoValidator::requireInt($input, 'id_pqrs');
            $estado = DtoValidator::requireEnum($input, 'estado', ['ABIERTA', 'EN_PROCESO', 'RESUELTA', 'CERRADA']);
            $updated = $pqrsModel->updateEstado($idPqrs, $estado);
            $this->registrarActividad((int)$user['id'], "PQRS #{$idPqrs} -> {$estado}", 'PQRS');
            $this->json(['success' => true, 'pqrs' => $updated]);
        }

        $filterCliente = null;
        if ($user['rol'] === 'CLIENTE') {
            /** @var Cliente $cliModel */
            $cliModel = $this->model('Cliente');
            $cli = $cliModel->findByEmail((string)$user['correo']);
            $filterCliente = $cli ? (int)$cli['id_cliente'] : -1;
        }
        $this->json(['success' => true, 'pqrs' => $pqrsModel->getAll($filterCliente)]);
    }

    public function reportes(): void {
        $user = $this->requireApiRole(['ADMIN']);
        /** @var Reporte $repModel */
        $repModel = $this->model('Reporte');
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($method === 'POST') {
            $input = DtoValidator::parseJsonInput(['fecha_inicio', 'fecha_fin', 'tipo']);
            $fi = DtoValidator::requireString($input, 'fecha_inicio', 10, 10);
            $ff = DtoValidator::requireString($input, 'fecha_fin', 10, 10);
            $tipo = DtoValidator::optionalString($input, 'tipo', 100) ?? 'VENTAS_PERIODO';
            $res = $repModel->generarResumen($fi, $ff, (int)$user['id'], $tipo);
            $this->registrarActividad((int)$user['id'], "Generacion reporte {$tipo} ({$fi}..{$ff})", 'REPORTES');
            $this->json(['success' => true, 'reporte' => $res], 201);
        }

        $hoy = date('Y-m-d');
        $fi = !empty($_GET['fecha_inicio']) ? (string)$_GET['fecha_inicio'] : date('Y-m-d', strtotime('-7 days'));
        $ff = !empty($_GET['fecha_fin']) ? (string)$_GET['fecha_fin'] : $hoy;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fi) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $ff) || $fi > $ff) {
            throw new DomainValidationException('Rango de fechas invalido (YYYY-MM-DD).');
        }
        $res = $repModel->generarResumen($fi, $ff, (int)$user['id'], null);
        $this->json(['success' => true, 'reporte' => $res]);
    }
}
