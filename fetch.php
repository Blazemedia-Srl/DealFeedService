<?php

use Blazemedia\App\DealFeedSpreadSheetFetcher;

require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$credentials = '';

new DealFeedSpreadSheetFetcher($credentials);
