# Flujo Principal del Sistema Requesty

**Proyecto**: Requesty - Sistema de Gestión Gastronómica  
**Programa**: ADSO · Ficha 3478025  
**Trimestre**: 4.º Trimestre  

---

## 1. Diagrama de Ruta del Dato (Arquitectura MVC + API + Móvil)

```
Navegador Web / App Kotlin (Android)
       │
       ▼
   public/index.php  (Front Controller)
       │
       ▼
   app/core/Router.php  (Enrutador Web / API REST)
       │
       ├── Web ──► Controller (app/controllers/) ──► Model (app/models/) ──► Base de Datos (MySQL/PostgreSQL)
       │                                                    │
       │                                                    ▼
       │                                         Vista HTML (app/views/)
       │
       └── API ──► ApiController (app/controllers/api/) ──► Model ──► Base de Datos
                                                            │
                                                            ▼
                                                   Respuesta JSON (HTTP 200/201/401/404)
```

---

## 2. Recorrido Paso a Paso del Flujo Principal

### Escenario: "Registro de Pedido, Atención en Cocina y Cierre en Caja"

1. **Autenticación Segura (Web / Móvil)**:
   - El usuario inicia sesión en la web (`/login`) o mediante la app móvil con credenciales cifradas (`password_verify`).
   - El sistema regenera el identificador de sesión (`session_regenerate_id`) o genera un Bearer Token único para la API REST.

2. **Creación de Pedido (CRUD / Móvil)**:
   - El mesero crea un nuevo pedido para la Mesa 4 a través de la interfaz web o mediante la App Android consumiendo `POST /api/pedidos`.
   - El controlador valida los datos en servidor, sanitiza entradas y persiste el pedido en la base de datos con estado `PENDIENTE`.

3. **Operación en Cocina y Estado**:
   - El módulo de cocina recibe el pedido en tiempo real.
   - Se actualiza el estado a `EN_PREPARACION` y luego a `LISTO` mediante `PUT /api/pedidos/{id}` o acción web.

4. **Arqueo y Facturación en Caja**:
   - El cajero consulta el pedido activo, aplica métodos de pago (Efectivo / Transferencia) y genera el cierre de cuenta.

5. **Reporte Tabular y Exportación CSV/PDF**:
   - El administrador ingresa a `/reportes`, filtra por rango de fechas y exporta el reporte de ventas y auditoría en formato CSV para análisis contable.
