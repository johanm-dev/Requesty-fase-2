CREATE DATABASE IF NOT EXISTS REQUESTY;
USE REQUESTY;

CREATE TABLE Rol (
    id_rol INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    descripcion VARCHAR(255)
);

INSERT INTO Rol (nombre, descripcion)
VALUES
    ('Administrador', 'Acceso a todos los módulos'),
    ('Cajero', 'Acceso a catálogo, mesas, pedidos y caja'),
    ('Mesero', 'Acceso a catálogo, mesas y pedidos'),
    ('Cocina', 'Acceso a catálogo, pedidos y cocina'),
    ('Cliente', 'Acceso a catálogo y PQRS');

CREATE TABLE Usuario (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    documento VARCHAR(30) NOT NULL UNIQUE,
    correo VARCHAR(150) NOT NULL UNIQUE,
    telefono VARCHAR(20),
    contrasena VARCHAR(255) NOT NULL,
    estado ENUM('Activo','Inactivo') DEFAULT 'Activo',
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
    ultimo_inicio_sesion DATETIME,
    id_rol INT NOT NULL,
    FOREIGN KEY (id_rol) REFERENCES Rol(id_rol)
);

CREATE TABLE Permiso (
    id_permiso INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(50) NOT NULL UNIQUE,
    nombre VARCHAR(100) NOT NULL
);

CREATE TABLE RolPermiso (
    id_rol INT NOT NULL,
    id_permiso INT NOT NULL,
    PRIMARY KEY (id_rol, id_permiso),
    FOREIGN KEY (id_rol) REFERENCES Rol(id_rol),
    FOREIGN KEY (id_permiso) REFERENCES Permiso(id_permiso)
);

CREATE TABLE UsuarioPermiso (
    id_usuario INT NOT NULL,
    id_permiso INT NOT NULL,
    permitido BOOLEAN NOT NULL DEFAULT TRUE,
    PRIMARY KEY (id_usuario, id_permiso),
    FOREIGN KEY (id_usuario) REFERENCES Usuario(id_usuario),
    FOREIGN KEY (id_permiso) REFERENCES Permiso(id_permiso)
);

INSERT INTO Permiso (clave, nombre)
VALUES
    ('menu', 'Catálogo'),
    ('mesas', 'Mesas'),
    ('pedidos', 'Pedidos'),
    ('cocina', 'Cocina'),
    ('caja', 'Pagos/Caja'),
    ('pqrs', 'PQRS'),
    ('reportes', 'Reportes');

INSERT INTO RolPermiso (id_rol, id_permiso)
SELECT rol.id_rol, permiso.id_permiso
FROM Rol AS rol
CROSS JOIN Permiso AS permiso
WHERE rol.nombre = 'Administrador'
   OR (rol.nombre = 'Cajero' AND permiso.clave IN ('menu', 'mesas', 'pedidos', 'caja'))
   OR (rol.nombre = 'Mesero' AND permiso.clave IN ('menu', 'mesas', 'pedidos'))
   OR (rol.nombre = 'Cocina' AND permiso.clave IN ('menu', 'pedidos', 'cocina'))
   OR (rol.nombre = 'Cliente' AND permiso.clave IN ('menu', 'pqrs'));

CREATE TABLE Cliente (
    id_cliente INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    telefono VARCHAR(20),
    correo VARCHAR(150),
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO Clientes (nombre, telefono, correo)
VALUES ('Angie Valentina''Sotelo Ramos','3143178945', 'valesotelo.0806@gmail.com'),
('Johan Saur Muñoz Castiblanco', '3227825778', 'tuntuntunsauuur.@gmail.com'),
('Brayan Santiago Cuta Molina', '3246589755', 'brayancuta@soy.sena.edu.co'),
('Juan David Ospina Moreno','3216549871', 'conchitas_con_amor.com@hotmail.com');

CREATE TABLE Mesa (
    id_mesa INT AUTO_INCREMENT PRIMARY KEY,
    numero_mesa INT NOT NULL UNIQUE,
    capacidad INT NOT NULL,
    codigo_qr VARCHAR(255),
    estado ENUM('Libre','Ocupada') DEFAULT 'Libre'
);

INSERT INTO Mesa (numero_mesa, capacidad, codigo_qr, estado)
VALUES ('1','4', '//drtgedrte4r4t5' 'Libre'),
('2', '4', '//drtgedrte4r4t6', 'Libre'),
('3', '4', '//drtgedrte4r4t7', 'Libre'),
('4','4', '//drtgedrte4r4t8', 'Libre'),
('5', '4', '//drtgedrte4r4t9', 'Libre'),
('6', '4', '//drtgedrte4r4t10', 'Libre'),
('7','4', '//drtgedrte4r4t11', 'Libre' ),
('8', '4', '//drtgedrte4r4t12', 'Libre'),
('9', '4', '//drtgedrte4r4t13', 'Libre'),
('10', '4', '//drtgedrte4r4t14', 'Libre'),
('11', '4', '//drtgedrte4r4t15', 'Libre'),
('12', '4', '//drtgedrte4r4t16', 'Libre'),
('13', '4', '//drtgedrte4r4t17' 'Libre'),
('14', '4', '//drtgedrte4r4t18', 'Libre'),
('15', '4', '//drtgedrte4r4t19', 'Libre'),
('16', '4', '//drtgedrte4r4t20', 'Libre'),
('17', '4', '//drtgedrte4r4t21', 'Libre'),
('18', '4', '//drtgedrte4r422', 'Libre');

CREATE TABLE Categoria (
    id_categoria INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion VARCHAR(255)
);


CREATE TABLE Producto (
    id_producto INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    descripcion VARCHAR(255),
    precio DECIMAL(10,2) NOT NULL,
    imagen VARCHAR(255),
    disponible BOOLEAN DEFAULT TRUE,
    tiempo_preparacion INT,
    id_categoria INT NOT NULL,
    FOREIGN KEY (id_categoria) REFERENCES Categoria(id_categoria)
);

INSERT INTO Producto (nombre, descripcion, precio, imagen, tiempo_preparacion) VALUES
('Bandeja paisa','plato tradicional colombiano muy abundante que incluye arroz, frijoles, carne molida, chicharrón, huevo frito, tajada de plátano maduro, chorizo, arepa y aguacate', '$45000', 'ioioi1', '10'),
('Caldo de Costilla', 'caldo a base de costilla de res cocinada a fuego lento con papa (usualmente sabanera o pastusa), cebolla larga, ajo y cilantro fresco', '$13000', 'oioiio2', '5'),
('Sopa de Ajiaco', 'Consiste en un caldo espeso preparado con tres variedades de papa (criolla, pastusa y sabanera), pollo desmechado, mazorca tierna y guascas', '$14000','oioiio2', '6'),
('Changua', 'LecheAguaHuevosCebolla largaCilantro frescoCalados (pan tostado)Queso campesinoSal', '$7500', 'ioioiio4', '12'),
('Pechuga gratinada', 'Consiste en un filete de pechuga de pollo deshuesado cocinado a la plancha o a la parrilla, el cual se cubre completamente con una generosa capa de queso', '$22000', 'ioiioii5', '9'),
('Churrasco', 'corte de carne de res (tradicionalmente lomo ancho) aplanado, tierno y jugoso, que se asa a la parrilla o a la plancha manteniendo una capa delgada de grasa lateral que potencia su sabor', '$18000', 'oioiio6', '15'), 
('Jugo de mango', 'Se prepara licuando la pulpa de mango fresco (comúnmente de las variedades hilacha o tommy) con agua o leche, endulzado al gusto con azúcar o panela', '$4000', 'oioiio8', '5'),
('Jugo de mora', 'se prepara licuando moras de Castilla frescas con agua o leche', '$4000', 'oioiio8', '5'),
('Jugo de lulo', 'Se prepara licuando la pulpa del lulo (una fruta ácida de pulpa verde) con agua o leche, y azúcar o panela', '$4000', 'oioiio8', '5'),
('Jugo de piña', 'Se prepara licuando trozos de piña fresca (generalmente de las variedades piña oro miel o piña manzana) con agua, endulzado al gusto con azúcar o panela, y se sirve bien frío', '$4000', 'oioiio8', '5'),
('Limonada',  ' Se prepara tradicionalmente exprimiendo zumo de limón fresco (por lo general, limón mandarino o tahití) en agua, endulzado con azúcar o panela', '$3500', 'oioiio7', '15'),
('Chocolate',  'Se prepara disolviendo pastillas de cacao puro en leche o agua hirviendo utilizando una chocolotera y un molinillo de madera para batir vigorosamente hasta obtener una textura espumosa', '$4000', 'oioiio8', '5'),
('Cafe',  'Se prepara mediante la infusión de granos de café arábigo tostados y molidos en agua caliente', '$3500', 'oioiio7', '15'),
('tinto',  'Consiste en una infusión de café molido (frecuentemente de tostión media o alta) filtrado en agua caliente, de cuerpo ligero y sabor intenso', '$3500', 'oioiio7', '15');


CREATE TABLE Ingrediente (
    id_ingrediente INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    unidad_medida VARCHAR(30) NOT NULL,
    stock_actual DECIMAL(10,2) DEFAULT 0,
    stock_minimo DECIMAL(10,2) DEFAULT 0,
    costo DECIMAL(10,2) DEFAULT 0,
    estado ENUM('Disponible','Agotado','Inactivo') DEFAULT 'Disponible'
);

CREATE TABLE Producto_Ingrediente (
    id_producto INT NOT NULL,
    id_ingrediente INT NOT NULL,
    cantidad DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (id_producto, id_ingrediente),
    FOREIGN KEY (id_producto) REFERENCES Producto(id_producto),
    FOREIGN KEY (id_ingrediente) REFERENCES Ingrediente(id_ingrediente)
);

CREATE TABLE Pedido (
    id_pedido INT AUTO_INCREMENT PRIMARY KEY,
    fecha DATE NOT NULL,
    hora TIME NOT NULL,
    estado ENUM('En cola','En preparación','Listo','Entregado','Cancelado') DEFAULT 'En cola',
    observaciones TEXT,
    total DECIMAL(10,2) DEFAULT 0,
    id_cliente INT,
    id_mesa INT,
    id_mesero INT NOT NULL,
    FOREIGN KEY (id_cliente) REFERENCES Cliente(id_cliente),
    FOREIGN KEY (id_mesa) REFERENCES Mesa(id_mesa),
    FOREIGN KEY (id_mesero) REFERENCES Usuario(id_usuario)
);

CREATE TABLE Detalle_Pedido (
    id_detalle INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL,
    id_producto INT NOT NULL,
    cantidad INT NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    observaciones TEXT,
    FOREIGN KEY (id_pedido) REFERENCES Pedido(id_pedido),
    FOREIGN KEY (id_producto) REFERENCES Producto(id_producto)
);

CREATE TABLE Personalizacion_Pedido (
    id_personalizacion INT AUTO_INCREMENT PRIMARY KEY,
    id_detalle INT NOT NULL,
    termino_coccion VARCHAR(50),
    ingredientes_eliminados TEXT,
    ingredientes_extra TEXT,
    observaciones TEXT,
    FOREIGN KEY (id_detalle) REFERENCES Detalle_Pedido(id_detalle)
);

CREATE TABLE Pago (
    id_pago INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL UNIQUE,
    metodo_pago ENUM('Efectivo','Tarjeta','Transferencia') NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('Pendiente','Pagado','Rechazado','Anulado') DEFAULT 'Pendiente',
    referencia VARCHAR(100),
    FOREIGN KEY (id_pedido) REFERENCES Pedido(id_pedido)
);

CREATE TABLE Factura (
    id_factura INT AUTO_INCREMENT PRIMARY KEY,
    id_pago INT NOT NULL UNIQUE,
    numero_factura VARCHAR(50) NOT NULL UNIQUE,
    xml TEXT,
    pdf VARCHAR(255),
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    total DECIMAL(10,2) NOT NULL,
    estado_dian VARCHAR(50),
    FOREIGN KEY (id_pago) REFERENCES Pago(id_pago)
);

CREATE TABLE Caja (
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

CREATE TABLE PQRS (
    id_pqrs INT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('Petición','Queja','Reclamo','Sugerencia','Felicitación') NOT NULL,
    descripcion TEXT NOT NULL,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('Pendiente','En revisión','Resuelta','Cerrada') DEFAULT 'Pendiente',
    id_cliente INT NOT NULL,
    id_pedido INT,
    FOREIGN KEY (id_cliente) REFERENCES Cliente(id_cliente),
    FOREIGN KEY (id_pedido) REFERENCES Pedido(id_pedido)
);

CREATE TABLE Actividad (
    id_actividad INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    accion VARCHAR(255) NOT NULL,
    modulo VARCHAR(100),
    fecha DATE NOT NULL,
    hora TIME NOT NULL,
    direccion_ip VARCHAR(45),
    FOREIGN KEY (id_usuario) REFERENCES Usuario(id_usuario)
);

CREATE TABLE Notificacion (
    id_notificacion INT AUTO_INCREMENT PRIMARY KEY,
    mensaje TEXT NOT NULL,
    tipo VARCHAR(50),
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    leida BOOLEAN DEFAULT FALSE,
    id_usuario INT NOT NULL,
    id_pedido INT,
    FOREIGN KEY (id_usuario) REFERENCES Usuario(id_usuario),
    FOREIGN KEY (id_pedido) REFERENCES Pedido(id_pedido)
);

CREATE TABLE Inventario (
    id_movimiento INT AUTO_INCREMENT PRIMARY KEY,
    id_ingrediente INT NOT NULL,
    tipo_movimiento ENUM('Entrada','Salida','Ajuste') NOT NULL,
    cantidad DECIMAL(10,2) NOT NULL,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    motivo VARCHAR(255),
    usuario INT NOT NULL,
    FOREIGN KEY (id_ingrediente) REFERENCES Ingrediente(id_ingrediente),
    FOREIGN KEY (usuario) REFERENCES Usuario(id_usuario)
);

CREATE TABLE Reporte (
    id_reporte INT AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(100) NOT NULL,
    fecha_inicio DATE,
    fecha_fin DATE,
    usuario INT NOT NULL,
    fecha_generacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario) REFERENCES Usuario(id_usuario)
);

CREATE TABLE Descuento (
    id_descuento INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    porcentaje DECIMAL(5,2) NOT NULL,
    motivo VARCHAR(255),
    autorizado_por INT NOT NULL,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (autorizado_por) REFERENCES Usuario(id_usuario)
);

CREATE TABLE Propina (
    id_propina INT AUTO_INCREMENT PRIMARY KEY,
    id_pago INT NOT NULL,
    porcentaje DECIMAL(5,2),
    valor DECIMAL(10,2) NOT NULL,
    distribuida BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (id_pago) REFERENCES Pago(id_pago)
);

