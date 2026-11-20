# Requesty - Sistema de Gestión Gastronómica
**Programa ADSO · Ficha 3478025 · Entrega y Sustentación 4.º Trimestre**

---

## 1. Integrantes del Equipo
- **Brayan Santiago Cuta**
- **Equipo de Desarrollo Requesty**

---

## 2. Requisitos del Sistema
- **Servidor Web**: Apache (vía XAMPP con módulo `mod_rewrite` habilitado y `AllowOverride All`).
- **Lenguaje**: PHP 8.2 o superior (con extensiones `pdo`, `pdo_mysql`, `pdo_pgsql`, `mbstring`, `json`, `openssl`).
- **Base de Datos**: MySQL / MariaDB (o PostgreSQL 17).
- **Móvil**: Android Studio (Ladybug / Iguana) con JDK 17 y Kotlin 1.9+.

---

## 3. Estructura del Proyecto (Conforme a la Guía ADSO)

```
requesty/
├── public/                 <- Única carpeta pública accesible desde la web
│   ├── index.php           <- Front Controller del sistema
│   ├── .htaccess           <- Reescritura limpia y conservación de Authorization Header
│   └── assets/             <- CSS, JS, imágenes y logo oficial
├── app/
│   ├── config/             <- config.php (BASE_URL) y Database.php (PDO)
│   ├── core/               <- Router.php, Auth.php (CSRF, roles y tokens)
│   ├── controllers/        <- AuthController, PedidoController, ReporteController, etc.
│   │   └── api/            <- AuthApiController.php, PedidoApiController.php
│   ├── models/             <- Usuario.php, Pedido.php, Producto.php, Caja.php, etc.
│   └── views/              <- Vistas organizadas por módulo (auth, pedidos, cocina, etc.)
├── database/               <- Scripts DDL + DML (schema.sql, requesty_database.sql)
├── postman/                <- Colección de pruebas exportada (requesty.json)
├── docs/                   <- Documentación de flujo principal y arquitectura
└── mobile/                 <- Proyecto Android Studio (Kotlin + Retrofit + Jetpack Compose)
```

---

## 4. Instalación y Puesta en Marcha

1. **Clonar o copiar el proyecto** en `C:\xampp\htdocs\Requesty - BETA v_0.2 UPDATE\`.
2. **Iniciar servicios**: Iniciar Apache y MySQL en el panel de control de XAMPP.
3. **Acceder a la aplicación**:
   - Abrir en el navegador: `http://localhost/Requesty%20-%20BETA%20v_0.2%20UPDATE/public/`
4. **Usuarios de Prueba preconfigurados**:
   - **Administrador**: `admin@requesty.com` / Clave: `Admin123*`
   - **Mesero**: `mesero@requesty.com` / Clave: `12345678`
   - **Cajero**: `cajero@requesty.com` / Clave: `12345678`
