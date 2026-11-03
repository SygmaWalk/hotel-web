# Registro de pruebas del cierre funcional

Fecha: 23/10/2026. Entorno: Windows, PHP y MySQL/MariaDB de XAMPP. Aplicación local PHP con PDO, HTML, CSS y JavaScript. Datos ficticios en bases temporales, separadas de `hotel_aurora`.

## Resultado automatizado

- `php tests/run.php`: **616 comprobaciones satisfactorias** de reglas, HTTP y procesos concurrentes.
- `php tests/seed-demo.php`: **17 comprobaciones satisfactorias** de carga demo e idempotencia.
- Las 95 comprobaciones nuevas de pagos se ejecutan después de las 521 existentes; los módulos previos siguen cubiertos por la misma suite.

| Escenario de pagos | Resultado esperado y obtenido |
| --- | --- |
| Tres noches de 100,10; seña de 100,10 | Total 300,30; pendiente 200,20, sin errores de redondeo |
| Cobro superior al pendiente o devolución superior al neto | Rechazo sin movimiento nuevo |
| Reenvío del mismo formulario | Un solo movimiento; cambiar los datos del token usado se rechaza |
| Anulación con motivo | Se conserva original y auditoría; saldo y reporte excluyen el registro |
| Anulación repetida o sobre otra reserva | Rechazo sin sobrescribir auditoría |
| Reprogramación a menor total | Pagos conservados y crédito visible |
| Cancelación con seña | Pendiente cero; seña a favor hasta registrar devolución |
| Visitante, CSRF inválido o token de otra sesión/reserva | Acceso o escritura rechazados |
| Texto con etiquetas HTML | Se muestra escapado en pagos y resumen |
| Reporte por fecha y medio | Señas y pagos suman; devoluciones restan; anulados quedan visibles sin sumar |
| Repetir migración | Movimientos idénticos antes y después |
| Dos cobros de 70 sobre un total de 100 | Solo uno se acepta |
| Dos devoluciones de 70 sobre 100 pagados | Solo una se acepta |
| Dos anulaciones del mismo movimiento | Solo una modifica la auditoría |
| Dos envíos del mismo token en paralelo | Ambos reconocen el mismo movimiento, sin duplicar |
| Pago junto con cancelación o reprogramación | Estado final y saldo consistentes; crédito conservado si corresponde |

Durante la primera pasada HTTP se detectó que Cobros sin parámetro de fecha no tomaba el día actual. Se corrigió el valor inicial y se repitió la suite completa con resultado satisfactorio. El fallo pertenecía a la copia de trabajo, antes de instalarla en XAMPP.

## Comprobación visual y límites

El recorrido en navegador de escritorio usa una reserva ficticia de $65.000: registrar seña, verificar el saldo, corregir una carga y consultar el resumen. La evidencia visual se conserva por separado de los datos temporales. Las verificaciones de saldos y concurrencia no dependen de capturas.

No se realizó una prueba bancaria ni se envió dinero. La aplicación registra movimientos manuales. La revisión móvil de estos formularios queda pendiente para la preparación final de la entrega. No se evaluó carga masiva ni un entorno de producción pública.

La comprobación de instalación compara los datos existentes antes y después de crear la tabla nueva y abre las pantallas con la cuenta local de recepción sin crear pagos en la base del hotel. Los números de la suite son comprobaciones automatizadas, no cantidad de requisitos académicos.
