<?php
/**
 * Controlador REST API endurecido: JWT HS256, RBAC, Anti-IDOR, Rate Limiting, Anti-XXE y DTOs estrictos
 * Compatible con PostgreSQL 17 y motor transaccional normalizado de 25 tablas.
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
                $_SESSION['usuario'] = [
                    'id'     => (int)$user['id_usuario'],
                    'nombre' => (string)$user['nombre'],
                    'correo' => (string)$user['correo'],
                    'rol'    => self::normalizeRole((string)$user['rol']),
                ];
            }

            $token = JwtService::issue($user);
            $this->json([
                'success' => true,
                'token'   => $token,
                'usuario' => [
                    'id_usuario' => (int)$user['id_usuario'],
                    'nombre'     => $user['nombre'],
                    'correo'     => $user['correo'],
                    'rol'        => self::normalizeRole((string)$user['rol']),
                ],
            ]);
        }

        $token = $this->extractBearerToken();
        if ($token !== null) {
            $user = $this->requireApiAuth();
            $this->json(['autenticado' => true, 'usuario' => $user]);
        }

        if (isset($_SESSION['usuario'])) {
            $this->json(['autenticado' => true, 'usuario' => $_SESSION['usuario']]);
        }

        // Si el frontend web (auth.js) consulta estado de sesion sin token, responder limpio
        $this->json(['autenticado' => false, 'usuario' => null]);
    }

    public function logout(): void {
        $token = $this->extractBearerToken();
        if ($token !== null) {
            $claims = JwtService::verify($token);
            if ($claims !== null) {
                JwtService::revoke((string)$claims['jti'], (int)$claims['exp']);
            }
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
        $this->json(['success' => true, 'message' => 'Sesion cerrada y token revocado.']);
    }

    public function usuarios(): void {
        $user = $this->requireApiRole(['ADMIN']);
        /** @var Usuario $userModel */
        $userModel = $this->model('Usuario');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            RateLimiter::check('api_write:' . (int)$user['id'], 60, 60);
            $input = DtoValidator::parseJsonInput(['nombre', 'apellido', 'documento', 'correo', 'telefono', 'contrasena', 'rol']);
            $id = $userModel->create([
                'nombre'     => DtoValidator::requireString($input, 'nombre', 2, 100),
                'apellido'   => DtoValidator::optionalString($input, 'apellido', 100) ?? '',
                'documento'  => DtoValidator::requireString($input, 'documento', 4, 30),
                'correo'     => DtoValidator::requireEmail($input, 'correo'),
                'telefono'   => DtoValidator::optionalString($input, 'telefono', 20),
                'contrasena' => DtoValidator::requireString($input, 'contrasena', 8, 128),
                'rol'        => DtoValidator::requireEnum($input, 'rol', ['ADMIN', 'CAJERO', 'MESERO', 'COCINA', 'CLIENTE']),
            ]);
            $this->json(['success' => true, 'id_usuario' => $id], 201);
        }

        if (!empty($_GET['documento'])) {
            $found = $userModel->findByDocument((string)$_GET['documento']);
            $this->json(['success' => true, 'usuario' => $found]);
        }
        $this->json(['success' => true, 'usuarios' => $userModel->getAll()]);
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
            $this->json(['success' => true, 'id_cliente' => $id], 201);
        }

        if (!empty($_GET['documento'])) {
            $found = $clienteModel->findByDocument((string)$_GET['documento']);
            $this->json(['success' => true, 'cliente' => $found]);
        }
        $this->json(['success' => true, 'clientes' => $clienteModel->getAll()]);
    }

    public function pedidos(): void {
        $user = $this->requireApiRole(['ADMIN', 'CAJERO', 'MESERO', 'COCINA']);
        /** @var Pedido $pedidoModel */
        $pedidoModel = $this->model('Pedido');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            RateLimiter::check('api_write:' . (int)$user['id'], 60, 60);
            $raw = file_get_contents('php://input');
            $decoded = json_decode((string)$raw, true);
            if (!is_array($decoded)) {
                throw new DomainValidationException('Payload JSON malformado.', 400);
            }

            if (array_key_exists('id_pedido', $decoded)) {
                $input = DtoValidator::parseJsonInput(['id_pedido', 'estado']);
                $idPedido = DtoValidator::requireInt($input, 'id_pedido');
                $estado = DtoValidator::requireEnum($input, 'estado', ['EN_PREPARACION', 'LISTO', 'ENTREGADO', 'PAGADO', 'CANCELADO']);

                $pedido = $pedidoModel->getById($idPedido);
                if (!$pedido) {
                    throw new DomainValidationException('Pedido no encontrado.');
                }
                if ($user['rol'] === 'MESERO' && (int)$pedido['id_mesero'] !== (int)$user['id']) {
                    throw new ForbiddenException('No puede modificar pedidos asignados a otro mesero.');
                }
                if (in_array($user['rol'], ['MESERO', 'COCINA'], true) && $estado === 'PAGADO') {
                    throw new ForbiddenException('Solo CAJERO o ADMIN pueden marcar un pedido como PAGADO.');
                }

                $updated = $pedidoModel->updateStatus($idPedido, $estado);
                $this->json(['success' => true, 'pedido' => $updated]);
            }

            $input = DtoValidator::parseJsonInput(['id_cliente', 'id_mesa', 'observaciones', 'items']);
            if (empty($input['items']) || !is_array($input['items'])) {
                throw new DomainValidationException('El pedido debe incluir al menos un item.');
            }
            $created = $pedidoModel->createWithItems(
                (int)$user['id'],
                DtoValidator::optionalInt($input, 'id_cliente'),
                DtoValidator::optionalInt($input, 'id_mesa'),
                $input['items'],
                DtoValidator::optionalString($input, 'observaciones', 500)
            );
            $this->json(['success' => true, 'pedido' => $created], 201);
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
            $this->json(['success' => true, 'pedido' => $pedido]);
        }

        $filterMesero = ($user['rol'] === 'MESERO') ? (int)$user['id'] : null;
        $this->json(['success' => true, 'pedidos' => $pedidoModel->getAll($filterMesero)]);
    }

    public function pedidoPersonalizacion(): void {
        $this->requireApiRole(['ADMIN', 'CAJERO', 'MESERO']);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            throw new DomainValidationException('Metodo HTTP no permitido.', 400);
        }
        $input = DtoValidator::parseJsonInput(['id_detalle', 'cantidad', 'termino_coccion', 'observaciones', 'ingredientes']);
        /** @var Pedido $pedidoModel */
        $pedidoModel = $this->model('Pedido');
        $res = $pedidoModel->agregarPersonalizacion([
            'id_detalle'      => DtoValidator::requireInt($input, 'id_detalle'),
            'cantidad'        => DtoValidator::optionalInt($input, 'cantidad', 1) ?? 1,
            'termino_coccion' => DtoValidator::optionalString($input, 'termino_coccion', 50),
            'observaciones'   => DtoValidator::optionalString($input, 'observaciones', 255),
            'ingredientes'    => is_array($input['ingredientes'] ?? null) ? $input['ingredientes'] : [],
        ]);
        $this->json(['success' => true, 'personalizacion' => $res], 201);
    }

    public function pedidoDescuento(): void {
        $user = $this->requireApiRole(['ADMIN', 'CAJERO']);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            throw new DomainValidationException('Metodo HTTP no permitido.', 400);
        }
        $input = DtoValidator::parseJsonInput(['id_pedido', 'id_descuento', 'porcentaje_aplicado']);
        /** @var Pedido $pedidoModel */
        $pedidoModel = $this->model('Pedido');
        $res = $pedidoModel->aplicarDescuento(
            DtoValidator::requireInt($input, 'id_pedido'),
            DtoValidator::requireInt($input, 'id_descuento'),
            (int)$user['id'],
            isset($input['porcentaje_aplicado']) ? DtoValidator::requirePositiveAmount($input, 'porcentaje_aplicado', false) : null
        );
        $this->json(['success' => true, 'pedido' => $res]);
    }

    public function mesas(): void {
        $this->requireApiRole(['ADMIN', 'CAJERO', 'MESERO']);
        /** @var Mesa $mesaModel */
        $mesaModel = $this->model('Mesa');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $input = DtoValidator::parseJsonInput(['numero_mesa', 'numeros_mesa', 'estado']);
            $estado = DtoValidator::requireEnum($input, 'estado', ['DISPONIBLE', 'OCUPADA', 'RESERVADA', 'INACTIVA']);
            if (array_key_exists('numeros_mesa', $input)) {
                if (array_key_exists('numero_mesa', $input) || !is_array($input['numeros_mesa']) || $input['numeros_mesa'] === []) {
                    throw new DomainValidationException('La lista de mesas seleccionadas no es valida.');
                }
                if (count($input['numeros_mesa']) > 100) {
                    throw new DomainValidationException('No se pueden actualizar mas de 100 mesas a la vez.');
                }
                $numeros = [];
                foreach ($input['numeros_mesa'] as $numero) {
                    $numeros[] = DtoValidator::requireInt(['numero_mesa' => $numero], 'numero_mesa');
                }
                $mesaModel->updateStatuses($numeros, $estado);
            } else {
                $num = DtoValidator::requireInt($input, 'numero_mesa');
                $mesaModel->updateStatus($num, $estado);
            }
            $this->json(['success' => true]);
        }

        $this->json(['success' => true, 'mesas' => $mesaModel->getAll()]);
    }

    public function crearMesa(): void {
        $user = $this->requireApiRole(['ADMIN']);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->json(['success' => false, 'message' => 'Metodo no permitido.'], 405);
        }

        RateLimiter::check('api_write:' . (int)$user['id'], 60, 60);
        $input = DtoValidator::parseJsonInput(['capacidad']);
        $capacidad = DtoValidator::requireInt($input, 'capacidad', 1, 12);
        /** @var Mesa $mesaModel */
        $mesaModel = $this->model('Mesa');
        $mesa = $mesaModel->createMesa($capacidad);
        $this->json(['success' => true, 'mesa' => $mesa], 201);
    }

    public function productos(): void {
        $this->requireApiAuth();
        /** @var Producto $prodModel */
        $prodModel = $this->model('Producto');
        $this->json(['success' => true, 'productos' => $prodModel->getAll()]);
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
        $this->json(['success' => true, 'caja' => $cerrada]);
    }

    public function pagos(): void {
        $user = $this->requireApiRole(['ADMIN', 'CAJERO']);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            throw new DomainValidationException('Metodo HTTP no permitido.', 400);
        }
        RateLimiter::check('api_write:' . (int)$user['id'], 60, 60);
        $input = DtoValidator::parseJsonInput(['id_pedido', 'id_caja', 'metodo_pago', 'valor', 'estado', 'referencia', 'propina']);

        $idCaja = DtoValidator::requireInt($input, 'id_caja');
        /** @var Caja $cajaModel */
        $cajaModel = $this->model('Caja');
        $caja = $cajaModel->findHeaderById($idCaja);
        if (!$caja) {
            throw new DomainValidationException('Caja no encontrada.');
        }
        if ($user['rol'] !== 'ADMIN' && (int)$caja['id_cajero'] !== (int)$user['id']) {
            throw new ForbiddenException('No puede registrar pagos en la caja de otro cajero.');
        }

        $pago = $cajaModel->registrarPago([
            'id_pedido'   => DtoValidator::requireInt($input, 'id_pedido'),
            'id_caja'     => $idCaja,
            'metodo_pago' => DtoValidator::requireEnum($input, 'metodo_pago', ['EFECTIVO', 'TARJETA_CREDITO', 'TARJETA_DEBITO', 'TRANSFERENCIA', 'NEQUI', 'DAVIPLATA', 'OTRO']),
            'valor'       => DtoValidator::requirePositiveAmount($input, 'valor', false),
            'estado'      => !empty($input['estado']) ? DtoValidator::requireEnum($input, 'estado', ['PENDIENTE', 'COMPLETADO']) : 'COMPLETADO',
            'referencia'  => DtoValidator::optionalString($input, 'referencia', 100),
            'propina'     => isset($input['propina']) ? DtoValidator::requirePositiveAmount($input, 'propina', true) : 0,
        ]);
        $this->json(['success' => true, 'pago' => $pago], 201);
    }

    public function propinaDistribuir(): void {
        $this->requireApiRole(['ADMIN', 'CAJERO']);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            throw new DomainValidationException('Metodo HTTP no permitido.', 400);
        }
        $input = DtoValidator::parseJsonInput(['id_propina', 'distribuciones']);
        if (empty($input['distribuciones']) || !is_array($input['distribuciones'])) {
            throw new DomainValidationException('Debe especificar las distribuciones de propina.');
        }
        /** @var Caja $cajaModel */
        $cajaModel = $this->model('Caja');
        $res = $cajaModel->distribuirPropina(
            DtoValidator::requireInt($input, 'id_propina'),
            $input['distribuciones']
        );
        $this->json(['success' => true, 'propina' => $res]);
    }

    public function facturas(): void {
        $user = $this->requireApiRole(['ADMIN', 'CAJERO', 'MESERO']);
        /** @var Caja $cajaModel */
        $cajaModel = $this->model('Caja');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!in_array($user['rol'], ['ADMIN', 'CAJERO'], true)) {
                throw new ForbiddenException('Solo ADMIN o CAJERO pueden emitir facturas.');
            }
            $input = DtoValidator::parseJsonInput(['id_pedido', 'numero_factura', 'xml', 'aceptar', 'cufe']);
            $factura = $cajaModel->emitirFactura([
                'id_pedido'      => DtoValidator::requireInt($input, 'id_pedido'),
                'numero_factura' => DtoValidator::requireString($input, 'numero_factura', 3, 50),
                'xml'            => DtoValidator::optionalString($input, 'xml', 65535),
                'aceptar'        => !empty($input['aceptar']),
                'cufe'           => DtoValidator::optionalString($input, 'cufe', 128),
            ]);
            $this->json(['success' => true, 'factura' => $factura], 201);
        }

        $filterMesero = ($user['rol'] === 'MESERO') ? (int)$user['id'] : null;
        $this->json(['success' => true, 'facturas' => $cajaModel->getFacturas($filterMesero)]);
    }

    public function facturaAnular(): void {
        $this->requireApiRole(['ADMIN', 'CAJERO']);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            throw new DomainValidationException('Metodo HTTP no permitido.', 400);
        }
        $input = DtoValidator::parseJsonInput(['id_factura']);
        /** @var Caja $cajaModel */
        $cajaModel = $this->model('Caja');
        $res = $cajaModel->anularFactura(DtoValidator::requireInt($input, 'id_factura'));
        $this->json(['success' => true, 'factura' => $res]);
    }

    public function descuentos(): void {
        $user = $this->requireApiRole(['ADMIN']);
        $db = Database::getConnection();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $input = DtoValidator::parseJsonInput(['nombre', 'porcentaje', 'motivo']);
            $nombre = DtoValidator::requireString($input, 'nombre', 2, 100);
            $porcentaje = DtoValidator::requirePositiveAmount($input, 'porcentaje', false);
            $motivo = DtoValidator::optionalString($input, 'motivo', 255);

            if ($db !== null) {
                $stmt = $db->prepare(
                    'INSERT INTO descuento (nombre, porcentaje, motivo, autorizado_por) VALUES (:n, :p, :m, :a) RETURNING id_descuento'
                );
                $stmt->execute([
                    ':n' => $nombre,
                    ':p' => $porcentaje,
                    ':m' => $motivo,
                    ':a' => (int)$user['id'],
                ]);
                $row = $stmt->fetch();
                $this->json(['success' => true, 'id_descuento' => (int)$row['id_descuento']], 201);
            }

            $newId = NormalizedStore::withTransaction(static function (array &$state) use ($nombre, $porcentaje, $motivo, $user): int {
                $maxId = 0;
                foreach ($state['descuento'] as $d) {
                    $maxId = max($maxId, (int)$d['id_descuento']);
                }
                $newId = $maxId + 1;
                $state['descuento'][] = [
                    'id_descuento'   => $newId,
                    'nombre'         => $nombre,
                    'porcentaje'     => $porcentaje,
                    'motivo'         => $motivo,
                    'autorizado_por' => (int)$user['id'],
                    'activo'         => true,
                ];
                return $newId;
            }, true);
            $this->json(['success' => true, 'id_descuento' => $newId], 201);
        }

        if ($db !== null) {
            $stmt = $db->query('SELECT * FROM descuento ORDER BY id_descuento DESC');
            $this->json(['success' => true, 'descuentos' => $stmt->fetchAll()]);
        }

        $descuentos = NormalizedStore::withTransaction(static fn(array &$s) => $s['descuento'], false);
        $this->json(['success' => true, 'descuentos' => $descuentos]);
    }

    public function actividad(): void {
        $this->requireApiRole(['ADMIN']);
        $db = Database::getConnection();
        if ($db !== null) {
            $stmt = $db->query('SELECT * FROM actividad ORDER BY id_actividad DESC LIMIT 100');
            $this->json(['success' => true, 'actividad' => $stmt->fetchAll()]);
        }
        $act = NormalizedStore::withTransaction(static fn(array &$s) => array_reverse($s['actividad']), false);
        $this->json(['success' => true, 'actividad' => $act]);
    }

    public function inventario(): void {
        $user = $this->requireApiRole(['ADMIN', 'CAJERO', 'MESERO', 'COCINA']);
        /** @var Producto $prodModel */
        $prodModel = $this->model('Producto');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if ($user['rol'] !== 'ADMIN') {
                throw new ForbiddenException('Solo ADMIN puede registrar movimientos de inventario.');
            }
            $input = DtoValidator::parseJsonInput(['id_ingrediente', 'tipo_movimiento', 'cantidad', 'motivo']);
            $idMov = $prodModel->registrarMovimientoInventario([
                'id_ingrediente'  => DtoValidator::requireInt($input, 'id_ingrediente'),
                'tipo_movimiento' => DtoValidator::requireEnum($input, 'tipo_movimiento', ['ENTRADA', 'SALIDA', 'AJUSTE_POSITIVO', 'AJUSTE_NEGATIVO', 'MERMA']),
                'cantidad'        => DtoValidator::requirePositiveAmount($input, 'cantidad', false),
                'motivo'          => DtoValidator::requireString($input, 'motivo', 3, 255),
                'id_usuario'      => (int)$user['id'],
            ]);
            $this->json(['success' => true, 'id_movimiento' => $idMov], 201);
        }

        $this->json(['success' => true, 'ingredientes' => $prodModel->getIngredientes()]);
    }

    public function asistencia(): void {
        $this->requireApiRole(['ADMIN']);
        $db = Database::getConnection();
        if ($db !== null) {
            $stmt = $db->query('SELECT * FROM vw_asistencia_horas ORDER BY id_asistencia DESC');
            $this->json(['success' => true, 'asistencia' => $stmt->fetchAll()]);
        }
        $rows = NormalizedStore::withTransaction(static fn(array &$s) => NormalizedStore::viewAsistenciaHoras($s), false);
        $this->json(['success' => true, 'asistencia' => $rows]);
    }

    public function conciliacion(): void {
        $this->requireApiRole(['ADMIN', 'CAJERO']);
        /** @var Pedido $pedidoModel */
        $pedidoModel = $this->model('Pedido');
        $this->json(['success' => true, 'conciliacion' => $pedidoModel->getConciliacion()]);
    }

    public function pqrs(): void {
        $this->requireApiAuth();
        /** @var Pqrs $pqrsModel */
        $pqrsModel = $this->model('Pqrs');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $input = DtoValidator::parseJsonInput(['tipo', 'descripcion', 'id_cliente', 'id_pedido']);
            $id = $pqrsModel->create([
                'tipo'        => DtoValidator::requireEnum($input, 'tipo', ['PETICION', 'QUEJA', 'RECLAMO', 'SUGERENCIA']),
                'descripcion' => DtoValidator::requireString($input, 'descripcion', 5, 2000),
                'id_cliente'  => DtoValidator::requireInt($input, 'id_cliente'),
                'id_pedido'   => DtoValidator::optionalInt($input, 'id_pedido'),
            ]);
            $this->json(['success' => true, 'id_pqrs' => $id], 201);
        }

        $this->json(['success' => true, 'pqrs' => $pqrsModel->getAll()]);
    }

    public function estadoDb(): void {
        $this->json([
            'conectado_postgres' => Database::isConnected(),
            'motor_activo'       => Database::isConnected() ? 'PostgreSQL 17 (esquema restaurante)' : 'Motor Transaccional Normalizado (25 tablas + AES-256 + Blind Index)',
            'tablas_esquema'     => 25,
            'vistas_analiticas'  => ['vw_usuarios_publico', 'vw_clientes_publico', 'vw_resumen_caja', 'vw_asistencia_horas', 'vw_conciliacion_pedidos'],
        ]);
    }
}
