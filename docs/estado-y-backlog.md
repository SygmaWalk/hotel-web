# Estado y próximos pasos

Revisión del 5 de octubre de 2026. Fuentes: código local, proyecto HOT de Jira y consigna Trabajo Práctico Integrador DSW. La temática está aprobada. Entrega integrada: 16/10; pruebas: 19–30/10; entrega final: 16/11/2026.

## Historias existentes

HOT-1 a HOT-10 y sus subtareas están Listo en Jira. HOT-11 y HOT-12 quedaron Listo en Jira el 05/10; HOT-13 a HOT-15 siguen Por hacer. HOT-11 se implementó localmente en esta revisión: panel diario con datos persistidos, acceso del personal, fecha visible, enlaces a detalle, estados vacíos y actualización dinámica. El estado Listo refleja la implementación local verificada; los cambios aún no están publicados en GitHub.

HOT-12 (calendario), HOT-13 (limpieza y mantenimiento), HOT-14 (alertas) y HOT-15 (historial) ya existen: no deben duplicarse. HOT-12 se implementó con ocupación prevista según fechas, señalada explícitamente; el check-in/check-out real sigue pendiente en HOT-26. Para HOT-14 faltan los umbrales de antigüedad y preparación.

## Historias registradas en Jira

### HOT-24 · Navegar y consultar el sistema en móvil y con teclado

Como visitante o integrante del personal, quiero una navegación consistente y adaptable para completar mis tareas desde distintos dispositivos.

Criterios: menú móvil operable con teclado y Escape; sección activa identificada; sesión y acceso al panel conservados al visitar el catálogo; formularios con etiquetas; indicadores comprensibles sin depender del color; sin desbordamiento horizontal de página a 360 px. Las tablas pueden tener desplazamiento propio. Implementado localmente y verificado en móvil y con teclado; marcado Listo en Jira.

### HOT-25 · Cancelar una reserva confirmada

Como recepcionista, quiero cancelar una reserva confirmada para liberar disponibilidad sin perder su historial.

Criterios propuestos: motivo obligatorio, confirmación explícita, actor y fecha registrados, nueva disponibilidad reflejada en el panel, protección CSRF y acceso restringido. Definir política para reservas pasadas o huéspedes alojados antes de implementar. Se complementa con HOT-15; no se implementa en esta revisión.

### HOT-26 · Registrar llegada y salida de huéspedes

Como recepcionista, quiero registrar check-in y check-out para distinguir una reserva prevista de una estadía efectiva.

Criterios propuestos: transiciones válidas sobre reservas confirmadas, fecha y actor, imposibilidad de registrar dos llegadas o salir sin haber ingresado, mensajes claros y permisos del personal. Coordinar con HOT-12, HOT-13 y HOT-15. Definir llegadas anticipadas, no-show y salida tardía antes de implementar.

### HOT-27 · Reactivar una habitación

Como administrador, quiero volver a ofrecer una habitación desactivada para recuperar su disponibilidad conservando reservas e historial.

Criterios propuestos: solo administrador, protección CSRF, conservar código e historial, no alterar reservas existentes y respetar bloqueos operativos de HOT-13 cuando estén disponibles.

## Tareas de entrega registradas

- **HOT-28 · Informe académico y trazabilidad:** identificar proyecto y estudiante; problema, objetivos, al menos 10 requisitos funcionales y 5 no funcionales; actores, entidades, arquitectura, decisiones, límites y conclusión. Relacionar requisitos con historias y pruebas. No presentar requisitos pendientes como implementados.
- **HOT-29 · Plan y registro de pruebas:** casos correctos, incompletos e inválidos; roles, sesión, fechas, concurrencia, móvil y teclado; registrar resultado esperado, obtenido y corrección con fecha. Usar la suite como evidencia, complementada por revisión manual.
- **HOT-30 · Datos y guion de demostración:** conjunto reproducible de datos ficticios con pendientes, confirmadas y rechazadas, además de habitaciones; instrucciones de instalación y recorrido de presentación. No incorporar contraseñas ni datos personales al repositorio. La carga separada scripts/seed-demo.php y el guion docs/demostracion.md cubren las reservas de ejemplo. Marcada Listo en Jira tras cargar y verificar ocho casos locales.

Estas propuestas no se consideran requisitos obligatorios nuevos de la docente. Las tareas de informe y testing sí corresponden a la consigna; las ampliaciones funcionales requieren priorización según el tiempo disponible.

## Verificación de esta implementación

La suite `tests/run.php` pasó 87 comprobaciones con una base temporal: 61 existentes y 26 nuevas. Las nuevas cubren panel vacío, validación de fechas, intervalos de siete noches, salida exclusiva, habitaciones inactivas, pendientes globales, permisos, enlaces y escape de nombres. Los archivos PHP pasaron el análisis de sintaxis. La base local no recibió reservas ficticias adicionales como parte de las pruebas automáticas.

La suite volvió a pasar sobre la instalación final de XAMPP. La revisión en navegador confirmó ingreso al panel, actualización de fecha sin recarga, navegación activa, menú móvil y cierre con Escape. Se inspeccionó el panel a 1366 px y 390 px, y se verificó ausencia de desplazamiento horizontal de página a 360 px; también se revisó el formulario de reservas en ese ancho. No se observaron errores de consola. La revisión visual utilizó la base local vacía de reservas; los escenarios con reservas fueron comprobados por la suite aislada. La actualización automática de un minuto está implementada, pero no se cronometró en la revisión manual.

Se añadió una versión a las URLs de CSS y JavaScript basada en la fecha de modificación de cada archivo, para evitar que estilos antiguos queden en caché después de los cambios. No se añadieron dependencias externas ni cambios al esquema de datos.

## Carga de demostración verificada

El 05/10/2026 se cargaron ocho reservas ficticias en la instalación local: tres pendientes, cuatro confirmadas y una rechazada, con fecha de referencia 2026-10-05. La segunda ejecución insertó cero registros y conservó los ocho existentes. La carga no elimina ni modifica reservas previas y revierte el lote completo ante conflictos.

Sobre la instalación de XAMPP pasaron 87 verificaciones de tests/run.php y 17 de tests/seed-demo.php, estas últimas en bases temporales. Cubren repetición, conservación de registros ajenos y decisiones del personal, habitaciones inactivas, datos parciales y reversión completa ante solapamientos.

La revisión en navegador con datos confirmó para el 05/10 una llegada, una salida, tres pendientes y una de tres habitaciones libres; al consultar el 07/10 se mostraron dos de tres libres. Se abrió el detalle de una solicitud desde el panel y no hubo errores de consola. HOT-11, HOT-24 y HOT-30 están Listo; HOT-25 a HOT-29 siguen Por hacer. Los cambios de código continúan locales, pendientes de commit y publicación en GitHub.
## Calendario de disponibilidad · HOT-12

Implementado y marcado Listo en Jira el 05/10/2026 para el alcance de disponibilidad prevista: consulta privada de 1 a 31 noches por habitación, enlaces a reservas, movimientos de entrada y salida, navegación entre rangos y tabla adaptable con desplazamiento por teclado. La salida libera la noche y las habitaciones inactivas conservan sus reservas visibles. «Ocupada prevista» representa noches confirmadas de hoy o anteriores; no acredita presencia física. HOT-26 permanece pendiente para registrar check-in/check-out.

Pasaron 127 verificaciones de tests/run.php (40 nuevas del calendario) y 17 de tests/seed-demo.php. Revisión manual: filtro 201, rango siguiente con filtro conservado, retorno a hoy, detalle desde una salida, vista móvil de 390 y 360 px y desplazamiento con flecha. Sin desbordamiento horizontal de página ni errores de consola. Guía: docs/calendario.md. No cambió el esquema ni las reservas locales; cambios pendientes de publicación en GitHub.