# Hotel Web · Hotel Aurora

Proyecto académico con necesidades y datos ficticios, pensado para aprender backend con PHP.

## Correos de reserva · 02/11/2026

Una solicitud pública genera un acuse de recepción; aprobarla o crear una reserva directa genera una confirmación. El envío por Gmail es opcional y ocurre después de guardar la operación. Recepción ve el resultado y puede reintentar desde el detalle. [Configuración y pruebas de correo](docs/correos.md). En la última verificación local pasaron 653 comprobaciones de reglas, HTTP y concurrencia, y 17 de la carga demo. El transporte de prueba no envía mensajes reales.

## Señas, pagos y cierre del alcance funcional · 23/10/2026

Recepción y administración pueden registrar señas, pagos y devoluciones manuales por reserva, consultar el saldo y corregir registros con motivo y auditoría. El resumen diario distingue cobros, devoluciones y neto por medio. Cancelaciones y reprogramaciones conservan los movimientos y muestran los importes a favor. La salida avisa si queda saldo pendiente, sin impedir registrar la salida real.

Validación: 616 comprobaciones del sistema y 17 de datos demo. No hay pasarela de pagos ni facturación fiscal. Ejecutar `php scripts/migrate.php` antes de usar el código actualizado. Guía: [Pagos](docs/pagos.md). La siguiente etapa es la documentación de entrega: [índice y pendientes documentales](docs/README.md), [registro de pruebas](docs/pruebas-cierre.md).

## Bloqueos por mantenimiento · 23/10/2026

Recepción y administración pueden bloquear habitaciones por fechas con un motivo, revisar conflictos antes de guardar y levantar el bloqueo anticipadamente. Calendario y panel reflejan las noches bloqueadas; las solicitudes, reservas y llegadas comprueban mantenimiento. Se conservan responsable, fechas e historial, sin modificar reservas ni completar limpiezas automáticamente.

Validación: 521 comprobaciones del sistema y 17 de datos demo, incluidas operaciones simultáneas. Recorrido visual de conflictos, guardado y acceso desde el calendario en una base temporal. Actualizar una instalación existente con `php scripts/migrate.php` antes de usar el código nuevo. Guía: [docs/mantenimiento.md](docs/mantenimiento.md).

## Reprogramación de reservas · 21/10/2026

Recepción y administración pueden cambiar fechas o habitación de una reserva confirmada sin ingreso registrado. El flujo consulta disponibilidad, compara importes y pide confirmación antes de guardar. Conserva la tarifa acordada si se mantiene la habitación; al cambiarla usa la tarifa vigente de destino. El historial muestra antes, después, motivo, empleado y hora.

Validación: 445 comprobaciones del sistema y 17 de datos demo, más revisión visual de consulta, diferencia de importe, guardado e historial. Actualizar una instalación existente con `php scripts/migrate.php` antes de usar el código nuevo. Guía: [docs/reprogramaciones.md](docs/reprogramaciones.md).

## Cancelación de reservas · 21/10/2026

HOT-25 permite a recepción y administración cancelar reservas confirmadas sin llegada registrada, con motivo obligatorio y confirmación explícita. Libera las fechas, conserva los datos originales y registra responsable y hora. El filtro **Cancelada** permite consultar el historial. Incluye protección frente a doble envío y llegada simultánea.

Validación: 364 comprobaciones del sistema y 17 de datos demo, más recorrido en navegador de escritorio con una base temporal. Actualizar las instalaciones existentes con `php scripts/migrate.php` antes de usar el código nuevo. Ver [docs/cancelaciones.md](docs/cancelaciones.md).

## Limpieza de habitaciones · instalada localmente el 19/10/2026

La primera etapa de HOT-13 incorpora limpieza pendiente después del check-out, la pantalla **Limpieza**, registro de empleado y hora al marcar una habitación como lista y bloqueo de nuevas llegadas hasta completar la limpieza. Se mantienen las reservas futuras. Panel y calendario muestran el estado actual por separado de la disponibilidad prevista.

Instalada en XAMPP con la migración de `room_cleanings`, conservando los datos existentes. Pasaron 307 comprobaciones del sistema y 17 de datos demo. Se verificó el recorrido en navegador de escritorio y el acceso de recepción en Apache real. La comprobación móvil quedó pendiente por un fallo de la herramienta de navegador. Ver [docs/limpieza.md](docs/limpieza.md).

## Reservas desde recepción · 19/10/2026

Desde **Calendario → Nueva reserva**, o **+ Reservar** en una noche libre, recepción y administración pueden consultar disponibilidad por intervalo y registrar una reserva confirmada. El servidor evita solapamientos, registra al empleado y vuelve al calendario. No requiere migración. Guía y recorrido de backend: [docs/recepcion.md](docs/recepcion.md).

Validación de esa ampliación: 261 comprobaciones del sistema y 17 de datos demo, más revisión del flujo en navegador de escritorio con una base temporal. La ampliación de limpieza eleva el total del sistema a 307. Cambios disponibles localmente.

## Usar la instalación local

1. Iniciar Apache y MySQL en XAMPP.
2. Abrir [el hotel](http://localhost/hotel-web/public/).
3. Editar el código en `C:\xampp\htdocs\hotel-web`.
4. Acceder al personal desde el menú. Las cuentas locales de administrador y recepción están en `config/access.local.txt`. Este archivo y `config/local.php` están excluidos de Git y bloqueados por HTTP.

La base se llama `hotel_aurora`. Contiene tres habitaciones ficticias. Una solicitud pendiente no ocupa una habitación hasta que el personal la confirma. El proyecto puede enviar avisos por Gmail cuando se activa la configuración local; no procesa cobros bancarios.

## Estado al 5 de octubre de 2026

| Sprint | Historias | Funcionalidad |
| --- | --- | --- |
| 1 · 7–13 septiembre | HOT-2 a HOT-4 | Presentación, catálogo y validación de formulario |
| 2 · 14–20 septiembre | HOT-5 a HOT-7 | Persistencia con PDO, sesiones y consulta de solicitudes |
| 3 · 21–27 septiembre | HOT-8 a HOT-10 | Confirmación sin solapamientos y administración de habitaciones |
| 4 · 28 septiembre–4 octubre | HOT-11 a HOT-15 | Panel diario y calendario implementados; pendientes limpieza, alertas e historial |

Las funciones de los primeros tres sprints están implementadas. Los sprints sirven para organización personal; sus fechas originales se conservan para distinguir trabajo planificado de recuperación.

### Panel del día

El personal ingresa a `public/dashboard.php` después de iniciar sesión. El panel consulta MySQL y muestra llegadas y salidas previstas, solicitudes pendientes de todas las fechas, disponibilidad por habitación y reservas previstas para siete noches desde la fecha elegida. Permite cambiar fecha y actualizar sin recargar la página. La actualización cada minuto es opcional; se pausa con la pestaña oculta, una fecha sin consultar o un enlace del contenido enfocado. Sin JavaScript, el formulario GET sigue funcionando.

Una reserva confirmada ocupa las noches desde la entrada inclusive hasta la salida exclusiva. Las habitaciones inactivas no forman parte del porcentaje disponible, pero sus llegadas y salidas siguen visibles. Estos indicadores siguen las fechas previstas. La sección Huéspedes alojados ahora muestra por separado las llegadas registradas que aún no tienen salida. Los pendientes muestran los ocho más antiguos y los movimientos diarios hasta veinte por tipo, con acceso al listado completo.

La revisión del backlog y los criterios propuestos están en `docs/estado-y-backlog.md`. La guía para continuar en otra PC está en `docs/otra-pc.md`.

## Calendario de disponibilidad

HOT-34 agrega una vista mensual interactiva con navegación, filtros y detalle de reservas mediante GET y JSON, sin dependencias nuevas. La tabla por fechas sigue disponible. El personal también puede abrir **Calendario** para consultar de 1 a 31 noches por habitación, avanzar o retroceder el rango y abrir el detalle de las reservas. La salida libera la noche; pendientes y rechazadas no bloquean. El calendario distingue ocupación prevista y estadías registradas mediante check-in y check-out. Ver `docs/calendario.md` para reglas, recorrido y pruebas.

## Carpetas y recorrido de una petición

- `public/`: páginas HTTP, CSS, imágenes y JavaScript.
- `src/`: validaciones, conexión PDO, consultas y reglas del hotel.
- `views/`: estructura HTML compartida.
- `config/`: configuración y cuentas locales privadas.
- `database/`: esquema relacional reproducible.
- `scripts/`: instalación y creación de usuarios, solo por consola.
- `tests/`: pruebas con una base temporal independiente.
- `docs/aprendizaje.md`: guía breve para seguir GET → POST → PDO → respuesta.

Empezá por `public/reservar.php`, luego `src/Validation.php`, `src/Hotel.php` (submit) y `src/Database.php`. Son las piezas del flujo del formulario. HOT-4 valida; HOT-5 agrega el INSERT al mismo flujo. Solo una escritura exitosa muestra un comprobante de recepción.

## Instalar en otra computadora

Requisitos: XAMPP con PHP 8.2 o superior, extensiones PDO MySQL, mbstring y fileinfo, y MariaDB/MySQL. Para las pruebas también se necesita cURL y proc_open (incluidos en este XAMPP).

1. Clonar el repositorio (o ejecutar `git pull --ff-only origin main` si ya está clonado) en `C:\xampp\htdocs\hotel-web`.
2. Iniciar MySQL y Apache.
3. Desde esa carpeta ejecutar:
   `C:\xampp\php\php.exe scripts/setup.php`.
4. Abrir el hotel y consultar las cuentas generadas en `config/access.local.txt`.

El instalador asume el root local sin contraseña de una instalación nueva de XAMPP. Si tu configuración difiere, usar variables de entorno `HOTEL_SETUP_DSN`, `HOTEL_SETUP_USER` y `HOTEL_SETUP_PASSWORD`. No poner secretos en Git. El instalador crea una base nueva; si ya existe la configuración local, no modifica nada. No reemplaza una base hotel_aurora preexistente.

Cada computadora tiene su propia base. Git sincroniza el código y el esquema, no las solicitudes ni contraseñas locales. Para un servidor externo, la raíz pública debe ser `public/`; la instalación actual también bloquea las otras carpetas mediante Apache.

## Datos de demostración

Después de instalar, ejecutar `C:\xampp\php\php.exe scripts/seed-demo.php 2026-10-05` para cargar ocho casos ficticios sobre las habitaciones existentes. Se agregan cuatro confirmadas, tres pendientes y una rechazada. La carga conserva registros previos, cancela ante solapamientos confirmados y no duplica ni cambia fechas al repetirse. Sin argumento usa el día local de la primera carga. Ver `docs/demostracion.md` para el guion y los indicadores esperados.

## Comprobar la instalación

`C:\xampp\php\php.exe tests/run.php`

`C:\xampp\php\php.exe tests/seed-demo.php`

La suite crea y elimina únicamente su propia base `hotel_test_<aleatorio>`, inicia un servidor HTTP temporal y comprueba validaciones, consultas, roles, CSRF, reenvíos y dos confirmaciones concurrentes. No modifica hotel_aurora. Puede configurarse con `HOTEL_TEST_DSN`, `HOTEL_TEST_USER` y `HOTEL_TEST_PASSWORD`.

El acceso de la aplicación usa un usuario con permisos limitados a su base. La configuración puede venir de `config/local.php` o de la ruta indicada en la variable de entorno del servidor `HOTEL_CONFIG_PATH`.

[Jira HOTEL](https://sygmawalk.atlassian.net/jira/software/projects/HOT/boards/35/backlog)

## Foto ilustrativa

[Bedroom hotel interior with open door window](https://commons.wikimedia.org/wiki/File:Bedroom_hotel_interior_with_open_door_window._(51536308276).jpg), de Nenad Stojkovic. [CC BY 2.0](https://creativecommons.org/licenses/by/2.0/). Se recorta visualmente; no representa un hotel real llamado Aurora.

## Llegadas, fotos y cuentas del personal

HOT-26, HOT-31, HOT-32 y HOT-33 implementados: registro de llegada/salida con empleado y hora; fotos de habitaciones; límite de un año en reservas/calendario; creación de usuarios staff por administración. Ver [guía de operación](docs/operacion.md).

En instalaciones existentes ejecutar `C:\xampp\php\php.exe scripts/migrate.php` después de actualizar el código. Las instalaciones nuevas incluyen las tablas mediante setup.php. Los archivos de fotos quedan en storage/rooms y no se suben a Git; para otra PC deben copiarse junto con la base de datos.

Validación de esta ampliación: 186 comprobaciones del sistema y 17 de carga demo, sobre bases temporales.
