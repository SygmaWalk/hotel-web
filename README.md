# Hotel Web · Hotel Aurora

Proyecto académico con necesidades y datos ficticios, pensado para aprender backend con PHP.

## Usar la instalación local

1. Iniciar Apache y MySQL en XAMPP.
2. Abrir [el hotel](http://localhost/hotel-web/public/).
3. Editar el código en `C:\xampp\htdocs\hotel-web`.
4. Acceder al personal desde el menú. Las cuentas locales de administrador y recepción están en `config/access.local.txt`. Este archivo y `config/local.php` están excluidos de Git y bloqueados por HTTP.

La base se llama `hotel_aurora`. Contiene tres habitaciones ficticias. Una solicitud pendiente no ocupa una habitación hasta que el personal la confirma. Este proyecto no envía correos ni procesa pagos.

## Estado al 21 de septiembre de 2026

| Sprint | Historias | Funcionalidad |
| --- | --- | --- |
| 1 · 7–13 septiembre | HOT-2 a HOT-4 | Presentación, catálogo y validación de formulario |
| 2 · 14–20 septiembre | HOT-5 a HOT-7 | Persistencia con PDO, sesiones y consulta de solicitudes |
| 3 · 21–27 septiembre | HOT-8 a HOT-10 | Confirmación sin solapamientos y administración de habitaciones |
| 4 · 28 septiembre–4 octubre | HOT-11 a HOT-15 | Pendiente: panel diario, calendario, limpieza, alertas e historial |

Las funciones de los primeros tres sprints están implementadas. Los sprints sirven para organización personal; sus fechas originales se conservan para distinguir trabajo planificado de recuperación.

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

Requisitos: XAMPP con PHP 8.2 o superior, extensiones PDO MySQL y mbstring, y MariaDB/MySQL. Para las pruebas también se necesita cURL y proc_open (incluidos en este XAMPP).

1. Clonar el repositorio en `C:\xampp\htdocs\hotel-web`.
2. Iniciar MySQL y Apache.
3. Desde esa carpeta ejecutar:
   `C:\xampp\php\php.exe scripts/setup.php`.
4. Abrir el hotel y consultar las cuentas generadas en `config/access.local.txt`.

El instalador asume el root local sin contraseña de una instalación nueva de XAMPP. Si tu configuración difiere, usar variables de entorno `HOTEL_SETUP_DSN`, `HOTEL_SETUP_USER` y `HOTEL_SETUP_PASSWORD`. No poner secretos en Git. El instalador crea una base nueva; si ya existe la configuración local, no modifica nada. No reemplaza una base hotel_aurora preexistente.

Cada computadora tiene su propia base. Git sincroniza el código y el esquema, no las solicitudes ni contraseñas locales. Para un servidor externo, la raíz pública debe ser `public/`; la instalación actual también bloquea las otras carpetas mediante Apache.

## Comprobar

`C:\xampp\php\php.exe tests/run.php`

La suite crea y elimina únicamente su propia base `hotel_test_<aleatorio>`, inicia un servidor HTTP temporal y comprueba validaciones, consultas, roles, CSRF, reenvíos y dos confirmaciones concurrentes. No modifica hotel_aurora. Puede configurarse con `HOTEL_TEST_DSN`, `HOTEL_TEST_USER` y `HOTEL_TEST_PASSWORD`.

El acceso de la aplicación usa un usuario con permisos limitados a su base. La configuración puede venir de `config/local.php` o de la ruta indicada en la variable de entorno del servidor `HOTEL_CONFIG_PATH`.

[Jira HOTEL](https://sygmawalk.atlassian.net/jira/software/projects/HOT/boards/35/backlog)

## Foto ilustrativa

[Bedroom hotel interior with open door window](https://commons.wikimedia.org/wiki/File:Bedroom_hotel_interior_with_open_door_window._(51536308276).jpg), de Nenad Stojkovic. [CC BY 2.0](https://creativecommons.org/licenses/by/2.0/). Se recorta visualmente; no representa un hotel real llamado Aurora.
