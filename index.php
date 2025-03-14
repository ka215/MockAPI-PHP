<?php

require 'vendor/autoload.php';
use Dotenv\Dotenv;

// .env の読み込み
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

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

// クライアントごとのポーリング回数リセットエンドポイント
if ($method === 'post' && $path === '/reset_polling') {
    $_SESSION['client_request_counts'][$client_id] = [];
    echo json_encode(['message' => 'Polling count reset for client', 'client_id' => $client_id]);
    exit;
}

$matched = false;
$endpoint = '/' . implode('/', $segments);
$request_id = uniqid();
$response_file = findResponseFile($client_id, $endpoint, $method, $request_data);

// hooksディレクトリにカスタムフックのphpファイルがある場合はそれを読み込んで実行する
$hooks_dir = __DIR__ . '/hooks';
$snake_case_endpoint = str_replace('/', '_', $endpoint);
$hook_file = "$hooks_dir/{$method}{$snake_case_endpoint}.php";
if (file_exists($hook_file)) {
    require $hook_file;
}

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

// リクエストのログ記録
function logRequest(string $method, string $endpoint, string $request_id, string $client_id, array $request_data): void
{
    global $LOG_DIR;

    $log_data = [
        'request_id' => $request_id,
        'timestamp' => date('c'),
        'client_id' => $client_id,
        'method' => $method,
        'endpoint' => $endpoint,
        'headers' => getallheaders(),
        'query_params' => $request_data['query_params'] ?? [],
        'body' => $request_data['body'] ?? [],
    ];

    file_put_contents("$LOG_DIR/request.log", json_encode($log_data, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
}

// レスポンスのログ記録
function logResponse($method, $endpoint, $request_id, $client_id, $response) {
    global $LOG_DIR;

    $log_data = [
        'request_id' => $request_id,
        'timestamp' => date('c'),
        'client_id' => $client_id,
        'method' => $method,
        'endpoint' => $endpoint,
        'response' => json_decode($response, true) ?? $response,
    ];

    file_put_contents("$LOG_DIR/response.log", json_encode($log_data, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
}

logRequest($method, $endpoint, $request_id, $client_id, $request_data);
