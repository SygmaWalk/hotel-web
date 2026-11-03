# Matriz de trazabilidad

Hotel Aurora. Revisión documental del 23/10/2026. Las rutas se expresan desde la raíz del proyecto. Los identificadores RF y RNF pertenecen a este informe; no sustituyen los identificadores históricos de Jira.

## Requisitos funcionales

| ID | Evidencia de implementación | Evidencia de pruebas |
| --- | --- | --- |
| RF01 | public/habitaciones.php, public/imagen.php, src/Hotel.php | tests/run.php y tests/operations-cases.php: catálogo activo, estado vacío e imágenes. |
| RF02 | public/reservar.php, src/Validation.php, Hotel::submit | tests/run.php: validación, envío y repetición de token. |
| RF03 | public/login.php, public/logout.php, src/bootstrap.php | tests/run.php: acceso, cierre y límite de intentos. |
| RF04 | public/solicitudes.php, public/solicitud.php | tests/run.php: filtros, detalle e identificador inválido. |
| RF05 | Hotel::resolve | tests/run.php y tests/confirm-worker.php: cruces y confirmaciones concurrentes; tests/maintenance-cases.php: bloqueo por mantenimiento. |
| RF06 | public/reserva-nueva.php, Hotel::createReservation | tests/reception-cases.php y tests/reception-worker.php. |
| RF07 | public/dashboard.php, views/dashboard-content.php, Hotel::dashboard | tests/run.php: indicadores, fecha, vacío y escape; casos de limpieza y mantenimiento. |
| RF08 | public/calendario.php, public/calendario-datos.php, public/js/calendar.js | tests/run.php y tests/calendar-month-cases.php: rangos, estados, filtros y JSON privado. |
| RF09 | public/solicitud.php, HotelOperations::recordStay | tests/operations-cases.php y tests/stay-worker.php. |
| RF10 | public/limpieza.php, HotelOperations | tests/cleaning-cases.php y tests/cleaning-worker.php. |
| RF11 | public/mantenimiento.php, src/Maintenance.php | tests/maintenance-cases.php y tests/maintenance-worker.php. |
| RF12 | public/solicitud.php, Hotel::cancelReservation | tests/cancellation-cases.php y tests/cancellation-worker.php. |
| RF13 | public/reserva-reprogramar.php, src/Rescheduling.php | tests/reschedule-cases.php y tests/reschedule-worker.php. |
| RF14 | public/reserva-pagos.php, HotelPayments::recordPayment | tests/payment-cases.php: importes, tipo, fecha, medio, permisos y reenvíos. |
| RF15 | views/payment-balance.php, HotelPayments::paymentBalance y voidPayment | tests/payment-cases.php: saldo, crédito, devolución y anulación; tests/payment-worker.php: concurrencia. |
| RF16 | public/cobros.php, HotelPayments::dailyPayments | tests/payment-cases.php: fecha, desglose y exclusión de anulados. |
| RF17 | public/habitaciones-admin.php, src/Hotel.php, src/RoomImages.php | tests/run.php y tests/operations-cases.php: administración, bajas, reactivación y archivos. |
| RF18 | public/usuarios.php, HotelOperations::createStaff | tests/operations-cases.php: rol fijo, contraseñas, correo y autorización. |

## Requisitos no funcionales

| ID | Evidencia | Alcance de la conclusión |
| --- | --- | --- |
| RNF01 | requireUser, actor y casos HTTP por rol | Acceso probado en las rutas de la suite. |
| RNF02 | Validation.php, bootstrap.php, prepare/execute y función e | Se prueban formatos inválidos, CSRF y escape. No equivale a pentest. |
| RNF03 | Transacciones InnoDB, FOR UPDATE y workers de pruebas | Escenarios concurrentes controlados; no ensayo de carga masiva. |
| RNF04 | Tokens únicos, tablas de historial y casos de repetición | Evita repetir una misma alta; no impide todas las duplicaciones humanas con formularios diferentes. |
| RNF05 | Etiquetas, navegación, CSS adaptable y revisión visual histórica | Falta repetir revisión móvil y teclado sobre los módulos nuevos. |
| RNF06 | schema.sql, migrate.php, setup.php y seed-demo.php | Migraciones y demo probadas. Ensayo final del paquete en instalación limpia pendiente. |
| RNF07 | .htaccess, exclusiones Git, cuenta limitada y manejador de errores | Protección local configurada; revisar configuración antes de un despliegue público. |
| RNF08 | Carpetas public, views, src, database, scripts, tests y docs | Inspección de organización; no se afirma arquitectura MVC completa. |

## Cobertura de la consigna

| Sección solicitada | Ubicación en el informe |
| --- | --- |
| Identificación, temática y propósito | Portada y sección 1. |
| Problema, objetivos y actores | Sección 1. |
| Al menos 10 requisitos funcionales | Sección 2: RF01 a RF18. |
| Al menos 5 requisitos no funcionales | Sección 3: RNF01 a RNF08. |
| Entidades principales | Sección 5 y diccionario-datos.md. |
| Funcionalidades operativas | Sección 2 y guías de cada módulo. |
| Arquitectura y decisiones | Secciones 4 y 6. |
| Pruebas y correcciones | Sección 7, anexo visual y docs/pruebas-cierre.md. |
| Ejecución, limitaciones y conclusión | Secciones 8 y 9. |

Los resultados corresponden a la última ejecución registrada de 616 y 17 comprobaciones. La actualización de este informe no ejecuta nuevamente las pruebas ni modifica reservas. El estado del código local no acredita que el repositorio remoto o Jira estén actualizados.
