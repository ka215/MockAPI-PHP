<?php

/**
 * MockAPI-PHP - Main Entry Point
 *
 * This script serves as the main entry point for handling mock API requests.
 * It handles routing, authorization, logging, and response delivery.
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

// Load .env file
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

// Set timezone
$TIMEZONE = isset($_ENV['TIMEZONE']) && !empty($_ENV['TIMEZONE']) ? trim($_ENV['TIMEZONE']) : 'UTC';
date_default_timezone_set($TIMEZONE);

$BASE_PATH = isset($_ENV['BASE_PATH']) ? trim($_ENV['BASE_PATH']) : '/api';
$LOG_DIR = isset($_ENV['LOG_DIR']) ? trim($_ENV['LOG_DIR']) : __DIR__ . '/logs';
$COOKIE_FILE = (isset($_ENV['TEMP_DIR']) ? trim($_ENV['TEMP_DIR']) : __DIR__) . '/cookies.txt';

// Create log directory if it doesn't exist
if (!is_dir($LOG_DIR)) {
    mkdir($LOG_DIR, 0777, true);
}

// Clear or delete cookies.txt
if (file_exists($COOKIE_FILE)) {
    unlink($COOKIE_FILE);
}

// Load HTTP status codes from an external file
$http_status = require __DIR__ . '/http_status.php';

// Start session (to keep track of request counts)
session_start();

// Set CORS headers
$allow_origin  = isset($_ENV['CORS_ALLOW_ORIGIN']) && !empty($_ENV['CORS_ALLOW_ORIGIN']) ? trim($_ENV['CORS_ALLOW_ORIGIN']) : '*';
$allow_methods = isset($_ENV['CORS_ALLOW_METHODS']) && !empty($_ENV['CORS_ALLOW_METHODS']) ? trim($_ENV['CORS_ALLOW_METHODS']) : 'GET, POST, DELETE, PATCH, PUT, OPTIONS';
$allow_headers = isset($_ENV['CORS_ALLOW_HEADERS']) && !empty($_ENV['CORS_ALLOW_HEADERS']) ? trim($_ENV['CORS_ALLOW_HEADERS']) : 'Origin, Content-Type, Accept';
$allow_credentials = isset($_ENV['CREDENTIAL']) || (isset($_ENV['CORS_ALLOW_CREDENTIALS']) && $_ENV['CORS_ALLOW_CREDENTIALS']);
header("Access-Control-Allow-Origin: {$allow_origin}");
header("Access-Control-Allow-Methods: {$allow_methods}");
header("Access-Control-Allow-Headers: {$allow_headers}");
if ($allow_credentials) {
    header("Access-Control-Allow-Credentials: true");
}

// Immediately exit on OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Generate a unique request ID
$request_id = uniqid();

$method = strtolower($_SERVER['REQUEST_METHOD']);
$request_uri = $_SERVER['REQUEST_URI'];
$path = str_replace($BASE_PATH, '', $request_uri);
$path = parse_url($path, PHP_URL_PATH);

$segments = explode('/', trim($path, '/'));
$responses_dir = __DIR__ . '/responses';

// Get the client's IP address (supports proxy headers)
$client_id = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown_client';

// Keep track of client request counts
$_SESSION['client_request_counts'] ??= [];

$request_data = [];

// Parse path parameters
$route_template = detectDynamicRoute($segments, $request_data);

// Require if polyfill is needed
$polyfill_file = __DIR__ . "/libs/polyfills.php";
if (file_exists($polyfill_file)) {
    require $polyfill_file;
}

// Require helper methods
$utils_file = __DIR__ . "/libs/utils.php";
if (file_exists($utils_file)) {
    require $utils_file;
}

// Authorization
$auth_hook_file = __DIR__ . "/hooks/authorization.php";
if (file_exists($auth_hook_file)) {
    require $auth_hook_file;
} else {
    if (!authorizeRequest($request_id)) {
        unauthorizedResponse($request_id);
    }
}

// Log the request
logRequest($request_id, $method, $path, $client_id, $request_data);

// Retrieve query parameters and separate `mock_response` and `mock_content_type`
$query_params = filter_input_array(INPUT_GET, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
$request_data['mock_response'] = $query_params['mock_response'] ?? null;
unset($query_params['mock_response']); // Remove `mock_response`
$request_data['mock_content_type'] = $query_params['mock_content_type'] ?? null;
unset($query_params['mock_content_type']); // Remove `mock_content_type`
$request_data['query_params'] = $query_params;

// Retrieve request body (for POST, PATCH, PUT requests)
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (
    stripos($contentType, 'application/json') !== false
) {
    // If JSON format
    $json_body = file_get_contents('php://input');
    $request_data['body'] = json_decode($json_body, true) ?? [];
} elseif (
    stripos($contentType, 'multipart/form-data') !== false
) {
    // If multipart format
    $request_data['body'] = $_POST;
    $request_data['files'] = $_FILES;
} else {
    // Fallback if URL encode format etc.
    $request_data['body'] = $_POST;
    $request_data['files'] = $_FILES;
}

// Special route: Version check endpoint
if ($method === 'get' && $path === '/version') {
    header('Content-Type: application/json');
    echo file_get_contents(__DIR__ . '/version.json');
    exit;
}

// Special route: Reset polling count per client
if ($method === 'post' && $path === '/reset_polling') {
    $_SESSION['client_request_counts'][$client_id] = [];
    echo json_encode(['message' => 'Polling count reset for client', 'client_id' => $client_id]);
    exit;
}

// Search for a response file based on the endpoint
$response_file = findResponseFile($client_id, $route_template, $method, $request_data);

// Load and execute a custom hook file if it exists in the hooks directory
$hook_file = __DIR__ . "/hooks/{$method}_" . str_replace('/', '_', trim($route_template, '/')) . ".php";
if (file_exists($hook_file)) {
    require $hook_file;
}

// Handle response
if ($response_file !== null) {
    handleResponse($request_id, $response_file, $request_data);
} else {
    errorResponse($request_id, 404);
}

// -----------------------------------------------------------------------------

/**
 * Authorization processing
 */
function authorizeRequest(string $request_id): bool
{
    $required_api_key = $_ENV['API_KEY'] ?? null;
    $required_credential = $_ENV['CREDENTIAL'] ?? null;
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    if ($required_api_key && !str_contains($auth_header, $required_api_key)) {
        logAuthFailure($request_id, "Invalid API Key");
        return false;
    }

    if ($required_credential && !str_contains($auth_header, $required_credential)) {
        logAuthFailure($request_id, "Invalid Credential");
        return false;
    }

    return true;
}

/**
 * Unauthorized response
 */
function unauthorizedResponse(string $request_id): void
{
    errorResponse($request_id, 401, "Unauthorized: Invalid API Key or Credential");
    exit;
}

/**
 * Error response
 */
function errorResponse(string $request_id, int $code, ?string $message = null): void
{
    global $responses_dir;
    $error_file_json = "$responses_dir/errors/$code.json";
    $error_file_txt = "$responses_dir/errors/$code.txt";

    header("HTTP/1.1 $code");

    if (file_exists($error_file_json)) {
        header('Content-Type: application/json');
        echo file_get_contents($error_file_json);
    } elseif (file_exists($error_file_txt)) {
        header('Content-Type: text/plain');
        echo file_get_contents($error_file_txt);
    } else {
        echo json_encode(["error" => $message ?? "Error $code"]);
    }

    logError($request_id, "Error $code: $message");
    exit;
}

/**
 * Parse dynamic route patterns
 * @param array<int, string> $segments
 * @param array<string, mixed> $request_data
 */
function detectDynamicRoute(array $segments, array &$request_data): string
{
    global $responses_dir;

    $dynamic_route = [];
    $current_path = $responses_dir; // Initial path

    foreach ($segments as $index => $segment) {
        $next_path = "$current_path/$segment";

        if (!is_dir($next_path)) {
            // If the directory does not exist, treat it as a dynamic parameter
            $param_name = "dynamicParam$index";
            $request_data[$param_name] = $segment;
        } else {
            // If the directory exists, treat it as a fixed path
            $dynamic_route[] = $segment;
            $current_path = $next_path;
        }
    }
    return '/' . implode('/', $dynamic_route);
}

/**
 * Search for a response file (JSON or TXT)
 * @param array<string, mixed> $request_data
 */
function findResponseFile(string $client_id, string $endpoint, string $method, array &$request_data): ?string
{
    global $responses_dir, $_SESSION;
    $dir_path = "$responses_dir$endpoint/$method";

    // If `mock_response` is specified, get the custom response
    $mock_response = $request_data['mock_response'] ?? null;
    if (!empty($mock_response)) {
        foreach (['json', 'txt'] as $ext) {
            $custom_file = "$dir_path/$mock_response.$ext";
            if (file_exists($custom_file)) {
                return $custom_file;
            }
        }
    }

    // Polling response management
    $_SESSION['client_request_counts'][$client_id][$endpoint] ??= 0;
    $_SESSION['client_request_counts'][$client_id][$endpoint]++;
    $current_count = $_SESSION['client_request_counts'][$client_id][$endpoint];

    // Search for response files
    foreach (["$current_count.json", "$current_count.txt", "default.json", "default.txt"] as $filename) {
        if (file_exists("$dir_path/$filename")) {
            return "$dir_path/$filename";
        }
    }

    return null; // No response files found
}

/**
 * Handle response processing
 * @param array<string, mixed> $request_data
 */
function handleResponse(string $request_id, string $response_file, array $request_data): void
{
    $extension = pathinfo($response_file, PATHINFO_EXTENSION);
    $response_content = file_get_contents($response_file);

    if ($extension === 'json') {
        header('Content-Type: application/json');
        $response_data = json_decode($response_content, true);

        if (isset($response_data['mockDelay'])) {
            usleep($response_data['mockDelay'] * 1000);
        }

        echo json_encode($response_data);
    } else {
        $content_type = $request_data['mock_content_type'] ?? 'text/plain';
        header("Content-Type: $content_type");
        echo $response_content;
    }

    logResponse($request_id, $response_content);
}

/**
 * Logging (Authorization error)
 */
function logAuthFailure(string $request_id, string $message): void
{
    global $LOG_DIR;
    file_put_contents("$LOG_DIR/auth.log", json_encode([
        'request_id' => $request_id,
        'timestamp' => date('Y-m-d H:i:s'),
        'timezone' => date_default_timezone_get(),
        'error' => $message,
    ], JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
}

/**
 * Logging (error)
 */
function logError(string $request_id, string $message): void
{
    global $LOG_DIR;
    file_put_contents("$LOG_DIR/error.log", json_encode([
        'request_id' => $request_id,
        'timestamp' => date('Y-m-d H:i:s'),
        'timezone' => date_default_timezone_get(),
        'error' => $message,
    ], JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
}

/**
 * Logging (Request)
 * @param array<string, mixed> $request_data
 */
function logRequest(string $request_id, string $method, string $endpoint, string $client_id, array $request_data): void
{
    global $LOG_DIR;

    $log_data = [
        'request_id' => $request_id,
        'timestamp' => date('Y-m-d H:i:s'),
        'timezone' => date_default_timezone_get(),
        'client_id' => $client_id,
        'method' => strtoupper($method),
        'endpoint' => $endpoint,
        'headers' => getallheaders(),
        'query_params' => $request_data['query_params'] ?? [],
        'body' => $request_data['body'] ?? [],
    ];

    file_put_contents("$LOG_DIR/request.log", json_encode($log_data, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
}

/**
 * Logging (Response)
 */
function logResponse(string $request_id, string $response): void
{
    global $LOG_DIR;
    file_put_contents("$LOG_DIR/response.log", json_encode([
        'request_id' => $request_id,
        'timestamp' => date('Y-m-d H:i:s'),
        'timezone' => date_default_timezone_get(),
        'response' => json_decode($response, true) ?? $response,
    ], JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
}
