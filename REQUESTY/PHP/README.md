# Requesty - Sistema Integral de Gestión de Restaurantes (PHP 8.2 MVC + PostgreSQL 17+)

## Arquitectura Canónica

- **Backend & Web MVC:** `REQUESTY/PHP` (`index.php` front controller -> `app/core/Router.php`, `app/controllers/`, `app/models/`, `app/views/`)
- **Base de Datos Única:** `database/schema.sql`, `database/seed_demo.sql`, `database/qa_suite.sql` (esquema `restaurante`, rol de mínimo privilegio `app_restaurante_rw`, 25 tablas base, 5 vistas, cifrado AES-256 + Blind Index HMAC-SHA256 y Bcrypt cost 12)
- **Seguridad:**
  - Sesiones `HttpOnly`, `SameSite=Strict`, CSRF token en formularios web y peticiones cookie-authenticated (`X-CSRF-Token`)
  - Autenticación API mediante JWT HS256 (`Authorization: Bearer`) o sesión activa
  - Control de acceso por rol (RBAC: `ADMIN`, `CAJERO`, `MESERO`, `COCINA`, `CLIENTE`) y Anti-IDOR
  - Sin almacenamiento de datos de negocio en `localStorage`/`sessionStorage`: persistencia 100% en PostgreSQL
- **Despliegue Producción:** `docker-compose.yml` (`web` Nginx + `php` PHP 8.2-FPM + `db` PostgreSQL 17-alpine)
