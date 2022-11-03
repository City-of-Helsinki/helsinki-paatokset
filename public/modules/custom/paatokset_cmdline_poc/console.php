<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use App\Commands\ListFetchCommand;
use Symfony\Component\Console\Application;

require_once '../../../autoload.php';
require_once __DIR__ . '/src/Commands/ListFetchCommand.php';

$client = new Client();
$application = new Application();
$application->add(new ListFetchCommand($client));
$application->run();
