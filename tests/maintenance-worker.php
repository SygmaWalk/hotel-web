<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Database.php';
require_once dirname(__DIR__) . '/src/Hotel.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');
class MaintenanceBarrierStatement extends PDOStatement {
    protected function __construct(private string $ready) {}
    public function execute(?array $params = null): bool {
        if (str_contains($this->queryString, 'FROM rooms WHERE id = ? FOR UPDATE')) file_put_contents($this->ready, 'ready');
        return parent::execute($params);
    }
}
$connection = connectDatabase(require $argv[1]);
$connection->setAttribute(PDO::ATTR_STATEMENT_CLASS,[MaintenanceBarrierStatement::class,[$argv[3]]]);
$hotel = new Hotel($connection);
$job = json_decode(file_get_contents($argv[2]),true,512,JSON_THROW_ON_ERROR);
try {
    switch ($job['action']) {
        case 'block': $hotel->createMaintenance($job['data'],$job['token'],true,$job['actor']); break;
        case 'release': $hotel->releaseMaintenance($job['id'],'Reparación terminada',true,$job['actor']); break;
        case 'move': $hotel->rescheduleReservation($job['quote'],true,$job['actor']); break;
        case 'confirm': $hotel->resolve($job['id'],'confirmed',$job['actor']); break;
        default: $hotel->createReservation($job['data'],bin2hex(random_bytes(32)),$job['actor']);
    }
    exit(0);
} catch (DomainException $error) { exit(2); }
