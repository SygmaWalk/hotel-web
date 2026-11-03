# Diccionario de datos

Hotel Aurora. Esquema de referencia: database/schema.sql al 23/10/2026. PK significa clave primaria y FK clave foránea. Los nombres de columnas son los del código; las agrupaciones no agregan entidades que no existan.

| Tabla y PK | Campos centrales | Relaciones y restricciones |
| --- | --- | --- |
| rooms · id | code, name, capacity, nightly_rate, active | Código único; capacidad mayor que cero; tarifa no negativa. Desactivar conserva referencias. |
| users · id | email, password_hash, role | Correo único; rol admin o staff; hash de contraseña. |
| reservations · id | room_id, guest_name, email, check_in, check_out, nightly_rate, status, submission_token, created_at, resolved_by, resolved_at | FK a rooms y opcional a users para resolución. Salida posterior a entrada. Token único; índice de disponibilidad por habitación, estado y fechas. |
| stays · reservation_id | checked_in_at, checked_in_by, checked_out_at, checked_out_by | PK también FK a reservations. Usuarios de ingreso y salida; salida y su actor opcionales. Salida no anterior al ingreso. |
| room_cleanings · reservation_id | pending_at, completed_at, completed_by | PK también FK a stays. Finalización y usuario simultáneamente presentes o ausentes; fecha de finalización no anterior a pendiente. |
| room_images · id | room_id, filename, mime_type, created_at | FK a rooms; nombre de archivo único. La imagen reside en storage/rooms. |
| reservation_cancellations · reservation_id | reason, cancelled_at, cancelled_by | PK también FK a reservations; FK de actor a users; motivo entre 1 y 500 caracteres. |
| reservation_changes · id | reservation_id, old_room_id, new_room_id, old_room_code, new_room_code, old_check_in, old_check_out, new_check_in, new_check_out, old_nightly_rate, new_nightly_rate, reason, changed_at, changed_by | FK a reserva, habitaciones de origen y destino y usuario. Fechas y tarifas anteriores/nuevas se conservan como instantánea histórica. |
| room_maintenance · id | room_id, start_date, end_date, reason, created_at, created_by, submission_token, released_at, released_by, release_reason | FK a habitación y usuarios; fin posterior al inicio; token único; datos de levantamiento completos o nulos. |
| reservation_payments · id | reservation_id, kind, amount, method, paid_on, note, recorded_at, recorded_by, submission_token, voided_at, voided_by, void_reason | FK a reserva y usuarios; importe positivo DECIMAL(12,2); token único; auditoría de anulación completa o nula. |
| login_attempts · identity_hash | failures, last_failure | Sin FK a users: se controla también una identidad que no existe como cuenta. No almacena la contraseña intentada. |

## Valores de dominio

- reservations.status: pending, confirmed, rejected, cancelled.
- users.role: admin, staff.
- reservation_payments.kind: deposit, payment, refund.
- reservation_payments.method: cash, transfer, card, other.

## Reglas de integridad en el servidor

Las claves foráneas garantizan referencias existentes, pero no expresan todas las reglas de negocio. El servidor comprueba fechas respecto de hoy, solapamientos, permisos, limpieza, huéspedes sin salida y saldo disponible. La revisión y la escritura se realizan dentro de transacciones cuando es necesario proteger una decisión concurrente.

La tarifa acordada está en reservations; cambiar la tarifa del catálogo no modifica automáticamente esa reserva. Reprogramar dentro de la misma habitación conserva la tarifa y cambiar de habitación utiliza la vigente del destino. El total se obtiene multiplicando noches por tarifa; no existe una columna de total ni una tabla separada de huéspedes.

El mantenimiento tiene fin exclusivo. Si se levanta, deja de afectar hoy y las noches futuras, conservando las noches anteriores. Los pagos vigentes son los que tienen voided_at nulo; las anulaciones conservan la fila original. El saldo es calculado y no una columna editable.

Las fechas y horas operativas se generan con la zona America/Argentina/Buenos_Aires. paid_on es la fecha declarada del movimiento, mientras recorded_at registra cuándo se cargó. La aplicación limita la fecha declarada al intervalo desde 2000 hasta hoy.
