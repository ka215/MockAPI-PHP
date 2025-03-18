<?php

require 'vendor/autoload.php';
use Dotenv\Dotenv;

// .env の読み込み
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

$PORT = $_ENV['PORT'] ?? 3030;
$HOST = 'localhost';

// PHP 組み込みサーバーを `.env` の PORT で起動
echo "Starting Mock API Server on http://$HOST:$PORT\n";
exec("php -S $HOST:$PORT -t .");
