# Señas, pagos y saldo de reservas

Implementación local del 23/10/2026. Registro manual para el proyecto académico Hotel Aurora, con importes en ARS. No ejecuta transferencias ni cargos en tarjetas y no emite comprobantes fiscales.

## Uso por recepción y administración

1. Abrir una reserva desde **Solicitudes** o **Calendario** y entrar en **Gestionar señas y pagos**.
2. Revisar total, cobros, devoluciones, pagos netos, saldo pendiente e importe a favor.
3. Elegir **Seña**, **Pago** o **Devolución**. Seña y pago tienen el mismo efecto en el saldo; la categoría conserva el concepto del cobro.
4. Ingresar un importe positivo sin separadores de miles, con punto o coma decimal y hasta dos decimales. Elegir efectivo, transferencia, tarjeta u otro.
5. Indicar la fecha real del movimiento y un concepto o referencia (1 a 500 caracteres). No registrar datos de tarjetas, códigos de seguridad ni claves. Se admiten fechas desde 2000 hasta hoy, para poder cargar movimientos anteriores; la fecha y hora de carga se guardan por separado y no son editables.
6. Confirmar que el movimiento ya se realizó y guardar. Revisar el saldo y la entrada del historial.

Los cobros requieren reserva confirmada y no pueden superar su saldo pendiente. Se admiten pagos después de registrar la salida para saldar una deuda. El registro de salida muestra el saldo pendiente, pero no exige saldarlo para representar la salida real.

## Corrección y devolución

**Anular registro** corrige un dato cargado por error. Exige motivo y aceptación explícita. Conserva importe, concepto, medio, empleado y hora originales y añade responsable, motivo y hora de anulación. El registro deja de contar en el saldo y en el resumen; no se elimina. Para reemplazarlo, cargar un movimiento nuevo después de anularlo.

**Devolución** registra dinero efectivamente devuelto. No puede superar los pagos netos de la reserva. Puede registrarse sobre una confirmada o cancelada. La anulación de un cobro se rechaza si dejaría más devoluciones que cobros; primero se deben revisar los registros de devolución incorrectos. Anular una devolución puede generar un importe a favor, que queda visible.

Ejemplo: una estadía de $65.000 recibe una seña de $20.000 y queda con $45.000 pendientes. Al cargar un pago de $45.000 el saldo llega a cero. Si ese pago se cargó por error, anularlo vuelve a mostrar $45.000 pendientes y conserva la corrección en el historial.

## Reprogramación y cancelación

- Reprogramar conserva todos los movimientos y calcula el saldo con el nuevo total. La revisión del cambio muestra el efecto sobre los pagos registrados en ese momento; otros empleados pueden registrar pagos antes de guardar.
- Si el nuevo total es menor que los pagos netos, la diferencia queda a favor. No se devuelve automáticamente.
- Cancelar deja de exigir el importe de la estadía y mantiene todos los pagos netos a favor del huésped. No hay penalidades de cancelación en este alcance. Una devolución se carga cuando efectivamente se realiza.
- Pendientes y rechazadas no reciben cobros. Confirmar una reserva habilita su seguimiento de pagos.

Fórmulas: `neto = cobros vigentes − devoluciones vigentes`; para confirmadas, `pendiente = máximo(0, total − neto)` y `a favor = máximo(0, neto − total)`. En canceladas, el pendiente es cero y el neto queda a favor. El total original se muestra como referencia.

## Cobros del día

La sección **Cobros** permite elegir una fecha y consultar cobros, devoluciones y neto, con desglose por medio y enlaces a cada reserva. Agrupa por la fecha declarada del movimiento, no por la hora de carga. Puede haber neto negativo si ese día hubo más devoluciones que cobros.

Los totales reflejan las correcciones actuales: una anulación posterior modifica el total mostrado para la fecha original, manteniendo la entrada y la auditoría visibles. No es un cierre inmutable de caja, una medida de ingresos devengados ni un reporte de ocupación. No incluye monedas adicionales, impuestos, consumos extra, conciliación bancaria, adjuntos o pasarela de pagos.

## Persistencia y consistencia

`reservation_payments` relaciona cada movimiento con una reserva y los usuarios que lo registran/anulan. Usa `DECIMAL(12,2)`; las reglas convierten los importes a centavos enteros antes de sumar y comparar. La reserva determina el total: nunca se toma del navegador.

Las altas y anulaciones bloquean primero la fila de la reserva. Reprogramaciones y cancelaciones también bloquean esa fila. Cada operación revalida el estado y el saldo después de esperar, dentro de una transacción. No modifica habitaciones ni estadías. El token del formulario pertenece a la sesión y la reserva, vence a las dos horas y evita duplicar un envío. El reenvío con datos diferentes se rechaza.

Todos los accesos requieren personal autenticado. Los cambios se realizan por POST con CSRF, confirmación y actor obtenido de la sesión. Los motivos se escapan al mostrarse. No se exponen movimientos en el calendario público ni se aceptan importes negativos para representar devoluciones.

Para actualizar: respaldar la instalación y ejecutar `php scripts/migrate.php` antes de poner en uso el código nuevo. La migración crea la tabla sin alterar las existentes y admite repetición. Las instalaciones nuevas la reciben desde `database/schema.sql`.

## Evidencia

La suite pasó 616 comprobaciones de reglas, HTTP y concurrencia, y la carga demo pasó 17. `tests/payment-cases.php` incluye 95 comprobaciones nuevas. Cubre centavos, formatos, límites, permisos, CSRF, reenvío, auditoría, sobrecobros, devoluciones, cancelación, reprogramación, resumen y migración repetida. `tests/payment-worker.php` verifica seis escenarios con dos procesos esperando sobre la misma reserva. Las pruebas usan bases temporales independientes.
