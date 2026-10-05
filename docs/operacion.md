# Operación diaria, fotos y usuarios

Implementado el 05/10/2026: HOT-26, HOT-31, HOT-32 y HOT-33.

## Actualizar una instalación existente

Con MySQL iniciado, ejecutar desde la carpeta del proyecto:

```text
C:\xampp\php\php.exe scripts/migrate.php
```

La migración agrega stays y room_images, sin modificar ni borrar reservas, habitaciones o usuarios. Puede repetirse. Requiere una cuenta de base de datos con permiso CREATE: toma HOTEL_SETUP_DSN, HOTEL_SETUP_USER y HOTEL_SETUP_PASSWORD o el root local de XAMPP. La aplicación continúa usando su cuenta restringida. Una instalación nueva mediante setup.php ya incluye las tablas.

## Llegadas y salidas

Abrir una reserva confirmada en Solicitudes. Recepción y administración pueden registrar llegada y salida por POST con CSRF.

- Check-in desde la fecha de entrada inclusive hasta antes de la fecha de salida. Se permite llegar después de la fecha inicial, dentro del intervalo contratado.
- Se rechazan ingresos anticipados, repetidos, sobre pendientes/rechazadas o en habitaciones inactivas.
- Se bloquea la habitación durante la transacción: no puede ingresar otro huésped si hay una estadía sin salida, incluso si la fecha prevista de esa salida ya pasó.
- Check-out requiere una llegada y no puede repetirse. Admite salida anticipada o tardía con la hora real.
- La tabla stays conserva fechas y empleados de ambos movimientos, vinculados por claves foráneas.
- No se infiere un no-show ni se registran movimientos automáticamente. Una confirmada sin llegada sigue siendo prevista.
- Registrar una salida no cancela ni cambia el intervalo contratado. La cancelación/liberación anticipada se trata por separado (HOT-25); la limpieza sigue pendiente (HOT-13).

El panel distingue huéspedes alojados ahora de indicadores por fechas previstas. El calendario muestra registros reales hasta hoy; las noches futuras siguen basadas en reservas. Una habitación inactiva puede conservar historial.

## Un año de anticipación

Entrada y salida de solicitudes nuevas deben quedar dentro del próximo año calendario desde hoy, inclusive para la salida. Se exige al menos una noche: la entrada máxima es el día anterior al límite. El 29/02 se limita al 28/02 del año siguiente. Ejemplo al 05/10/2026: salida máxima 05/10/2027.

PHP valida al recibir el POST y al confirmar; los inputs también tienen max. El calendario admite historial desde 2000 y llega hasta ese límite, con hasta 31 noches por consulta. Los registros previos permanecen intactos.

## Imágenes

Administración → Habitaciones permite adjuntar imágenes opcionales al crear o editar. Hasta cinco por habitación, de 1 MB cada una, JPG/PNG/WebP, máximo 6000 píxeles por lado y 20 megapíxeles.

El formulario usa multipart/form-data y PHP recibe los archivos en $_FILES. RoomImages verifica error de subida, tamaño, tipo real con finfo y dimensiones con getimagesize. No confía en el nombre ni en el tipo declarado por el navegador. Genera nombres aleatorios y guarda archivos en storage/rooms, fuera de public. room_images guarda las referencias; imagen.php transmite bytes como imagen con nosniff y CSP restrictiva. El archivo nunca se incluye ni se ejecuta como PHP.

Guardar habitación y referencias utiliza una transacción. Si falla, se revierten las filas y se retiran solo los archivos generados en ese intento. Una foto existente no se reemplaza ni elimina. En errores, los datos escritos se conservan pero el navegador requiere seleccionar de nuevo los archivos.

Las fotos aparecen en el catálogo y se pueden abrir en tamaño completo. Las imágenes de habitaciones inactivas solo son accesibles para administración. storage/ se excluye de Git: para otra PC hay que transferir las fotos por separado junto con una copia de la base. Las imágenes pueden contener metadatos propios de la foto; no se realiza una conversión del archivo.

## Usuarios de recepción

Administración → Usuarios: correo único, contraseña y repetición. Mínimo 12 y máximo 72 bytes (límite compatible con bcrypt); una contraseña solo de espacios no se acepta. El rol staff se fija en servidor, aunque se envíe role=admin.

La contraseña se guarda únicamente como hash. No se muestra en el listado ni se añade al archivo de cuentas de instalación. Comunicarla personalmente al empleado. El nuevo usuario tiene exactamente el acceso de recepción: panel, calendario, solicitudes y movimientos; no administra habitaciones ni usuarios.

## Aprendizaje y pruebas

- Movimientos: POST → autorización → reglas → transacción → INSERT/UPDATE → redirección 303.
- Fotos: multipart/form-data → $_FILES → validación de archivo → almacenamiento + referencia SQL.
- Usuarios: validación → password_hash → INSERT con rol fijo → login con password_verify.
- Límite de fechas: una sola función bookingLimit comparte la regla entre formulario, servicio y calendario.
- Nuevas tablas: migración aditiva para ampliar el esquema sin reiniciar la base.

tests/run.php incluye los casos nuevos en tests/operations-cases.php: 186 comprobaciones, entre ellas dos procesos concurrentes de check-in, fechas límite, roles, contraseñas, adjuntos válidos/falsos/grandes y rollback de lote inválido. tests/seed-demo.php mantiene 17 comprobaciones. Ambas suites usan bases temporales, sin cambiar la base del hotel.
