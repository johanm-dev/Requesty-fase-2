-- ========================================================
-- REQUESTY - BASE DE DATOS COMPLETA
-- Esquema relacional optimizado para MySQL / MariaDB (XAMPP)
-- ========================================================

CREATE DATABASE IF NOT EXISTS REQUESTY CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE REQUESTY;

-- 1. ROLES
CREATE TABLE IF NOT EXISTS Rol (
    id_rol INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion VARCHAR(255)
);

INSERT INTO Rol (id_rol, nombre, descripcion)
VALUES
    (1, 'Administrador', 'Acceso a todos los módulos'),
    (2, 'Cajero', 'Acceso a catálogo, mesas, pedidos y caja'),
    (3, 'Mesero', 'Acceso a catálogo, mesas y pedidos'),
    (4, 'Cocina', 'Acceso a catálogo, pedidos y cocina'),
    (5, 'Cliente', 'Acceso a catálogo y PQRS')
ON DUPLICATE KEY UPDATE descripcion=VALUES(descripcion);

-- 2. USUARIOS
CREATE TABLE IF NOT EXISTS Usuario (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) DEFAULT '',
    documento VARCHAR(30) NOT NULL UNIQUE,
    correo VARCHAR(150) NOT NULL UNIQUE,
    telefono VARCHAR(20),
    contrasena VARCHAR(255) NOT NULL,
    estado ENUM('Activo','Inactivo') DEFAULT 'Activo',
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
    ultimo_inicio_sesion DATETIME,
    id_rol INT NOT NULL,
    FOREIGN KEY (id_rol) REFERENCES Rol(id_rol) ON UPDATE CASCADE
);

-- Usuarios semilla
INSERT INTO Usuario (id_usuario, nombre, apellido, documento, correo, telefono, contrasena, estado, id_rol)
VALUES
    (1, 'Juan Pérez', 'González', '1000000001', '2', '3101234567', 'admin123', 'Activo', 1),
    (2, 'Carlos Cajero', 'Martínez', '1000000002', 'cajero@requesty.com', '3119876543', 'caja123', 'Activo', 2),
    (3, 'Mateo Mesero', 'Ramírez', '1000000003', 'mesero@requesty.com', '3124567890', 'mesero123', 'Activo', 3),
    (4, 'Claudia Cocina', 'López', '1000000004', 'cocina@requesty.com', '3131122334', 'cocina123', 'Activo', 4),
    (5, 'Cliente Demo', 'Suárez', '1000000005', 'cliente@gmail.com', '3145566778', 'cliente123', 'Activo', 5)
ON DUPLICATE KEY UPDATE nombre=VALUES(nombre);

-- 3. PERMISOS
CREATE TABLE IF NOT EXISTS Permiso (
    id_permiso INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(50) NOT NULL UNIQUE,
    nombre VARCHAR(100) NOT NULL
);

INSERT INTO Permiso (id_permiso, clave, nombre)
VALUES
    (1, 'menu', 'Catálogo'),
    (2, 'mesas', 'Mesas'),
    (3, 'pedidos', 'Pedidos'),
    (4, 'cocina', 'Cocina'),
    (5, 'caja', 'Pagos/Caja'),
    (6, 'pqrs', 'PQRS'),
    (7, 'reportes', 'Reportes')
ON DUPLICATE KEY UPDATE nombre=VALUES(nombre);

CREATE TABLE IF NOT EXISTS RolPermiso (
    id_rol INT NOT NULL,
    id_permiso INT NOT NULL,
    PRIMARY KEY (id_rol, id_permiso),
    FOREIGN KEY (id_rol) REFERENCES Rol(id_rol) ON DELETE CASCADE,
    FOREIGN KEY (id_permiso) REFERENCES Permiso(id_permiso) ON DELETE CASCADE
);

INSERT IGNORE INTO RolPermiso (id_rol, id_permiso)
SELECT rol.id_rol, permiso.id_permiso
FROM Rol AS rol
CROSS JOIN Permiso AS permiso
WHERE rol.nombre = 'Administrador'
   OR (rol.nombre = 'Cajero' AND permiso.clave IN ('menu', 'mesas', 'pedidos', 'caja'))
   OR (rol.nombre = 'Mesero' AND permiso.clave IN ('menu', 'mesas', 'pedidos'))
   OR (rol.nombre = 'Cocina' AND permiso.clave IN ('menu', 'pedidos', 'cocina'))
   OR (rol.nombre = 'Cliente' AND permiso.clave IN ('menu', 'pqrs'));

-- 4. CLIENTES
CREATE TABLE IF NOT EXISTS Cliente (
    id_cliente INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    telefono VARCHAR(20),
    correo VARCHAR(150),
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO Cliente (id_cliente, nombre, telefono, correo)
VALUES 
    (1, 'Angie Valentina Sotelo Ramos', '3143178945', 'valesotelo.0806@gmail.com'),
    (2, 'Johan Saur Muñoz Castiblanco', '3227825778', 'tuntuntunsauuur.@gmail.com'),
    (3, 'Brayan Santiago Cuta Molina', '3246589755', 'brayancuta@soy.sena.edu.co'),
    (4, 'Juan David Ospina Moreno', '3216549871', 'conchitas_con_amor.com@hotmail.com')
ON DUPLICATE KEY UPDATE nombre=VALUES(nombre);

-- 5. MESAS
CREATE TABLE IF NOT EXISTS Mesa (
    id_mesa INT AUTO_INCREMENT PRIMARY KEY,
    numero_mesa INT NOT NULL UNIQUE,
    capacidad INT NOT NULL DEFAULT 4,
    codigo_qr VARCHAR(255),
    estado ENUM('Libre','Ocupada') DEFAULT 'Libre'
);

INSERT INTO Mesa (numero_mesa, capacidad, codigo_qr, estado)
VALUES 
    (1, 4, 'qr_mesa_1', 'Libre'),
    (2, 4, 'qr_mesa_2', 'Libre'),
    (3, 4, 'qr_mesa_3', 'Libre'),
    (4, 4, 'qr_mesa_4', 'Libre'),
    (5, 4, 'qr_mesa_5', 'Libre'),
    (6, 4, 'qr_mesa_6', 'Libre'),
    (7, 4, 'qr_mesa_7', 'Libre'),
    (8, 4, 'qr_mesa_8', 'Libre'),
    (9, 4, 'qr_mesa_9', 'Libre'),
    (10, 4, 'qr_mesa_10', 'Libre'),
    (11, 4, 'qr_mesa_11', 'Libre'),
    (12, 4, 'qr_mesa_12', 'Libre'),
    (13, 4, 'qr_mesa_13', 'Libre'),
    (14, 4, 'qr_mesa_14', 'Libre'),
    (15, 4, 'qr_mesa_15', 'Libre'),
    (16, 4, 'qr_mesa_16', 'Libre'),
    (17, 4, 'qr_mesa_17', 'Libre'),
    (18, 4, 'qr_mesa_18', 'Libre')
ON DUPLICATE KEY UPDATE capacidad=VALUES(capacidad);

-- 6. CATEGORÍAS Y PRODUCTOS
CREATE TABLE IF NOT EXISTS Categoria (
    id_categoria INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion VARCHAR(255)
);

INSERT INTO Categoria (id_categoria, nombre, descripcion)
VALUES
    (1, 'Platos típicos', 'Especialidades tradicionales colombianas'),
    (2, 'Sopas', 'Caldos y sopas tradicionales'),
    (3, 'Carnes', 'Cortes de res y aves a la parrilla'),
    (4, 'Bebidas', 'Jugos naturales y bebidas calientes')
ON DUPLICATE KEY UPDATE descripcion=VALUES(descripcion);

CREATE TABLE IF NOT EXISTS Producto (
    id_producto INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    descripcion VARCHAR(255),
    precio DECIMAL(10,2) NOT NULL,
    imagen VARCHAR(255),
    disponible BOOLEAN DEFAULT TRUE,
    tiempo_preparacion INT DEFAULT 10,
    id_categoria INT NOT NULL,
    FOREIGN KEY (id_categoria) REFERENCES Categoria(id_categoria)
);

INSERT INTO Producto (id_producto, nombre, descripcion, precio, imagen, tiempo_preparacion, id_categoria)
VALUES
    (1, 'Bandeja paisa', 'Plato tradicional con arroz, frijoles, carne, chicharrón, huevo, tajada, chorizo y aguacate', 45000.00, 'img/tarjeta/bandeja-paisa.jpg', 15, 1),
    (2, 'Caldo de Costilla', 'Caldo a base de costilla de res con papa, cebolla larga y cilantro fresco', 13000.00, 'img/tarjeta/caldo-de-costilla.jpg', 10, 2),
    (3, 'Sopa de Ajiaco', 'Caldo con tres variedades de papa, pollo desmechado, mazorca y guascas', 14000.00, 'img/tarjeta/caldo-de-costilla.jpg', 12, 2),
    (4, 'Huevos Pericos', 'Huevos revueltos con tomate y cebolla, con arepa caliente', 8500.00, 'img/tarjeta/huevos-pericos.jpg', 8, 1),
    (5, 'Churrasco', 'Corte de res tierno a la plancha con papas y ensalada fresca', 28000.00, 'img/tarjeta/bandeja-paisa.jpg', 20, 3),
    (6, 'Pechuga gratinada', 'Filete de pechuga a la plancha cubierto con abundante queso mozzarella', 22000.00, 'img/tarjeta/bandeja-paisa.jpg', 15, 3),
    (7, 'Jugo de mora', 'Jugo natural preparado con mora fresca en agua o leche', 4500.00, 'img/tarjeta/jugo-de-mora.jpg', 5, 4),
    (8, 'Jugo de mango', 'Jugo natural de mango fresco en agua o leche', 4500.00, 'img/tarjeta/jugo-de-mora.jpg', 5, 4),
    (9, 'Limonada', 'Zumo de limón fresco con panela o azúcar', 3500.00, 'img/tarjeta/jugo-de-mora.jpg', 5, 4),
    (10, 'Café / Tinto', 'Infusión de café colombiano recién preparado', 3000.00, 'img/tarjeta/caldo-de-costilla.jpg', 3, 4)
ON DUPLICATE KEY UPDATE precio=VALUES(precio);

-- 7. INGREDIENTES
CREATE TABLE IF NOT EXISTS Ingrediente (
    id_ingrediente INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    unidad_medida VARCHAR(30) NOT NULL,
    stock_actual DECIMAL(10,2) DEFAULT 0,
    stock_minimo DECIMAL(10,2) DEFAULT 0,
    costo DECIMAL(10,2) DEFAULT 0,
    estado ENUM('Disponible','Agotado','Inactivo') DEFAULT 'Disponible'
);

-- 8. PEDIDOS
CREATE TABLE IF NOT EXISTS Pedido (
    id_pedido INT AUTO_INCREMENT PRIMARY KEY,
    fecha DATE NOT NULL,
    hora TIME NOT NULL,
    estado ENUM('En cola','En preparación','Listo','Listo para servir','Entregado','Cancelado') DEFAULT 'En cola',
    observaciones TEXT,
    total DECIMAL(10,2) DEFAULT 0,
    canal ENUM('Mesa','Domicilio','Para Llevar') DEFAULT 'Mesa',
    id_cliente INT,
    id_mesa INT,
    id_mesero INT,
    FOREIGN KEY (id_cliente) REFERENCES Cliente(id_cliente) ON DELETE SET NULL,
    FOREIGN KEY (id_mesa) REFERENCES Mesa(id_mesa) ON DELETE SET NULL,
    FOREIGN KEY (id_mesero) REFERENCES Usuario(id_usuario) ON DELETE SET NULL
);

-- 9. DETALLE PEDIDO
CREATE TABLE IF NOT EXISTS Detalle_Pedido (
    id_detalle INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL,
    id_producto INT NOT NULL,
    cantidad INT NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    observaciones TEXT,
    FOREIGN KEY (id_pedido) REFERENCES Pedido(id_pedido) ON DELETE CASCADE,
    FOREIGN KEY (id_producto) REFERENCES Producto(id_producto)
);

-- 10. PAGOS Y FACTURAS
CREATE TABLE IF NOT EXISTS Pago (
    id_pago INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL UNIQUE,
    metodo_pago ENUM('Efectivo','Tarjeta','Transferencia') NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('Pendiente','Pagado','Rechazado','Anulado') DEFAULT 'Pendiente',
    referencia VARCHAR(100),
    FOREIGN KEY (id_pedido) REFERENCES Pedido(id_pedido)
);

CREATE TABLE IF NOT EXISTS Factura (
    id_factura INT AUTO_INCREMENT PRIMARY KEY,
    id_pago INT NOT NULL UNIQUE,
    numero_factura VARCHAR(50) NOT NULL UNIQUE,
    xml TEXT,
    pdf VARCHAR(255),
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    total DECIMAL(10,2) NOT NULL,
    estado_dian VARCHAR(50) DEFAULT 'Aprobada',
    FOREIGN KEY (id_pago) REFERENCES Pago(id_pago)
);

-- 11. CAJA
CREATE TABLE IF NOT EXISTS Caja (
    id_caja INT AUTO_INCREMENT PRIMARY KEY,
    fecha_apertura DATETIME NOT NULL,
    fecha_cierre DATETIME,
    efectivo DECIMAL(10,2) DEFAULT 0,
    ventas_digitales DECIMAL(10,2) DEFAULT 0,
    diferencia DECIMAL(10,2) DEFAULT 0,
    estado ENUM('Abierta','Cerrada') DEFAULT 'Abierta',
    id_cajero INT NOT NULL,
    FOREIGN KEY (id_cajero) REFERENCES Usuario(id_usuario)
);

-- 12. PQRS
CREATE TABLE IF NOT EXISTS PQRS (
    id_pqrs INT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('Petición','Queja','Reclamo','Sugerencia','Felicitación') NOT NULL,
    descripcion TEXT NOT NULL,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('Pendiente','En revisión','Resuelta','Cerrada') DEFAULT 'Pendiente',
    id_cliente INT,
    id_pedido INT,
    FOREIGN KEY (id_cliente) REFERENCES Cliente(id_cliente) ON DELETE SET NULL,
    FOREIGN KEY (id_pedido) REFERENCES Pedido(id_pedido) ON DELETE SET NULL
);