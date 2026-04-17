<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/app/Core/Helpers.php';

use App\Core\App;
use App\Core\Bootstrap;

$basePath = dirname(__DIR__);
Bootstrap::loadEnvironment($basePath);
Bootstrap::configureTimezone();

$app = new App();
$app->run();
