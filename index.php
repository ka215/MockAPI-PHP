<?php

require 'vendor/autoload.php';
use Dotenv\Dotenv;

// .env の読み込み
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

// タイムゾーン設定
$TIMEZONE = $_ENV['TIMEZONE'] ?? 'UTC';
date_default_timezone_set($TIMEZONE);

$BASE_PATH = $_ENV['BASE_PATH'] ?? '/api';
$LOG_DIR = $_ENV['LOG_DIR'] ?? __DIR__ . '/logs';
$COOKIE_FILE = $_ENV['TEMP_DIR'] ?? __DIR__ . '/cookies.txt';

// ログディレクトリを作成（存在しない場合）
if (!is_dir($LOG_DIR)) {
    mkdir($LOG_DIR, 0777, true);
}

// cookies.txt をクリアまたは削除
if (file_exists($COOKIE_FILE)) {
    unlink($COOKIE_FILE);
}

// HTTP ステータスコードを外部ファイルから読み込み
$http_status = require __DIR__ . '/http_status.php';

// セッションの開始（リクエストカウントを保持するため）
session_start();

// CORS ヘッダー設定
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE, PATCH, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// OPTIONS リクエストは即終了
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// リクエストIDを生成
$request_id = uniqid();

$method = strtolower($_SERVER['REQUEST_METHOD']);
$request_uri = $_SERVER['REQUEST_URI'];
$path = str_replace($BASE_PATH, '', $request_uri);
$path = parse_url($path, PHP_URL_PATH);

$segments = explode('/', trim($path, '/'));
$responses_dir = __DIR__ . '/responses';

// クライアントのIPアドレスを取得（プロキシ対応）
$client_id = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown_client';

// クライアントごとのリクエストカウントを保持
$_SESSION['client_request_counts'] ??= [];

$request_data = [];

// 認証処理
if (!authorizeRequest($request_id)) {
    unauthorizedResponse($request_id);
}

// リクエストログの記録
logRequest($request_id, $method, $path, $client_id, $request_data);

// クエリパラメータの取得と `mock_response` および `mock_content_type` の分離
$query_params = filter_input_array(INPUT_GET, FILTER_SANITIZE_SPECIAL_CHARS) ?? [];
$request_data['mock_response'] = $query_params['mock_response'] ?? null;
unset($query_params['mock_response']); // `mock_response` は削除
$request_data['mock_content_type'] = $query_params['mock_content_type'] ?? null;
unset($query_params['mock_content_type']); // `mock_content_type` は削除
$request_data['query_params'] = $query_params;

// JSON ボディの取得（POST, PATCH, PUT 用）
$json_body = file_get_contents('php://input');
$request_data['body'] = json_decode($json_body, true) ?? [];

// 特殊ルート: バージョン確認用エンドポイント
if ($method === 'get' && $path === '/version') {
    header('Content-Type: application/json');
    echo file_get_contents(__DIR__ . '/version.json');
    exit;
}

// 特殊ルート: クライアントごとのポーリング回数リセットエンドポイント
if ($method === 'post' && $path === '/reset_polling') {
    $_SESSION['client_request_counts'][$client_id] = [];
    echo json_encode(['message' => 'Polling count reset for client', 'client_id' => $client_id]);
    exit;
}

$matched = false;
$endpoint = '/' . implode('/', $segments);
$response_file = findResponseFile($client_id, $endpoint, $method, $request_data);

// hooksディレクトリにカスタムフックのphpファイルがある場合はそれを読み込んで実行する
$hook_file = __DIR__ . "/hooks/{$method}_" . str_replace('/', '_', trim($path, '/')) . ".php";
if (file_exists($hook_file)) {
    require $hook_file;
}

// レスポンスの処理
if ($response_file) {
    handleResponse($request_id, $response_file, $request_data);
} else {
    errorResponse($request_id, 404);
}
/*
if ($response_file) {
    $matched = true;
    $extension = pathinfo($response_file, PATHINFO_EXTENSION);
    
    if (preg_match('/^(\d{3})\\.(json|txt)$/', basename($response_file), $matches)) {
        $status_code = (int) $matches[1];
        header("HTTP/1.0 $status_code");
    }

    $response_content = file_get_contents($response_file);
    
    if ($extension === 'json') {
        header('Content-Type: application/json');
        $response_data = json_decode($response_content, true);
        
        if (isset($response_data['mockDelay'])) {
            usleep($response_data['mockDelay'] * 1000);
        }
        
        echo json_encode($response_data);
    } elseif ($extension === 'txt') {
        // `mock_content_type` が指定されている場合はそれを使う
        if (isset($request_data['mock_content_type'])) {
            header("Content-Type: {$request_data['mock_content_type']}");
        } else {
            header('Content-Type: text/plain');
        }
        echo $response_content;
    }
    logResponse($method, $endpoint, $request_id, $client_id, $response_content);
} else {
    header("HTTP/1.0 404 Not Found");
    $error_response = json_encode(['error' => 'Route not found']);
    echo $error_response;
    logResponse($method, $endpoint, $request_id, $client_id, $error_response);
}
*/

/**
 * 認証処理
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
 * 認証エラー
 */
function unauthorizedResponse(string $request_id): void
{
    errorResponse($request_id, 401, "Unauthorized: Invalid API Key or Credential");
    exit;
}

/**
 * エラーレスポンス
 */
function errorResponse(string $request_id, int $code, ?string $message = null)
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
 * レスポンスファイルを検索（JSON or TXT）
 */
function findResponseFile(string $client_id, string $endpoint, string $method, array &$request_data): ?string
{
    global $responses_dir, $_SESSION;
    $dir_path = "$responses_dir$endpoint/$method";

    // `mock_response` が指定されている場合はカスタムレスポンスを取得
    $mock_response = $request_data['mock_response'] ?? null;
    if (!empty($mock_response)) {
        foreach (['json', 'txt'] as $ext) {
            $custom_file = "$dir_path/$mock_response.$ext";
            if (file_exists($custom_file)) return $custom_file;
        }
    }

    // ポーリング用レスポンス管理
    $_SESSION['client_request_counts'][$client_id][$endpoint] ??= 0;
    $_SESSION['client_request_counts'][$client_id][$endpoint]++;
    $current_count = $_SESSION['client_request_counts'][$client_id][$endpoint];

    // レスポンス用のファイルを探す
    foreach (["$current_count.json", "$current_count.txt", "default.json", "default.txt"] as $filename) {
        if (file_exists("$dir_path/$filename")) return "$dir_path/$filename";
    }

    return null; // どのレスポンスも見つからない場合
}
/*
// レスポンスファイルを検索（JSONファイルがなければtxtファイルを検索する）
function findResponseFile(string $client_id, string $endpoint, string $method, array &$request_data): ?string
{
    global $responses_dir, $_SESSION;

    $dir_path = "$responses_dir$endpoint/$method";

    // `mock_response` が指定されている場合はカスタムレスポンスを取得
    $mock_response = $request_data['mock_response'] ?? null;
    if (!empty($mock_response)) {
        $custom_file_json = "$dir_path/$mock_response.json";
        $custom_file_txt = "$dir_path/$mock_response.txt";
        if (file_exists($custom_file_json)) {
            return $custom_file_json;
        } elseif (file_exists($custom_file_txt)) {
            return $custom_file_txt;
        }
    }

    // ポーリング用レスポンス管理
    $_SESSION['client_request_counts'][$client_id][$endpoint] ??= 0;
    $_SESSION['client_request_counts'][$client_id][$endpoint]++;
    $current_count = $_SESSION['client_request_counts'][$client_id][$endpoint];

    // ポーリング用のファイルを探す
    $polling_file_json = "$dir_path/$current_count.json";
    $polling_file_txt = "$dir_path/$current_count.txt";
    if (file_exists($polling_file_json)) {
        return $polling_file_json;
    } elseif (file_exists($polling_file_txt)) {
        return $polling_file_txt;
    }

    // `default.json` または `default.txt` を探す
    $default_file_json = "$dir_path/default.json";
    $default_file_txt = "$dir_path/default.txt";
    if (file_exists($default_file_json)) {
        $_SESSION['client_request_counts'][$client_id][$endpoint] = 0; // カウントをリセット
        return $default_file_json;
    } elseif (file_exists($default_file_txt)) {
        $_SESSION['client_request_counts'][$client_id][$endpoint] = 0; // カウントをリセット
        return $default_file_txt;
    }

    return null; // どのレスポンスも見つからない場合
}
*/

/**
 * レスポンスの処理
 */
function handleResponse(string $request_id, string $response_file, array $request_data)
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
 * ロギング（認証エラー）
 */
function logAuthFailure(string $request_id, string $message)
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
 * ロギング（エラー）
 */
function logError(string $request_id, string $message)
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
 * ロギング（リクエスト）
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
 * ロギング（レスポンス）
 */
function logResponse(string $request_id, string $response)
{
    global $LOG_DIR;
    file_put_contents("$LOG_DIR/response.log", json_encode([
        'request_id' => $request_id,
        'timestamp' => date('Y-m-d H:i:s'),
        'timezone' => date_default_timezone_get(),
        'response' => json_decode($response, true) ?? $response,
    ], JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
}
