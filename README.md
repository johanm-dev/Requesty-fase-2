# Requesty — Sistema Integral de Gestión de Restaurantes (Laravel 13 + PostgreSQL 17+)

Sistema web full-stack y API REST construido con **Laravel 13 (PHP 8.2+)** y **PostgreSQL 17+** (esquema `restaurante`, 25 tablas base, 5 vistas, triggers transaccionales, `pgcrypto` AES-256 + Blind Index HMAC-SHA256 y rol de mínimo privilegio `app_restaurante_rw`).

---

## 1. Estructura del Repositorio

- `database/schema.sql`: Esquema completo PostgreSQL (`restaurante`, 25 tablas, 5 vistas, funciones `fn_*`, triggers, Bcrypt y AES-256 `pgcrypto`).
- `database/seed_demo.sql`: Semilla certificada (roles, cuentas demo, mesas, menú, recetas, inventario, caja, factura y notificaciones).
- `database/qa_suite.sql`: Suite de certificación SQL sobre las 25 tablas.
- `REQUESTY/LARAVEL/`: Aplicación principal en **Laravel 13** conectada directamente a PostgreSQL (`app/`, `config/`, `public/`, `resources/views/`, `routes/`, `tests/`).

---

## 2. Puesta en Marcha Local

```bash
# 1. Crear e inicializar la base de datos en PostgreSQL 17+
psql -U postgres -d postgres -c "CREATE DATABASE restaurante_db;"
psql -U postgres -d restaurante_db -v ON_ERROR_STOP=1 -f database/schema.sql
PGOPTIONS="-c restaurante.encryption_key=ClaveMaestraPgcryptoRestaurante2026" psql -U postgres -d restaurante_db -v ON_ERROR_STOP=1 -f database/seed_demo.sql

# 2. Configurar variables de entorno en Laravel
cd REQUESTY/LARAVEL
cp .env.example .env

# 3. Levantar el servidor Laravel conectado a PostgreSQL
php artisan optimize:clear
php artisan serve --host=127.0.0.1 --port=8099
```

Abre `http://127.0.0.1:8099` en el navegador.

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

## 4. Verificación y Suites QA

```bash
cd REQUESTY/LARAVEL
php artisan test
TEST_BASE_URL=http://127.0.0.1:8099 php tests/Qa/redteam_http_qa.php
TEST_BASE_URL=http://127.0.0.1:8099 php tests/Qa/e2e_25tables_qa.php
```
