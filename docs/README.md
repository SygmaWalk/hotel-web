# Documentación de Hotel Aurora

Estado local al 23/10/2026. El alcance funcional elegido cierra con el registro manual de señas, pagos y devoluciones. La documentación académica está preparada en docs/entrega; resta completar las revisiones previas a la entrega.

## Guías disponibles

| Tema | Documento |
| --- | --- |
| Avisos de recepción y confirmación por Gmail (02/11/2026) | [Correos](correos.md) |
| Informe, diagramas y anexos | [Abrir documentación académica](entrega/index.html) |
| Fuente editable del informe | [Informe en Markdown](entrega/informe-academico.md) |
| Instalación y acceso local | [README del proyecto](../README.md) |
| Llevar el proyecto a otra PC | [Otra PC](otra-pc.md) |
| Arquitectura y recorrido de una petición | [Aprendizaje](aprendizaje.md) |
| Llegadas, salidas, imágenes y usuarios | [Operación](operacion.md) |
| Consulta de disponibilidad | [Calendario](calendario.md) |
| Reservas creadas por recepción | [Recepción](recepcion.md) |
| Cancelación y auditoría | [Cancelaciones](cancelaciones.md) |
| Cambios de fechas y habitación | [Reprogramaciones](reprogramaciones.md) |
| Preparación de habitaciones | [Limpieza](limpieza.md) |
| Bloqueos por fechas | [Mantenimiento](mantenimiento.md) |
| Señas, pagos, devoluciones y resumen | [Pagos](pagos.md) |
| Recorrido con datos ficticios | [Demostración](demostracion.md) |
| Evidencia técnica actual | [Pruebas del cierre funcional](pruebas-cierre.md) |
| Evolución y prioridades anteriores | [Estado y backlog](estado-y-backlog.md) |

## Preparación de la entrega

Se preparó un informe de diez páginas A4 con portada de José Dugarte y profesora Milagros Luzzi, problema, objetivos, 18 requisitos funcionales, 8 no funcionales, actores, arquitectura, modelo de datos, decisiones y conclusiones. Incluye tres diagramas, trazabilidad, diccionario de once tablas, capturas y guion de exposición. Se entrega en HTML para lectura e impresión y Markdown editable.

Antes de entregar, completar la revisión móvil y con teclado de los módulos recientes, el ensayo del paquete en una instalación limpia y la lectura personal del informe. El detalle está en [Preparación de entrega](entrega/preparacion-entrega.md).

Usar estas guías y el código instalado como evidencia. Las notas históricas del backlog conservan fechas y estados anteriores; no deben copiarse como estado actual de Jira. En esta etapa no se actualizó Jira ni se publicó código en GitHub.

La ampliación del 02/11 incorpora avisos de solicitud y confirmación por Gmail y la tabla `reservation_emails`. El informe anterior describe la versión previa a esta ampliación. Quedan fuera del alcance las alertas operativas, las pasarelas de pago, la facturación fiscal y un cierre formal de caja. Las pruebas móviles de los módulos nuevos aún requieren una revisión final.
