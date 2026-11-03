<?php
declare(strict_types=1);

interface HotelMailTransport
{
    public function enabled(): bool;
    public function send(array $message): void;
}

final class MailDeliveryException extends RuntimeException
{
    public function __construct(public readonly bool $uncertain = false)
    {
        parent::__construct('No se pudo comprobar el envío del correo.');
    }
}

final class GmailTransport implements HotelMailTransport
{
    public function __construct(private array $config) {}

    public static function fromLocalConfig(): self
    {
        // Un entorno aislado nunca toma por accidente las credenciales del hotel.
        $path = getenv('HOTEL_MAIL_CONFIG_PATH');
        if (!$path && getenv('HOTEL_CONFIG_PATH')) return new self(['enabled'=>false]);
        $path = $path ?: dirname(__DIR__) . '/config/mail.local.php';
        try { $config = is_file($path) ? require $path : []; }
        catch (Throwable $error) { $config = []; }
        return new self(is_array($config) ? $config : []);
    }

    public function enabled(): bool { return ($this->config['enabled'] ?? false) === true; }

    public function send(array $message): void
    {
        if (!$this->enabled()) throw new MailDeliveryException();
        $address = (string) ($this->config['gmail_address'] ?? '');
        $password = preg_replace('/\s+/', '', (string) ($this->config['app_password'] ?? ''));
        if (!filter_var($address, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $address)
            || !preg_match('/^[a-zA-Z0-9]{16}$/D', $password)
            || !filter_var($message['recipient'], FILTER_VALIDATE_EMAIL)
            || preg_match('/[\r\n]/', $message['recipient'] . $message['subject'])) {
            throw new MailDeliveryException();
        }
        foreach (['Exception','SMTP','PHPMailer'] as $file) require_once dirname(__DIR__) . '/lib/PHPMailer/' . $file . '.php';
        // Conserva la fase para distinguir un rechazo de un resultado posiblemente entregado.
        $smtp = new class extends \PHPMailer\PHPMailer\SMTP {
            public bool $dataStarted = false;
            public function data($msg_data) { $this->dataStarted = true; return parent::data($msg_data); }
        };
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $smtp->Timelimit = 20;
        try {
            $mail->setSMTPInstance($smtp);
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->Port = 587;
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->SMTPAuth = true;
            $mail->Username = $address;
            $mail->Password = $password;
            $mail->Timeout = 10;
            $mail->SMTPDebug = 0;
            // No se desactiva la validación de certificados TLS.
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            $mail->setFrom($address, 'Hotel Aurora');
            $mail->addReplyTo($address, 'Hotel Aurora');
            $mail->addAddress($message['recipient']);
            $mail->Subject = $message['subject'];
            $mail->Body = $message['body'];
            $mail->MessageID = '<hotel-' . $message['message_key'] . '@' . substr(strrchr($address, '@'), 1) . '>';
            $mail->send();
        } catch (Throwable $error) {
            // No persistir ErrorInfo: puede contener direcciones, respuestas SMTP o secretos.
            throw new MailDeliveryException($smtp->dataStarted);
        } finally { $mail->smtpClose(); }
    }
}
