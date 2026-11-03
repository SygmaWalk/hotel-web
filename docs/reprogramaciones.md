# Reprogramación de reservas

Recepción y administración pueden cambiar fechas o habitación de una reserva confirmada sin ingreso registrado. Se conserva su número, huésped, correo y confirmación original. Cada modificación registra habitación, fechas y tarifa anteriores y nuevas, además del motivo, hora y empleado.

## Uso

1. Abrir el detalle desde Solicitudes o Calendario y elegir **Reprogramar reserva**.
2. Consultar las fechas nuevas. La disponibilidad excluye la propia reserva y contempla el intervalo completo.
3. Elegir una habitación disponible e indicar el motivo, de hasta 500 caracteres.
4. Pulsar **Revisar cambio e importe** para comparar antes, después y diferencia total.
5. Marcar la confirmación y guardar. La propuesta vence a los 20 minutos.

Consultar y revisar no cambian la reserva ni retienen la habitación de destino. Si otra persona ocupa las fechas, modifica la reserva, registra el ingreso o cambia la tarifa de destino, el servidor rechaza una propuesta desactualizada. La reserva original permanece intacta ante cualquier fallo. El flujo también funciona sin JavaScript; la revisión muestra siempre las fechas que se guardarán.

## Tarifa e historial

- Misma habitación: conserva la tarifa por noche acordada, aunque haya cambiado la tarifa del catálogo. El total cambia según la cantidad de noches.
- Otra habitación: utiliza la tarifa vigente de destino, que debe seguir coincidiendo con la revisada al guardar.
- La diferencia se calcula en centavos enteros. El proyecto no cobra ni devuelve dinero.
- Una habitación inactiva puede ser el origen del traslado, pero no el destino. Reprogramar no reactiva habitaciones ni cambia tareas de limpieza.
- Después de cualquier ingreso registrado, incluso con salida, no se permite reprogramar. Pendientes, rechazadas y canceladas tampoco admiten cambios.
- El historial permanece visible aun después de una cancelación. Guarda también los códigos de habitación usados en ese momento.

## Recorrido del backend

`public/reserva-reprogramar.php` usa POST y CSRF para revisar y guardar. La propuesta se guarda en la sesión: el navegador solo devuelve un identificador aleatorio; no determina tarifa, importe ni empleado. Una propuesta no sirve para otra reserva ni otra sesión.

`src/Rescheduling.php` concentra la regla y reutiliza la disponibilidad de `Hotel`. Al guardar bloquea las habitaciones por orden numérico y luego la reserva. Verifica su estado, ausencia de ingreso, datos revisados y último cambio del historial; esto invalida formularios antiguos incluso si alguien vuelve a las fechas iniciales. El registro de llegada y la cancelación también comprueban que la habitación leída antes del bloqueo siga siendo la actual.

La actualización de la reserva y la inserción en `reservation_changes` forman una sola transacción. El calendario y el panel leen la habitación y fechas actualizadas de la misma reserva; no se crea otra ni se borra el historial.

## Instalación y verificación

El esquema nuevo incluye `reservation_changes`. Para una base existente, ejecutar por consola `php scripts/migrate.php` antes de instalar el código nuevo. `database/rescheduling.sql` es repetible y no modifica las filas existentes.

`php tests/run.php` usa una base temporal para comprobar validación, acceso, CSRF, importes, revisión sin escrituras, reenvíos, propuestas antiguas, historial, reversión conjunta, calendario, panel y concurrencia entre reprogramaciones, llegadas y altas. `php tests/seed-demo.php` comprueba los datos de demostración.
