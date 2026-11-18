# Requesty - Sistema de Gestión Gastronómica (Arquitectura MVC PHP)

Este proyecto ha sido migrado completamente del formato estático HTML/JS a una arquitectura **MVC (Modelo - Vista - Controlador)** en **PHP 8+**, optimizado para ejecutarse en servidores locales como **XAMPP** o cualquier entorno Apache/Nginx con PHP y MySQL.

---

## 📁 Estructura del Proyecto (MVC)

```text
Requesty/
├── app/
│   ├── config/
│   │   ├── config.php              # Configuración global, BASE_URL, sesiones y permisos
│   │   └── Database.php            # Conexión Singleton PDO a MySQL con respaldo resiliente
│   ├── core/
│   │   ├── App.php                 # Inicializador del sistema
│   │   ├── Controller.php          # Controlador base (vistas, modelos, respuestas JSON, auth)
│   │   ├── Model.php               # Modelo base (consultas preparadas PDO)
│   │   └── Router.php              # Enrutador Front Controller (URLs limpias y parámetros)
│   ├── models/
│   │   ├── Usuario.php             # Gestión de usuarios, autenticación y permisos
│   │   ├── Pedido.php              # Pedidos, detalles y estados operativos
│   │   ├── Producto.php            # Menú, categorías, insumos e inventario
│   │   ├── Mesa.php                # Mesas, ocupación y asignación
│   │   ├── Caja.php                # Pagos, facturas electrónicas y cierres de caja
│   │   └── Pqrs.php                # Peticiones, Quejas, Reclamos y Sugerencias
│   ├── controllers/
│   │   ├── AuthController.php      # Login, registro y cierre de sesión
│   │   ├── PedidoController.php    # Módulo de pedidos
│   │   ├── CocinaController.php    # Módulo KDS cocina
│   │   ├── MenuController.php      # Módulo del catálogo y menú
│   │   ├── CajaController.php      # Módulo de cobros y facturación
│   │   ├── MesaController.php      # Módulo de distribución de mesas
│   │   ├── ReporteController.php   # Reportes, inventario y cuentas del personal
│   │   ├── PqrsController.php      # Módulo de PQRS para clientes
│   │   ├── ApiController.php       # Endpoints AJAX para sincronización en tiempo real
│   │   └── ErrorController.php     # Manejo de errores HTTP 404
│   └── views/
│       ├── layouts/
│       │   ├── header.php          # Encabezado HTML y estilos base
│       │   ├── sidebar.php         # Menú lateral dinámico y responsivo
│       │   ├── topbar.php          # Barra superior del restaurante
│       │   └── footer.php          # Scripts base y cierre de etiquetas
│       ├── auth/
│       │   ├── login.php           # Vista de inicio de sesión
│       │   └── registro.php        # Vista de registro de cuenta
│       ├── pedidos/index.php       # Vista de gestión operativa de pedidos
│       ├── cocina/index.php        # Pantalla KDS de cocina
│       ├── menu/index.php          # Catálogo interactivo de platos
│       ├── caja/index.php          # Punto de venta, facturas y cierre de caja
│       ├── mesas/index.php         # Estado y asignación de mesas
│       ├── reportes/index.php      # Dashboard con KPIs, gráficas y personal
│       ├── pqrs/index.php          # Formulario de registro de PQRS
│       └── error/404.php           # Página personalizada de error 404
│
├── css/                            # Estilos CSS de cada módulo
├── js/                             # Scripts de interactividad cliente
├── img/                            # Imágenes de productos y diseño
├── database/
│   ├── requesty_database.sql       # Script SQL optimizado para importar en MySQL/phpMyAdmin
│   └── *_backup.json               # Almacenamiento JSON para respaldo local
│
├── index.php                       # Front Controller único (punto de entrada central del sistema)
└── .htaccess                       # Reglas de reescritura Apache para URLs limpias
```

---

## 🚀 Cómo ejecutar en XAMPP

1. **Colocar en htdocs**:
   Asegúrate de que la carpeta esté dentro de `C:\xampp\htdocs\` (por ejemplo: `C:\xampp\htdocs\Requesty - BETA v_0.2 UPDATE`).

2. **Iniciar servicios en el Panel de Control de XAMPP**:
   - Inicia **Apache**.
   - (Opcional recomendado) Inicia **MySQL**.

3. **(Opcional) Importar la Base de Datos MySQL**:
   - Abre `http://localhost/phpmyadmin/`.
   - Crea una base de datos llamada `REQUESTY` o haz clic en **Importar** y selecciona el archivo:
     `database/requesty_database.sql`.
   *Nota: Si aún no has iniciado MySQL, el sistema funciona de inmediato utilizando los respaldos de datos locales de forma resiliente.*

4. **Abrir en el navegador**:
   - Visita: `http://localhost/Requesty%20-%20BETA%20v_0.2%20UPDATE/`
   - O directamente: `http://localhost/Requesty%20-%20BETA%20v_0.2%20UPDATE/index.php`

---

## 👥 Cuentas de demostración iniciales

| Rol | Correo Electrónico | Contraseña | Módulos Permitidos |
|---|---|---|---|
| **Administrador** | `admin@requesty.com` | `admin123` | Todos los módulos (Mesas, Pedidos, Cocina, Menú, Caja, Reportes, Cuentas, PQRS) |
| **Cajero** | `cajero@requesty.com` | `caja123` | Menú, Mesas, Pedidos, Caja y facturación |
| **Mesero** | `mesero@requesty.com` | `mesero123` | Menú, Mesas, Pedidos |
| **Cocina** | `cocina@requesty.com` | `cocina123` | Menú, Pedidos, Cocina (KDS) |
| **Cliente** | `cliente@gmail.com` | `cliente123` | Catálogo de menú, Mis pedidos, PQRS |
