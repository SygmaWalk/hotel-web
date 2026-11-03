<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Database.php';
require_once dirname(__DIR__) . '/src/Hotel.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');
$hotel = new Hotel(connectDatabase(require $argv[1]));
$job = json_decode(file_get_contents($argv[2]), true, 512, JSON_THROW_ON_ERROR);
file_put_contents($argv[3], 'ready');
try {
    if ($job['action'] === 'cancel') $hotel->cancelReservation($job['reservation'], 'Cancelación concurrente', true, $job['actor']);
    else $hotel->recordStay($job['reservation'], 'checkin', $job['actor']);
    exit(0);
} catch (DomainException $error) { exit(2); }
