-- ============================================================================
-- Datos de prueba E2E completos (25 tablas) + cifrado AES-256 + Blind Index
-- Requiere haber configurado previamente en la sesion:
--   SET restaurante.encryption_key = '<APP_ENCRYPTION_KEY_MIN_32_CHARS>';
-- ============================================================================

SET search_path TO restaurante, public;

BEGIN;

INSERT INTO rol (nombre, descripcion) VALUES
    ('ADMIN', 'Administrador general del restaurante'),
    ('CAJERO', 'Encargado de apertura, cobros y cierre de caja'),
    ('MESERO', 'Atencion en mesas y toma de pedidos');

-- Contrasenas hasheadas con Bcrypt (cost 12) + documentos cifrados AES-256 + Blind Index HMAC-SHA256
INSERT INTO usuario (nombre, apellido, documento_cifrado, documento_hash, correo, telefono, contrasena, id_rol) VALUES
    ('Carlos', 'Gomez', fn_cifrar_documento('1001001001'), fn_hash_documento('1001001001'), 'admin@restaurante.local', '3001112233', 'AdminRest2026*', 1),
    ('Laura', 'Martinez', fn_cifrar_documento('1002002002'), fn_hash_documento('1002002002'), 'cajero@restaurante.local', '3002223344', 'CajeroRest2026*', 2),
    ('Mateo', 'Ruiz', fn_cifrar_documento('1003003003'), fn_hash_documento('1003003003'), 'mesero@restaurante.local', '3003334455', 'MeseroRest2026*', 3);

-- Auditoria inmutable y asistencia nocturna (fecha generada automaticamente en America/Bogota)
INSERT INTO actividad (id_usuario, accion, modulo, direccion_ip) VALUES
    (1, 'Creacion de catalogo inicial', 'INVENTARIO', '192.168.1.10'),
    (2, 'Apertura de caja turno principal', 'CAJA', '192.168.1.11');

INSERT INTO asistencia (id_usuario, hora_entrada, hora_salida) VALUES
    (3, '2026-11-17 18:00:00-05', '2026-11-18 03:30:00-05');

INSERT INTO cliente (tipo_documento, documento_cifrado, documento_hash, nombre, telefono, correo) VALUES
    ('CC', fn_cifrar_documento('1098765432'), fn_hash_documento('1098765432'), 'Ana Lopez', '3104445566', 'ana.lopez@correo.com');

INSERT INTO mesa (numero_mesa, capacidad, codigo_qr, estado) VALUES
    (1, 4, 'QR-MESA-001', 'OCUPADA');

INSERT INTO categoria (nombre, descripcion) VALUES
    ('Hamburguesas', 'Hamburguesas artesanales a la parrilla');

INSERT INTO ingrediente (nombre, unidad_medida, stock_actual, stock_minimo, costo) VALUES
    ('Carne de res', 'g', 0, 1000, 35.00),
    ('Queso cheddar', 'unidad', 0, 10, 1500.00),
    ('Cebolla caramelizada', 'g', 0, 500, 12.00);

INSERT INTO inventario (id_ingrediente, tipo_movimiento, cantidad, motivo, id_usuario) VALUES
    (1, 'ENTRADA', 5000, 'Compra inicial proveedor', 1),
    (2, 'ENTRADA', 50, 'Compra inicial proveedor', 1),
    (3, 'ENTRADA', 2000, 'Compra inicial proveedor', 1);

INSERT INTO producto (nombre, descripcion, precio, impuesto_porcentaje, tiempo_preparacion, id_categoria) VALUES
    ('Hamburguesa Clasica', 'Carne 180g, queso cheddar y cebolla', 28000.00, 8.00, 15, 1);

INSERT INTO producto_ingrediente (id_producto, id_ingrediente, cantidad) VALUES
    (1, 1, 180),
    (1, 2, 1),
    (1, 3, 30);

-- Apertura de caja
INSERT INTO caja (id_cajero, monto_apertura, estado) VALUES
    (2, 200000.00, 'ABIERTA');

-- Pedido + Detalle + Personalizacion (1FN) + Descuento + Maquina de estados
INSERT INTO pedido (estado, observaciones, id_cliente, id_mesa, id_mesero) VALUES
    ('PENDIENTE', 'Mesa junto a la ventana', 1, 1, 3);

INSERT INTO detalle_pedido (id_pedido, id_producto, cantidad, precio_unitario, impuesto_porcentaje) VALUES
    (1, 1, 2, 28000.00, 8.00);

INSERT INTO personalizacion_pedido (id_detalle, cantidad, termino_coccion, observaciones) VALUES
    (1, 1, 'TERMINO MEDIO', 'Una sin cebolla y con queso extra');

INSERT INTO personalizacion_ingrediente (id_personalizacion, id_ingrediente, tipo_ajuste, cantidad, costo_extra) VALUES
    (1, 3, 'ELIMINAR', 1, 0),
    (1, 2, 'EXTRA', 1, 3000.00);

INSERT INTO descuento (nombre, porcentaje, motivo, autorizado_por) VALUES
    ('Cliente Frecuente 10%', 10.00, 'Beneficio cliente registrado', 1);

INSERT INTO pedido_descuento (id_pedido, id_descuento, autorizado_por, porcentaje_aplicado) VALUES
    (1, 1, 1, 10.00);

UPDATE pedido SET estado = 'EN_PREPARACION' WHERE id_pedido = 1;
UPDATE pedido SET estado = 'LISTO' WHERE id_pedido = 1;
UPDATE pedido SET estado = 'ENTREGADO' WHERE id_pedido = 1;

-- Salida de inventario trazada al detalle del pedido
INSERT INTO inventario (id_ingrediente, tipo_movimiento, cantidad, motivo, id_usuario, id_detalle) VALUES
    (1, 'SALIDA', 360, 'Consumo pedido #1', 3, 1),
    (2, 'SALIDA', 3, 'Consumo pedido #1 (incluye 1 extra)', 3, 1),
    (3, 'SALIDA', 30, 'Consumo pedido #1 (1 unidad sin cebolla)', 3, 1);

-- Pago mixto ligado a caja + Propina + Distribucion automatica + Cierre de pedido
INSERT INTO pago (id_pedido, id_caja, metodo_pago, valor, estado, referencia) VALUES
    (1, 1, 'EFECTIVO', 30000.00, 'COMPLETADO', 'EF-001'),
    (1, 1, 'NEQUI', 27348.00, 'COMPLETADO', 'NQ-987654');

INSERT INTO propina (id_pago, porcentaje, valor) VALUES
    (1, 10.00, 5000.00);

INSERT INTO propina_distribucion (id_propina, id_usuario, monto) VALUES
    (1, 3, 5000.00);

UPDATE pedido SET estado = 'PAGADO' WHERE id_pedido = 1;

-- Factura DIAN: primer intento rechazado + refacturacion exitosa aceptada
INSERT INTO factura (id_pedido, numero_factura, subtotal, descuento_total, impuesto_total, estado_dian) VALUES
    (1, 'FE-000001', 59000.00, 5900.00, 4248.00, 'PENDIENTE');

UPDATE factura
   SET estado_dian = 'RECHAZADA',
       xml = '<Error>Timeout DIAN intento 1</Error>'
 WHERE id_factura = 1;

INSERT INTO factura (id_pedido, numero_factura, subtotal, descuento_total, impuesto_total, estado_dian) VALUES
    (1, 'FE-000002', NULL, NULL, NULL, 'PENDIENTE');

UPDATE factura
   SET estado_dian = 'ACEPTADA',
       cufe = 'CUFE-DEMO-1234567890ABCDEF',
       xml = '<Invoice><ID>FE-000002</ID></Invoice>'
 WHERE id_factura = 2;

-- Cierre de caja con arqueo exacto
UPDATE caja
   SET estado = 'CERRADA',
       fecha_cierre = NOW(),
       efectivo_contado = 235000.00,
       observaciones = 'Cierre turno sin diferencias'
 WHERE id_caja = 1;

-- PQRS, Notificaciones y Reportes
INSERT INTO pqrs (tipo, descripcion, estado, id_cliente, id_pedido) VALUES
    ('SUGERENCIA', 'Excelente atencion, sugiero ampliar carta de bebidas', 'ABIERTA', 1, 1);

INSERT INTO notificacion (mensaje, tipo, id_usuario, id_pedido, id_ingrediente) VALUES
    ('Pedido #1 listo para entregar en mesa 1', 'PEDIDO', 3, 1, NULL),
    ('Stock de queso cheddar verificado', 'INVENTARIO', 1, NULL, 2);

INSERT INTO reporte (tipo, fecha_inicio, fecha_fin, id_usuario) VALUES
    ('VENTAS_DIARIAS', '2026-11-18', '2026-11-18', 1);

COMMIT;
