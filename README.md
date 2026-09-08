# Hotel Web

Proyecto académico para aprender y explicar desarrollo backend. Las necesidades del hotel son supuestas; no provienen de investigación de campo.

## Entorno

HTML, CSS, JavaScript, PHP y MySQL/MariaDB mediante PDO. XAMPP instalado en `C:\xampp`.

1. Iniciar Apache y MySQL desde XAMPP.
2. Abrir http://localhost/hotel-web/public/.
3. Editar el proyecto en `C:\xampp\htdocs\hotel-web`.

HOT-2: presentación del hotel, servicios, ubicación y contacto ficticios, navegación y estilos adaptables. PHP genera la página; CSS y JavaScript se sirven como archivos separados. Todavía no existe conexión a la base de datos ni formulario de reservas.

## Organización prevista

- `public/`: páginas accesibles y recursos de interfaz.
- `src/`: reglas de negocio y acceso a datos (se incorporará al desarrollar).
- `config/`: configuración local privada (no publicar secretos).
- `database/`: esquema y datos ficticios de ejemplo (etapa 2).

## Recorrido de aprendizaje

1. HOT-2: presentación del hotel.
2. HOT-3: habitaciones generadas con PHP.
3. HOT-4: formulario, POST y validación; aún sin persistencia.
4. HOT-5: guardar solicitudes pendientes con PDO.
5. HOT-6 a HOT-10: acceso del personal y administración.
6. HOT-11 a HOT-15: panel, calendario y otras mejoras futuras.

[Jira HOTEL](https://sygmawalk.atlassian.net/jira/software/projects/HOT/boards/35/backlog)

Usar la clave de Jira en ramas y commits, por ejemplo `HOT-4-formulario-reserva`. La conexión GitHub/Jira debe verificarse en el panel de desarrollo.

## Acuerdo de trabajo

Desarrollar una funcionalidad por vez, comprobarla y poder explicar petición, validación, procesamiento y respuesta. Los sprints son una ayuda personal de organización. No guardar credenciales, datos reales de huéspedes ni archivos de configuración privados en Git.

## Foto ilustrativa

[Bedroom hotel interior with open door window](https://commons.wikimedia.org/wiki/File:Bedroom_hotel_interior_with_open_door_window._(51536308276).jpg), de Nenad Stojkovic. Licencia [CC BY 2.0](https://creativecommons.org/licenses/by/2.0/). Se recorta visualmente para adaptarse al diseño; no representa un hotel real llamado Aurora.
