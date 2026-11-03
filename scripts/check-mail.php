<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/src/MailTransport.php';
$path = dirname(__DIR__) . '/config/mail.local.php';
if (!is_file($path)) exit("Falta config/mail.local.php. Copiá mail.example.php y completalo de forma privada.\n");
$config = require $path;
$gmail = new GmailTransport(is_array($config) ? $config : []);
if (!$gmail->enabled()) exit("El envío está desactivado. Completá la cuenta y contraseña de aplicación antes de activar enabled.\n");
if (($argv[1] ?? '') !== '--send-test') exit("Envío habilitado. Para probar Gmail enviando un único mensaje a la misma cuenta, usá --send-test.\n");
try {
    $gmail->send(['recipient'=>(string)($config['gmail_address'] ?? ''),'subject'=>'Hotel Aurora · Prueba de correo',
        'body'=>"Mensaje de prueba de Hotel Aurora. No corresponde a una reserva ni a un pago.\n",'message_key'=>bin2hex(random_bytes(16))]);
    echo "Gmail aceptó el mensaje de prueba. Revisá la bandeja de entrada y Spam.\n";
} catch (MailDeliveryException $error) {
    fwrite(STDERR,$error->uncertain ? "Resultado incierto: revisá Enviados antes de repetir.\n" : "No se pudo enviar. Revisá contraseña de aplicación, conexión, OpenSSL y certificados.\n");
    exit(1);
}
