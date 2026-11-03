<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require_once dirname(__DIR__).'/src/Database.php';
require_once dirname(__DIR__).'/src/ReservationEmails.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');
[$script,$config,$id,$log,$ready] = $argv;
$pdo = connectDatabase(require $config);
$transport = new class($log) implements HotelMailTransport {
    public function __construct(private string $log) {}
    public function enabled(): bool { return true; }
    public function send(array $message): void { file_put_contents($this->log, $message['id']."\n", FILE_APPEND | LOCK_EX); usleep(100000); }
};
file_put_contents($ready,'ready');
$outcome = (new ReservationEmails($pdo,$transport))->send((int)$id);
exit(in_array($outcome,['sent','busy','wait'],true)?0:2);
