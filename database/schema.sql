-- ============================================================================
-- Base de Datos Normalizada y Blindada para Sistema de Restaurante (PostgreSQL 17)
-- Incluye:
--   - Normalizacion 1FN, 2FN, 3FN / FNBC sin campos derivados desincronizados
--   - Cifrado PII AES-256 (pgcrypto pgp_sym_encrypt) + Blind Index HMAC-SHA256
--   - Hash Bcrypt obligatorio (pgcrypto, cost >= 12) + vista publica segura
--   - Principio de minimo privilegio (rol app_restaurante_rw sin acceso a usuario.contrasena)
--   - Maquinas de estado estrictas (pedido, pago, caja, factura DIAN) + anti-XXE en XML
--   - Cero DELETE fisico en entidades transaccionales/contables y auditoria inmutable
--   - 100% de FKs indexadas + indices compuestos, parciales y B-Tree en blind index
-- ============================================================================

CREATE EXTENSION IF NOT EXISTS pgcrypto SCHEMA public;

CREATE SCHEMA IF NOT EXISTS restaurante;
SET search_path TO restaurante, public;

-- ============================================================================
-- 0. ROL DE APLICACION DE MINIMO PRIVILEGIO (SIN CREDENCIALES QUEMADAS)
-- ============================================================================

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'app_restaurante_rw') THEN
        CREATE ROLE app_restaurante_rw LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT;
    ELSE
        ALTER ROLE app_restaurante_rw WITH LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT;
    END IF;
END $$;

ALTER ROLE app_restaurante_rw SET search_path TO restaurante, public;
ALTER ROLE app_restaurante_rw SET statement_timeout TO '5000ms';
ALTER ROLE app_restaurante_rw SET lock_timeout TO '2000ms';
ALTER ROLE app_restaurante_rw SET idle_in_transaction_session_timeout TO '10000ms';

-- ============================================================================
-- 1. TIPOS ENUMERADOS (ENUM)
-- ============================================================================

CREATE TYPE estado_usuario AS ENUM ('ACTIVO', 'INACTIVO', 'SUSPENDIDO');
CREATE TYPE estado_mesa AS ENUM ('DISPONIBLE', 'OCUPADA', 'RESERVADA', 'INACTIVA');
CREATE TYPE estado_ingrediente AS ENUM ('ACTIVO', 'AGOTADO', 'INACTIVO');
CREATE TYPE tipo_movimiento_inv AS ENUM ('ENTRADA', 'SALIDA', 'AJUSTE_POSITIVO', 'AJUSTE_NEGATIVO', 'MERMA');
CREATE TYPE estado_pedido AS ENUM ('PENDIENTE', 'EN_PREPARACION', 'LISTO', 'ENTREGADO', 'PAGADO', 'CANCELADO');
CREATE TYPE tipo_ajuste_ingrediente AS ENUM ('ELIMINAR', 'EXTRA');
CREATE TYPE estado_caja AS ENUM ('ABIERTA', 'CERRADA');
CREATE TYPE metodo_pago_enum AS ENUM (
    'EFECTIVO',
    'TARJETA_CREDITO',
    'TARJETA_DEBITO',
    'TRANSFERENCIA',
    'NEQUI',
    'DAVIPLATA',
    'OTRO'
);
CREATE TYPE estado_pago AS ENUM ('PENDIENTE', 'COMPLETADO', 'RECHAZADO', 'REEMBOLSADO');
CREATE TYPE estado_dian_enum AS ENUM ('PENDIENTE', 'ACEPTADA', 'RECHAZADA', 'ANULADA');
CREATE TYPE tipo_pqrs AS ENUM ('PETICION', 'QUEJA', 'RECLAMO', 'SUGERENCIA');
CREATE TYPE estado_pqrs AS ENUM ('ABIERTA', 'EN_PROCESO', 'RESUELTA', 'CERRADA');

-- ============================================================================
-- 2. TABLAS DE SEGURIDAD, USUARIOS Y AUDITORIA
-- ============================================================================

CREATE TABLE rol (
    id_rol      INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nombre      VARCHAR(50) NOT NULL UNIQUE,
    descripcion VARCHAR(255)
);

CREATE TABLE usuario (
    id_usuario           INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nombre               VARCHAR(100) NOT NULL,
    apellido             VARCHAR(100) NOT NULL,
    documento_cifrado    BYTEA        NOT NULL,
    documento_hash       VARCHAR(64)  NOT NULL UNIQUE,
    correo               VARCHAR(150) NOT NULL UNIQUE,
    telefono             VARCHAR(20),
    contrasena           VARCHAR(255) NOT NULL,
    estado               estado_usuario NOT NULL DEFAULT 'ACTIVO',
    fecha_registro       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    ultimo_inicio_sesion TIMESTAMPTZ,
    id_rol               INTEGER NOT NULL REFERENCES rol(id_rol)
                         ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_usuario_documento_hash_hex CHECK (documento_hash ~ '^[0-9a-f]{64}$'),
    CONSTRAINT chk_usuario_contrasena_bcrypt CHECK (
        contrasena ~ '^\$2[aby]\$(1[2-9]|2[0-9]|3[01])\$[./A-Za-z0-9]{53}$'
    )
);

-- Registro de auditoria inmutable (solo INSERT)
CREATE TABLE actividad (
    id_actividad BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_usuario   INTEGER NOT NULL REFERENCES usuario(id_usuario)
                 ON UPDATE CASCADE ON DELETE RESTRICT,
    accion       VARCHAR(255) NOT NULL,
    modulo       VARCHAR(100) NOT NULL,
    fecha_hora   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    direccion_ip INET
);

CREATE TABLE asistencia (
    id_asistencia INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_usuario    INTEGER NOT NULL REFERENCES usuario(id_usuario)
                  ON UPDATE CASCADE ON DELETE RESTRICT,
    hora_entrada  TIMESTAMPTZ NOT NULL,
    hora_salida   TIMESTAMPTZ,
    fecha         DATE GENERATED ALWAYS AS ((hora_entrada AT TIME ZONE 'America/Bogota')::DATE) STORED,
    CONSTRAINT chk_asistencia_horas CHECK (hora_salida IS NULL OR hora_salida > hora_entrada)
);

-- ============================================================================
-- 3. CLIENTES, MESAS Y CATALOGO
-- ============================================================================

CREATE TABLE cliente (
    id_cliente        INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    tipo_documento    VARCHAR(10) NOT NULL DEFAULT 'CC',
    documento_cifrado BYTEA,
    documento_hash    VARCHAR(64) UNIQUE,
    nombre            VARCHAR(100) NOT NULL,
    telefono          VARCHAR(20),
    correo            VARCHAR(150) UNIQUE,
    fecha_registro    TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    activo            BOOLEAN NOT NULL DEFAULT TRUE,
    CONSTRAINT chk_cliente_documento_par CHECK (
        (documento_cifrado IS NULL AND documento_hash IS NULL)
        OR (documento_cifrado IS NOT NULL AND documento_hash ~ '^[0-9a-f]{64}$')
    )
);

CREATE TABLE mesa (
    id_mesa            INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    numero_mesa        INTEGER NOT NULL UNIQUE CHECK (numero_mesa > 0),
    capacidad          INTEGER NOT NULL CHECK (capacidad > 0),
    codigo_qr          VARCHAR(255) NOT NULL UNIQUE,
    estado             estado_mesa NOT NULL DEFAULT 'DISPONIBLE',
    ocupantes_actuales INTEGER NOT NULL DEFAULT 0 CHECK (ocupantes_actuales >= 0),
    hora_ocupacion     TIMESTAMPTZ,
    id_cliente_actual  INTEGER REFERENCES cliente(id_cliente)
                       ON UPDATE CASCADE ON DELETE SET NULL
);

CREATE TABLE categoria (
    id_categoria INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nombre       VARCHAR(100) NOT NULL UNIQUE,
    descripcion  VARCHAR(255),
    activa       BOOLEAN NOT NULL DEFAULT TRUE
);

CREATE TABLE producto (
    id_producto         INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nombre              VARCHAR(150) NOT NULL,
    descripcion         VARCHAR(255),
    precio              NUMERIC(12,2) NOT NULL CHECK (precio >= 0),
    impuesto_porcentaje NUMERIC(5,2) NOT NULL DEFAULT 8.00 CHECK (impuesto_porcentaje >= 0 AND impuesto_porcentaje <= 100),
    imagen              VARCHAR(255),
    disponible          BOOLEAN NOT NULL DEFAULT TRUE,
    tiempo_preparacion  INTEGER NOT NULL DEFAULT 0 CHECK (tiempo_preparacion >= 0),
    id_categoria        INTEGER NOT NULL REFERENCES categoria(id_categoria)
                        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT uq_producto_categoria UNIQUE (nombre, id_categoria)
);

-- ============================================================================
-- 4. INGREDIENTES Y RECETAS
-- ============================================================================

CREATE TABLE ingrediente (
    id_ingrediente INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nombre         VARCHAR(150) NOT NULL UNIQUE,
    unidad_medida  VARCHAR(30) NOT NULL,
    stock_actual   NUMERIC(12,3) NOT NULL DEFAULT 0 CHECK (stock_actual >= 0),
    stock_minimo   NUMERIC(12,3) NOT NULL DEFAULT 0 CHECK (stock_minimo >= 0),
    costo          NUMERIC(12,2) NOT NULL DEFAULT 0 CHECK (costo >= 0),
    estado         estado_ingrediente NOT NULL DEFAULT 'ACTIVO'
);

CREATE TABLE producto_ingrediente (
    id_producto    INTEGER NOT NULL REFERENCES producto(id_producto)
                   ON UPDATE CASCADE ON DELETE CASCADE,
    id_ingrediente INTEGER NOT NULL REFERENCES ingrediente(id_ingrediente)
                   ON UPDATE CASCADE ON DELETE RESTRICT,
    cantidad       NUMERIC(12,3) NOT NULL CHECK (cantidad > 0),
    PRIMARY KEY (id_producto, id_ingrediente)
);

-- ============================================================================
-- 5. PEDIDOS, DETALLE, PERSONALIZACION NORMALIZADA (1FN) Y DESCUENTOS
-- ============================================================================

CREATE TABLE pedido (
    id_pedido       INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    fecha_hora      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    estado          estado_pedido NOT NULL DEFAULT 'PENDIENTE',
    observaciones   TEXT,
    subtotal        NUMERIC(12,2) NOT NULL DEFAULT 0 CHECK (subtotal >= 0),
    descuento_total NUMERIC(12,2) NOT NULL DEFAULT 0 CHECK (descuento_total >= 0),
    impuesto_total  NUMERIC(12,2) NOT NULL DEFAULT 0 CHECK (impuesto_total >= 0),
    total           NUMERIC(12,2) GENERATED ALWAYS AS (GREATEST(subtotal - descuento_total + impuesto_total, 0)) STORED,
    id_cliente      INTEGER REFERENCES cliente(id_cliente)
                    ON UPDATE CASCADE ON DELETE RESTRICT,
    id_mesa         INTEGER REFERENCES mesa(id_mesa)
                    ON UPDATE CASCADE ON DELETE RESTRICT,
    id_mesero       INTEGER NOT NULL REFERENCES usuario(id_usuario)
                    ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_pedido_descuento_valido CHECK (descuento_total <= subtotal)
);

CREATE TABLE detalle_pedido (
    id_detalle          INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_pedido           INTEGER NOT NULL REFERENCES pedido(id_pedido)
                        ON UPDATE CASCADE ON DELETE RESTRICT,
    id_producto         INTEGER NOT NULL REFERENCES producto(id_producto)
                        ON UPDATE CASCADE ON DELETE RESTRICT,
    cantidad            INTEGER NOT NULL CHECK (cantidad > 0),
    precio_unitario     NUMERIC(12,2) NOT NULL CHECK (precio_unitario >= 0),
    impuesto_porcentaje NUMERIC(5,2) NOT NULL DEFAULT 8.00 CHECK (impuesto_porcentaje >= 0 AND impuesto_porcentaje <= 100),
    subtotal            NUMERIC(12,2) GENERATED ALWAYS AS (ROUND(cantidad * precio_unitario, 2)) STORED,
    observaciones       TEXT
);

CREATE TABLE personalizacion_pedido (
    id_personalizacion INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_detalle         INTEGER NOT NULL REFERENCES detalle_pedido(id_detalle)
                       ON UPDATE CASCADE ON DELETE CASCADE,
    cantidad           INTEGER NOT NULL DEFAULT 1 CHECK (cantidad > 0),
    termino_coccion    VARCHAR(50),
    observaciones      TEXT
);

CREATE TABLE personalizacion_ingrediente (
    id_personalizacion INTEGER NOT NULL REFERENCES personalizacion_pedido(id_personalizacion)
                       ON UPDATE CASCADE ON DELETE CASCADE,
    id_ingrediente     INTEGER NOT NULL REFERENCES ingrediente(id_ingrediente)
                       ON UPDATE CASCADE ON DELETE RESTRICT,
    tipo_ajuste        tipo_ajuste_ingrediente NOT NULL,
    cantidad           NUMERIC(12,3) NOT NULL DEFAULT 1 CHECK (cantidad > 0),
    costo_extra        NUMERIC(12,2) NOT NULL DEFAULT 0 CHECK (costo_extra >= 0),
    PRIMARY KEY (id_personalizacion, id_ingrediente, tipo_ajuste),
    CONSTRAINT chk_pers_costo_ajuste CHECK (tipo_ajuste = 'EXTRA' OR costo_extra = 0)
);

CREATE TABLE descuento (
    id_descuento   INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nombre         VARCHAR(100) NOT NULL UNIQUE,
    porcentaje     NUMERIC(5,2) NOT NULL CHECK (porcentaje > 0 AND porcentaje <= 100),
    motivo         VARCHAR(255),
    activo         BOOLEAN NOT NULL DEFAULT TRUE,
    autorizado_por INTEGER NOT NULL REFERENCES usuario(id_usuario)
                   ON UPDATE CASCADE ON DELETE RESTRICT,
    fecha          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE pedido_descuento (
    id_pedido_descuento INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_pedido           INTEGER NOT NULL REFERENCES pedido(id_pedido)
                        ON UPDATE CASCADE ON DELETE RESTRICT,
    id_descuento        INTEGER NOT NULL REFERENCES descuento(id_descuento)
                        ON UPDATE CASCADE ON DELETE RESTRICT,
    autorizado_por      INTEGER NOT NULL REFERENCES usuario(id_usuario)
                        ON UPDATE CASCADE ON DELETE RESTRICT,
    porcentaje_aplicado NUMERIC(5,2) NOT NULL CHECK (porcentaje_aplicado > 0 AND porcentaje_aplicado <= 100),
    monto_descontado    NUMERIC(12,2) NOT NULL DEFAULT 0 CHECK (monto_descontado >= 0),
    fecha_aplicacion    TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_pedido_descuento UNIQUE (id_pedido, id_descuento)
);

-- Kardex inmutable (solo INSERT; correcciones con AJUSTE_POSITIVO / AJUSTE_NEGATIVO)
CREATE TABLE inventario (
    id_movimiento   BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_ingrediente  INTEGER NOT NULL REFERENCES ingrediente(id_ingrediente)
                    ON UPDATE CASCADE ON DELETE RESTRICT,
    tipo_movimiento tipo_movimiento_inv NOT NULL,
    cantidad        NUMERIC(12,3) NOT NULL CHECK (cantidad > 0),
    fecha           TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    motivo          VARCHAR(255) NOT NULL,
    id_usuario      INTEGER NOT NULL REFERENCES usuario(id_usuario)
                    ON UPDATE CASCADE ON DELETE RESTRICT,
    id_detalle      INTEGER REFERENCES detalle_pedido(id_detalle)
                    ON UPDATE CASCADE ON DELETE RESTRICT
);

-- ============================================================================
-- 6. CAJA, PAGOS, PROPINAS Y FACTURACION DIAN
-- ============================================================================

CREATE TABLE caja (
    id_caja          INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_cajero        INTEGER NOT NULL REFERENCES usuario(id_usuario)
                     ON UPDATE CASCADE ON DELETE RESTRICT,
    fecha_apertura   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    fecha_cierre     TIMESTAMPTZ,
    monto_apertura   NUMERIC(12,2) NOT NULL DEFAULT 0 CHECK (monto_apertura >= 0),
    efectivo_contado NUMERIC(12,2) CHECK (efectivo_contado >= 0),
    estado           estado_caja NOT NULL DEFAULT 'ABIERTA',
    observaciones    TEXT,
    CONSTRAINT chk_caja_fechas CHECK (fecha_cierre IS NULL OR fecha_cierre >= fecha_apertura),
    CONSTRAINT chk_caja_cierre CHECK (
        estado = 'ABIERTA' OR (fecha_cierre IS NOT NULL AND efectivo_contado IS NOT NULL)
    )
);

CREATE TABLE pago (
    id_pago     INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_pedido   INTEGER NOT NULL REFERENCES pedido(id_pedido)
                ON UPDATE CASCADE ON DELETE RESTRICT,
    id_caja     INTEGER NOT NULL REFERENCES caja(id_caja)
                ON UPDATE CASCADE ON DELETE RESTRICT,
    metodo_pago metodo_pago_enum NOT NULL,
    valor       NUMERIC(12,2) NOT NULL CHECK (valor > 0),
    fecha       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    estado      estado_pago NOT NULL DEFAULT 'COMPLETADO',
    referencia  VARCHAR(100)
);

CREATE TABLE propina (
    id_propina         INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_pago            INTEGER NOT NULL UNIQUE REFERENCES pago(id_pago)
                       ON UPDATE CASCADE ON DELETE RESTRICT,
    porcentaje         NUMERIC(5,2) CHECK (porcentaje >= 0 AND porcentaje <= 100),
    valor              NUMERIC(12,2) NOT NULL CHECK (valor >= 0),
    distribuida        BOOLEAN NOT NULL DEFAULT FALSE,
    fecha_distribucion TIMESTAMPTZ
);

CREATE TABLE propina_distribucion (
    id_distribucion INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_propina      INTEGER NOT NULL REFERENCES propina(id_propina)
                    ON UPDATE CASCADE ON DELETE RESTRICT,
    id_usuario      INTEGER NOT NULL REFERENCES usuario(id_usuario)
                    ON UPDATE CASCADE ON DELETE RESTRICT,
    monto           NUMERIC(12,2) NOT NULL CHECK (monto > 0),
    fecha_registro  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_propina_usuario UNIQUE (id_propina, id_usuario)
);

-- Factura electronica DIAN (permite reemitir si una previa fue RECHAZADA o ANULADA)
CREATE TABLE factura (
    id_factura      INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_pedido       INTEGER NOT NULL REFERENCES pedido(id_pedido)
                    ON UPDATE CASCADE ON DELETE RESTRICT,
    numero_factura  VARCHAR(50) NOT NULL UNIQUE,
    cufe            VARCHAR(128) UNIQUE,
    xml             TEXT,
    pdf             VARCHAR(255),
    fecha           TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    subtotal        NUMERIC(12,2) NOT NULL CHECK (subtotal >= 0),
    descuento_total NUMERIC(12,2) NOT NULL DEFAULT 0 CHECK (descuento_total >= 0),
    impuesto_total  NUMERIC(12,2) NOT NULL DEFAULT 0 CHECK (impuesto_total >= 0),
    total           NUMERIC(12,2) GENERATED ALWAYS AS (GREATEST(subtotal - descuento_total + impuesto_total, 0)) STORED,
    estado_dian     estado_dian_enum NOT NULL DEFAULT 'PENDIENTE',
    CONSTRAINT chk_factura_descuento CHECK (descuento_total <= subtotal),
    CONSTRAINT chk_factura_xml_sin_xxe CHECK (
        xml IS NULL OR (xml !~* '<!DOCTYPE' AND xml !~* '<!ENTITY')
    )
);

-- ============================================================================
-- 7. PQRS, NOTIFICACIONES Y REPORTES
-- ============================================================================

CREATE TABLE pqrs (
    id_pqrs     INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    tipo        tipo_pqrs NOT NULL,
    descripcion TEXT NOT NULL,
    fecha       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    estado      estado_pqrs NOT NULL DEFAULT 'ABIERTA',
    id_cliente  INTEGER NOT NULL REFERENCES cliente(id_cliente)
                ON UPDATE CASCADE ON DELETE RESTRICT,
    id_pedido   INTEGER REFERENCES pedido(id_pedido)
                ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE notificacion (
    id_notificacion BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    mensaje         TEXT NOT NULL,
    tipo            VARCHAR(50) NOT NULL,
    fecha           TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    leida           BOOLEAN NOT NULL DEFAULT FALSE,
    id_usuario      INTEGER NOT NULL REFERENCES usuario(id_usuario)
                    ON UPDATE CASCADE ON DELETE CASCADE,
    id_pedido       INTEGER REFERENCES pedido(id_pedido)
                    ON UPDATE CASCADE ON DELETE CASCADE,
    id_ingrediente  INTEGER REFERENCES ingrediente(id_ingrediente)
                    ON UPDATE CASCADE ON DELETE CASCADE
);

CREATE TABLE reporte (
    id_reporte       INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    tipo             VARCHAR(100) NOT NULL,
    fecha_inicio     DATE NOT NULL,
    fecha_fin        DATE NOT NULL,
    id_usuario       INTEGER NOT NULL REFERENCES usuario(id_usuario)
                     ON UPDATE CASCADE ON DELETE RESTRICT,
    fecha_generacion TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT chk_reporte_rango CHECK (fecha_fin >= fecha_inicio)
);

-- ============================================================================
-- 8. INDICES COMPLETOS (100% FKs INDEXADAS + BLIND INDEX + COMPUESTOS/PARCIALES)
-- ============================================================================

-- Seguridad, usuarios y auditoria
CREATE INDEX idx_usuario_rol            ON usuario(id_rol);
CREATE INDEX idx_usuario_correo_lower   ON usuario(lower(correo));
CREATE INDEX idx_usuario_activos_rol    ON usuario(id_rol, id_usuario) WHERE estado = 'ACTIVO';
CREATE INDEX idx_actividad_usuario      ON actividad(id_usuario, fecha_hora DESC);
CREATE INDEX idx_asistencia_usuario     ON asistencia(id_usuario, fecha DESC);

-- Clientes, catalogo y recetas
CREATE INDEX idx_cliente_activos_nombre ON cliente(nombre) WHERE activo = TRUE;
CREATE INDEX idx_mesa_id_cliente_actual ON mesa(id_cliente_actual);
CREATE INDEX idx_producto_categoria     ON producto(id_categoria);
CREATE INDEX idx_producto_disp_cat      ON producto(id_categoria, nombre) WHERE disponible = TRUE;
CREATE INDEX idx_prod_ing_ingrediente   ON producto_ingrediente(id_ingrediente);

-- Pedidos, personalizaciones y descuentos
CREATE INDEX idx_pedido_cliente_fecha   ON pedido(id_cliente, fecha_hora DESC);
CREATE INDEX idx_pedido_mesa            ON pedido(id_mesa);
CREATE INDEX idx_pedido_mesero          ON pedido(id_mesero);
CREATE INDEX idx_pedido_fecha_estado    ON pedido(fecha_hora DESC, estado);
CREATE INDEX idx_pedido_activos         ON pedido(estado, fecha_hora DESC)
    WHERE estado IN ('PENDIENTE', 'EN_PREPARACION', 'LISTO', 'ENTREGADO');
CREATE INDEX idx_detalle_pedido         ON detalle_pedido(id_pedido);
CREATE INDEX idx_detalle_producto       ON detalle_pedido(id_producto);
CREATE INDEX idx_pers_detalle           ON personalizacion_pedido(id_detalle);
CREATE INDEX idx_pers_ing_ingrediente   ON personalizacion_ingrediente(id_ingrediente);
CREATE INDEX idx_descuento_autorizado   ON descuento(autorizado_por);
CREATE INDEX idx_pedido_desc_autorizado ON pedido_descuento(autorizado_por);
CREATE INDEX idx_pedido_desc_descuento  ON pedido_descuento(id_descuento);

-- Inventario (Kardex)
CREATE INDEX idx_inv_ingrediente        ON inventario(id_ingrediente, fecha DESC);
CREATE INDEX idx_inv_fecha_tipo         ON inventario(fecha DESC, tipo_movimiento);
CREATE INDEX idx_inventario_detalle     ON inventario(id_detalle);
CREATE INDEX idx_inventario_usuario     ON inventario(id_usuario);

-- Caja, pagos, propinas y facturacion DIAN
CREATE INDEX idx_caja_cajero            ON caja(id_cajero, fecha_apertura DESC);
CREATE UNIQUE INDEX uq_caja_abierta_cajero ON caja(id_cajero) WHERE estado = 'ABIERTA';
CREATE INDEX idx_pago_pedido            ON pago(id_pedido);
CREATE INDEX idx_pago_caja_estado_met   ON pago(id_caja, estado, metodo_pago);
CREATE INDEX idx_pago_fecha             ON pago(fecha DESC);
CREATE INDEX idx_propina_dist_usuario   ON propina_distribucion(id_usuario);
CREATE INDEX idx_factura_pedido         ON factura(id_pedido, fecha DESC);
CREATE INDEX idx_factura_fecha_estado   ON factura(fecha DESC, estado_dian);
CREATE UNIQUE INDEX uq_factura_activa_pedido ON factura(id_pedido) WHERE estado_dian IN ('PENDIENTE', 'ACEPTADA');

-- PQRS, notificaciones y reportes
CREATE INDEX idx_pqrs_cliente           ON pqrs(id_cliente);
CREATE INDEX idx_pqrs_pedido            ON pqrs(id_pedido);
CREATE INDEX idx_pqrs_abiertas          ON pqrs(estado, fecha DESC) WHERE estado IN ('ABIERTA', 'EN_PROCESO');
CREATE INDEX idx_notif_usuario_leida    ON notificacion(id_usuario, leida, fecha DESC);
CREATE INDEX idx_notif_usuario_no_leida ON notificacion(id_usuario, fecha DESC) WHERE leida = FALSE;
CREATE INDEX idx_notif_ingrediente      ON notificacion(id_ingrediente);
CREATE INDEX idx_notif_pedido           ON notificacion(id_pedido);
CREATE INDEX idx_reporte_usuario        ON reporte(id_usuario);

-- ============================================================================
-- 9. CIFRADO PII (AES-256) + BLIND INDEX (HMAC-SHA256) + BCRYPT (PGCRYPTO)
-- ============================================================================

-- 9.1 Helper interno para obtener y validar longitud de llave de cifrado (>= 32 chars)
CREATE OR REPLACE FUNCTION fn_obtener_llave_cifrado(p_key TEXT DEFAULT NULL)
RETURNS TEXT AS $$
DECLARE
    v_key TEXT;
BEGIN
    v_key := NULLIF(trim(COALESCE(p_key, current_setting('restaurante.encryption_key', true))), '');
    IF v_key IS NULL OR char_length(v_key) < 32 THEN
        RAISE EXCEPTION 'APP_ENCRYPTION_KEY no configurada o menor a 32 caracteres en restaurante.encryption_key';
    END IF;
    RETURN v_key;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER SET search_path = restaurante, pg_temp;

-- 9.2 Blind Index deterministico con HMAC-SHA256 (separacion de dominio)
CREATE OR REPLACE FUNCTION fn_hash_documento(p_documento TEXT, p_key TEXT DEFAULT NULL)
RETURNS VARCHAR(64) AS $$
DECLARE
    v_key  TEXT;
    v_norm TEXT;
BEGIN
    v_norm := upper(trim(COALESCE(p_documento, '')));
    IF char_length(v_norm) < 4 THEN
        RAISE EXCEPTION 'El documento debe tener al menos 4 caracteres';
    END IF;

    v_key := fn_obtener_llave_cifrado(p_key);
    RETURN encode(
        public.hmac(convert_to(v_norm, 'UTF8'), convert_to('blind-index:' || v_key, 'UTF8'), 'sha256'),
        'hex'
    );
END;
$$ LANGUAGE plpgsql SECURITY DEFINER SET search_path = restaurante, pg_temp;

-- 9.3 Cifrado autenticado AES-256 (pgcrypto OpenPGP AES-256 + MDC)
CREATE OR REPLACE FUNCTION fn_cifrar_documento(p_documento TEXT, p_key TEXT DEFAULT NULL)
RETURNS BYTEA AS $$
DECLARE
    v_key  TEXT;
    v_norm TEXT;
BEGIN
    v_norm := upper(trim(COALESCE(p_documento, '')));
    IF char_length(v_norm) < 4 THEN
        RAISE EXCEPTION 'El documento debe tener al menos 4 caracteres';
    END IF;

    v_key := fn_obtener_llave_cifrado(p_key);
    RETURN public.pgp_sym_encrypt(v_norm, v_key, 'cipher-algo=aes256, s2k-mode=0');
END;
$$ LANGUAGE plpgsql SECURITY DEFINER SET search_path = restaurante, pg_temp;

CREATE OR REPLACE FUNCTION fn_descifrar_documento(p_cifrado BYTEA, p_key TEXT DEFAULT NULL)
RETURNS TEXT AS $$
DECLARE
    v_key TEXT;
BEGIN
    IF p_cifrado IS NULL THEN
        RETURN NULL;
    END IF;

    v_key := fn_obtener_llave_cifrado(p_key);
    RETURN public.pgp_sym_decrypt(p_cifrado, v_key, 'cipher-algo=aes256, s2k-mode=0');
END;
$$ LANGUAGE plpgsql SECURITY DEFINER SET search_path = restaurante, pg_temp;

-- 9.4 Busquedas O(log n) por Blind Index sin exponer texto plano en disco
CREATE OR REPLACE FUNCTION fn_buscar_usuario_por_documento(p_documento TEXT, p_key TEXT DEFAULT NULL)
RETURNS TABLE (
    id_usuario           INTEGER,
    nombre               VARCHAR,
    apellido             VARCHAR,
    documento            TEXT,
    correo               VARCHAR,
    telefono             VARCHAR,
    estado               estado_usuario,
    id_rol               INTEGER
) AS $$
DECLARE
    v_hash VARCHAR(64);
BEGIN
    v_hash := fn_hash_documento(p_documento, p_key);

    RETURN QUERY
    SELECT u.id_usuario,
           u.nombre,
           u.apellido,
           fn_descifrar_documento(u.documento_cifrado, p_key) AS documento,
           u.correo,
           u.telefono,
           u.estado,
           u.id_rol
    FROM usuario u
    WHERE u.documento_hash = v_hash;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER SET search_path = restaurante, pg_temp;

CREATE OR REPLACE FUNCTION fn_buscar_cliente_por_documento(p_documento TEXT, p_key TEXT DEFAULT NULL)
RETURNS TABLE (
    id_cliente     INTEGER,
    tipo_documento VARCHAR,
    documento      TEXT,
    nombre         VARCHAR,
    telefono       VARCHAR,
    correo         VARCHAR,
    activo         BOOLEAN
) AS $$
DECLARE
    v_hash VARCHAR(64);
BEGIN
    v_hash := fn_hash_documento(p_documento, p_key);

    RETURN QUERY
    SELECT c.id_cliente,
           c.tipo_documento,
           fn_descifrar_documento(c.documento_cifrado, p_key) AS documento,
           c.nombre,
           c.telefono,
           c.correo,
           c.activo
    FROM cliente c
    WHERE c.documento_hash = v_hash;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER SET search_path = restaurante, pg_temp;

-- 9.5 Trigger obligatorio de hash Bcrypt (factor de costo >= 12) en usuario.contrasena
CREATE OR REPLACE FUNCTION fn_hash_contrasena_bcrypt()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.contrasena IS NULL OR char_length(trim(NEW.contrasena)) = 0 THEN
        RAISE EXCEPTION 'La contrasena no puede estar vacia';
    END IF;

    -- Rechaza explicitamente hashes Bcrypt con costo debil (< 12)
    IF NEW.contrasena ~ '^\$2[aby]\$(0[0-9]|1[01])\$' THEN
        RAISE EXCEPTION 'El hash Bcrypt debe tener factor de costo >= 12';
    END IF;

    -- Si ya viene como hash Bcrypt valido con costo >= 12, se respeta (evita doble hash)
    IF NEW.contrasena ~ '^\$2[aby]\$(1[2-9]|2[0-9]|3[01])\$[./A-Za-z0-9]{53}$' THEN
        RETURN NEW;
    END IF;

    -- Politica minima para texto plano antes de aplicar Bcrypt cost 12
    IF char_length(NEW.contrasena) < 8
       OR NEW.contrasena !~ '[A-Z]'
       OR NEW.contrasena !~ '[a-z]'
       OR NEW.contrasena !~ '[0-9]' THEN
        RAISE EXCEPTION 'La contrasena en texto plano debe tener al menos 8 caracteres, mayuscula, minuscula y digito';
    END IF;

    NEW.contrasena := public.crypt(NEW.contrasena, public.gen_salt('bf', 12));
    RETURN NEW;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER SET search_path = restaurante, pg_temp;

CREATE TRIGGER trg_usuario_hash_contrasena
BEFORE INSERT OR UPDATE OF contrasena ON usuario
FOR EACH ROW
EXECUTE FUNCTION fn_hash_contrasena_bcrypt();

-- 9.6 Funcion segura de autenticacion (verifica Bcrypt sin exponer el hash)
CREATE OR REPLACE FUNCTION fn_autenticar_usuario(
    p_correo VARCHAR,
    p_contrasena_plana TEXT
)
RETURNS TABLE (
    id_usuario INTEGER,
    nombre     VARCHAR,
    apellido   VARCHAR,
    id_rol     INTEGER,
    estado     estado_usuario
) AS $$
DECLARE
    v_user usuario%ROWTYPE;
BEGIN
    SELECT * INTO v_user
    FROM usuario u
    WHERE lower(u.correo) = lower(trim(p_correo));

    IF NOT FOUND OR v_user.estado <> 'ACTIVO' THEN
        RETURN;
    END IF;

    IF v_user.contrasena = public.crypt(p_contrasena_plana, v_user.contrasena) THEN
        UPDATE usuario
           SET ultimo_inicio_sesion = NOW()
         WHERE usuario.id_usuario = v_user.id_usuario;

        RETURN QUERY
        SELECT v_user.id_usuario, v_user.nombre, v_user.apellido, v_user.id_rol, v_user.estado;
    END IF;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER SET search_path = restaurante, pg_temp;

-- 9.7 Funciones compartidas para inmutabilidad y bloqueo de DELETE fisico
CREATE OR REPLACE FUNCTION fn_bloquear_mutacion_inmutable()
RETURNS TRIGGER AS $$
BEGIN
    RAISE EXCEPTION 'La tabla % es inmutable (no permite %); registre un movimiento compensatorio',
                    TG_TABLE_NAME, TG_OP;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION fn_bloquear_delete_fisico()
RETURNS TRIGGER AS $$
BEGIN
    RAISE EXCEPTION 'No se permite DELETE fisico en la entidad transaccional/contable %; use anulacion o cambio de estado',
                    TG_TABLE_NAME;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_inventario_inmutable
BEFORE UPDATE OR DELETE ON inventario
FOR EACH ROW
EXECUTE FUNCTION fn_bloquear_mutacion_inmutable();

CREATE TRIGGER trg_actividad_inmutable
BEFORE UPDATE OR DELETE ON actividad
FOR EACH ROW
EXECUTE FUNCTION fn_bloquear_mutacion_inmutable();

CREATE TRIGGER trg_pedido_no_delete BEFORE DELETE ON pedido FOR EACH ROW EXECUTE FUNCTION fn_bloquear_delete_fisico();
CREATE TRIGGER trg_caja_no_delete BEFORE DELETE ON caja FOR EACH ROW EXECUTE FUNCTION fn_bloquear_delete_fisico();
CREATE TRIGGER trg_pago_no_delete BEFORE DELETE ON pago FOR EACH ROW EXECUTE FUNCTION fn_bloquear_delete_fisico();
CREATE TRIGGER trg_propina_no_delete BEFORE DELETE ON propina FOR EACH ROW EXECUTE FUNCTION fn_bloquear_delete_fisico();
CREATE TRIGGER trg_propina_dist_no_delete BEFORE DELETE ON propina_distribucion FOR EACH ROW EXECUTE FUNCTION fn_bloquear_delete_fisico();
CREATE TRIGGER trg_factura_no_delete BEFORE DELETE ON factura FOR EACH ROW EXECUTE FUNCTION fn_bloquear_delete_fisico();
CREATE TRIGGER trg_asistencia_no_delete BEFORE DELETE ON asistencia FOR EACH ROW EXECUTE FUNCTION fn_bloquear_delete_fisico();
CREATE TRIGGER trg_pqrs_no_delete BEFORE DELETE ON pqrs FOR EACH ROW EXECUTE FUNCTION fn_bloquear_delete_fisico();
CREATE TRIGGER trg_reporte_no_delete BEFORE DELETE ON reporte FOR EACH ROW EXECUTE FUNCTION fn_bloquear_delete_fisico();

-- ============================================================================
-- 10. PROTECCION DE INVENTARIO / KARDEX
-- ============================================================================

CREATE OR REPLACE FUNCTION fn_proteger_stock_ingrediente()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.stock_actual IS DISTINCT FROM OLD.stock_actual THEN
        IF COALESCE(current_setting('restaurante.allow_stock_update', true), 'off') <> 'on' THEN
            RAISE EXCEPTION 'No se permite modificar stock_actual directamente; registre un movimiento en inventario';
        END IF;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_ingrediente_proteger_stock
BEFORE UPDATE OF stock_actual ON ingrediente
FOR EACH ROW
EXECUTE FUNCTION fn_proteger_stock_ingrediente();

CREATE OR REPLACE FUNCTION fn_actualizar_stock_ingrediente()
RETURNS TRIGGER AS $$
DECLARE
    v_delta       NUMERIC(12,3);
    v_nuevo_stock NUMERIC(12,3);
BEGIN
    v_delta := CASE
                   WHEN NEW.tipo_movimiento IN ('ENTRADA', 'AJUSTE_POSITIVO') THEN NEW.cantidad
                   ELSE -NEW.cantidad
               END;

    PERFORM set_config('restaurante.allow_stock_update', 'on', true);

    UPDATE ingrediente
       SET stock_actual = stock_actual + v_delta,
           estado = CASE
                        WHEN stock_actual + v_delta = 0 THEN 'AGOTADO'::estado_ingrediente
                        WHEN estado = 'AGOTADO'::estado_ingrediente AND stock_actual + v_delta > 0 THEN 'ACTIVO'::estado_ingrediente
                        ELSE estado
                    END
     WHERE id_ingrediente = NEW.id_ingrediente
       AND stock_actual + v_delta >= 0
     RETURNING stock_actual INTO v_nuevo_stock;

    IF NOT FOUND THEN
        PERFORM set_config('restaurante.allow_stock_update', 'off', true);
        RAISE EXCEPTION 'Stock insuficiente para el ingrediente %', NEW.id_ingrediente;
    END IF;

    PERFORM set_config('restaurante.allow_stock_update', 'off', true);
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_inventario_actualizar_stock
AFTER INSERT ON inventario
FOR EACH ROW
EXECUTE FUNCTION fn_actualizar_stock_ingrediente();

-- ============================================================================
-- 11. CALCULO UNIFICADO DE PEDIDOS, VALIDACIONES Y MAQUINAS DE ESTADO
-- ============================================================================

CREATE OR REPLACE FUNCTION fn_proteger_totales_pedido()
RETURNS TRIGGER AS $$
BEGIN
    IF TG_OP = 'INSERT' THEN
        NEW.subtotal := 0;
        NEW.descuento_total := 0;
        NEW.impuesto_total := 0;
        RETURN NEW;
    END IF;

    IF COALESCE(current_setting('restaurante.allow_pedido_recalc', true), 'off') <> 'on' THEN
        IF NEW.subtotal IS DISTINCT FROM OLD.subtotal
           OR NEW.descuento_total IS DISTINCT FROM OLD.descuento_total
           OR NEW.impuesto_total IS DISTINCT FROM OLD.impuesto_total THEN
            RAISE EXCEPTION 'Los totales de pedido son calculados automaticamente y no pueden modificarse manualmente';
        END IF;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_pedido_proteger_totales
BEFORE INSERT OR UPDATE ON pedido
FOR EACH ROW
EXECUTE FUNCTION fn_proteger_totales_pedido();

CREATE OR REPLACE FUNCTION fn_proteger_monto_descuento()
RETURNS TRIGGER AS $$
BEGIN
    IF TG_OP = 'UPDATE'
       AND NEW.monto_descontado IS DISTINCT FROM OLD.monto_descontado
       AND COALESCE(current_setting('restaurante.allow_pedido_recalc', true), 'off') <> 'on' THEN
        RAISE EXCEPTION 'monto_descontado se recalcula automaticamente desde el subtotal del pedido';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_pedido_descuento_proteger_monto
BEFORE UPDATE OF monto_descontado ON pedido_descuento
FOR EACH ROW
EXECUTE FUNCTION fn_proteger_monto_descuento();

CREATE OR REPLACE FUNCTION fn_recalcular_pedido_por_id(p_id_pedido INTEGER)
RETURNS VOID AS $$
DECLARE
    v_subtotal_bruto   NUMERIC(12,2);
    v_descuento_total  NUMERIC(12,2);
    v_impuesto_total   NUMERIC(12,2);
    v_total_nuevo      NUMERIC(12,2);
    v_factor_neto      NUMERIC(12,6);
    v_pagado_previo    NUMERIC(12,2);
BEGIN
    SELECT COALESCE((
               SELECT SUM(d.subtotal)
               FROM detalle_pedido d
               WHERE d.id_pedido = p_id_pedido
           ), 0) + COALESCE((
               SELECT SUM(pi.costo_extra * pi.cantidad * pp.cantidad)
               FROM detalle_pedido d
               JOIN personalizacion_pedido pp ON pp.id_detalle = d.id_detalle
               JOIN personalizacion_ingrediente pi ON pi.id_personalizacion = pp.id_personalizacion
               WHERE d.id_pedido = p_id_pedido
                 AND pi.tipo_ajuste = 'EXTRA'
           ), 0)
      INTO v_subtotal_bruto;

    PERFORM set_config('restaurante.allow_pedido_recalc', 'on', true);

    UPDATE pedido_descuento pd
       SET monto_descontado = ROUND(v_subtotal_bruto * (pd.porcentaje_aplicado / 100.0), 2)
     WHERE pd.id_pedido = p_id_pedido
       AND pd.monto_descontado IS DISTINCT FROM ROUND(v_subtotal_bruto * (pd.porcentaje_aplicado / 100.0), 2);

    SELECT LEAST(COALESCE(SUM(pd.monto_descontado), 0), v_subtotal_bruto)
      INTO v_descuento_total
      FROM pedido_descuento pd
     WHERE pd.id_pedido = p_id_pedido;

    v_factor_neto := CASE
                         WHEN v_subtotal_bruto > 0 THEN (v_subtotal_bruto - v_descuento_total) / v_subtotal_bruto
                         ELSE 1
                     END;

    SELECT ROUND(COALESCE(SUM(
               (d.subtotal + COALESCE((
                   SELECT SUM(pi.costo_extra * pi.cantidad * pp.cantidad)
                   FROM personalizacion_pedido pp
                   JOIN personalizacion_ingrediente pi ON pi.id_personalizacion = pp.id_personalizacion
                   WHERE pp.id_detalle = d.id_detalle
                     AND pi.tipo_ajuste = 'EXTRA'
               ), 0)) * v_factor_neto * (d.impuesto_porcentaje / 100.0)
           ), 0), 2)
      INTO v_impuesto_total
      FROM detalle_pedido d
     WHERE d.id_pedido = p_id_pedido;

    v_total_nuevo := GREATEST(v_subtotal_bruto - v_descuento_total + v_impuesto_total, 0);

    SELECT COALESCE(SUM(valor), 0)
      INTO v_pagado_previo
      FROM pago
     WHERE id_pedido = p_id_pedido
       AND estado = 'COMPLETADO';

    IF v_total_nuevo < v_pagado_previo THEN
        PERFORM set_config('restaurante.allow_pedido_recalc', 'off', true);
        RAISE EXCEPTION 'El nuevo total del pedido % (%) no puede ser inferior a los pagos ya completados (%)',
                        p_id_pedido, v_total_nuevo, v_pagado_previo;
    END IF;

    UPDATE pedido p
       SET subtotal        = v_subtotal_bruto,
           descuento_total = v_descuento_total,
           impuesto_total  = v_impuesto_total
     WHERE p.id_pedido = p_id_pedido
       AND (p.subtotal IS DISTINCT FROM v_subtotal_bruto
            OR p.descuento_total IS DISTINCT FROM v_descuento_total
            OR p.impuesto_total IS DISTINCT FROM v_impuesto_total);

    PERFORM set_config('restaurante.allow_pedido_recalc', 'off', true);
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION fn_resolver_id_pedido(
    p_tabla NAME,
    p_id_pedido INTEGER,
    p_id_detalle INTEGER,
    p_id_personalizacion INTEGER
)
RETURNS INTEGER AS $$
DECLARE
    v_id_detalle INTEGER;
    v_id_pedido  INTEGER;
BEGIN
    IF p_tabla IN ('detalle_pedido', 'pedido_descuento') THEN
        RETURN p_id_pedido;
    END IF;

    IF p_tabla = 'personalizacion_pedido' THEN
        v_id_detalle := p_id_detalle;
    ELSE
        SELECT id_detalle INTO v_id_detalle
        FROM personalizacion_pedido
        WHERE id_personalizacion = p_id_personalizacion;
    END IF;

    IF v_id_detalle IS NULL THEN
        RETURN NULL;
    END IF;

    SELECT id_pedido INTO v_id_pedido
    FROM detalle_pedido
    WHERE id_detalle = v_id_detalle;

    RETURN v_id_pedido;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION fn_validar_pedido_editable()
RETURNS TRIGGER AS $$
DECLARE
    v_id_pedido       INTEGER;
    v_estado_pedido   estado_pedido;
    v_tiene_factura   BOOLEAN;
    v_tiene_kardex    BOOLEAN;
    v_p_pedido        INTEGER := NULL;
    v_p_detalle       INTEGER := NULL;
    v_p_pers          INTEGER := NULL;
BEGIN
    IF TG_TABLE_NAME IN ('detalle_pedido', 'pedido_descuento') THEN
        v_p_pedido := CASE WHEN TG_OP = 'DELETE' THEN OLD.id_pedido ELSE NEW.id_pedido END;
    ELSIF TG_TABLE_NAME = 'personalizacion_pedido' THEN
        v_p_detalle := CASE WHEN TG_OP = 'DELETE' THEN OLD.id_detalle ELSE NEW.id_detalle END;
    ELSE
        v_p_pers := CASE WHEN TG_OP = 'DELETE' THEN OLD.id_personalizacion ELSE NEW.id_personalizacion END;
    END IF;

    v_id_pedido := fn_resolver_id_pedido(TG_TABLE_NAME, v_p_pedido, v_p_detalle, v_p_pers);

    IF v_id_pedido IS NOT NULL THEN
        SELECT estado INTO v_estado_pedido FROM pedido WHERE id_pedido = v_id_pedido;

        IF v_estado_pedido IN ('PAGADO', 'CANCELADO') THEN
            RAISE EXCEPTION 'No se puede modificar % cuando el pedido % esta en estado %',
                            TG_TABLE_NAME, v_id_pedido, v_estado_pedido;
        END IF;

        SELECT EXISTS (
            SELECT 1 FROM factura
            WHERE id_pedido = v_id_pedido
              AND estado_dian IN ('PENDIENTE', 'ACEPTADA')
        ) INTO v_tiene_factura;

        IF v_tiene_factura THEN
            RAISE EXCEPTION 'No se puede modificar % porque el pedido % tiene una factura activa',
                            TG_TABLE_NAME, v_id_pedido;
        END IF;
    END IF;

    IF TG_TABLE_NAME = 'detalle_pedido' AND TG_OP = 'DELETE' THEN
        SELECT EXISTS (
            SELECT 1 FROM inventario WHERE id_detalle = OLD.id_detalle
        ) INTO v_tiene_kardex;

        IF v_tiene_kardex THEN
            RAISE EXCEPTION 'No se puede eliminar detalle_pedido % porque tiene movimientos en kardex',
                            OLD.id_detalle;
        END IF;
    END IF;

    RETURN CASE WHEN TG_OP = 'DELETE' THEN OLD ELSE NEW END;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION fn_recalcular_totales_trigger()
RETURNS TRIGGER AS $$
DECLARE
    v_id_pedido   INTEGER;
    v_p_pedido    INTEGER := NULL;
    v_p_detalle   INTEGER := NULL;
    v_p_pers      INTEGER := NULL;
BEGIN
    IF TG_TABLE_NAME IN ('detalle_pedido', 'pedido_descuento') THEN
        v_p_pedido := COALESCE(NEW.id_pedido, OLD.id_pedido);
    ELSIF TG_TABLE_NAME = 'personalizacion_pedido' THEN
        v_p_detalle := COALESCE(NEW.id_detalle, OLD.id_detalle);
    ELSE
        v_p_pers := COALESCE(NEW.id_personalizacion, OLD.id_personalizacion);
    END IF;

    v_id_pedido := fn_resolver_id_pedido(TG_TABLE_NAME, v_p_pedido, v_p_detalle, v_p_pers);

    IF v_id_pedido IS NOT NULL THEN
        PERFORM fn_recalcular_pedido_por_id(v_id_pedido);
    END IF;
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION fn_preparar_detalle_pedido()
RETURNS TRIGGER AS $$
DECLARE
    v_prod producto%ROWTYPE;
BEGIN
    SELECT * INTO v_prod FROM producto WHERE id_producto = NEW.id_producto;

    IF TG_OP = 'INSERT' AND NOT v_prod.disponible THEN
        RAISE EXCEPTION 'El producto % no esta disponible para la venta', v_prod.nombre;
    END IF;

    IF NEW.precio_unitario IS NULL THEN
        NEW.precio_unitario := v_prod.precio;
    END IF;

    IF NEW.impuesto_porcentaje IS NULL THEN
        NEW.impuesto_porcentaje := v_prod.impuesto_porcentaje;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION fn_validar_pedido_descuento()
RETURNS TRIGGER AS $$
DECLARE
    v_desc            descuento%ROWTYPE;
    v_suma_porcentaje NUMERIC(7,2);
BEGIN
    SELECT * INTO v_desc FROM descuento WHERE id_descuento = NEW.id_descuento;

    IF TG_OP = 'INSERT' AND NOT v_desc.activo THEN
        RAISE EXCEPTION 'El descuento % esta inactivo', v_desc.nombre;
    END IF;

    IF NEW.porcentaje_aplicado IS NULL THEN
        NEW.porcentaje_aplicado := v_desc.porcentaje;
    END IF;

    SELECT COALESCE(SUM(porcentaje_aplicado), 0) INTO v_suma_porcentaje
    FROM pedido_descuento
    WHERE id_pedido = NEW.id_pedido
      AND id_pedido_descuento <> COALESCE(NEW.id_pedido_descuento, -1);

    IF v_suma_porcentaje + NEW.porcentaje_aplicado > 100.00 THEN
        RAISE EXCEPTION 'La suma de descuentos (% + %) supera el 100%% del pedido %',
                        v_suma_porcentaje, NEW.porcentaje_aplicado, NEW.id_pedido;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_detalle_editable BEFORE INSERT OR UPDATE OR DELETE ON detalle_pedido
FOR EACH ROW EXECUTE FUNCTION fn_validar_pedido_editable();
CREATE TRIGGER trg_detalle_preparar BEFORE INSERT ON detalle_pedido
FOR EACH ROW EXECUTE FUNCTION fn_preparar_detalle_pedido();
CREATE TRIGGER trg_detalle_recalcular_pedido AFTER INSERT OR UPDATE OR DELETE ON detalle_pedido
FOR EACH ROW EXECUTE FUNCTION fn_recalcular_totales_trigger();

CREATE TRIGGER trg_descuento_editable BEFORE INSERT OR UPDATE OR DELETE ON pedido_descuento
FOR EACH ROW EXECUTE FUNCTION fn_validar_pedido_editable();
CREATE TRIGGER trg_descuento_validar BEFORE INSERT OR UPDATE OF porcentaje_aplicado ON pedido_descuento
FOR EACH ROW EXECUTE FUNCTION fn_validar_pedido_descuento();
CREATE TRIGGER trg_descuento_recalcular_pedido AFTER INSERT OR DELETE ON pedido_descuento
FOR EACH ROW EXECUTE FUNCTION fn_recalcular_totales_trigger();
CREATE TRIGGER trg_descuento_upd_recalcular_pedido AFTER UPDATE OF porcentaje_aplicado ON pedido_descuento
FOR EACH ROW EXECUTE FUNCTION fn_recalcular_totales_trigger();

CREATE TRIGGER trg_pers_editable BEFORE INSERT OR UPDATE OR DELETE ON personalizacion_pedido
FOR EACH ROW EXECUTE FUNCTION fn_validar_pedido_editable();
CREATE TRIGGER trg_pers_recalcular_pedido AFTER INSERT OR UPDATE OR DELETE ON personalizacion_pedido
FOR EACH ROW EXECUTE FUNCTION fn_recalcular_totales_trigger();

CREATE TRIGGER trg_pers_ing_editable BEFORE INSERT OR UPDATE OR DELETE ON personalizacion_ingrediente
FOR EACH ROW EXECUTE FUNCTION fn_validar_pedido_editable();
CREATE TRIGGER trg_pers_ing_recalcular_pedido AFTER INSERT OR UPDATE OR DELETE ON personalizacion_ingrediente
FOR EACH ROW EXECUTE FUNCTION fn_recalcular_totales_trigger();

CREATE OR REPLACE FUNCTION fn_validar_cantidad_personalizacion()
RETURNS TRIGGER AS $$
DECLARE
    v_cantidad_detalle INTEGER;
    v_total_pers       INTEGER;
BEGIN
    SELECT cantidad INTO v_cantidad_detalle
    FROM detalle_pedido
    WHERE id_detalle = NEW.id_detalle;

    SELECT COALESCE(SUM(cantidad), 0) INTO v_total_pers
    FROM personalizacion_pedido
    WHERE id_detalle = NEW.id_detalle
      AND id_personalizacion <> COALESCE(NEW.id_personalizacion, -1);

    IF v_total_pers + NEW.cantidad > v_cantidad_detalle THEN
        RAISE EXCEPTION 'La cantidad personalizada (% + %) supera la cantidad pedida (%) en detalle_pedido %',
                        v_total_pers, NEW.cantidad, v_cantidad_detalle, NEW.id_detalle;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_pers_validar_cantidad
BEFORE INSERT OR UPDATE ON personalizacion_pedido
FOR EACH ROW
EXECUTE FUNCTION fn_validar_cantidad_personalizacion();

CREATE OR REPLACE FUNCTION fn_validar_reduccion_detalle()
RETURNS TRIGGER AS $$
DECLARE
    v_total_pers INTEGER;
BEGIN
    SELECT COALESCE(SUM(cantidad), 0) INTO v_total_pers
    FROM personalizacion_pedido
    WHERE id_detalle = NEW.id_detalle;

    IF NEW.cantidad < v_total_pers THEN
        RAISE EXCEPTION 'No puede reducir detalle_pedido.cantidad (%) por debajo de las unidades personalizadas (%)',
                        NEW.cantidad, v_total_pers;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_detalle_validar_reduccion
BEFORE UPDATE OF cantidad ON detalle_pedido
FOR EACH ROW
EXECUTE FUNCTION fn_validar_reduccion_detalle();

CREATE OR REPLACE FUNCTION fn_maquina_estado_pedido()
RETURNS TRIGGER AS $$
DECLARE
    v_estado_mesa    estado_mesa;
    v_tiene_items    BOOLEAN;
    v_total_pagado   NUMERIC(12,2);
    v_tiene_pagos    BOOLEAN;
    v_tiene_factura  BOOLEAN;
BEGIN
    IF TG_OP = 'INSERT' THEN
        IF NEW.estado <> 'PENDIENTE' THEN
            RAISE EXCEPTION 'Todo pedido nuevo debe iniciar en estado PENDIENTE (recibido: %)', NEW.estado;
        END IF;

        IF NEW.id_mesa IS NOT NULL THEN
            SELECT estado INTO v_estado_mesa FROM mesa WHERE id_mesa = NEW.id_mesa;
            IF v_estado_mesa = 'INACTIVA' THEN
                RAISE EXCEPTION 'No se puede asignar un pedido a la mesa % porque esta INACTIVA', NEW.id_mesa;
            END IF;
        END IF;

        RETURN NEW;
    END IF;

    IF NEW.estado IS NOT DISTINCT FROM OLD.estado THEN
        RETURN NEW;
    END IF;

    IF OLD.estado IN ('PAGADO', 'CANCELADO') THEN
        RAISE EXCEPTION 'Transicion invalida en pedido %: el estado % es terminal', NEW.id_pedido, OLD.estado;
    END IF;

    IF NOT (
        (OLD.estado = 'PENDIENTE'      AND NEW.estado IN ('EN_PREPARACION', 'CANCELADO')) OR
        (OLD.estado = 'EN_PREPARACION' AND NEW.estado IN ('LISTO', 'CANCELADO')) OR
        (OLD.estado = 'LISTO'          AND NEW.estado IN ('ENTREGADO', 'CANCELADO')) OR
        (OLD.estado = 'ENTREGADO'      AND NEW.estado = 'PAGADO')
    ) THEN
        RAISE EXCEPTION 'Transicion de estado invalida en pedido %: % -> %',
                        NEW.id_pedido, OLD.estado, NEW.estado;
    END IF;

    IF NEW.estado = 'EN_PREPARACION' THEN
        SELECT EXISTS (SELECT 1 FROM detalle_pedido WHERE id_pedido = NEW.id_pedido) INTO v_tiene_items;
        IF NOT v_tiene_items THEN
            RAISE EXCEPTION 'El pedido % debe tener al menos un detalle antes de pasar a EN_PREPARACION', NEW.id_pedido;
        END IF;
    END IF;

    IF NEW.estado = 'CANCELADO' THEN
        SELECT EXISTS (SELECT 1 FROM pago WHERE id_pedido = NEW.id_pedido AND estado = 'COMPLETADO') INTO v_tiene_pagos;
        IF v_tiene_pagos THEN
            RAISE EXCEPTION 'No se puede cancelar el pedido % porque tiene pagos COMPLETADOS', NEW.id_pedido;
        END IF;

        SELECT EXISTS (SELECT 1 FROM factura WHERE id_pedido = NEW.id_pedido AND estado_dian IN ('PENDIENTE', 'ACEPTADA')) INTO v_tiene_factura;
        IF v_tiene_factura THEN
            RAISE EXCEPTION 'No se puede cancelar el pedido % porque tiene una factura activa', NEW.id_pedido;
        END IF;
    END IF;

    IF NEW.estado = 'PAGADO' THEN
        SELECT COALESCE(SUM(valor), 0) INTO v_total_pagado
        FROM pago
        WHERE id_pedido = NEW.id_pedido AND estado = 'COMPLETADO';

        IF v_total_pagado <> NEW.total THEN
            RAISE EXCEPTION 'El pedido % no puede pasar a PAGADO: total pagado (%) <> total pedido (%)',
                            NEW.id_pedido, v_total_pagado, NEW.total;
        END IF;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_pedido_estado_maquina
BEFORE INSERT OR UPDATE ON pedido
FOR EACH ROW
EXECUTE FUNCTION fn_maquina_estado_pedido();

CREATE OR REPLACE FUNCTION fn_maquina_estado_caja()
RETURNS TRIGGER AS $$
DECLARE
    v_pagos_pendientes BOOLEAN;
BEGIN
    IF TG_OP = 'INSERT' THEN
        IF NEW.estado <> 'ABIERTA' OR NEW.fecha_cierre IS NOT NULL THEN
            RAISE EXCEPTION 'Toda caja nueva debe iniciar ABIERTA y sin fecha_cierre';
        END IF;
        RETURN NEW;
    END IF;

    IF OLD.estado = 'CERRADA' THEN
        RAISE EXCEPTION 'La caja % esta CERRADA y es inmutable; abra una nueva sesion de caja', NEW.id_caja;
    END IF;

    IF NEW.estado = 'CERRADA' THEN
        SELECT EXISTS (
            SELECT 1 FROM pago WHERE id_caja = NEW.id_caja AND estado = 'PENDIENTE'
        ) INTO v_pagos_pendientes;

        IF v_pagos_pendientes THEN
            RAISE EXCEPTION 'No se puede cerrar la caja % mientras existan pagos en estado PENDIENTE', NEW.id_caja;
        END IF;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_caja_estado_maquina
BEFORE INSERT OR UPDATE ON caja
FOR EACH ROW
EXECUTE FUNCTION fn_maquina_estado_caja();

CREATE OR REPLACE FUNCTION fn_validar_pago_flujo()
RETURNS TRIGGER AS $$
DECLARE
    v_estado_caja     estado_caja;
    v_estado_pedido   estado_pedido;
    v_total_pedido    NUMERIC(12,2);
    v_pagado_previo   NUMERIC(12,2);
    v_factura_activa  BOOLEAN;
BEGIN
    IF TG_OP = 'INSERT' THEN
        IF NEW.estado NOT IN ('PENDIENTE', 'COMPLETADO') THEN
            RAISE EXCEPTION 'Un pago nuevo solo puede registrarse como PENDIENTE o COMPLETADO (recibido: %)', NEW.estado;
        END IF;

        SELECT estado INTO v_estado_caja
        FROM caja
        WHERE id_caja = NEW.id_caja
        FOR SHARE;

        IF v_estado_caja <> 'ABIERTA' THEN
            RAISE EXCEPTION 'No se pueden registrar pagos en la caja % porque su estado es %',
                            NEW.id_caja, v_estado_caja;
        END IF;
    ELSE
        IF NEW.id_pedido IS DISTINCT FROM OLD.id_pedido
           OR NEW.id_caja IS DISTINCT FROM OLD.id_caja
           OR NEW.metodo_pago IS DISTINCT FROM OLD.metodo_pago
           OR NEW.valor IS DISTINCT FROM OLD.valor THEN
            RAISE EXCEPTION 'No se pueden alterar los datos contables del pago %; registre reembolso o nuevo pago', NEW.id_pago;
        END IF;

        IF NEW.estado IS DISTINCT FROM OLD.estado THEN
            IF OLD.estado IN ('RECHAZADO', 'REEMBOLSADO') THEN
                RAISE EXCEPTION 'Transicion invalida en pago %: el estado % es terminal', NEW.id_pago, OLD.estado;
            END IF;

            IF NOT (
                (OLD.estado = 'PENDIENTE'  AND NEW.estado IN ('COMPLETADO', 'RECHAZADO')) OR
                (OLD.estado = 'COMPLETADO' AND NEW.estado = 'REEMBOLSADO')
            ) THEN
                RAISE EXCEPTION 'Transicion de estado invalida en pago %: % -> %',
                                NEW.id_pago, OLD.estado, NEW.estado;
            END IF;
        END IF;
    END IF;

    SELECT estado, total INTO v_estado_pedido, v_total_pedido
    FROM pedido
    WHERE id_pedido = NEW.id_pedido
    FOR UPDATE;

    IF TG_OP = 'INSERT' AND v_estado_pedido IN ('CANCELADO', 'PAGADO') THEN
        RAISE EXCEPTION 'No se pueden registrar pagos para el pedido % porque esta %',
                        NEW.id_pedido, v_estado_pedido;
    END IF;

    IF TG_OP = 'UPDATE' AND OLD.estado = 'COMPLETADO' AND NEW.estado = 'REEMBOLSADO' THEN
        IF v_estado_pedido = 'PAGADO' THEN
            RAISE EXCEPTION 'No se puede reembolsar el pago % de un pedido ya marcado como PAGADO', NEW.id_pago;
        END IF;

        SELECT EXISTS (
            SELECT 1 FROM factura WHERE id_pedido = NEW.id_pedido AND estado_dian = 'ACEPTADA'
        ) INTO v_factura_activa;

        IF v_factura_activa THEN
            RAISE EXCEPTION 'Anule la factura ACEPTADA antes de reembolsar el pago %', NEW.id_pago;
        END IF;
    END IF;

    IF NEW.estado = 'COMPLETADO' THEN
        SELECT COALESCE(SUM(valor), 0) INTO v_pagado_previo
        FROM pago
        WHERE id_pedido = NEW.id_pedido
          AND estado = 'COMPLETADO'
          AND id_pago <> COALESCE(NEW.id_pago, -1);

        IF v_pagado_previo + NEW.valor > v_total_pedido THEN
            RAISE EXCEPTION 'El pago (% + %) excede el total del pedido (%)',
                            v_pagado_previo, NEW.valor, v_total_pedido;
        END IF;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_pago_validar_flujo
BEFORE INSERT OR UPDATE ON pago
FOR EACH ROW
EXECUTE FUNCTION fn_validar_pago_flujo();

CREATE OR REPLACE FUNCTION fn_validar_propina_pago()
RETURNS TRIGGER AS $$
DECLARE
    v_estado_pago estado_pago;
BEGIN
    SELECT estado INTO v_estado_pago FROM pago WHERE id_pago = NEW.id_pago;

    IF v_estado_pago IN ('RECHAZADO', 'REEMBOLSADO') THEN
        RAISE EXCEPTION 'No se puede asociar propina al pago % en estado %', NEW.id_pago, v_estado_pago;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_propina_validar_pago
BEFORE INSERT OR UPDATE OF id_pago ON propina
FOR EACH ROW
EXECUTE FUNCTION fn_validar_propina_pago();

CREATE OR REPLACE FUNCTION fn_validar_y_sync_distribucion_propina()
RETURNS TRIGGER AS $$
DECLARE
    v_valor_propina   NUMERIC(12,2);
    v_total_repartido NUMERIC(12,2);
BEGIN
    IF TG_OP IN ('INSERT', 'UPDATE') THEN
        SELECT valor INTO v_valor_propina FROM propina WHERE id_propina = NEW.id_propina;

        SELECT COALESCE(SUM(monto), 0) INTO v_total_repartido
        FROM propina_distribucion
        WHERE id_propina = NEW.id_propina
          AND id_distribucion <> COALESCE(NEW.id_distribucion, -1);

        IF v_total_repartido + NEW.monto > v_valor_propina THEN
            RAISE EXCEPTION 'La distribucion (% + %) supera el valor de la propina (%)',
                            v_total_repartido, NEW.monto, v_valor_propina;
        END IF;
        RETURN NEW;
    END IF;

    RETURN OLD;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION fn_actualizar_estado_propina()
RETURNS TRIGGER AS $$
DECLARE
    v_id_propina      INTEGER;
    v_valor_propina   NUMERIC(12,2);
    v_total_repartido NUMERIC(12,2);
    v_distribuida     BOOLEAN;
BEGIN
    v_id_propina := COALESCE(NEW.id_propina, OLD.id_propina);

    SELECT valor INTO v_valor_propina FROM propina WHERE id_propina = v_id_propina;
    SELECT COALESCE(SUM(monto), 0) INTO v_total_repartido
    FROM propina_distribucion WHERE id_propina = v_id_propina;

    v_distribuida := (v_valor_propina > 0 AND v_total_repartido = v_valor_propina);

    UPDATE propina
       SET distribuida = v_distribuida,
           fecha_distribucion = CASE WHEN v_distribuida THEN COALESCE(fecha_distribucion, NOW()) ELSE NULL END
     WHERE id_propina = v_id_propina
       AND distribuida IS DISTINCT FROM v_distribuida;

    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_propina_validar_distribucion
BEFORE INSERT OR UPDATE ON propina_distribucion
FOR EACH ROW
EXECUTE FUNCTION fn_validar_y_sync_distribucion_propina();

CREATE TRIGGER trg_propina_sync_estado
AFTER INSERT OR UPDATE OR DELETE ON propina_distribucion
FOR EACH ROW
EXECUTE FUNCTION fn_actualizar_estado_propina();

CREATE OR REPLACE FUNCTION fn_validar_factura_pedido()
RETURNS TRIGGER AS $$
DECLARE
    v_pedido      pedido%ROWTYPE;
    v_tiene_items BOOLEAN;
BEGIN
    IF TG_OP = 'INSERT' THEN
        IF NEW.estado_dian <> 'PENDIENTE' THEN
            RAISE EXCEPTION 'Toda factura DIAN nueva debe emitirse en estado PENDIENTE (recibido: %)', NEW.estado_dian;
        END IF;

        SELECT * INTO v_pedido FROM pedido WHERE id_pedido = NEW.id_pedido;

        IF v_pedido.estado = 'CANCELADO' THEN
            RAISE EXCEPTION 'No se puede facturar el pedido % porque esta CANCELADO', NEW.id_pedido;
        END IF;

        SELECT EXISTS (SELECT 1 FROM detalle_pedido WHERE id_pedido = NEW.id_pedido) INTO v_tiene_items;
        IF NOT v_tiene_items THEN
            RAISE EXCEPTION 'No se puede facturar el pedido % sin items en detalle_pedido', NEW.id_pedido;
        END IF;

        NEW.subtotal := COALESCE(NEW.subtotal, v_pedido.subtotal);
        NEW.descuento_total := COALESCE(NEW.descuento_total, v_pedido.descuento_total);
        NEW.impuesto_total := COALESCE(NEW.impuesto_total, v_pedido.impuesto_total);

        IF NEW.subtotal <> v_pedido.subtotal
           OR NEW.descuento_total <> v_pedido.descuento_total
           OR NEW.impuesto_total <> v_pedido.impuesto_total THEN
            RAISE EXCEPTION 'Los montos de la factura (%, %, %) no coinciden con el pedido % (%, %, %)',
                            NEW.subtotal, NEW.descuento_total, NEW.impuesto_total,
                            NEW.id_pedido, v_pedido.subtotal, v_pedido.descuento_total, v_pedido.impuesto_total;
        END IF;

        RETURN NEW;
    END IF;

    IF NEW.id_pedido IS DISTINCT FROM OLD.id_pedido
       OR NEW.numero_factura IS DISTINCT FROM OLD.numero_factura
       OR NEW.subtotal IS DISTINCT FROM OLD.subtotal
       OR NEW.descuento_total IS DISTINCT FROM OLD.descuento_total
       OR NEW.impuesto_total IS DISTINCT FROM OLD.impuesto_total THEN
        RAISE EXCEPTION 'No se pueden alterar los montos o referencia de pedido de la factura %', OLD.numero_factura;
    END IF;

    IF OLD.estado_dian IN ('RECHAZADA', 'ANULADA') THEN
        RAISE EXCEPTION 'La factura % esta en estado terminal %; emita una nueva factura',
                        OLD.numero_factura, OLD.estado_dian;
    END IF;

    IF NEW.estado_dian IS DISTINCT FROM OLD.estado_dian THEN
        IF NOT (
            (OLD.estado_dian = 'PENDIENTE' AND NEW.estado_dian IN ('ACEPTADA', 'RECHAZADA')) OR
            (OLD.estado_dian = 'ACEPTADA'  AND NEW.estado_dian = 'ANULADA')
        ) THEN
            RAISE EXCEPTION 'Transicion DIAN invalida en factura %: % -> %',
                            OLD.numero_factura, OLD.estado_dian, NEW.estado_dian;
        END IF;
    END IF;

    IF NEW.estado_dian = 'ACEPTADA' AND (NEW.cufe IS NULL OR char_length(trim(NEW.cufe)) = 0) THEN
        RAISE EXCEPTION 'Una factura DIAN ACEPTADA requiere CUFE valido';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_factura_validar_pedido
BEFORE INSERT OR UPDATE ON factura
FOR EACH ROW
EXECUTE FUNCTION fn_validar_factura_pedido();

CREATE OR REPLACE FUNCTION fn_validar_cliente_pqrs()
RETURNS TRIGGER AS $$
DECLARE
    v_cliente_pedido INTEGER;
BEGIN
    IF NEW.id_pedido IS NOT NULL THEN
        SELECT id_cliente INTO v_cliente_pedido
        FROM pedido
        WHERE id_pedido = NEW.id_pedido;

        IF v_cliente_pedido IS NOT NULL AND v_cliente_pedido <> NEW.id_cliente THEN
            RAISE EXCEPTION 'El cliente de la PQRS (%) no coincide con el cliente del pedido (%)',
                            NEW.id_cliente, NEW.id_pedido;
        END IF;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_pqrs_validar_cliente
BEFORE INSERT OR UPDATE ON pqrs
FOR EACH ROW
EXECUTE FUNCTION fn_validar_cliente_pqrs();

-- ============================================================================
-- 12. VISTAS SEGURAS Y ANALITICAS (SIN EXPOSICION DE HASHES NI PII EN CLARO)
-- ============================================================================

CREATE OR REPLACE VIEW vw_usuarios_publico AS
SELECT
    u.id_usuario,
    u.nombre,
    u.apellido,
    u.documento_hash,
    u.correo,
    u.telefono,
    u.estado,
    u.fecha_registro,
    u.ultimo_inicio_sesion,
    u.id_rol,
    r.nombre AS rol_nombre
FROM usuario u
JOIN rol r ON r.id_rol = u.id_rol;

CREATE OR REPLACE VIEW vw_clientes_publico AS
SELECT
    c.id_cliente,
    c.tipo_documento,
    c.documento_hash,
    c.nombre,
    c.telefono,
    c.correo,
    c.fecha_registro,
    c.activo
FROM cliente c;

CREATE OR REPLACE VIEW vw_resumen_caja AS
SELECT
    c.id_caja,
    c.id_cajero,
    c.fecha_apertura,
    c.fecha_cierre,
    c.estado,
    c.monto_apertura,
    COALESCE(SUM(CASE WHEN p.metodo_pago = 'EFECTIVO' AND p.estado = 'COMPLETADO' THEN p.valor ELSE 0 END), 0)::NUMERIC(12,2) AS total_efectivo_ventas,
    COALESCE(SUM(CASE WHEN p.metodo_pago = 'EFECTIVO' AND p.estado = 'COMPLETADO' THEN COALESCE(pr.valor, 0) ELSE 0 END), 0)::NUMERIC(12,2) AS total_efectivo_propinas,
    COALESCE(SUM(CASE WHEN p.metodo_pago <> 'EFECTIVO' AND p.estado = 'COMPLETADO' THEN p.valor ELSE 0 END), 0)::NUMERIC(12,2) AS total_ventas_digitales,
    COALESCE(SUM(CASE WHEN p.metodo_pago <> 'EFECTIVO' AND p.estado = 'COMPLETADO' THEN COALESCE(pr.valor, 0) ELSE 0 END), 0)::NUMERIC(12,2) AS total_propinas_digitales,
    (c.monto_apertura
        + COALESCE(SUM(CASE WHEN p.metodo_pago = 'EFECTIVO' AND p.estado = 'COMPLETADO' THEN p.valor + COALESCE(pr.valor, 0) ELSE 0 END), 0))::NUMERIC(12,2) AS efectivo_esperado,
    c.efectivo_contado,
    CASE
        WHEN c.efectivo_contado IS NOT NULL THEN
            (c.efectivo_contado - (
                c.monto_apertura
                + COALESCE(SUM(CASE WHEN p.metodo_pago = 'EFECTIVO' AND p.estado = 'COMPLETADO' THEN p.valor + COALESCE(pr.valor, 0) ELSE 0 END), 0)
            ))::NUMERIC(12,2)
        ELSE NULL
    END AS diferencia
FROM caja c
LEFT JOIN pago p ON p.id_caja = c.id_caja
LEFT JOIN propina pr ON pr.id_pago = p.id_pago
GROUP BY c.id_caja;

CREATE OR REPLACE VIEW vw_asistencia_horas AS
SELECT
    a.id_asistencia,
    a.id_usuario,
    a.fecha,
    a.hora_entrada,
    a.hora_salida,
    CASE
        WHEN a.hora_salida IS NOT NULL THEN
            ROUND((EXTRACT(EPOCH FROM (a.hora_salida - a.hora_entrada)) / 3600.0)::NUMERIC, 2)
        ELSE NULL
    END AS horas_trabajadas,
    CASE
        WHEN a.hora_salida IS NOT NULL THEN
            GREATEST(ROUND((EXTRACT(EPOCH FROM (a.hora_salida - a.hora_entrada)) / 3600.0 - 8.0)::NUMERIC, 2), 0)
        ELSE NULL
    END AS horas_extra
FROM asistencia a;

CREATE OR REPLACE VIEW vw_conciliacion_pedidos AS
SELECT
    pe.id_pedido,
    pe.fecha_hora,
    pe.estado,
    pe.subtotal,
    pe.descuento_total,
    pe.impuesto_total,
    pe.total,
    COALESCE(pg.total_pagado, 0)::NUMERIC(12,2) AS total_pagado,
    (pe.total - COALESCE(pg.total_pagado, 0))::NUMERIC(12,2) AS saldo_pendiente,
    COALESCE(pg.total_propinas, 0)::NUMERIC(12,2) AS total_propinas,
    f.id_factura AS id_factura_activa,
    f.numero_factura,
    f.estado_dian,
    f.total AS total_facturado
FROM pedido pe
LEFT JOIN (
    SELECT
        p.id_pedido,
        SUM(CASE WHEN p.estado = 'COMPLETADO' THEN p.valor ELSE 0 END) AS total_pagado,
        SUM(CASE WHEN p.estado = 'COMPLETADO' THEN COALESCE(pr.valor, 0) ELSE 0 END) AS total_propinas
    FROM pago p
    LEFT JOIN propina pr ON pr.id_pago = p.id_pago
    GROUP BY p.id_pedido
) pg ON pg.id_pedido = pe.id_pedido
LEFT JOIN factura f
  ON f.id_pedido = pe.id_pedido
 AND f.estado_dian IN ('PENDIENTE', 'ACEPTADA');

-- ============================================================================
-- 13. ENDURECIMIENTO DE PRIVILEGIOS (LEAST PRIVILEGE -> app_restaurante_rw)
-- ============================================================================

REVOKE ALL ON SCHEMA restaurante FROM PUBLIC;
REVOKE ALL ON ALL TABLES IN SCHEMA restaurante FROM PUBLIC;
REVOKE ALL ON ALL SEQUENCES IN SCHEMA restaurante FROM PUBLIC;
REVOKE ALL ON ALL FUNCTIONS IN SCHEMA restaurante FROM PUBLIC;

GRANT USAGE ON SCHEMA restaurante TO app_restaurante_rw;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA restaurante TO app_restaurante_rw;

-- Columna usuario.contrasena excluida de SELECT directo; solo accesible via fn_autenticar_usuario
GRANT SELECT (
    id_usuario, nombre, apellido, documento_cifrado, documento_hash,
    correo, telefono, estado, fecha_registro, ultimo_inicio_sesion, id_rol
) ON usuario TO app_restaurante_rw;
GRANT INSERT (
    nombre, apellido, documento_cifrado, documento_hash,
    correo, telefono, contrasena, estado, id_rol
) ON usuario TO app_restaurante_rw;
GRANT UPDATE (
    nombre, apellido, documento_cifrado, documento_hash,
    correo, telefono, contrasena, estado, ultimo_inicio_sesion, id_rol
) ON usuario TO app_restaurante_rw;

-- Tablas append-only / inmutables (sin UPDATE, sin DELETE, sin TRUNCATE)
GRANT SELECT, INSERT ON actividad, inventario TO app_restaurante_rw;

-- Tablas operativas sin DELETE fisico (baja logica o anulacion por estado)
GRANT SELECT, INSERT, UPDATE ON
    rol, asistencia, cliente, mesa, categoria, producto, ingrediente,
    pedido, descuento, pedido_descuento, caja, pago, propina,
    propina_distribucion, factura, pqrs, reporte
TO app_restaurante_rw;

-- Tablas detalle/puente editables dentro de las reglas de estado de los triggers
GRANT SELECT, INSERT, UPDATE, DELETE ON
    producto_ingrediente, detalle_pedido, personalizacion_pedido,
    personalizacion_ingrediente, notificacion
TO app_restaurante_rw;

-- Vistas permitidas
GRANT SELECT ON
    vw_usuarios_publico, vw_clientes_publico, vw_resumen_caja, vw_asistencia_horas, vw_conciliacion_pedidos
TO app_restaurante_rw;

-- Funciones publicas y helpers de triggers permitidos para el backend
GRANT EXECUTE ON FUNCTION
    fn_autenticar_usuario(VARCHAR, TEXT),
    fn_hash_documento(TEXT, TEXT),
    fn_cifrar_documento(TEXT, TEXT),
    fn_descifrar_documento(BYTEA, TEXT),
    fn_buscar_usuario_por_documento(TEXT, TEXT),
    fn_buscar_cliente_por_documento(TEXT, TEXT),
    fn_recalcular_pedido_por_id(INTEGER),
    fn_resolver_id_pedido(NAME, INTEGER, INTEGER, INTEGER)
TO app_restaurante_rw;
