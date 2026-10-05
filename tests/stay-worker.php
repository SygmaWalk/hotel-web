<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Database.php';
require_once dirname(__DIR__) . '/src/Hotel.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');
$pdo=connectDatabase(require $argv[1]);
file_put_contents($argv[4],'ready');
try { (new Hotel($pdo))->recordStay((int)$argv[2], 'checkin', (int)$argv[3]); exit(0); }
catch (DomainException $error) { exit(2); }
