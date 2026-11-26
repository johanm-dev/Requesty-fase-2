<?php
/**
 * Modelo de Reportes y Analitica SQL en tiempo real (reporte + factura + detalle_pedido + vw_conciliacion_pedidos).
 */
class Reporte extends Model {
    public function generarResumen(string $fechaInicio, string $fechaFin, int $idUsuario, ?string $tipoRegistro = null): array {
        if ($tipoRegistro !== null) {
            $this->execute(
                'INSERT INTO reporte (tipo, fecha_inicio, fecha_fin, id_usuario) VALUES (:t, :fi, :ff, :u)',
                [
                    ':t'  => $tipoRegistro,
                    ':fi' => $fechaInicio,
                    ':ff' => $fechaFin,
                    ':u'  => $idUsuario,
                ]
            );
        }

        // Facturas no anuladas/rechazadas o pedidos pagados dentro del periodo
        $kpis = $this->fetchOne(
            "SELECT
                COALESCE(SUM(CASE WHEN p.estado = 'PAGADO' THEN p.total ELSE 0 END), 0)::NUMERIC(12,2) AS ventas_totales,
                COUNT(DISTINCT CASE WHEN p.estado = 'PAGADO' THEN p.id_pedido END) AS pedidos_cobrados,
                COALESCE(SUM(CASE WHEN p.estado = 'PAGADO' THEN p.descuento_total ELSE 0 END), 0)::NUMERIC(12,2) AS descuentos_totales,
                COALESCE(SUM(CASE WHEN p.estado = 'PAGADO' THEN p.impuesto_total ELSE 0 END), 0)::NUMERIC(12,2) AS impuestos_totales
             FROM pedido p
             WHERE (p.fecha_hora AT TIME ZONE 'America/Bogota')::DATE BETWEEN :fi::DATE AND :ff::DATE",
            [':fi' => $fechaInicio, ':ff' => $fechaFin]
        );

        $porDia = $this->fetchAll(
            "SELECT (p.fecha_hora AT TIME ZONE 'America/Bogota')::DATE AS dia,
                    COALESCE(SUM(p.total), 0)::NUMERIC(12,2) AS total_ventas,
                    COUNT(*) AS cantidad_pedidos
             FROM pedido p
             WHERE p.estado = 'PAGADO'
               AND (p.fecha_hora AT TIME ZONE 'America/Bogota')::DATE BETWEEN :fi::DATE AND :ff::DATE
             GROUP BY (p.fecha_hora AT TIME ZONE 'America/Bogota')::DATE
             ORDER BY dia ASC",
            [':fi' => $fechaInicio, ':ff' => $fechaFin]
        );

        $topProductos = $this->fetchAll(
            "SELECT pr.id_producto, pr.nombre, SUM(dp.cantidad)::INT AS cantidad_vendida,
                    COALESCE(SUM(dp.subtotal), 0)::NUMERIC(12,2) AS total_generado
             FROM detalle_pedido dp
             JOIN pedido p ON p.id_pedido = dp.id_pedido
             JOIN producto pr ON pr.id_producto = dp.id_producto
             WHERE p.estado = 'PAGADO'
               AND (p.fecha_hora AT TIME ZONE 'America/Bogota')::DATE BETWEEN :fi::DATE AND :ff::DATE
             GROUP BY pr.id_producto, pr.nombre
             ORDER BY cantidad_vendida DESC, total_generado DESC
             LIMIT 5",
            [':fi' => $fechaInicio, ':ff' => $fechaFin]
        );

        $horasPico = $this->fetchAll(
            "SELECT EXTRACT(HOUR FROM (p.fecha_hora AT TIME ZONE 'America/Bogota'))::INT AS hora,
                    COUNT(*)::INT AS cantidad_pedidos
             FROM pedido p
             WHERE p.estado IN ('ENTREGADO', 'PAGADO')
               AND (p.fecha_hora AT TIME ZONE 'America/Bogota')::DATE BETWEEN :fi::DATE AND :ff::DATE
             GROUP BY hora
             ORDER BY cantidad_pedidos DESC, hora ASC
             LIMIT 5",
            [':fi' => $fechaInicio, ':ff' => $fechaFin]
        );

        $facturas = $this->fetchAll(
            "SELECT f.id_factura, f.id_pedido, f.numero_factura, f.fecha, f.total,
                    f.estado_dian, f.cufe,
                    c.nombre AS cliente_nombre, c.correo AS cliente_correo,
                    m.numero_mesa, u.nombre AS mesero_nombre,
                    (SELECT STRING_AGG(pg.metodo_pago::TEXT, ', ' ORDER BY pg.id_pago)
                       FROM pago pg WHERE pg.id_pedido = f.id_pedido AND pg.estado = 'COMPLETADO') AS metodo_pago
             FROM factura f
             JOIN pedido p ON p.id_pedido = f.id_pedido
             LEFT JOIN vw_clientes_publico c ON c.id_cliente = p.id_cliente
             LEFT JOIN mesa m ON m.id_mesa = p.id_mesa
             LEFT JOIN vw_usuarios_publico u ON u.id_usuario = p.id_mesero
             WHERE (f.fecha AT TIME ZONE 'America/Bogota')::DATE BETWEEN :fi::DATE AND :ff::DATE
             ORDER BY f.id_factura DESC",
            [':fi' => $fechaInicio, ':ff' => $fechaFin]
        );

        $historial = $this->fetchAll(
            "SELECT r.id_reporte, r.tipo, r.fecha_inicio, r.fecha_fin, r.fecha_generacion,
                    u.nombre AS usuario_nombre
             FROM reporte r
             JOIN vw_usuarios_publico u ON u.id_usuario = r.id_usuario
             ORDER BY r.id_reporte DESC LIMIT 20"
        );

        return [
            'fecha_inicio'  => $fechaInicio,
            'fecha_fin'     => $fechaFin,
            'kpis'          => $kpis,
            'serie_diaria'  => $porDia,
            'top_productos' => $topProductos,
            'horas_pico'    => $horasPico,
            'facturas'      => $facturas,
            'historial'     => $historial,
        ];
    }
}
