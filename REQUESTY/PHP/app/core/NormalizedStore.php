<?php
/**
 * Motor Transaccional Normalizado (25 Tablas + 4 Vistas + Maquinas de Estado + Cifrado AES-256 / Blind Index / Bcrypt)
 * Respaldo local 100% funcional homologado con el esquema PostgreSQL 17 (database/schema.sql y seed_demo.sql).
 */
class NormalizedStore {
    private static function storageFile(): string {
        $dir = ROOTPATH . '/storage/db';
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        return $dir . '/restaurante_db_norm.json';
    }

    /**
     * Ejecuta una operacion de lectura o escritura con bloqueo atomico (FOR UPDATE / FOR SHARE).
     */
    public static function withTransaction(callable $callback, bool $write = true) {
        $file = self::storageFile();
        $fp = fopen($file, 'c+');
        if ($fp === false) {
            throw new RuntimeException('No se pudo abrir el almacenamiento transaccional.');
        }

        $lockType = $write ? LOCK_EX : LOCK_SH;
        if (!flock($fp, $lockType)) {
            fclose($fp);
            throw new RuntimeException('Timeout al adquirir bloqueo transaccional.');
        }

        try {
            $raw = stream_get_contents($fp);
            $state = (is_string($raw) && trim($raw) !== '') ? json_decode($raw, true) : null;
            if (!is_array($state) || empty($state['initialized'])) {
                $state = self::buildSeedState();
                if (!$write) {
                    flock($fp, LOCK_UN);
                    flock($fp, LOCK_EX);
                    $write = true;
                }
                self::writeState($fp, $state);
            }

            $snapshot = $state;
            try {
                $result = $callback($state);
                if ($write) {
                    self::writeState($fp, $state);
                }
                return $result;
            } catch (Throwable $e) {
                $state = $snapshot;
                throw $e;
            }
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    private static function writeState($fp, array $state): void {
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        fflush($fp);
    }

    public static function resetToSeed(): void {
        self::withTransaction(static function (array &$state): void {
            $state = self::buildSeedState();
        }, true);
    }

    /**
     * Semilla inicial identica a database/seed_demo.sql + cuentas de demostracion web.
     */
    private static function buildSeedState(): array {
        $now = date('c');
        return [
            'initialized' => true,
            'rol' => [
                ['id_rol' => 1, 'nombre' => 'ADMIN',   'descripcion' => 'Administrador general del restaurante'],
                ['id_rol' => 2, 'nombre' => 'CAJERO',  'descripcion' => 'Encargado de apertura, cobros y cierre de caja'],
                ['id_rol' => 3, 'nombre' => 'MESERO',  'descripcion' => 'Atencion en mesas y toma de pedidos'],
                ['id_rol' => 4, 'nombre' => 'COCINA',  'descripcion' => 'Preparacion y despacho de pedidos en cocina'],
                ['id_rol' => 5, 'nombre' => 'CLIENTE', 'descripcion' => 'Cliente registrado del restaurante'],
            ],
            'usuario' => [
                [
                    'id_usuario'           => 1,
                    'nombre'               => 'Carlos',
                    'apellido'             => 'Gomez',
                    'documento_cifrado'    => CryptoService::encryptDocument('1001001001'),
                    'documento_hash'       => CryptoService::hashDocument('1001001001'),
                    'correo'               => 'admin@restaurante.local',
                    'telefono'             => '3001112233',
                    'contrasena'           => CryptoService::hashPassword('AdminRest2026*', true),
                    'estado'               => 'ACTIVO',
                    'fecha_registro'       => $now,
                    'ultimo_inicio_sesion' => $now,
                    'id_rol'               => 1,
                ],
                [
                    'id_usuario'           => 2,
                    'nombre'               => 'Laura',
                    'apellido'             => 'Martinez',
                    'documento_cifrado'    => CryptoService::encryptDocument('1002002002'),
                    'documento_hash'       => CryptoService::hashDocument('1002002002'),
                    'correo'               => 'cajero@restaurante.local',
                    'telefono'             => '3002223344',
                    'contrasena'           => CryptoService::hashPassword('CajeroRest2026*', true),
                    'estado'               => 'ACTIVO',
                    'fecha_registro'       => $now,
                    'ultimo_inicio_sesion' => $now,
                    'id_rol'               => 2,
                ],
                [
                    'id_usuario'           => 3,
                    'nombre'               => 'Mateo',
                    'apellido'             => 'Ruiz',
                    'documento_cifrado'    => CryptoService::encryptDocument('1003003003'),
                    'documento_hash'       => CryptoService::hashDocument('1003003003'),
                    'correo'               => 'mesero@restaurante.local',
                    'telefono'             => '3003334455',
                    'contrasena'           => CryptoService::hashPassword('MeseroRest2026*', true),
                    'estado'               => 'ACTIVO',
                    'fecha_registro'       => $now,
                    'ultimo_inicio_sesion' => $now,
                    'id_rol'               => 3,
                ],
                // Cuentas adicionales de compatibilidad con el frontend de VS Code
                [
                    'id_usuario'           => 4,
                    'nombre'               => 'Juan Pérez',
                    'apellido'             => 'Admin',
                    'documento_cifrado'    => CryptoService::encryptDocument('1004004004'),
                    'documento_hash'       => CryptoService::hashDocument('1004004004'),
                    'correo'               => 'admin@requesty.com',
                    'telefono'             => '3101234567',
                    'contrasena'           => CryptoService::hashPassword('admin123', false),
                    'estado'               => 'ACTIVO',
                    'fecha_registro'       => $now,
                    'ultimo_inicio_sesion' => $now,
                    'id_rol'               => 1,
                ],
                [
                    'id_usuario'           => 5,
                    'nombre'               => 'Carlos Cajero',
                    'apellido'             => 'Turno',
                    'documento_cifrado'    => CryptoService::encryptDocument('1005005005'),
                    'documento_hash'       => CryptoService::hashDocument('1005005005'),
                    'correo'               => 'cajero@requesty.com',
                    'telefono'             => '3119876543',
                    'contrasena'           => CryptoService::hashPassword('caja123', false),
                    'estado'               => 'ACTIVO',
                    'fecha_registro'       => $now,
                    'ultimo_inicio_sesion' => $now,
                    'id_rol'               => 2,
                ],
                [
                    'id_usuario'           => 6,
                    'nombre'               => 'Mateo Mesero',
                    'apellido'             => 'Sala',
                    'documento_cifrado'    => CryptoService::encryptDocument('1006006006'),
                    'documento_hash'       => CryptoService::hashDocument('1006006006'),
                    'correo'               => 'mesero@requesty.com',
                    'telefono'             => '3124567890',
                    'contrasena'           => CryptoService::hashPassword('mesero123', false),
                    'estado'               => 'ACTIVO',
                    'fecha_registro'       => $now,
                    'ultimo_inicio_sesion' => $now,
                    'id_rol'               => 3,
                ],
                [
                    'id_usuario'           => 7,
                    'nombre'               => 'Claudia Cocina',
                    'apellido'             => 'Chef',
                    'documento_cifrado'    => CryptoService::encryptDocument('1007007007'),
                    'documento_hash'       => CryptoService::hashDocument('1007007007'),
                    'correo'               => 'cocina@requesty.com',
                    'telefono'             => '3131122334',
                    'contrasena'           => CryptoService::hashPassword('cocina123', false),
                    'estado'               => 'ACTIVO',
                    'fecha_registro'       => $now,
                    'ultimo_inicio_sesion' => $now,
                    'id_rol'               => 4,
                ],
                [
                    'id_usuario'           => 8,
                    'nombre'               => 'Cliente Demo',
                    'apellido'             => 'VIP',
                    'documento_cifrado'    => CryptoService::encryptDocument('1008008008'),
                    'documento_hash'       => CryptoService::hashDocument('1008008008'),
                    'correo'               => 'cliente@gmail.com',
                    'telefono'             => '3145566778',
                    'contrasena'           => CryptoService::hashPassword('cliente123', false),
                    'estado'               => 'ACTIVO',
                    'fecha_registro'       => $now,
                    'ultimo_inicio_sesion' => $now,
                    'id_rol'               => 5,
                ],
            ],
            'actividad' => [
                ['id_actividad' => 1, 'id_usuario' => 1, 'accion' => 'Creacion de catalogo inicial', 'modulo' => 'INVENTARIO', 'fecha_hora' => $now, 'direccion_ip' => '192.168.1.10'],
                ['id_actividad' => 2, 'id_usuario' => 2, 'accion' => 'Apertura de caja turno principal', 'modulo' => 'CAJA', 'fecha_hora' => $now, 'direccion_ip' => '192.168.1.11'],
            ],
            'asistencia' => [
                [
                    'id_asistencia' => 1,
                    'id_usuario'    => 3,
                    'hora_entrada'  => '2026-11-17T18:00:00-05:00',
                    'hora_salida'   => '2026-11-18T03:30:00-05:00',
                    'fecha'         => '2026-11-17',
                ],
            ],
            'cliente' => [
                [
                    'id_cliente'        => 1,
                    'tipo_documento'    => 'CC',
                    'documento_cifrado' => CryptoService::encryptDocument('1098765432'),
                    'documento_hash'    => CryptoService::hashDocument('1098765432'),
                    'nombre'            => 'Ana Lopez',
                    'telefono'          => '3104445566',
                    'correo'            => 'ana.lopez@correo.com',
                    'fecha_registro'    => $now,
                    'activo'            => true,
                ],
            ],
            'mesa' => [
                ['id_mesa' => 1, 'numero_mesa' => 1, 'capacidad' => 4, 'codigo_qr' => 'QR-MESA-001', 'estado' => 'OCUPADA'],
                ['id_mesa' => 2, 'numero_mesa' => 2, 'capacidad' => 4, 'codigo_qr' => 'QR-MESA-002', 'estado' => 'DISPONIBLE'],
                ['id_mesa' => 3, 'numero_mesa' => 3, 'capacidad' => 2, 'codigo_qr' => 'QR-MESA-003', 'estado' => 'DISPONIBLE'],
                ['id_mesa' => 4, 'numero_mesa' => 4, 'capacidad' => 6, 'codigo_qr' => 'QR-MESA-004', 'estado' => 'RESERVADA'],
                ['id_mesa' => 5, 'numero_mesa' => 5, 'capacidad' => 4, 'codigo_qr' => 'QR-MESA-005', 'estado' => 'DISPONIBLE'],
                ['id_mesa' => 6, 'numero_mesa' => 6, 'capacidad' => 8, 'codigo_qr' => 'QR-MESA-006', 'estado' => 'DISPONIBLE'],
            ],
            'categoria' => [
                ['id_categoria' => 1, 'nombre' => 'Hamburguesas',   'descripcion' => 'Hamburguesas artesanales a la parrilla', 'activa' => true],
                ['id_categoria' => 2, 'nombre' => 'Platos Fuertes', 'descripcion' => 'Platos tradicionales principales',       'activa' => true],
                ['id_categoria' => 3, 'nombre' => 'Desayunos',      'descripcion' => 'Caldos, huevos y desayunos tipicos',     'activa' => true],
                ['id_categoria' => 4, 'nombre' => 'Bebidas',        'descripcion' => 'Jugos naturales y bebidas frias',        'activa' => true],
            ],
            'ingrediente' => [
                ['id_ingrediente' => 1, 'nombre' => 'Carne de res',         'unidad_medida' => 'g',      'stock_actual' => 4640.00, 'stock_minimo' => 1000.00, 'costo' => 35.00,   'estado' => 'ACTIVO'],
                ['id_ingrediente' => 2, 'nombre' => 'Queso cheddar',        'unidad_medida' => 'unidad', 'stock_actual' => 47.00,   'stock_minimo' => 10.00,   'costo' => 1500.00, 'estado' => 'ACTIVO'],
                ['id_ingrediente' => 3, 'nombre' => 'Cebolla caramelizada', 'unidad_medida' => 'g',      'stock_actual' => 1970.00, 'stock_minimo' => 500.00,  'costo' => 12.00,   'estado' => 'ACTIVO'],
            ],
            'inventario' => [
                ['id_movimiento' => 1, 'id_ingrediente' => 1, 'tipo_movimiento' => 'ENTRADA', 'cantidad' => 5000.00, 'motivo' => 'Compra inicial proveedor', 'id_usuario' => 1, 'id_detalle' => null, 'fecha_hora' => $now],
                ['id_movimiento' => 2, 'id_ingrediente' => 2, 'tipo_movimiento' => 'ENTRADA', 'cantidad' => 50.00,   'motivo' => 'Compra inicial proveedor', 'id_usuario' => 1, 'id_detalle' => null, 'fecha_hora' => $now],
                ['id_movimiento' => 3, 'id_ingrediente' => 3, 'tipo_movimiento' => 'ENTRADA', 'cantidad' => 2000.00, 'motivo' => 'Compra inicial proveedor', 'id_usuario' => 1, 'id_detalle' => null, 'fecha_hora' => $now],
                ['id_movimiento' => 4, 'id_ingrediente' => 1, 'tipo_movimiento' => 'SALIDA',  'cantidad' => 360.00,  'motivo' => 'Consumo pedido #1',        'id_usuario' => 3, 'id_detalle' => 1,    'fecha_hora' => $now],
                ['id_movimiento' => 5, 'id_ingrediente' => 2, 'tipo_movimiento' => 'SALIDA',  'cantidad' => 3.00,    'motivo' => 'Consumo pedido #1 (incluye 1 extra)', 'id_usuario' => 3, 'id_detalle' => 1, 'fecha_hora' => $now],
                ['id_movimiento' => 6, 'id_ingrediente' => 3, 'tipo_movimiento' => 'SALIDA',  'cantidad' => 30.00,   'motivo' => 'Consumo pedido #1 (1 unidad sin cebolla)', 'id_usuario' => 3, 'id_detalle' => 1, 'fecha_hora' => $now],
            ],
            'producto' => [
                [
                    'id_producto'         => 1,
                    'nombre'              => 'Hamburguesa Clasica',
                    'descripcion'         => 'Carne 180g, queso cheddar y cebolla',
                    'precio'              => 28000.00,
                    'impuesto_porcentaje' => 8.00,
                    'imagen'              => 'img/tarjeta/bandeja-paisa.jpg',
                    'disponible'          => true,
                    'tiempo_preparacion'  => 15,
                    'id_categoria'        => 1,
                ],
                [
                    'id_producto'         => 2,
                    'nombre'              => 'Bandeja Paisa',
                    'descripcion'         => 'Frijoles, arroz, chicharron, carne molida, chorizo, huevo, tajadas y aguacate',
                    'precio'              => 32000.00,
                    'impuesto_porcentaje' => 8.00,
                    'imagen'              => 'img/tarjeta/bandeja-paisa.jpg',
                    'disponible'          => true,
                    'tiempo_preparacion'  => 20,
                    'id_categoria'        => 2,
                ],
                [
                    'id_producto'         => 3,
                    'nombre'              => 'Caldo de Costilla',
                    'descripcion'         => 'Costilla de res seleccionada con papa, cilantro fresco y arepa',
                    'precio'              => 18000.00,
                    'impuesto_porcentaje' => 8.00,
                    'imagen'              => 'img/tarjeta/caldo-de-costilla.jpg',
                    'disponible'          => true,
                    'tiempo_preparacion'  => 12,
                    'id_categoria'        => 3,
                ],
                [
                    'id_producto'         => 4,
                    'nombre'              => 'Huevos Pericos',
                    'descripcion'         => 'Huevos revueltos con tomate y cebolla, acompanados de arepa con queso',
                    'precio'              => 14000.00,
                    'impuesto_porcentaje' => 8.00,
                    'imagen'              => 'img/tarjeta/huevos-pericos.jpg',
                    'disponible'          => true,
                    'tiempo_preparacion'  => 10,
                    'id_categoria'        => 3,
                ],
                [
                    'id_producto'         => 5,
                    'nombre'              => 'Jugo de Mora',
                    'descripcion'         => 'Jugo 100% natural en agua o en leche',
                    'precio'              => 8500.00,
                    'impuesto_porcentaje' => 8.00,
                    'imagen'              => 'img/tarjeta/jugo-de-mora.jpg',
                    'disponible'          => true,
                    'tiempo_preparacion'  => 5,
                    'id_categoria'        => 4,
                ],
            ],
            'producto_ingrediente' => [
                ['id_producto' => 1, 'id_ingrediente' => 1, 'cantidad' => 180.00],
                ['id_producto' => 1, 'id_ingrediente' => 2, 'cantidad' => 1.00],
                ['id_producto' => 1, 'id_ingrediente' => 3, 'cantidad' => 30.00],
            ],
            'caja' => [
                [
                    'id_caja'          => 1,
                    'id_cajero'        => 2,
                    'fecha_apertura'   => $now,
                    'fecha_cierre'     => $now,
                    'monto_apertura'   => 200000.00,
                    'efectivo_contado' => 235000.00,
                    'estado'           => 'CERRADA',
                    'observaciones'    => 'Cierre turno sin diferencias',
                ],
            ],
            'pedido' => [
                [
                    'id_pedido'       => 1,
                    'fecha_hora'      => $now,
                    'estado'          => 'PAGADO',
                    'observaciones'   => 'Mesa junto a la ventana',
                    'subtotal'        => 59000.00,
                    'descuento_total' => 5900.00,
                    'impuesto_total'  => 4248.00,
                    'total'           => 57348.00,
                    'id_cliente'      => 1,
                    'id_mesa'         => 1,
                    'id_mesero'       => 3,
                ],
            ],
            'detalle_pedido' => [
                [
                    'id_detalle'          => 1,
                    'id_pedido'           => 1,
                    'id_producto'         => 1,
                    'cantidad'            => 2,
                    'precio_unitario'     => 28000.00,
                    'impuesto_porcentaje' => 8.00,
                    'observaciones'       => null,
                ],
            ],
            'personalizacion_pedido' => [
                [
                    'id_personalizacion' => 1,
                    'id_detalle'         => 1,
                    'cantidad'           => 1,
                    'termino_coccion'    => 'TERMINO MEDIO',
                    'observaciones'      => 'Una sin cebolla y con queso extra',
                ],
            ],
            'personalizacion_ingrediente' => [
                ['id_personalizacion' => 1, 'id_ingrediente' => 3, 'tipo_ajuste' => 'ELIMINAR', 'cantidad' => 1.00, 'costo_extra' => 0.00],
                ['id_personalizacion' => 1, 'id_ingrediente' => 2, 'tipo_ajuste' => 'EXTRA',    'cantidad' => 1.00, 'costo_extra' => 3000.00],
            ],
            'descuento' => [
                ['id_descuento' => 1, 'nombre' => 'Cliente Frecuente 10%', 'porcentaje' => 10.00, 'motivo' => 'Beneficio cliente registrado', 'autorizado_por' => 1, 'activo' => true],
            ],
            'pedido_descuento' => [
                ['id_pedido' => 1, 'id_descuento' => 1, 'autorizado_por' => 1, 'porcentaje_aplicado' => 10.00, 'monto_descontado' => 5900.00],
            ],
            'pago' => [
                ['id_pago' => 1, 'id_pedido' => 1, 'id_caja' => 1, 'metodo_pago' => 'EFECTIVO', 'valor' => 30000.00, 'estado' => 'COMPLETADO', 'referencia' => 'EF-001',    'fecha_hora' => $now],
                ['id_pago' => 2, 'id_pedido' => 1, 'id_caja' => 1, 'metodo_pago' => 'NEQUI',    'valor' => 27348.00, 'estado' => 'COMPLETADO', 'referencia' => 'NQ-987654', 'fecha_hora' => $now],
            ],
            'propina' => [
                ['id_propina' => 1, 'id_pago' => 1, 'porcentaje' => 10.00, 'valor' => 5000.00, 'distribuida' => true],
            ],
            'propina_distribucion' => [
                ['id_propina' => 1, 'id_usuario' => 3, 'monto' => 5000.00],
            ],
            'factura' => [
                [
                    'id_factura'      => 1,
                    'id_pedido'       => 1,
                    'numero_factura'  => 'FE-000001',
                    'fecha_emision'   => $now,
                    'cufe'            => null,
                    'xml'             => '<Error>Timeout DIAN intento 1</Error>',
                    'subtotal'        => 59000.00,
                    'descuento_total' => 5900.00,
                    'impuesto_total'  => 4248.00,
                    'total'           => 57348.00,
                    'estado_dian'     => 'RECHAZADA',
                ],
                [
                    'id_factura'      => 2,
                    'id_pedido'       => 1,
                    'numero_factura'  => 'FE-000002',
                    'fecha_emision'   => $now,
                    'cufe'            => 'CUFE-DEMO-1234567890ABCDEF',
                    'xml'             => '<Invoice><ID>FE-000002</ID></Invoice>',
                    'subtotal'        => 59000.00,
                    'descuento_total' => 5900.00,
                    'impuesto_total'  => 4248.00,
                    'total'           => 57348.00,
                    'estado_dian'     => 'ACEPTADA',
                ],
            ],
            'pqrs' => [
                [
                    'id_pqrs'        => 1,
                    'tipo'           => 'SUGERENCIA',
                    'descripcion'    => 'Excelente atencion, sugiero ampliar carta de bebidas',
                    'estado'         => 'ABIERTA',
                    'fecha_creacion' => $now,
                    'id_cliente'     => 1,
                    'id_pedido'      => 1,
                ],
            ],
            'notificacion' => [
                ['id_notificacion' => 1, 'mensaje' => 'Pedido #1 listo para entregar en mesa 1', 'tipo' => 'PEDIDO',     'leida' => false, 'id_usuario' => 3, 'id_pedido' => 1,    'id_ingrediente' => null, 'fecha_hora' => $now],
                ['id_notificacion' => 2, 'mensaje' => 'Stock de queso cheddar verificado',       'tipo' => 'INVENTARIO', 'leida' => false, 'id_usuario' => 1, 'id_pedido' => null, 'id_ingrediente' => 2,    'fecha_hora' => $now],
            ],
            'reporte' => [
                ['id_reporte' => 1, 'tipo' => 'VENTAS_DIARIAS', 'fecha_inicio' => '2026-11-18', 'fecha_fin' => '2026-11-18', 'id_usuario' => 1, 'fecha_generacion' => $now],
            ],
        ];
    }

    // ========================================================================
    // VISTAS ANALITICAS Y DE SEGURIDAD (4 VISTAS DEL INFORME TECNICO)
    // ========================================================================

    public static function viewUsuariosPublico(array $state): array {
        $roles = [];
        foreach ($state['rol'] as $r) {
            $roles[(int)$r['id_rol']] = $r['nombre'];
        }
        $out = [];
        foreach ($state['usuario'] as $u) {
            $out[] = [
                'id_usuario'           => (int)$u['id_usuario'],
                'nombre'               => $u['nombre'],
                'apellido'             => $u['apellido'],
                'documento_hash'       => $u['documento_hash'],
                'correo'               => $u['correo'],
                'telefono'             => $u['telefono'],
                'estado'               => $u['estado'],
                'fecha_registro'       => $u['fecha_registro'],
                'ultimo_inicio_sesion' => $u['ultimo_inicio_sesion'],
                'id_rol'               => (int)$u['id_rol'],
                'rol_nombre'           => $roles[(int)$u['id_rol']] ?? 'MESERO',
                'rol'                  => $roles[(int)$u['id_rol']] ?? 'MESERO',
            ];
        }
        return $out;
    }

    public static function viewClientesPublico(array $state): array {
        $out = [];
        foreach ($state['cliente'] as $c) {
            $out[] = [
                'id_cliente'     => (int)$c['id_cliente'],
                'tipo_documento' => $c['tipo_documento'],
                'documento_hash' => $c['documento_hash'],
                'nombre'         => $c['nombre'],
                'telefono'       => $c['telefono'],
                'correo'         => $c['correo'],
                'fecha_registro' => $c['fecha_registro'],
                'activo'         => (bool)$c['activo'],
            ];
        }
        return $out;
    }

    public static function viewResumenCaja(array $state): array {
        $out = [];
        foreach ($state['caja'] as $c) {
            $idCaja = (int)$c['id_caja'];
            $ventasEfectivo = 0.0;
            $ventasDigitales = 0.0;
            $propinasEfectivo = 0.0;
            $propinasDigitales = 0.0;

            $propinasPorPago = [];
            foreach ($state['propina'] as $pr) {
                $propinasPorPago[(int)$pr['id_pago']] = ($propinasPorPago[(int)$pr['id_pago']] ?? 0.0) + (float)$pr['valor'];
            }

            foreach ($state['pago'] as $pg) {
                if ((int)$pg['id_caja'] === $idCaja && $pg['estado'] === 'COMPLETADO') {
                    $val = (float)$pg['valor'];
                    $tip = $propinasPorPago[(int)$pg['id_pago']] ?? 0.0;
                    if ($pg['metodo_pago'] === 'EFECTIVO') {
                        $ventasEfectivo += $val;
                        $propinasEfectivo += $tip;
                    } else {
                        $ventasDigitales += $val;
                        $propinasDigitales += $tip;
                    }
                }
            }

            $apertura = (float)$c['monto_apertura'];
            $esperado = round($apertura + $ventasEfectivo + $propinasEfectivo, 2);
            $contado = $c['efectivo_contado'] !== null ? round((float)$c['efectivo_contado'], 2) : null;
            $diferencia = $contado !== null ? round($contado - $esperado, 2) : null;

            $out[] = [
                'id_caja'            => $idCaja,
                'id_cajero'          => (int)$c['id_cajero'],
                'fecha_apertura'     => $c['fecha_apertura'],
                'fecha_cierre'       => $c['fecha_cierre'],
                'estado'             => $c['estado'],
                'monto_apertura'     => $apertura,
                'ventas_efectivo'    => round($ventasEfectivo, 2),
                'ventas_digitales'   => round($ventasDigitales, 2),
                'propinas_efectivo'  => round($propinasEfectivo, 2),
                'propinas_digitales' => round($propinasDigitales, 2),
                'efectivo_esperado'  => $esperado,
                'efectivo_contado'   => $contado,
                'diferencia'         => $diferencia,
                'observaciones'      => $c['observaciones'],
            ];
        }
        usort($out, static fn($a, $b) => $b['id_caja'] <=> $a['id_caja']);
        return $out;
    }

    public static function viewConciliacionPedidos(array $state): array {
        $out = [];
        foreach ($state['pedido'] as $p) {
            $idPed = (int)$p['id_pedido'];
            $pagado = 0.0;
            $propinas = 0.0;

            $propinasPorPago = [];
            foreach ($state['propina'] as $pr) {
                $propinasPorPago[(int)$pr['id_pago']] = ($propinasPorPago[(int)$pr['id_pago']] ?? 0.0) + (float)$pr['valor'];
            }

            foreach ($state['pago'] as $pg) {
                if ((int)$pg['id_pedido'] === $idPed && $pg['estado'] === 'COMPLETADO') {
                    $pagado += (float)$pg['valor'];
                    $propinas += ($propinasPorPago[(int)$pg['id_pago']] ?? 0.0);
                }
            }

            $facturaActiva = null;
            foreach ($state['factura'] as $f) {
                if ((int)$f['id_pedido'] === $idPed && in_array($f['estado_dian'], ['ACEPTADA', 'PENDIENTE'], true)) {
                    $facturaActiva = $f;
                }
            }

            $total = round((float)$p['total'], 2);
            $pagado = round($pagado, 2);
            $out[$idPed] = [
                'id_pedido'       => $idPed,
                'estado'          => $p['estado'],
                'subtotal'        => round((float)$p['subtotal'], 2),
                'descuento_total' => round((float)$p['descuento_total'], 2),
                'impuesto_total'  => round((float)$p['impuesto_total'], 2),
                'total'           => $total,
                'total_pagado'    => $pagado,
                'saldo_pendiente' => round(max(0.0, $total - $pagado), 2),
                'total_propinas'  => round($propinas, 2),
                'numero_factura'  => $facturaActiva['numero_factura'] ?? null,
                'estado_dian'     => $facturaActiva['estado_dian'] ?? null,
            ];
        }
        return $out;
    }

    public static function viewAsistenciaHoras(array $state): array {
        $out = [];
        foreach ($state['asistencia'] as $a) {
            $t1 = strtotime((string)$a['hora_entrada']);
            $t2 = !empty($a['hora_salida']) ? strtotime((string)$a['hora_salida']) : null;
            $horas = ($t1 && $t2 && $t2 > $t1) ? round(($t2 - $t1) / 3600, 2) : 0.0;
            $extra = max(0.0, round($horas - 8.0, 2));
            $out[] = [
                'id_asistencia'    => (int)$a['id_asistencia'],
                'id_usuario'       => (int)$a['id_usuario'],
                'fecha'            => $a['fecha'],
                'hora_entrada'     => $a['hora_entrada'],
                'hora_salida'      => $a['hora_salida'],
                'horas_trabajadas' => $horas,
                'horas_extra'      => $extra,
            ];
        }
        return $out;
    }
}
