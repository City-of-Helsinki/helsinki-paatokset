<?php

/**
 * @file
 * Symfony console data aggregator.
 */

declare(strict_types=1);

use App\Commands\ListFetchCommand;
use GuzzleHttp\Client;
use Symfony\Component\Console\Application;

require_once '../../../autoload.php';
require_once __DIR__ . '/src/Commands/ListFetchCommand.php';

$client = new Client();
$application = new Application();
$application->add(new ListFetchCommand($client));
$application->run();
