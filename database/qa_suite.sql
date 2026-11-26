-- ============================================================================
-- Suite Gatekeeper QA + Red-Team DB Security Certification (PostgreSQL 17)
-- Requiere sesion con restaurante.encryption_key configurada
-- ============================================================================

SET search_path TO restaurante, public;

-- 1) Auditoria estructural, indices de FKs y tipos monetarios
SELECT
    (SELECT COUNT(*) FROM information_schema.tables
      WHERE table_schema = 'restaurante' AND table_type = 'BASE TABLE') AS total_tablas_base,
    (SELECT COUNT(*)
     FROM pg_constraint c
     JOIN pg_class cl ON cl.oid = c.conrelid
     JOIN pg_namespace ns ON ns.oid = cl.relnamespace
     WHERE ns.nspname = 'restaurante'
       AND c.contype = 'f'
       AND NOT EXISTS (
           SELECT 1
           FROM pg_index ix
           WHERE ix.indrelid = c.conrelid
             AND ix.indisvalid
             AND ix.indpred IS NULL
             AND (ix.indkey::smallint[])[0:array_length(c.conkey, 1) - 1] = c.conkey
       )) AS fks_sin_indice,
    (SELECT COUNT(*)
     FROM pg_constraint c
     JOIN pg_class cl ON cl.oid = c.conrelid
     JOIN pg_namespace ns ON ns.oid = cl.relnamespace
     WHERE ns.nspname = 'restaurante'
       AND c.contype = 'f') AS total_fks,
    (SELECT COUNT(*) FROM information_schema.columns
      WHERE table_schema = 'restaurante'
        AND data_type = 'numeric'
        AND numeric_scale = 2
        AND column_name IN ('precio','costo','precio_unitario','costo_extra','valor','monto','subtotal','descuento_total','impuesto_total','total','monto_descontado','monto_apertura','efectivo_contado')
        AND numeric_precision <> 12) AS cols_dinero_no_12_2;

-- 2) Cobertura total: 0 tablas vacias
DO $$
DECLARE
    r RECORD;
    v_cnt BIGINT;
    v_empty INT := 0;
    v_total_tables INT := 0;
BEGIN
    FOR r IN
        SELECT table_name
        FROM information_schema.tables
        WHERE table_schema = 'restaurante' AND table_type = 'BASE TABLE'
        ORDER BY table_name
    LOOP
        v_total_tables := v_total_tables + 1;
        EXECUTE format('SELECT COUNT(*) FROM restaurante.%I', r.table_name) INTO v_cnt;
        IF v_cnt = 0 THEN
            v_empty := v_empty + 1;
            RAISE NOTICE 'TABLA VACIA: %', r.table_name;
        END IF;
    END LOOP;
    ASSERT v_total_tables = 25, format('Se esperaban 25 tablas base, hay %s', v_total_tables);
    ASSERT v_empty = 0, format('Existen %s tablas base vacias', v_empty);
    RAISE NOTICE 'COBERTURA_OK: %/% tablas pobladas (0 vacias)', v_total_tables, v_total_tables;
END $$;

-- 3) Suite de Pruebas Positivas (E2E + Cifrado PII + Blind Index + Maquinas de estado)
DO $$
DECLARE
    v_pos_ok          INT := 0;
    v_pos_total       INT := 12;
    v_auth_id         INTEGER;
    v_auth_fail       INTEGER;
    v_bcrypt_invalid  INT;
    v_has_pass_col    BOOLEAN;
    v_ped             pedido%ROWTYPE;
    v_caja            vw_resumen_caja%ROWTYPE;
    v_asist           vw_asistencia_horas%ROWTYPE;
    v_conc            vw_conciliacion_pedidos%ROWTYPE;
    v_stock_carne     NUMERIC(12,3);
    v_stock_queso     NUMERIC(12,3);
    v_stock_cebolla   NUMERIC(12,3);
    v_ped2            INTEGER;
    v_det2            INTEGER;
    v_caja2           INTEGER;
    v_pago2           INTEGER;
    v_fac2            INTEGER;
    v_estado_ing      estado_ingrediente;
    v_doc_cifrado     BYTEA;
    v_doc_plano       TEXT;
    v_user_found_id   INTEGER;
    v_cli_found_id    INTEGER;
    v_t0              TIMESTAMPTZ;
    v_lat_ms          NUMERIC;
BEGIN
    -- P1: Bcrypt cost >= 12 y vista publica sin contrasena
    SELECT COUNT(*) INTO v_bcrypt_invalid
    FROM usuario
    WHERE contrasena !~ '^\$2[aby]\$(1[2-9]|2[0-9]|3[01])\$[./A-Za-z0-9]{53}$';
    ASSERT v_bcrypt_invalid = 0, 'Hay usuarios sin hash Bcrypt cost >= 12';

    SELECT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'restaurante' AND table_name = 'vw_usuarios_publico' AND column_name = 'contrasena'
    ) INTO v_has_pass_col;
    ASSERT NOT v_has_pass_col, 'vw_usuarios_publico expone contrasena';
    v_pos_ok := v_pos_ok + 1;

    -- P2: Autenticacion valida e invalida
    SELECT id_usuario INTO v_auth_id FROM fn_autenticar_usuario('ADMIN@restaurante.local', 'AdminRest2026*');
    ASSERT v_auth_id = 1, 'Autenticacion valida fallo';
    SELECT id_usuario INTO v_auth_fail FROM fn_autenticar_usuario('admin@restaurante.local', 'ClaveIncorrecta1');
    ASSERT v_auth_fail IS NULL, 'Autenticacion invalida debio retornar NULL';
    v_pos_ok := v_pos_ok + 1;

    -- P3: Stocks iniciales tras entradas y salidas del pedido #1
    SELECT stock_actual INTO v_stock_carne FROM ingrediente WHERE id_ingrediente = 1;
    SELECT stock_actual INTO v_stock_queso FROM ingrediente WHERE id_ingrediente = 2;
    SELECT stock_actual INTO v_stock_cebolla FROM ingrediente WHERE id_ingrediente = 3;
    ASSERT v_stock_carne = 4640.000 AND v_stock_queso = 47.000 AND v_stock_cebolla = 1970.000,
           format('Stock inesperado: %, %, %', v_stock_carne, v_stock_queso, v_stock_cebolla);
    v_pos_ok := v_pos_ok + 1;

    -- P4: Calculo automatico de pedido #1
    SELECT * INTO v_ped FROM pedido WHERE id_pedido = 1;
    ASSERT v_ped.subtotal = 59000.00 AND v_ped.descuento_total = 5900.00
       AND v_ped.impuesto_total = 4248.00 AND v_ped.total = 57348.00 AND v_ped.estado = 'PAGADO',
       'Totales de pedido #1 no cuadran';
    v_pos_ok := v_pos_ok + 1;

    -- P5: Conciliacion en vw_conciliacion_pedidos y refacturacion DIAN
    SELECT * INTO v_conc FROM vw_conciliacion_pedidos WHERE id_pedido = 1;
    ASSERT v_conc.total_pagado = 57348.00 AND v_conc.saldo_pendiente = 0.00
       AND v_conc.total_propinas = 5000.00 AND v_conc.numero_factura = 'FE-000002'
       AND v_conc.estado_dian = 'ACEPTADA' AND v_conc.total_facturado = 57348.00,
       'vw_conciliacion_pedidos no cuadra';
    v_pos_ok := v_pos_ok + 1;

    -- P6: Cuadre de vw_resumen_caja
    SELECT * INTO v_caja FROM vw_resumen_caja WHERE id_caja = 1;
    ASSERT v_caja.monto_apertura = 200000.00 AND v_caja.total_efectivo_ventas = 30000.00
       AND v_caja.total_efectivo_propinas = 5000.00 AND v_caja.total_ventas_digitales = 27348.00
       AND v_caja.efectivo_esperado = 235000.00 AND v_caja.diferencia = 0.00
       AND v_caja.estado = 'CERRADA',
       'vw_resumen_caja no cuadra';
    v_pos_ok := v_pos_ok + 1;

    -- P7: Turno nocturno en vw_asistencia_horas
    SELECT * INTO v_asist FROM vw_asistencia_horas WHERE id_asistencia = 1;
    ASSERT v_asist.fecha = DATE '2026-11-17' AND v_asist.horas_trabajadas = 9.50 AND v_asist.horas_extra = 1.50,
           'vw_asistencia_horas no cuadra';
    v_pos_ok := v_pos_ok + 1;

    -- P8: Ciclo completo de pedido con descuento, anulacion DIAN + refacturacion y cierre de caja #2
    BEGIN
        INSERT INTO caja (id_cajero, monto_apertura, estado) VALUES (2, 100000.00, 'ABIERTA') RETURNING id_caja INTO v_caja2;
        INSERT INTO pedido (estado, id_cliente, id_mesa, id_mesero) VALUES ('PENDIENTE', 1, 1, 3) RETURNING id_pedido INTO v_ped2;
        INSERT INTO detalle_pedido (id_pedido, id_producto, cantidad, precio_unitario, impuesto_porcentaje)
        VALUES (v_ped2, 1, 1, NULL, NULL) RETURNING id_detalle INTO v_det2;
        UPDATE pedido SET estado = 'EN_PREPARACION' WHERE id_pedido = v_ped2;
        UPDATE pedido SET estado = 'LISTO' WHERE id_pedido = v_ped2;
        UPDATE pedido SET estado = 'ENTREGADO' WHERE id_pedido = v_ped2;
        INSERT INTO pago (id_pedido, id_caja, metodo_pago, valor, estado)
        VALUES (v_ped2, v_caja2, 'TARJETA_DEBITO', 30240.00, 'COMPLETADO') RETURNING id_pago INTO v_pago2;
        UPDATE pedido SET estado = 'PAGADO' WHERE id_pedido = v_ped2;
        INSERT INTO factura (id_pedido, numero_factura, subtotal, descuento_total, impuesto_total, estado_dian)
        VALUES (v_ped2, 'FE-TEST-001', NULL, NULL, NULL, 'PENDIENTE') RETURNING id_factura INTO v_fac2;
        UPDATE factura SET estado_dian = 'ACEPTADA', cufe = 'CUFE-TEST-001' WHERE id_factura = v_fac2;
        UPDATE factura SET estado_dian = 'ANULADA' WHERE id_factura = v_fac2;
        INSERT INTO factura (id_pedido, numero_factura, subtotal, descuento_total, impuesto_total, estado_dian)
        VALUES (v_ped2, 'FE-TEST-002', NULL, NULL, NULL, 'PENDIENTE') RETURNING id_factura INTO v_fac2;
        UPDATE factura SET estado_dian = 'ACEPTADA', cufe = 'CUFE-TEST-002' WHERE id_factura = v_fac2;
        UPDATE caja SET estado = 'CERRADA', fecha_cierre = NOW(), efectivo_contado = 100000.00 WHERE id_caja = v_caja2;
        RAISE EXCEPTION '__ROLLBACK_P8__';
    EXCEPTION WHEN OTHERS THEN
        IF SQLERRM <> '__ROLLBACK_P8__' THEN RAISE; END IF;
    END;
    v_pos_ok := v_pos_ok + 1;

    -- P9: Kardex compensatorio AGOTADO <-> ACTIVO
    BEGIN
        INSERT INTO inventario (id_ingrediente, tipo_movimiento, cantidad, motivo, id_usuario)
        VALUES (2, 'MERMA', 47.000, 'Prueba agotado', 1);
        SELECT estado, stock_actual INTO v_estado_ing, v_stock_queso FROM ingrediente WHERE id_ingrediente = 2;
        ASSERT v_stock_queso = 0 AND v_estado_ing = 'AGOTADO', 'No paso a AGOTADO';
        INSERT INTO inventario (id_ingrediente, tipo_movimiento, cantidad, motivo, id_usuario)
        VALUES (2, 'AJUSTE_POSITIVO', 10.000, 'Reposicion compensatoria', 1);
        SELECT estado, stock_actual INTO v_estado_ing, v_stock_queso FROM ingrediente WHERE id_ingrediente = 2;
        ASSERT v_stock_queso = 10.000 AND v_estado_ing = 'ACTIVO', 'No volvio a ACTIVO';
        RAISE EXCEPTION '__ROLLBACK_P9__';
    EXCEPTION WHEN OTHERS THEN
        IF SQLERRM <> '__ROLLBACK_P9__' THEN RAISE; END IF;
    END;
    v_pos_ok := v_pos_ok + 1;

    -- P10: Cancelacion valida y rechazo de pago
    BEGIN
        INSERT INTO caja (id_cajero, monto_apertura, estado) VALUES (2, 50000.00, 'ABIERTA') RETURNING id_caja INTO v_caja2;
        INSERT INTO pedido (estado, id_cliente, id_mesa, id_mesero) VALUES ('PENDIENTE', 1, 1, 3) RETURNING id_pedido INTO v_ped2;
        INSERT INTO pago (id_pedido, id_caja, metodo_pago, valor, estado)
        VALUES (v_ped2, v_caja2, 'EFECTIVO', 1000.00, 'PENDIENTE') RETURNING id_pago INTO v_pago2;
        UPDATE pago SET estado = 'RECHAZADO' WHERE id_pago = v_pago2;
        UPDATE pedido SET estado = 'CANCELADO' WHERE id_pedido = v_ped2;
        RAISE EXCEPTION '__ROLLBACK_P10__';
    EXCEPTION WHEN OTHERS THEN
        IF SQLERRM <> '__ROLLBACK_P10__' THEN RAISE; END IF;
    END;
    v_pos_ok := v_pos_ok + 1;

    -- P11: Cifrado AES-256 en reposo (sin texto plano en BYTEA) + descifrado valido
    SELECT documento_cifrado INTO v_doc_cifrado FROM usuario WHERE id_usuario = 1;
    ASSERT position(convert_to('1001001001', 'UTF8') IN v_doc_cifrado) = 0,
           'El documento aparece en texto plano dentro de documento_cifrado';
    v_doc_plano := fn_descifrar_documento(v_doc_cifrado);
    ASSERT v_doc_plano = '1001001001', format('Descifrado incorrecto: %s', v_doc_plano);
    v_pos_ok := v_pos_ok + 1;

    -- P12: Blind Index HMAC-SHA256 O(log n) en usuario y cliente + latencia < 50ms
    v_t0 := clock_timestamp();
    SELECT id_usuario, documento INTO v_user_found_id, v_doc_plano
    FROM fn_buscar_usuario_por_documento('1001001001');
    ASSERT v_user_found_id = 1 AND v_doc_plano = '1001001001', 'Busqueda por blind index usuario fallo';

    SELECT id_cliente, documento INTO v_cli_found_id, v_doc_plano
    FROM fn_buscar_cliente_por_documento('1098765432');
    ASSERT v_cli_found_id = 1 AND v_doc_plano = '1098765432', 'Busqueda por blind index cliente fallo';

    v_lat_ms := EXTRACT(EPOCH FROM (clock_timestamp() - v_t0)) * 1000.0;
    ASSERT v_lat_ms < 50.0, format('Latencia de busqueda cifrada excede 50ms: %s ms', v_lat_ms);
    v_pos_ok := v_pos_ok + 1;

    RAISE NOTICE 'POSITIVAS_OK: %/% aprobadas (latencia blind index + AES-256: % ms)',
                 v_pos_ok, v_pos_total, ROUND(v_lat_ms, 2);
END $$;

-- 4) Suite de Pruebas Negativas y Red-Team DB (27 casos destructivos/ofensivos)
DO $$
DECLARE
    v_neg_ok    INT := 0;
    v_neg_total INT := 27;
    v_tmp_id    INTEGER;
    v_tmp_caja  INTEGER;
    v_tmp_text  TEXT;
BEGIN
    -- N1: Contrasena debil en texto plano
    BEGIN
        INSERT INTO usuario (nombre, apellido, documento_cifrado, documento_hash, correo, contrasena, id_rol)
        VALUES ('Test', 'Debil', fn_cifrar_documento('999001'), fn_hash_documento('999001'), 'debil1@test.local', '1234567', 3);
        RAISE EXCEPTION 'FALLO_N1: acepto contrasena debil';
    EXCEPTION WHEN OTHERS THEN
        IF SQLERRM LIKE 'FALLO_N1%' THEN RAISE; END IF;
        v_neg_ok := v_neg_ok + 1;
    END;

    -- N2: Hash Bcrypt con costo debil (<12)
    BEGIN
        INSERT INTO usuario (nombre, apellido, documento_cifrado, documento_hash, correo, contrasena, id_rol)
        VALUES ('Test', 'Bcrypt10', fn_cifrar_documento('999002'), fn_hash_documento('999002'), 'bcrypt10@test.local', '$2a$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy', 3);
        RAISE EXCEPTION 'FALLO_N2: acepto Bcrypt cost < 12';
    EXCEPTION WHEN OTHERS THEN
        IF SQLERRM LIKE 'FALLO_N2%' THEN RAISE; END IF;
        v_neg_ok := v_neg_ok + 1;
    END;

    -- N3: Edicion directa de stock_actual en ingrediente
    BEGIN
        UPDATE ingrediente SET stock_actual = 99999 WHERE id_ingrediente = 1;
        RAISE EXCEPTION 'FALLO_N3: permitio alterar stock_actual directo';
    EXCEPTION WHEN OTHERS THEN
        IF SQLERRM LIKE 'FALLO_N3%' THEN RAISE; END IF;
        v_neg_ok := v_neg_ok + 1;
    END;

    -- N4: UPDATE en kardex (inventario)
    BEGIN
        UPDATE inventario SET cantidad = 1 WHERE id_movimiento = 1;
        RAISE EXCEPTION 'FALLO_N4: permitio UPDATE en inventario';
    EXCEPTION WHEN OTHERS THEN
        IF SQLERRM LIKE 'FALLO_N4%' THEN RAISE; END IF;
        v_neg_ok := v_neg_ok + 1;
    END;

    -- N5: DELETE en kardex (inventario)
    BEGIN
        DELETE FROM inventario WHERE id_movimiento = 1;
        RAISE EXCEPTION 'FALLO_N5: permitio DELETE en inventario';
    EXCEPTION WHEN OTHERS THEN
        IF SQLERRM LIKE 'FALLO_N5%' THEN RAISE; END IF;
        v_neg_ok := v_neg_ok + 1;
    END;

    -- N6: DELETE en auditoria (actividad)
    BEGIN
        DELETE FROM actividad WHERE id_actividad = 1;
        RAISE EXCEPTION 'FALLO_N6: permitio DELETE en actividad';
    EXCEPTION WHEN OTHERS THEN
        IF SQLERRM LIKE 'FALLO_N6%' THEN RAISE; END IF;
        v_neg_ok := v_neg_ok + 1;
    END;

    -- N7: DELETE fisico en pedido
    BEGIN
        DELETE FROM pedido WHERE id_pedido = 1;
        RAISE EXCEPTION 'FALLO_N7: permitio DELETE en pedido';
    EXCEPTION WHEN OTHERS THEN
        IF SQLERRM LIKE 'FALLO_N7%' THEN RAISE; END IF;
        v_neg_ok := v_neg_ok + 1;
    END;

    -- N8: Sobrepago en pedido
    BEGIN
        INSERT INTO caja (id_cajero, monto_apertura, estado) VALUES (2, 10000.00, 'ABIERTA') RETURNING id_caja INTO v_tmp_caja;
        INSERT INTO pedido (estado, id_cliente, id_mesa, id_mesero) VALUES ('PENDIENTE', 1, 1, 3) RETURNING id_pedido INTO v_tmp_id;
        INSERT INTO detalle_pedido (id_pedido, id_producto, cantidad, precio_unitario, impuesto_porcentaje) VALUES (v_tmp_id, 1, 1, 28000.00, 8.00);
        BEGIN
            INSERT INTO pago (id_pedido, id_caja, metodo_pago, valor, estado) VALUES (v_tmp_id, v_tmp_caja, 'EFECTIVO', 999999.00, 'COMPLETADO');
            RAISE EXCEPTION 'FALLO_N8: permitio sobrepago';
        EXCEPTION WHEN OTHERS THEN
            IF SQLERRM LIKE 'FALLO_N8%' THEN RAISE; END IF;
            v_neg_ok := v_neg_ok + 1;
        END;
        RAISE EXCEPTION '__CLEANUP_N8__';
    EXCEPTION WHEN OTHERS THEN
        IF SQLERRM <> '__CLEANUP_N8__' THEN RAISE; END IF;
    END;

    -- N9: Pago a pedido CANCELADO
    BEGIN
        INSERT INTO caja (id_cajero, monto_apertura, estado) VALUES (2, 10000.00, 'ABIERTA') RETURNING id_caja INTO v_tmp_caja;
        INSERT INTO pedido (estado, id_cliente, id_mesa, id_mesero) VALUES ('PENDIENTE', 1, 1, 3) RETURNING id_pedido INTO v_tmp_id;
        UPDATE pedido SET estado = 'CANCELADO' WHERE id_pedido = v_tmp_id;
        BEGIN
            INSERT INTO pago (id_pedido, id_caja, metodo_pago, valor, estado) VALUES (v_tmp_id, v_tmp_caja, 'EFECTIVO', 1000.00, 'COMPLETADO');
            RAISE EXCEPTION 'FALLO_N9: permitio pago a pedido CANCELADO';
        EXCEPTION WHEN OTHERS THEN
            IF SQLERRM LIKE 'FALLO_N9%' THEN RAISE; END IF;
            v_neg_ok := v_neg_ok + 1;
        END;
        RAISE EXCEPTION '__CLEANUP_N9__';
    EXCEPTION WHEN OTHERS THEN
        IF SQLERRM <> '__CLEANUP_N9__' THEN RAISE; END IF;
    END;

    -- N10: Pago en caja CERRADA
    BEGIN
        INSERT INTO pedido (estado, id_cliente, id_mesa, id_mesero) VALUES ('PENDIENTE', 1, 1, 3) RETURNING id_pedido INTO v_tmp_id;
        INSERT INTO detalle_pedido (id_pedido, id_producto, cantidad, precio_unitario, impuesto_porcentaje) VALUES (v_tmp_id, 1, 1, 28000.00, 8.00);
        BEGIN
            INSERT INTO pago (id_pedido, id_caja, metodo_pago, valor, estado) VALUES (v_tmp_id, 1, 'EFECTIVO', 1000.00, 'COMPLETADO');
            RAISE EXCEPTION 'FALLO_N10: permitio pago en caja cerrada';
        EXCEPTION WHEN OTHERS THEN
            IF SQLERRM LIKE 'FALLO_N10%' THEN RAISE; END IF;
            v_neg_ok := v_neg_ok + 1;
        END;
        RAISE EXCEPTION '__CLEANUP_N10__';
    EXCEPTION WHEN OTHERS THEN
        IF SQLERRM <> '__CLEANUP_N10__' THEN RAISE; END IF;
    END;

    -- N11: Cierre de caja sin arqueo
    BEGIN
        INSERT INTO caja (id_cajero, monto_apertura, estado) VALUES (2, 10000.00, 'ABIERTA') RETURNING id_caja INTO v_tmp_caja;
        BEGIN
            UPDATE caja SET estado = 'CERRADA', fecha_cierre = NOW(), efectivo_contado = NULL WHERE id_caja = v_tmp_caja;
            RAISE EXCEPTION 'FALLO_N11: permitio cierre sin arqueo';
        EXCEPTION WHEN OTHERS THEN
            IF SQLERRM LIKE 'FALLO_N11%' THEN RAISE; END IF;
            v_neg_ok := v_neg_ok + 1;
        END;
        RAISE EXCEPTION '__CLEANUP_N11__';
    EXCEPTION WHEN OTHERS THEN
        IF SQLERRM <> '__CLEANUP_N11__' THEN RAISE; END IF;
    END;

    -- N12: Reabrir caja CERRADA
    BEGIN
        UPDATE caja SET estado = 'ABIERTA' WHERE id_caja = 1;
        RAISE EXCEPTION 'FALLO_N12: permitio reabrir caja cerrada';
    EXCEPTION WHEN OTHERS THEN
        IF SQLERRM LIKE 'FALLO_N12%' THEN RAISE; END IF;
        v_neg_ok := v_neg_ok + 1;
    END;

    -- N13: Reducir detalle_pedido.cantidad debajo de personalizaciones
    BEGIN
        INSERT INTO pedido (estado, id_cliente, id_mesa, id_mesero) VALUES ('PENDIENTE', 1, 1, 3) RETURNING id_pedido INTO v_tmp_id;
        INSERT INTO detalle_pedido (id_pedido, id_producto, cantidad, precio_unitario, impuesto_porcentaje) VALUES (v_tmp_id, 1, 3, 28000.00, 8.00) RETURNING id_detalle INTO v_tmp_caja;
        INSERT INTO personalizacion_pedido (id_detalle, cantidad, termino_coccion) VALUES (v_tmp_caja, 2, 'MEDIO');
        BEGIN
            UPDATE detalle_pedido SET cantidad = 1 WHERE id_detalle = v_tmp_caja;
            RAISE EXCEPTION 'FALLO_N13: permitio reducir detalle debajo de personalizacion';
        EXCEPTION WHEN OTHERS THEN
            IF SQLERRM LIKE 'FALLO_N13%' THEN RAISE; END IF;
            v_neg_ok := v_neg_ok + 1;
        END;
        RAISE EXCEPTION '__CLEANUP_N13__';
    EXCEPTION WHEN OTHERS THEN
        IF SQLERRM <> '__CLEANUP_N13__' THEN RAISE; END IF;
    END;

    -- N14: Duplicidad de factura activa
    BEGIN
        INSERT INTO factura (id_pedido, numero_factura, subtotal, descuento_total, impuesto_total, estado_dian)
        VALUES (1, 'FE-DUP-001', 59000.00, 5900.00, 4248.00, 'PENDIENTE');
        RAISE EXCEPTION 'FALLO_N14: permitio factura activa duplicada';
    EXCEPTION WHEN OTHERS THEN
        IF SQLERRM LIKE 'FALLO_N14%' THEN RAISE; END IF;
        v_neg_ok := v_neg_ok + 1;
    END;

    -- N15: Transicion invalida en pedido
    BEGIN
        UPDATE pedido SET estado = 'PENDIENTE' WHERE id_pedido = 1;
        RAISE EXCEPTION 'FALLO_N15: permitio transicion invalida en pedido';
    EXCEPTION WHEN OTHERS THEN
        IF SQLERRM LIKE 'FALLO_N15%' THEN RAISE; END IF;
        v_neg_ok := v_neg_ok + 1;
    END;

    -- N16: Transicion invalida en factura
    BEGIN
        UPDATE factura SET estado_dian = 'ACEPTADA', cufe = 'CUFE-ILEGAL' WHERE id_factura = 1;
        RAISE EXCEPTION 'FALLO_N16: permitio transicion invalida en factura';
    EXCEPTION WHEN OTHERS THEN
        IF SQLERRM LIKE 'FALLO_N16%' THEN RAISE; END IF;
        v_neg_ok := v_neg_ok + 1;
    END;

    -- N17: Alteracion manual de pedido.subtotal
    BEGIN
        UPDATE pedido SET subtotal = 1.00 WHERE id_pedido = 1;
        RAISE EXCEPTION 'FALLO_N17: permitio alterar subtotal manualmente';
    EXCEPTION WHEN OTHERS THEN
        IF SQLERRM LIKE 'FALLO_N17%' THEN RAISE; END IF;
        v_neg_ok := v_neg_ok + 1;
    END;

    -- N18: Distribucion de propina superior a su valor
    BEGIN
        INSERT INTO propina_distribucion (id_propina, id_usuario, monto) VALUES (1, 2, 1.00);
        RAISE EXCEPTION 'FALLO_N18: permitio distribuir mas propina que su valor';
    EXCEPTION WHEN OTHERS THEN
        IF SQLERRM LIKE 'FALLO_N18%' THEN RAISE; END IF;
        v_neg_ok := v_neg_ok + 1;
    END;

    -- N19: Stock negativo en inventario
    BEGIN
        INSERT INTO inventario (id_ingrediente, tipo_movimiento, cantidad, motivo, id_usuario)
        VALUES (2, 'SALIDA', 999999.000, 'Intento stock negativo', 1);
        RAISE EXCEPTION 'FALLO_N19: permitio stock negativo';
    EXCEPTION WHEN OTHERS THEN
        IF SQLERRM LIKE 'FALLO_N19%' THEN RAISE; END IF;
        v_neg_ok := v_neg_ok + 1;
    END;

    -- N20: Doble caja abierta por cajero
    BEGIN
        INSERT INTO caja (id_cajero, monto_apertura, estado) VALUES (2, 10000.00, 'ABIERTA');
        BEGIN
            INSERT INTO caja (id_cajero, monto_apertura, estado) VALUES (2, 20000.00, 'ABIERTA');
            RAISE EXCEPTION 'FALLO_N20: permitio doble caja abierta';
        EXCEPTION WHEN OTHERS THEN
            IF SQLERRM LIKE 'FALLO_N20%' THEN RAISE; END IF;
            v_neg_ok := v_neg_ok + 1;
        END;
        RAISE EXCEPTION '__CLEANUP_N20__';
    EXCEPTION WHEN OTHERS THEN
        IF SQLERRM <> '__CLEANUP_N20__' THEN RAISE; END IF;
    END;

    -- N21 (Red-Team XXE): XML malicioso con DOCTYPE/ENTITY en factura
    BEGIN
        INSERT INTO pedido (estado, id_cliente, id_mesa, id_mesero) VALUES ('PENDIENTE', 1, 1, 3) RETURNING id_pedido INTO v_tmp_id;
        INSERT INTO detalle_pedido (id_pedido, id_producto, cantidad, precio_unitario, impuesto_porcentaje) VALUES (v_tmp_id, 1, 1, 28000.00, 8.00);
        BEGIN
            INSERT INTO factura (id_pedido, numero_factura, xml, subtotal, descuento_total, impuesto_total, estado_dian)
            VALUES (v_tmp_id, 'FE-XXE-001', '<?xml version="1.0"?><!DOCTYPE foo [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><Invoice>&xxe;</Invoice>', NULL, NULL, NULL, 'PENDIENTE');
            RAISE EXCEPTION 'FALLO_N21: permitio payload XXE en factura.xml';
        EXCEPTION WHEN OTHERS THEN
            IF SQLERRM LIKE 'FALLO_N21%' THEN RAISE; END IF;
            v_neg_ok := v_neg_ok + 1;
        END;
        RAISE EXCEPTION '__CLEANUP_N21__';
    EXCEPTION WHEN OTHERS THEN
        IF SQLERRM <> '__CLEANUP_N21__' THEN RAISE; END IF;
    END;

    -- N22 (Red-Team Crypto): Descifrado con llave incorrecta o vacia debe fallar
    BEGIN
        SELECT fn_descifrar_documento(documento_cifrado, 'LlaveIncorrectaDePruebaCon32Caracteres!') INTO v_tmp_text
        FROM usuario WHERE id_usuario = 1;
        RAISE EXCEPTION 'FALLO_N22: descifro con llave incorrecta';
    EXCEPTION WHEN OTHERS THEN
        IF SQLERRM LIKE 'FALLO_N22%' THEN RAISE; END IF;
        v_neg_ok := v_neg_ok + 1;
    END;

    -- N23 (Red-Team Least Privilege): app_restaurante_rw intentando SELECT contrasena FROM usuario
    BEGIN
        SET LOCAL ROLE app_restaurante_rw;
        BEGIN
            PERFORM contrasena FROM restaurante.usuario LIMIT 1;
            RAISE EXCEPTION 'FALLO_N23: app_restaurante_rw leyo usuario.contrasena';
        EXCEPTION WHEN OTHERS THEN
            IF SQLERRM LIKE 'FALLO_N23%' THEN RAISE; END IF;
            v_neg_ok := v_neg_ok + 1;
        END;
        RESET ROLE;
    EXCEPTION WHEN OTHERS THEN
        RESET ROLE;
        RAISE;
    END;

    -- N24 (Red-Team Least Privilege): app_restaurante_rw intentando DROP TABLE
    BEGIN
        SET LOCAL ROLE app_restaurante_rw;
        BEGIN
            DROP TABLE restaurante.pedido;
            RAISE EXCEPTION 'FALLO_N24: app_restaurante_rw ejecuto DROP TABLE';
        EXCEPTION WHEN OTHERS THEN
            IF SQLERRM LIKE 'FALLO_N24%' THEN RAISE; END IF;
            v_neg_ok := v_neg_ok + 1;
        END;
        RESET ROLE;
    EXCEPTION WHEN OTHERS THEN
        RESET ROLE;
        RAISE;
    END;

    -- N25 (Red-Team Least Privilege): app_restaurante_rw intentando TRUNCATE actividad
    BEGIN
        SET LOCAL ROLE app_restaurante_rw;
        BEGIN
            TRUNCATE TABLE restaurante.actividad;
            RAISE EXCEPTION 'FALLO_N25: app_restaurante_rw ejecuto TRUNCATE';
        EXCEPTION WHEN OTHERS THEN
            IF SQLERRM LIKE 'FALLO_N25%' THEN RAISE; END IF;
            v_neg_ok := v_neg_ok + 1;
        END;
        RESET ROLE;
    EXCEPTION WHEN OTHERS THEN
        RESET ROLE;
        RAISE;
    END;

    -- N26 (Red-Team Least Privilege): app_restaurante_rw intentando DELETE FROM inventario
    BEGIN
        SET LOCAL ROLE app_restaurante_rw;
        BEGIN
            DELETE FROM restaurante.inventario WHERE id_movimiento = 1;
            RAISE EXCEPTION 'FALLO_N26: app_restaurante_rw ejecuto DELETE en inventario';
        EXCEPTION WHEN OTHERS THEN
            IF SQLERRM LIKE 'FALLO_N26%' THEN RAISE; END IF;
            v_neg_ok := v_neg_ok + 1;
        END;
        RESET ROLE;
    EXCEPTION WHEN OTHERS THEN
        RESET ROLE;
        RAISE;
    END;

    -- N27 (Red-Team Least Privilege): app_restaurante_rw intentando UPDATE en actividad
    BEGIN
        SET LOCAL ROLE app_restaurante_rw;
        BEGIN
            UPDATE restaurante.actividad SET accion = 'Alterado' WHERE id_actividad = 1;
            RAISE EXCEPTION 'FALLO_N27: app_restaurante_rw ejecuto UPDATE en actividad';
        EXCEPTION WHEN OTHERS THEN
            IF SQLERRM LIKE 'FALLO_N27%' THEN RAISE; END IF;
            v_neg_ok := v_neg_ok + 1;
        END;
        RESET ROLE;
    EXCEPTION WHEN OTHERS THEN
        RESET ROLE;
        RAISE;
    END;

    ASSERT v_neg_ok = v_neg_total, format('Solo pasaron %s/%s pruebas negativas', v_neg_ok, v_neg_total);
    RAISE NOTICE 'NEGATIVAS_REDTEAM_OK: %/% bloqueadas correctamente', v_neg_ok, v_neg_total;
END $$;

-- 5) Verificacion operativa con rol app_restaurante_rw (autenticacion, vistas, timeouts y blind index)
BEGIN;
SET LOCAL ROLE app_restaurante_rw;
SHOW statement_timeout;
SELECT id_usuario, nombre, apellido, rol_nombre, estado FROM vw_usuarios_publico ORDER BY id_usuario;
SELECT id_usuario, nombre, apellido, id_rol, estado FROM fn_autenticar_usuario('admin@restaurante.local', 'AdminRest2026*');
SELECT id_usuario, nombre, documento, correo FROM fn_buscar_usuario_por_documento('1001001001');
SELECT id_cliente, nombre, documento, correo FROM fn_buscar_cliente_por_documento('1098765432');
SELECT id_caja, id_cajero, estado, monto_apertura, total_efectivo_ventas, total_efectivo_propinas, total_ventas_digitales, efectivo_esperado, efectivo_contado, diferencia FROM vw_resumen_caja;
SELECT id_asistencia, id_usuario, fecha, horas_trabajadas, horas_extra FROM vw_asistencia_horas;
SELECT id_pedido, estado, subtotal, descuento_total, impuesto_total, total, total_pagado, saldo_pendiente, total_propinas, numero_factura, estado_dian, total_facturado FROM vw_conciliacion_pedidos;
RESET ROLE;
COMMIT;
