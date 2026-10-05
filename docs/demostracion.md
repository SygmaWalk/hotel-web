# Demostración de Hotel Aurora

Datos ficticios para la evaluación académica. Primera carga local: 05/10/2026, con esa misma fecha como referencia. Los nombres comienzan con Demo y los correos terminan en @demo.example. No se envían correos ni se procesan pagos.

## Preparar la demostración

1. Iniciar Apache y MySQL en XAMPP. La instalación debe estar configurada mediante `scripts/setup.php` y conservar las habitaciones 101, 102 y 201 activas y una cuenta de personal.
2. Desde `C:\xampp\htdocs\hotel-web`, ejecutar `C:\xampp\php\php.exe scripts/seed-demo.php 2026-10-05`.
3. Abrir `http://localhost/hotel-web/public/login.php`. Las cuentas están en `config/access.local.txt`; no copiar sus contraseñas a Git ni al informe.
4. Abrir el panel y elegir el **05/10/2026**. En otra fecha, las llegadas y salidas cambian: no significa que falten datos.

En una instalación nueva se puede omitir el argumento para usar el día de ejecución en Argentina. La colección se carga una sola vez: repetir el comando no duplica ni mueve las fechas, aunque se indique otro día. Tampoco deshace decisiones tomadas por el personal. Si encuentra conflictos, una colección parcial o faltan habitaciones activas, cancela la carga sin modificar reservas existentes. No hay un comando de borrado o reinicio automático.

## Casos iniciales

| Caso | Habitación | Entrada | Salida | Estado |
|---|---|---|---|---|
| Demo Salida | 101 | 03/10/2026 | 05/10/2026 | Confirmada |
| Demo Llegada | 101 | 05/10/2026 | 07/10/2026 | Confirmada |
| Demo Estadia | 102 | 04/10/2026 | 06/10/2026 | Confirmada |
| Demo Reserva Futura | 201 | 07/10/2026 | 10/10/2026 | Confirmada |
| Demo Pendiente Hoy | 201 | 05/10/2026 | 07/10/2026 | Pendiente |
| Demo Pendiente Manana | 102 | 06/10/2026 | 08/10/2026 | Pendiente |
| Demo Rechazada | 101 | 05/10/2026 | 08/10/2026 | Rechazada |
| Demo Pendiente Futura | 201 | 11/10/2026 | 13/10/2026 | Pendiente |

Las fechas de creación y resolución de la colección son datos simulados. Las reservas confirmadas se atribuyen a una cuenta de personal existente solo para completar la demostración; no son acciones históricas reales de esa persona.

## Guion de presentación

1. **Visitante:** mostrar la portada, las tres habitaciones y el formulario. Explicar que el envío registra una solicitud pendiente; no confirma automáticamente la estadía.
2. **Recepción:** ingresar como staff y consultar el panel del 05/10. Debe mostrar **1 llegada, 1 salida, 3 pendientes y 1 habitación libre de 3**; porcentaje reservado **67 %**.
3. **Disponibilidad:** explicar que la salida libera la noche. Por eso la habitación 101 puede tener salida y llegada el mismo día sin solapamiento. Las solicitudes pendientes y rechazadas no bloquean disponibilidad.
4. **Consulta dinámica:** cambiar la fecha al 07/10 y actualizar. En el estado inicial debe haber **1 llegada, 1 salida y 2 habitaciones libres**. Para las noches del 05 al 11/10, los totales reservados son **2, 1, 1, 1, 1, 0, 0**.
5. **Solicitudes:** abrir un pendiente desde el panel, comprobar habitación, fechas y tarifa. Mostrar los filtros de estado y las reservas confirmadas y rechazadas. Los indicadores reflejan lo guardado en MySQL.
6. **Permisos:** staff puede consultar, confirmar y rechazar solicitudes. Solo administrador puede agregar, editar y desactivar habitaciones. Mostrar el inventario con la cuenta de administrador, sin cambiarlo durante el recorrido inicial.
7. **Explicación técnica:** recorrer formulario → validación en servidor → consulta preparada PDO → base de datos → respuesta. Explicar hash de contraseñas, sesiones, roles y protección CSRF.

El recorrido anterior es de consulta y conserva el estado inicial. Si luego se confirma o rechaza una solicitud para mostrar cambios, los totales dejan de coincidir con esta tabla, y volver a ejecutar la carga no los restablece. Las confirmaciones de entradas pasadas serán rechazadas por la regla del sistema; para una demostración posterior se puede crear una nueva solicitud con fechas futuras desde el formulario.

## Verificación

`C:\xampp\php\php.exe tests/seed-demo.php` ejecuta 17 comprobaciones en una base temporal: casos iniciales, repetición, conservación de reservas ajenas y decisiones del personal, fechas inválidas, habitación inactiva, colección parcial y reversión completa ante conflictos. No utiliza la base de demostración.

La suite general se ejecuta con `C:\xampp\php\php.exe tests/run.php` y contiene 127 comprobaciones adicionales, incluidas las del calendario.

Trabajo relacionado: HOT-11 (panel), HOT-24 (navegación), HOT-29 (testing) y HOT-30 (datos y guion).
