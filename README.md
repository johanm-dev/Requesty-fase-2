# Requesty — Sistema Integral de Gestión de Restaurantes

Sistema web MVC + API REST construido con **PHP 8.2+** y **PostgreSQL 17+** (esquema `restaurante`, 25 tablas base, 5 vistas, triggers transaccionales e integridad criptográfica).

---

## 1. Estructura Limpia del Proyecto

- `database/schema.sql`: Esquema completo PostgreSQL (25 tablas, 5 vistas, Bcrypt, AES-256 + Blind Index HMAC-SHA256, rol de mínimo privilegio `app_restaurante_rw`).
- `database/seed_demo.sql`: Datos iniciales operativos (roles, cuentas demo, mesas, menú, recetas, caja, factura y notificaciones).
- `database/qa_suite.sql`: Suite de certificación SQL (cobertura 25/25 tablas y 39 pruebas positivas/Red-Team).
- `REQUESTY/PHP/`: Aplicación canónica PHP MVC (`index.php`, `app/config/`, `app/core/`, `app/controllers/`, `app/models/`, `app/views/`, `css/`, `img/tarjeta/`, `js/`, `tests/`).

---

## 2. Puesta en Marcha Local

```bash
# 1. Crear e inicializar la base de datos en PostgreSQL 17+
psql -U postgres -d postgres -c "CREATE DATABASE restaurante_db;"
psql -U postgres -d restaurante_db -v ON_ERROR_STOP=1 -f database/schema.sql
psql -U postgres -d restaurante_db -c "ALTER ROLE app_restaurante_rw WITH LOGIN PASSWORD 'AppRestaurante_Local2026!';"
PGOPTIONS="-c restaurante.encryption_key=DemoAppSecKey_AES256_32Bytes_2026!" psql -U postgres -d restaurante_db -v ON_ERROR_STOP=1 -f database/seed_demo.sql

# 2. Copiar configuración local (sin commitear .env)
cp REQUESTY/PHP/.env.example REQUESTY/PHP/.env

# 3. Levantar el servidor local PHP conectado a PostgreSQL
php -S 127.0.0.1:8098 -t REQUESTY/PHP REQUESTY/PHP/index.php
```

Abre `http://127.0.0.1:8098` en el navegador. *(Gestión de cuentas por parte del Administrador: **Reportes → Cuentas del personal**).*

---

## 3. Cuentas Demo (`database/seed_demo.sql`)

| Rol | Correo | Contraseña |
| :--- | :--- | :--- |
| `ADMIN` | `admin@restaurante.local` | `AdminRest2026*` |
| `CAJERO` | `cajero@restaurante.local` | `CajeroRest2026*` |
| `MESERO` | `mesero@restaurante.local` | `MeseroRest2026*` |
| `COCINA` | `cocina@restaurante.local` | `CocinaRest2026*` |
| `CLIENTE` | `ana.lopez@correo.com` | `ClienteRest2026*` |

---

## 4. Verificación QA

```bash
PGOPTIONS="-c restaurante.encryption_key=DemoAppSecKey_AES256_32Bytes_2026!" psql -U postgres -d restaurante_db -v ON_ERROR_STOP=1 -f database/qa_suite.sql
TEST_BASE_URL=http://127.0.0.1:8098 php REQUESTY/PHP/tests/redteam_http_qa.php
TEST_BASE_URL=http://127.0.0.1:8098 php REQUESTY/PHP/tests/e2e_25tables_qa.php
```
