<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Bootstrap;

$basePath = dirname(__DIR__);
Bootstrap::loadEnvironment($basePath);
Bootstrap::configureTimezone();
