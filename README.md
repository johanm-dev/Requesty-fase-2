# Requesty Fase 2 — Guía para Colaboradores

Sistema integral de gestión de restaurantes construido con **PHP 8.2+ (MVC + API REST)** y **PostgreSQL 17+** (esquema `restaurante`, 25 tablas base, 5 vistas, triggers transaccionales e integridad criptográfica).

---

## 1. Cambios Recientes y Estructura Canónica

- **Código único activo en `REQUESTY/PHP/`:** Se eliminaron copias rezagadas (`Requesty - BETA v_0.2 UPDATE/`, respaldos JSON y `requesty_database.sql` de MySQL) para evitar código duplicado o desincronizado.
- **Cero `localStorage` / `sessionStorage`:** Todos los módulos web (`auth`, `registro`, `mesas`, `pedidos`, `cocina`, `menu`, `inventario`, `caja`, `pqrs`, `cuentas`, `reportes`, `asistencia`) leen y persisten el 100% de la información directamente en PostgreSQL mediante la API REST y sesiones `HttpOnly` + CSRF.
- **Cobertura completa de las 25 tablas PostgreSQL:**
  - Usuarios, roles, asistencia y bitácora inmutable: `rol`, `usuario`, `asistencia`, `actividad`.
  - Clientes, mesas, menú, recetas y kardex: `cliente`, `mesa`, `categoria`, `producto`, `ingrediente`, `producto_ingrediente`, `inventario`.
  - Pedidos, personalizaciones y descuentos: `pedido`, `detalle_pedido`, `personalizacion_pedido`, `personalizacion_ingrediente`, `descuento`, `pedido_descuento`.
  - Caja, pagos, propinas, facturación DIAN, PQRS, notificaciones y reportes: `caja`, `pago`, `propina`, `propina_distribucion`, `factura`, `pqrs`, `notificacion`, `reporte`.
- **Seguridad AppSec & DB:**
  - Contraseñas hasheadas con Bcrypt (`cost >= 12`) desde trigger PostgreSQL (`fn_autenticar_usuario`).
  - Documentos de identidad cifrados con AES-256 (`documento_cifrado`) + Blind Index HMAC-SHA256 (`documento_hash`).
  - Rol de base de datos de mínimo privilegio (`app_restaurante_rw`), tokens JWT HS256 / sesión `SameSite=Strict`, CSRF (`X-CSRF-Token`), Anti-IDOR, Rate Limiting y protección Anti-XXE en XML.

---

## 2. Puesta en Marcha Local (Para Colaboradores)

### Paso 1: Crear la base de datos en PostgreSQL 17+
```bash
psql -U postgres -d postgres -c "CREATE DATABASE restaurante_db;"
psql -U postgres -d restaurante_db -v ON_ERROR_STOP=1 -f database/schema.sql
psql -U postgres -d restaurante_db -c "ALTER ROLE app_restaurante_rw WITH LOGIN PASSWORD 'AppRestaurante_Local2026!';"
PGOPTIONS="-c restaurante.encryption_key=DemoAppSecKey_AES256_32Bytes_2026!" psql -U postgres -d restaurante_db -v ON_ERROR_STOP=1 -f database/seed_demo.sql
```

### Paso 2: Configurar variables de entorno
Copia `REQUESTY/PHP/.env.example` a `REQUESTY/PHP/.env` y verifica las credenciales (`DB_HOST=127.0.0.1`, `DB_PORT=5432`, `DB_NAME=restaurante_db`, `DB_USER=app_restaurante_rw`, `CORS_ALLOWED_ORIGINS=http://127.0.0.1:8098,http://localhost:8098`). *(Nunca hagas commit del archivo `.env`)*.

### Paso 3: Levantar el servidor local
```bash
php -S 127.0.0.1:8098 -t REQUESTY/PHP REQUESTY/PHP/index.php
```
Abre `http://127.0.0.1:8098` en tu navegador. *(Nota: `REQUESTY/PHP/index.php` sirve los recursos estáticos `css/`, `js/`, `img/` bajo `cli-server` y bloquea con `403` rutas internas como `app/`, `tests/`, `storage/`, `database/`).*

---

## 3. Cuentas Demo (`database/seed_demo.sql`)

| Rol | Correo | Contraseña |
| :--- | :--- | :--- |
| `ADMIN` | `admin@restaurante.local` | `AdminRest2026*` |
| `CAJERO` | `cajero@restaurante.local` | `CajeroRest2026*` |
| `MESERO` | `mesero@restaurante.local` | `MeseroRest2026*` |
| `COCINA` | `cocina@restaurante.local` | `CocinaRest2026*` |
| `CLIENTE` | `ana.lopez@correo.com` | `ClienteRest2026*` |

*(La gestión de cuentas del personal por parte del `ADMIN` se encuentra en **Reportes → Cuentas del personal**).*

---

## 4. Suites de Verificación QA y Seguridad

```bash
# 1. QA de Base de Datos (39/39 pruebas positivas y Red-Team SQL)
PGOPTIONS="-c restaurante.encryption_key=DemoAppSecKey_AES256_32Bytes_2026!" psql -U postgres -d restaurante_db -v ON_ERROR_STOP=1 -f database/qa_suite.sql

# 2. Red-Team HTTP + Integración 25 Tablas (con servidor activo en 8098)
TEST_BASE_URL=http://127.0.0.1:8098 php REQUESTY/PHP/tests/redteam_http_qa.php
TEST_BASE_URL=http://127.0.0.1:8098 php REQUESTY/PHP/tests/e2e_25tables_qa.php
```
