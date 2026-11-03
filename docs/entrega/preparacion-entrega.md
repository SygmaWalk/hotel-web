# Preparación de la entrega y exposición

Fecha prevista de entrega en la consigna: 16/11/2026. Esta lista organiza el cierre del trabajo y no agrega nuevas funciones al sistema.

## Documentación preparada

- Informe con portada adaptada a Hotel Aurora, José Dugarte, Desarrollo de Sistemas Web y profesora Milagros Luzzi.
- 18 requisitos funcionales y 8 no funcionales con matriz de trazabilidad.
- Diagramas de arquitectura, relaciones operativas y estados; fuentes Mermaid editables.
- Diccionario de las once tablas del esquema actual.
- Registro de pruebas, corrección observada y capturas de demostración.

El informe tiene una versión HTML local para lectura e impresión y una fuente Markdown editable. Las imágenes y estilos deben conservarse junto al HTML. El informe no se publicó en Drive; el documento de Empreges se utilizó únicamente como referencia de portada.

## Controles todavía pendientes

- Revisar el informe completo y personalizar la reflexión de cierre según lo que el estudiante pueda explicar en la defensa.
- Completar la revisión de pagos, mantenimiento y reprogramación en móvil y con teclado. Registrar ancho, navegador, pasos, resultado y correcciones si hicieran falta.
- Ensayar la entrega en una instalación local limpia y aislada: setup, carga demo, login y un recorrido completo. No vaciar ni sustituir la base del hotel para realizar ese ensayo.
- Preparar un paquete con el código local actualizado. No asumir que GitHub contiene las ampliaciones recientes ni publicar automáticamente sin revisar los archivos.
- Verificar que el paquete excluya config/local.php, config/access.local.txt, sesiones, cookies, datos de prueba temporales y copias privadas de bases. Las fotos necesarias para la demo se trasladan deliberadamente con su estructura.
- Confirmar el formato de entrega solicitado por la docente y guardar la versión final del informe una vez terminadas las revisiones.

## Guion de exposición de ocho a diez minutos

| Tiempo orientativo | Recorrido | Idea que debe explicarse |
| --- | --- | --- |
| 1 minuto | Presentar el hotel ficticio y el problema | Disponibilidad, presencia real, preparación y pagos son datos diferentes. |
| 1 minuto | Catálogo y solicitud pública | El POST valida y guarda una solicitud pendiente; no confirma automáticamente. |
| 2 minutos | Ingresar como recepción y abrir calendario | Crear una reserva directa; explicar fechas de entrada inclusiva y salida exclusiva. |
| 1 minuto | Abrir detalle y estadía | Llegada y salida son hechos reales; la salida genera limpieza. |
| 1 minuto | Revisar mantenimiento | Un bloqueo por fechas impide reservar y muestra conflictos existentes. |
| 1 minuto | Registrar seña y consultar Cobros | Mostrar saldo, concepto, responsable y diferencia entre anulación y devolución. |
| 1 minuto | Abrir diagramas y estructura del código | Navegador, PHP, PDO y base de datos; límites de la separación por responsabilidades. |
| 1 a 2 minutos | Pruebas y conclusión | Explicar una carrera de confirmaciones y la corrección del filtro inicial de Cobros. |

Utilizar datos ficticios, una reserva que corresponda a la fecha de exposición y una habitación disponible. No mostrar contraseñas, cookies o configuración privada. Una copia de los datos de la demostración debe prepararse antes; seed-demo no reinicia reservas modificadas.

## Preguntas para practicar

1. ¿Por qué se valida en PHP aunque el formulario tenga validación JavaScript?
2. ¿Qué diferencia hay entre autenticación, autorización y protección CSRF?
3. ¿Por qué una salida y una entrada el mismo día no se superponen?
4. ¿Cómo se evita que dos empleados confirmen una misma habitación a la vez?
5. ¿Por qué se conserva una reserva cancelada o un pago anulado?
6. ¿Qué ocurre si una reprogramación reduce el total después de recibir una seña?
7. ¿Qué prueban las 616 comprobaciones y qué no se puede concluir de ese número?

## Referencia de la portada

Documento consultado: [Misión y Visión](https://docs.google.com/document/d/1_fjBO8oJJ71Ig1lvkfIJ2QKMoQc4ATbt5mvIZEBSw44/edit?usp=drivesdk), dentro de TSDS_PP3_EMPREGES_DUGARTE_2026 / 02_ANALISIS. Se conservan el encabezado institucional, la carrera, el estudiante y la disposición de portada. Se reemplazan materia, título y docente; Milagros Luzzi fue indicada por el estudiante en esta conversación. No se copió contenido académico de Empreges al informe de Hotel Aurora.
