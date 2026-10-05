# Calendario de disponibilidad · HOT-12

Disponible para recepción y administración desde **Calendario** en el menú del personal, en `public/calendario.php`.

## Consulta

- Seleccionar primera y última noche, ambas incluidas: entre 1 y 31 noches, en los años 2000–2099.
- Consultar todas las habitaciones o una en particular. Se incluyen las inactivas con una advertencia junto al nombre.
- Anteriores y Siguientes desplazan el rango completo y conservan la habitación seleccionada. Hoy y próximas noches restablece siete noches y todas las habitaciones.
- Abrir el nombre de una reserva, su entrada o su salida para consultar el detalle. El calendario no modifica reservas.
- En móvil, desplazar la tabla horizontalmente. La columna de habitación permanece visible. Con teclado se puede enfocar la tabla y usar las flechas.

## Estados y límites

La disponibilidad se calcula con reservas confirmadas y con la regla **entrada incluida, salida excluida**. La noche de salida queda libre salvo que comience otra reserva. Los movimientos de entrada y salida permanecen visibles incluso cuando coinciden el mismo día. Una reserva iniciada antes del rango aparece mientras incluya alguna noche consultada.

- **Libre:** habitación activa sin reserva confirmada para esa noche.
- **Reservada:** noche futura cubierta por una reserva confirmada.
- **Ocupada prevista:** noche de hoy o anterior cubierta por una reserva confirmada. Es una estimación según las fechas, no prueba de presencia ni de check-in.
- **Inactiva:** habitación desactivada y sin reserva confirmada en esa noche. Si conserva reservas, se muestran junto a la advertencia de habitación inactiva; nunca se interpreta como disponible para nuevas solicitudes.

Los estados temporales se calculan respecto de la fecha del servidor en Argentina, no respecto de la primera noche del filtro. Las solicitudes pendientes y rechazadas no bloquean disponibilidad. La distinción con check-in real queda pendiente en HOT-26; los bloqueos de limpieza y mantenimiento, en HOT-13. Este alcance utiliza ocupación prevista como criterio inicial, informado al usuario, sin incorporar registros de presencia física.

## Demostración con los ocho casos locales

Consultar del 05/10/2026 al 11/10/2026:

1. La 101 tiene salida #1 y entrada #2 el 05/10; queda libre la noche del 07/10.
2. La 102 tiene salida #3 el 06/10; esa noche queda libre.
3. La 201 tiene reserva #4 desde el 07/10 hasta la noche del 09/10; queda libre el 10/10.
4. Filtrar la 201, avanzar al siguiente rango y comprobar que se conserva el filtro.
5. Abrir una reserva desde la tabla y volver al calendario.

Los números corresponden a la primera carga en la base local vacía; otras instalaciones pueden tener identificadores diferentes. Las etiquetas de ocupación prevista cambian al avanzar la fecha real del servidor.

## Verificación

`tests/run.php` contiene 127 verificaciones, incluidas 40 nuevas para el calendario: intervalos, fechas inválidas y arrays HTTP, año bisiesto, límites del rango, salida exclusiva, entradas y salidas simultáneas, reservas que atraviesan el rango, filtro por habitación, inactivas, estados previstos, autenticación, acceso de ambos roles, escape de nombres y ausencia de correos/tokens en la tabla.

Las pruebas utilizan una base efímera independiente. Esta implementación no cambia el esquema ni los ocho registros de demostración. El código, esquema y scripts se versionan juntos; la base de cada computadora se configura por separado. Ver docs/otra-pc.md.
