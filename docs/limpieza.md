# Limpieza de habitaciones · HOT-13, primera etapa

Instalado en XAMPP el 19/10/2026. Primera etapa de HOT-13: limpieza después de cada salida registrada.

## Uso

1. Registrar la salida de un huésped desde el detalle de su reserva. En la misma transacción se crea una tarea de limpieza pendiente.
2. Abrir **Limpieza** desde el menú, desde el panel del día o desde el calendario.
3. Una vez terminada la limpieza, pulsar **Marcar como lista** en la habitación correspondiente.
4. El sistema registra empleado y hora. La habitación puede recibir una nueva llegada, siempre que también se cumplan las reglas de la reserva y de ocupación.

Recepción y administración tienen acceso. Las operaciones de escritura requieren sesión y CSRF y usan POST con redirección 303. La pantalla funciona sin JavaScript.

## Reglas

- La limpieza es un estado actual, independiente de la fecha consultada en el panel y el calendario. No se presenta como un estado histórico o una predicción futura.
- Una limpieza pendiente bloquea el check-in, incluso si alguien envía directamente el POST. No impide crear reservas actuales o futuras; esas reservas no equivalen a una llegada física.
- La salida y la tarea se guardan juntas: si falla el INSERT de limpieza, se revierte el check-out.
- La salida anticipada no acorta las noches contratadas. La limpieza tampoco cambia fechas ni estados de reservas.
- Un formulario antiguo identifica la tarea de una salida concreta. No puede completar una tarea nueva de la misma habitación.
- Repetir la acción no sobrescribe el empleado ni la hora de una limpieza terminada.
- Se puede limpiar una habitación inactiva; eso no la reactiva.
- Una habitación con huésped sin salida registrada no se puede marcar como lista. La pantalla muestra «En uso» para evitar confundir limpieza con disponibilidad.
- Los bloqueos de mantenimiento por intervalos quedan para la segunda etapa de HOT-13.

## Datos y migración

La nueva tabla `room_cleanings` vincula cada tarea con la estadía que terminó. Guarda `pending_at`, `completed_at` y `completed_by`. El empleado que registró la salida se conserva en `stays`.

Las instalaciones nuevas usan `database/schema.sql`. En una instalación existente, `scripts/migrate.php` aplica `database/operations.sql` y `database/cleaning.sql` con CREATE TABLE IF NOT EXISTS. No modifica ni elimina reservas, habitaciones, usuarios o estadías existentes.

No se reconstruyen limpiezas anteriores a la instalación: los registros empiezan con las nuevas salidas. «Sin limpieza pendiente» significa que no hay una tarea abierta en el sistema, no una inspección física certificada.

## Para explicar el backend

**Salida:** POST → sesión y CSRF → bloqueo de habitación → UPDATE de estadía → INSERT de limpieza → COMMIT.

**Habitación lista:** POST → sesión y CSRF → bloqueo de habitación → comprobar tarea y ausencia de huésped → UPDATE con empleado y hora → COMMIT.

**Llegada:** además de validar reserva, fechas, habitación activa y ausencia de otro huésped, se comprueba que no haya limpieza pendiente.

Las tres operaciones toman primero el mismo bloqueo de habitación para serializar cambios concurrentes. Un GET solo consulta; no termina limpiezas.

## Pruebas realizadas

La suite `tests/run.php` pasó **307 comprobaciones** en una base temporal. Incluye 46 comprobaciones adicionales respecto de la versión de reservas desde recepción: salida y limpieza atómicas ante fallo simulado, bloqueo de check-in, reservas futuras conservadas, permisos, CSRF, GET sin efectos, tareas de otra habitación, formularios antiguos, auditoría, inactivas, migración repetida y dos procesos concurrentes tanto para check-out como para completar limpieza.

También pasó la revisión de sintaxis PHP y de `calendar.js`. En la instalación final volvieron a pasar las 307 comprobaciones y las 17 de datos de demostración, siempre con bases temporales.

Se verificó en navegador de escritorio con datos ficticios: aviso y botón de llegada deshabilitado, completar limpieza con empleado y hora, habilitar y registrar llegada, registrar salida y crear una nueva tarea pendiente. Se revisaron el panel del día, la pantalla Limpieza y el indicador actual del calendario mensual. La comprobación de ancho móvil se interrumpió por un fallo de sincronización de la herramienta; no se afirma verificación móvil.

La migración en `hotel_aurora` conservó exactamente los registros de las seis tablas existentes, comprobados antes y después. La cuenta de la aplicación pudo consultar la tabla nueva. En Apache real se comprobó acceso autenticado como recepción a limpieza, panel, calendario, nueva reserva y JSON, y se cerró la sesión sin cambiar reservas ni estados de limpieza.
