<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Database.php';
require_once dirname(__DIR__) . '/src/Hotel.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');
$pdo = connectDatabase(require $argv[1]);
$job = json_decode(file_get_contents($argv[2]), true, 512, JSON_THROW_ON_ERROR);
file_put_contents($argv[3], 'ready');
try {
    $hotel = new Hotel($pdo);
    if (isset($job['confirm'])) $hotel->resolve($job['confirm'], 'confirmed', $job['actor']);
    else $hotel->createReservation($job['data'], $job['token'], $job['actor']);
    exit(0);
} catch (DomainException $error) { exit(2); }
