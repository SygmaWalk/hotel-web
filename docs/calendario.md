# Calendario de disponibilidad · HOT-12

Disponible para recepción y administración desde **Calendario** en el menú del personal, en `public/calendario.php`.

## Consulta

- Seleccionar primera y última noche, ambas incluidas: entre 1 y 31 noches, desde el año 2000 hasta un año desde hoy.
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

Los estados temporales se calculan respecto de la fecha del servidor en Argentina, no respecto de la primera noche del filtro. Las solicitudes pendientes y rechazadas no bloquean disponibilidad. HOT-26 agrega el estado Estadía registrada y los movimientos reales hasta hoy. Las fechas futuras siguen las reservas previstas. Los bloqueos de limpieza y mantenimiento quedan pendientes en HOT-13.

## Demostración opcional con los ocho casos del script

Consultar del 05/10/2026 al 11/10/2026:

1. La 101 tiene salida #1 y entrada #2 el 05/10; queda libre la noche del 07/10.
2. La 102 tiene salida #3 el 06/10; esa noche queda libre.
3. La 201 tiene reserva #4 desde el 07/10 hasta la noche del 09/10; queda libre el 10/10.
4. Filtrar la 201, avanzar al siguiente rango y comprobar que se conserva el filtro.
5. Abrir una reserva desde la tabla y volver al calendario.

Los números corresponden a la primera carga en la base local vacía; otras instalaciones pueden tener identificadores diferentes. Las etiquetas de ocupación prevista cambian al avanzar la fecha real del servidor.

## Verificación

`tests/run.php` contiene 200 verificaciones, incluidas 40 nuevas para el calendario: intervalos, fechas inválidas y arrays HTTP, año bisiesto, límites del rango, salida exclusiva, entradas y salidas simultáneas, reservas que atraviesan el rango, filtro por habitación, inactivas, estados previstos, autenticación, acceso de ambos roles, escape de nombres y ausencia de correos/tokens en la tabla.

Las pruebas utilizan una base efímera independiente. La ampliación de operación agrega stays y room_images mediante scripts/migrate.php, conservando los registros existentes. El código, esquema y scripts se versionan juntos; la base de cada computadora se configura por separado. Ver docs/otra-pc.md.

## Calendario mensual interactivo · HOT-34

Arriba de la consulta por fechas se muestra el mes completo. Anterior, Siguiente, Hoy y el selector de habitación actualizan los datos sin recargar la página. Cada reserva abre un diálogo con nombre, habitación, fechas disponibles y enlace para gestionarla. Escape o Cerrar detalle cierran el diálogo y devuelven el foco al botón.

El mes final se recorta al límite anual; los días posteriores se muestran fuera de plazo. Los estados conservan las reglas de reservas previstas y estadías reales. En pantallas pequeñas los días se organizan en una o dos columnas. La tabla tradicional continúa disponible, incluso sin JavaScript.

### Recorrido para explicar en clase

1. JavaScript envía un GET, por ejemplo calendario-datos.php?month=2026-10&room_id=1.
2. PHP comprueba la sesión y valida mes y habitación.
3. Hotel::calendar consulta MySQL mediante PDO y calcula estados.
4. PHP responde JSON con fechas, habitaciones y reservas, sin correos ni tokens.
5. fetch recibe la respuesta; JavaScript crea los elementos con textContent, sin interpretar nombres como HTML.

HTTP 200 indica éxito; 401 pide volver a ingresar; 422 indica filtros inválidos; 503 indica un fallo temporal. Un error conserva el último mes visible. Las consultas anteriores se cancelan cuando se solicita otro mes, para que una respuesta tardía no sobrescriba la consulta nueva.

No requiere migración ni dependencias nuevas. Pruebas del sistema: 200, incluyendo 14 casos nuevos en tests/calendar-month-cases.php. La revisión visual no pudo realizarse por un fallo de la herramienta de navegador; se comprobó sintaxis JavaScript y respuestas HTTP.
