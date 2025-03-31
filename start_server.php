<?php

/**
 * MockAPI-PHP - Local Development Server Launcher
 *
 * Launches PHP's built-in development server using the specified port
 * from the `.env` file. This is intended for local testing of the Mock API.
 *
 * PHP version 8.3+
 *
 * @author    Katsuhiko Maeno
 * @copyright Copyright (c) 2025 Katsuhiko Maeno
 * @license   MIT License
 * @link      https://github.com/ka215/MockAPI-PHP
 */

require 'vendor/autoload.php';
use Dotenv\Dotenv;

// Loading .env
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

$PORT = $_ENV['PORT'] ?? 3030;
$HOST = 'localhost';

// Start the PHP built-in server on the PORT in `.env`.
echo "Starting Mock API Server on http://$HOST:$PORT\n";
exec("php -S $HOST:$PORT -t .");
