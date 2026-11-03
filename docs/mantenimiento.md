# Bloqueos por mantenimiento

Recepción y administración pueden bloquear habitaciones por fechas sin desactivarlas indefinidamente. La pantalla **Mantenimiento** permite revisar conflictos, confirmar un bloqueo y levantarlo anticipadamente con un motivo. Cada operación registra empleado y hora; no se eliminan registros.

## Uso

1. Elegir habitación, primera noche bloqueada, fecha desde la que vuelve a estar disponible y motivo (hasta 500 caracteres).
2. Pulsar **Revisar conflictos**. Las reservas confirmadas afectadas aparecen con enlaces a sus detalles. Si el bloqueo comienza hoy, también se comprueban huéspedes sin salida registrada, aunque su estadía prevista ya haya terminado.
3. Resolver reservas mediante reprogramación o cancelación cuando corresponda, o registrar la salida real. No hay cambios automáticos sobre reservas.
4. Revisar nuevamente y confirmar. El servidor vuelve a comprobar conflictos al guardar. Una revisión vence en 20 minutos y no retiene la habitación.
5. Para terminar antes, abrir **Levantar bloqueo**, indicar el motivo y confirmar. Se liberan las noches pendientes desde hoy y se conserva el historial de noches anteriores.

El fin es exclusivo: del 23 al 25 bloquea las noches del 23 y 24. El 25 ya no se aplica ese bloqueo. No admite fechas de inicio pasadas ni períodos fuera del límite de un año. Al alcanzar la fecha final, deja de bloquear automáticamente sin tareas programadas.

## Integración

- No permite bloques superpuestos ni bloques sobre reservas confirmadas. Las solicitudes pendientes no ocupan noches, pero no podrán confirmarse durante mantenimiento.
- Las solicitudes públicas, altas desde recepción, confirmaciones, reprogramaciones y registro de llegada comprueban mantenimiento en el servidor.
- El calendario mensual y la tabla muestran **En mantenimiento** y enlazan el bloqueo. El JSON no expone motivo, empleado ni tokens.
- El panel descuenta mantenimiento de habitaciones disponibles para la noche consultada. No lo cuenta como reserva.
- Levantar un bloqueo no completa la limpieza ni reactiva habitaciones. Las reservas y los datos de huéspedes se conservan.
- El historial muestra los 30 últimos bloqueos terminados o levantados. El enlace del calendario también permite consultar un bloqueo más antiguo.

## Backend y pruebas

`src/Maintenance.php` concentra revisión, alta, consulta y levantamiento. Al guardar o levantar toma primero el bloqueo de la habitación, compartido por las operaciones de reserva. Esto evita aceptar a la vez un mantenimiento y una reserva para el mismo período. El token de creación evita duplicados al reenviar; un segundo levantamiento no sobrescribe la auditoría.

`database/maintenance.sql` crea `room_maintenance` sin modificar los registros existentes. Para actualizar, ejecutar `php scripts/migrate.php` antes de cargar el código nuevo. Las instalaciones nuevas usan `database/schema.sql`.

`php tests/run.php` verifica límites, conflictos, permisos, CSRF, reenvíos, conservación de datos, calendario, panel, limpieza y concurrencia real entre procesos. `php tests/seed-demo.php` verifica datos demo. Ambas utilizan bases temporales, no la instalación del hotel.
