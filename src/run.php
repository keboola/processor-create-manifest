<?php

declare(strict_types=1);

use Keboola\Component\Logger;
use Keboola\Processor\CreateManifest\Component;

require __DIR__ . '/../vendor/autoload.php';

$logger = new Logger();
try {
    $app = new Component($logger);
    $app->run();
    exit(0);
} catch (\Keboola\Csv\Exception $e) {
    $logger->error("The CSV file is invalid: " . $e->getMessage());
    exit(1);
} catch (\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException $e) {
    $logger->error("Invalid configuration: " . $e->getMessage());
    exit(1);
} catch (\Throwable $e) {
    $logger->critical(
        get_class($e) . ':' . $e->getMessage(),
        [
            'errFile' => $e->getFile(),
            'errLine' => $e->getLine(),
            'errCode' => $e->getCode(),
            'errTrace' => $e->getTraceAsString(),
            'errPrevious' => $e->getPrevious() ? get_class($e->getPrevious()) : '',
        ]
    );
    exit(2);
}
