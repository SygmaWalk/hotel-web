# Revisión visual y accesibilidad de Hotel Aurora

Revisión local del 16 de noviembre de 2026.

Se aplicó una base visual compartida a las páginas públicas y del personal. Incluye tipografía, tamaños de títulos, textos auxiliares, espaciado, formularios, botones principales y secundarios, estados deshabilitados, tablas, contraste, foco y preferencias de movimiento reducido.

Se revisaron los textos de acceso, solicitud pública, comprobante, solicitudes y reservas, mantenimiento, usuarios e inventario. Los errores reciben foco y permiten llegar al campo asociado. La contraseña puede mostrarse y ocultarse mediante un botón accesible. Las tablas admiten desplazamiento con teclado.

## Comprobaciones

Se recorrieron 20 rutas y variantes en Chrome con axe-core 4.10.3. No se detectaron infracciones automáticas de los criterios WCAG A y AA incluidos en las etiquetas wcag2a, wcag2aa, wcag21aa y wcag22aa, en escritorio y en 320 píxeles.

Rutas evaluadas en navegador:

- Inicio, catálogo, solicitud con habitación elegida y acceso.
- Hoy, calendario semanal, solicitudes y reservas, cobros, limpieza y mantenimiento.
- Alta y edición de habitaciones, usuarios de recepción.
- Nueva reserva sin fechas y con disponibilidad consultada.
- Detalle de reserva confirmada, solicitud pendiente y rechazada, pagos y reprogramación.

Se comprobó ausencia de desbordes de página a 768, 375 y 320 píxeles. El menú permite abrir con Enter y cerrar con Escape devolviendo el foco. El panel admite texto al 200 %. Un error de fecha enfoca su resumen.

La suite funcional pasó 657 comprobaciones de reglas, concurrencia y HTTP tras integrar la base visual. Incluye el flujo del comprobante de solicitud, cuya vista también recibió revisión de código y texto.

## Alcance

La referencia es https://www.w3.org/WAI/WCAG22/quickref/.

La revisión automática y las pruebas de teclado no constituyen una certificación completa de WCAG. Queda pendiente una evaluación manual con lector de pantalla y personas usuarias, y ampliar la auditoría a cada combinación de errores, permisos y datos. Los estados dinámicos de pagos, mantenimiento y reprogramación tienen pruebas funcionales, pero la auditoría automática visual se realizó sobre los estados disponibles en la demo local.

## Distribución de las secciones del administrador

Calendario con filtros visibles y acciones agrupadas; solicitudes en tarjetas con fechas y estado; detalle con acciones prioritarias y correos y cancelación desplegables; limpieza con indicadores y tarjetas; mantenimiento con formulario separado de bloqueos e historial; cobros con totales destacados y movimientos organizados.
