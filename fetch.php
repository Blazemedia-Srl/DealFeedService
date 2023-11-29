<?php

use Blazemedia\App\DealFeedSpreadSheetFetcher;

require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$credentials_path = __DIR__."/".env('BLAZE_GA4_KEY_FILE_NAME','');

if(!$credentials_path || !file_exists($credentials_path)){
    echo"Credentials not found";
    die();
}

new DealFeedSpreadSheetFetcher($credentials_path);
