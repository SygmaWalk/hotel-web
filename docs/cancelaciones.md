# Cancelación de reservas · HOT-25

Recepción y administración pueden cancelar una reserva confirmada mientras no tenga una llegada registrada. La reserva permanece consultable y sus noches dejan de bloquear el calendario. Se conserva la confirmación original y se registra por separado quién canceló, cuándo y por qué.

## Cómo usarla

1. Abrir el detalle desde Calendario o Solicitudes.
2. En **Cancelar reserva**, escribir el motivo (entre 1 y 500 caracteres).
3. Marcar la confirmación, que identifica reserva, habitación y fechas.
4. Pulsar **Confirmar cancelación**. El detalle mostrará el historial.
5. Usar el filtro **Cancelada** en Solicitudes para consultar cancelaciones anteriores.

Funciona sin JavaScript. Si otra persona ya canceló o registró la llegada, el servidor rechaza el formulario anterior y muestra el estado actualizado. Después de una llegada, incluso con salida registrada, no se permite cancelar. Las solicitudes pendientes se confirman o rechazan mediante sus acciones habituales.

El importe original se conserva como dato de la reserva; no representa un cobro ni un reembolso. Este proyecto no procesa pagos. No se reactiva una habitación inactiva ni se cambia su estado de limpieza al cancelar. No hay reactivación de reservas canceladas en esta etapa: una nueva reserva pasa nuevamente por disponibilidad.

## Recorrido para aprender backend

- `public/solicitud.php` recibe el POST, usa la sesión para identificar al empleado y solicita la operación a `Hotel`.
- `src/bootstrap.php` valida CSRF antes de procesar el formulario. El motivo se escapa al mostrarlo.
- `Hotel::cancelReservation()` valida motivo, confirmación, empleado y estado.
- La transacción bloquea primero la habitación, luego la reserva y la estadía, igual que el registro de llegada. Así una llegada y una cancelación concurrentes no pueden aceptarse juntas.
- La transacción inserta la auditoría en `reservation_cancellations` y cambia el estado a `cancelled`; cualquier fallo revierte ambas escrituras.
- Calendario, disponibilidad y panel cuentan reservas `confirmed`, por lo que las fechas se liberan al confirmar la transacción.
- La respuesta redirige al detalle; reenviar la cancelación no sobrescribe motivo, fecha ni responsable.

## Instalación y pruebas

Las instalaciones nuevas usan `database/schema.sql`. En una instalación existente, ejecutar por consola `php scripts/migrate.php` antes de cargar el código nuevo. Incorpora el estado `cancelled` y la tabla de auditoría mediante `database/cancellations.sql`, sin borrar registros. Puede repetirse.

`php tests/run.php` crea una base temporal y prueba permisos, CSRF, motivo obligatorio, confirmación explícita, historial, escapado, liberación de fechas, panel, reservas posteriores, bloqueos con estadía, migración desde el esquema anterior, reversión ante fallo y concurrencia real entre procesos. `php tests/seed-demo.php` verifica los datos de demostración. Las pruebas no utilizan `hotel_aurora`.
