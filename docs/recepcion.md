# Reservas creadas por recepción

Implementación local del 19/10/2026. Disponible para recepción y administración.

## Recorrido

1. Ingresar como personal y abrir **Calendario → Nueva reserva**, o elegir **+ Reservar** en una noche libre. Desde una noche se precargan la habitación y una estadía de una noche.
2. Elegir entrada y salida y pulsar **Consultar disponibilidad**. Se muestran solo las habitaciones activas disponibles durante todo el intervalo.
3. Completar nombre y correo del huésped y elegir habitación. La tarifa por noche está visible; JavaScript calcula el importe orientativo.
4. Pulsar **Guardar reserva confirmada**. Se vuelve al calendario en el mes, fecha y habitación correspondientes, con un aviso y la reserva visible.
5. Abrir la reserva para consultar su detalle o registrar la llegada cuando corresponda. El detalle muestra el empleado que la resolvió y la fecha.

Cambiar las fechas requiere consultar nuevamente antes de guardar. El formulario y el acceso desde la tabla también funcionan sin JavaScript. Las fechas de la reserva que se va a guardar se muestran sobre el formulario del huésped.

## Reglas

- Las reservas manuales nacen confirmadas. Las solicitudes del sitio público siguen naciendo pendientes.
- La entrada puede ser hoy; la salida debe ser posterior y quedar dentro del próximo año calendario.
- La salida no ocupa esa noche: se permiten reservas contiguas.
- Las pendientes y rechazadas no bloquean. Una salida anticipada no acorta el intervalo contratado.
- Para una entrada de hoy, un huésped con estadía abierta bloquea la habitación hasta registrar su salida. Las fechas futuras siguen basadas en las reservas previstas.
- No se registra check-in automáticamente ni se envían correos.
- La tarifa se toma de la habitación y el empleado de la sesión; no se aceptan precios, estados ni identificadores de empleado enviados por el navegador.
- La consulta inicial es orientativa: al guardar, el servidor comprueba nuevamente disponibilidad dentro de una transacción.
- Un reenvío del mismo formulario no crea otra reserva. Reutilizarlo con otros datos se rechaza; para otra reserva se abre un formulario nuevo.

No requiere migración: se usan las columnas existentes `status`, `resolved_by`, `resolved_at` y `submission_token`. No se incorpora una etiqueta de origen manual/público; el empleado registrado es el responsable de la confirmación.

## Conceptos de backend

**GET → validación de fechas → SELECT con PDO → habitaciones disponibles.**

**POST → sesión y CSRF → validación → transacción → bloqueo de habitación → control de solapamientos → INSERT confirmado → COMMIT → redirección 303 al calendario.**

`Hotel::createReservation()` guarda y confirma en una única transacción. Si falla la disponibilidad, no queda una solicitud pendiente accidental. `Hotel::resolve()` utiliza la misma comprobación de disponibilidad y el mismo bloqueo de habitación; por eso una confirmación y un alta manual no pueden ocupar simultáneamente las mismas noches.

## Verificación

La suite `tests/run.php` pasa 261 comprobaciones, con 61 adicionales para esta ampliación. `tests/seed-demo.php` conserva 17 comprobaciones. Se usan bases temporales, nunca las reservas de `hotel_aurora`.

Los casos nuevos cubren disponibilidad de todo el intervalo, fechas límite, habitaciones inactivas, estadías abiertas, tarifas y actores del servidor, sesión, CSRF, escape de HTML, conflictos, reenvíos y visualización en el calendario. Tres pruebas con procesos independientes comprueban dos altas concurrentes, alta contra confirmación y dos reenvíos concurrentes del mismo token.

Revisión de navegador de escritorio en un entorno temporal: ingreso como recepción, selección de noche libre, ampliación a tres noches, consulta de disponibilidad, cálculo del importe, guardado, regreso al calendario, detalle de la reserva y cierre con Escape. También se comprobó que cambiar fechas deshabilita Guardar hasta consultar nuevamente. Sin errores de consola. No se realizó en esta revisión una comprobación específica de pantalla móvil.
