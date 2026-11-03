# Avisos de reserva por Gmail

Hotel Aurora prepara un acuse de recepción al guardar una solicitud pública. Ese mensaje dice expresamente que la reserva todavía no está confirmada. Al confirmar la solicitud, o al crear una reserva directa desde recepción, prepara una confirmación con la referencia, habitación, entrada, salida, noches e importe. No se envían correos por pagos, rechazos, reprogramaciones o cancelaciones.

## Activación en esta PC

1. Abrí `config/mail.local.php` en tu editor (en otra instalación, copiá `config/mail.example.php` a ese nombre). Usá la dirección del hotel en `gmail_address`.
2. Activá la verificación en dos pasos en esa cuenta y generá una **contraseña de aplicación** para Hotel Aurora. [Instrucciones oficiales de Google](https://support.google.com/mail/answer/185833?hl=es). Si Google no ofrece esa opción, revisá las restricciones de la cuenta. No uses la contraseña habitual.
3. Escribí la contraseña de aplicación en `app_password` **solo en el archivo privado** y cambiá `enabled` a `true`. El archivo está excluido de Git. No lo incluyas en el informe ni en el paquete de entrega.
4. Desde la carpeta del proyecto, la prueba opcional `C:\xampp\php\php.exe scripts/check-mail.php --send-test` envía un solo mensaje a la propia cuenta configurada. Sin ese argumento el script solo informa si el envío está activado. Revisá Recibidos y Spam. La prueba no crea reservas.
5. En una instalación existente, ejecutá `C:\xampp\php\php.exe scripts/migrate.php` antes de usar la función. Las instalaciones nuevas ya incorporan la tabla en `database/schema.sql`.

El transporte utiliza [PHPMailer](https://github.com/PHPMailer/PHPMailer/releases/tag/v7.1.1), `smtp.gmail.com`, puerto 587 y STARTTLS con autenticación. Se requiere OpenSSL y un almacén de certificados válido en PHP. Si falla la conexión, comprobá la configuración y los certificados; no desactives la verificación TLS.

## Qué ve recepción

En el detalle de una reserva, **Correos al huésped** muestra cada aviso y su estado. El mensaje se registra junto a la reserva; el intento de envío ocurre después de guardarla. Si Gmail está desactivado o falla, la reserva permanece válida. No se crean avisos para reservas históricas ni se envía un lote antiguo al activar Gmail.

Con Gmail activado, recepción puede enviar un pendiente o reintentar un fallo desde el detalle. Hay un minuto de espera entre intentos y un máximo de cinco intentos por aviso. Un aviso que ya no coincide con el estado, las fechas o la habitación se omite. Si el resultado es incierto, revisá Enviados antes de autorizar otro intento: un servidor puede haber aceptado el mensaje aunque la aplicación no haya recibido su respuesta. «Aceptado por Gmail» no garantiza llegada a la bandeja de entrada.

## Pruebas y evidencia

`tests/email-cases.php` se ejecuta desde `tests/run.php` con transporte simulado y base temporal. Cubre reserva pública y directa, confirmación, duplicados, fallos, reintentos, cambios de estado, permisos y dos procesos concurrentes. No utiliza la cuenta Gmail real. Una prueba real de extremo a extremo requiere configurar la cuenta y verificar el mensaje en un buzón propio.
