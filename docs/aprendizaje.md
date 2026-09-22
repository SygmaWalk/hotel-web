# Del formulario a MySQL

Proyecto ficticio. El objetivo es entender cada paso y poder seguirlo en el código.

## Primera prueba

Abrí las herramientas de desarrollo del navegador, pestaña Red (Network). Entrá en habitaciones, completá una solicitud con datos ficticios y enviá el formulario.

1. **GET /habitaciones.php**: el navegador pide la página. PHP llama a Hotel::rooms(), PDO ejecuta SELECT y fetchAll devuelve un array. El foreach construye el HTML que recibe el navegador. El navegador no recibe ni ejecuta PHP.
2. **GET /reservar.php?room_id=1**: el parámetro viaja en la URL y preselecciona la habitación; todavía no cambia datos.
3. **POST /reservar.php**: los campos viajan en el cuerpo de la petición. PHP los recibe en $_POST. Validation.php valida nombre, correo, fechas e identificador. JavaScript solo mejora la experiencia.
4. **INSERT**: si el formulario es válido, Hotel::submit obtiene la tarifa desde rooms y guarda una fila en reservations, con estado pending. PDO usa prepare + execute: SQL y valores viajan separados.
5. **303 → GET /solicitud-enviada.php**: PHP responde con una redirección. Al actualizar el comprobante se repite GET, no INSERT. Un token único en la base protege además el reenvío del POST original.

En Network mirá Method, Status, Payload y Response. No compartas cookies ni tokens.

## Conexión y relaciones

Database.php crea un objeto PDO usando config/local.php. El DSN indica servidor, base y codificación. El usuario de la aplicación tiene permisos limitados a hotel_aurora; no utiliza root.

rooms.id es la clave primaria de una habitación. reservations.room_id es una clave foránea: relaciona la solicitud con una habitación existente. El JOIN del panel recupera ambos registros. users guarda contraseñas con hash, nunca el texto original.

Podés ver las tablas en phpMyAdmin de XAMPP. Consulta de lectura para observar lo guardado:

```sql
SELECT r.id, r.guest_name, h.code, r.check_in, r.check_out, r.status
FROM reservations r
JOIN rooms h ON h.id = r.room_id
ORDER BY r.id DESC;
```

## Acceso del personal

El login verifica password_verify, regenera el identificador de sesión y guarda el id del usuario del lado del servidor. El navegador conserva una cookie de sesión, no la contraseña. Cada página del personal comprueba la sesión; cada operación administrativa comprueba también el rol.

Los formularios POST incluyen un token CSRF: PHP verifica que la petición provenga de un formulario de esa sesión. Esto no reemplaza validar permisos ni los campos. Se limita el acceso tras cinco intentos fallidos por correo en quince minutos.

## Confirmar y desactivar

Confirmar usa una transacción: bloquea la fila de la habitación con FOR UPDATE, revisa el estado y busca solapamientos. El bloqueo serializa decisiones para la misma habitación. Si falla una condición, ROLLBACK; si todo está bien, UPDATE y COMMIT. El cruce es entradaExistente < salidaNueva AND salidaExistente > entradaNueva. Así se admite salida y entrada el mismo día.

Desactivar hace UPDATE active = 0, sin DELETE. Las solicitudes y reservas siguen existiendo. Si hay reservas vigentes o futuras, exige aceptar una advertencia. Una habitación inactiva no se ofrece ni admite nuevas confirmaciones.

## Estados HTTP que podés observar

- 200: página obtenida.
- 303: redirección después de una operación.
- 403: falta permiso o el token CSRF es inválido.
- 404: referencia inexistente.
- 409: conflicto, por ejemplo fechas ocupadas al confirmar.
- 422: campos inválidos; el formulario conserva los datos.
- 429: demasiados intentos de acceso.
- 503: fallo inesperado o de conexión, sin mostrar credenciales.

HOT-4 es la validación; HOT-5 agrega la persistencia sobre el mismo formulario. Una validación fallida no guarda nada. El mensaje de recepción aparece solo después de guardar. Todo sigue siendo una demo local, sin correos, pagos ni reservas reales.
