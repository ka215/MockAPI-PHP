<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class MockApiTest extends TestCase
{
    private string $baseUrl = 'http://localhost:3030/api';
    private string $cookieFile = __DIR__ . '/cookies.txt'; // Cookie を管理するファイル

    public function testGetRequestShouldReturnValidResponse(): void
    {
        $response = $this->makeRequest('GET', '/users');
        $this->assertNotEmpty($response, "GET /users should return a response.");
        $this->assertJson($response, "Response should be in JSON format.");
    }

    public function testGetRequestWithQueryParams(): void
    {
        $response = $this->makeRequest('GET', '/users?mock_response=success&sort=desc&limit=5');
        
        $this->assertNotEmpty($response, "GET /users with query params should return a response.");
        $this->assertJson($response, "Response should be in JSON format.");

        $decoded = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        $this->assertIsArray($decoded, "Response should be an array.");
    }

    public function testPostRequestShouldWorkCorrectly(): void
    {
        $data = ['name' => 'New User'];

        // クエリパラメータを使って成功レスポンスを取得
        $response = $this->makeRequest('POST', '/users?mock_response=success', $data);

        $this->assertNotEmpty($response, "POST /users should return a response.");
        $this->assertJson($response, "Response should be in JSON format.");

        $decoded = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        //var_dump($decoded); // デバッグ用

        $this->assertIsArray($decoded, "Response should be an array.");
        $this->assertArrayHasKey('message', $decoded, "Response should contain 'message' key.");
        $this->assertEquals("User created successfully", $decoded['message'] ?? '', "Message should indicate success.");
        $this->assertArrayHasKey('id', $decoded, "Response should contain 'id' key.");
        $this->assertIsInt($decoded['id'], "ID should be an integer.");
    }

    public function testDeleteRequestShouldWorkCorrectly(): void
    {
        $response = $this->makeRequest('DELETE', '/users/1');
        $this->assertNotEmpty($response, "DELETE /users/1 should return a response.");
    }

    public function testInvalidRouteShouldReturnError(): void
    {
        $response = $this->makeRequest('GET', '/invalid_endpoint');
        $this->assertJson($response, "Error response should be in JSON format.");
        $decoded = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('error', $decoded, "Response should contain 'error' key.");
    }

    public function testPollingResponsesShouldChangeOverTime(): void
    {
        $response1 = $this->makeRequest('GET', '/users');
        sleep(1); // 1秒待って次のリクエストを実行
        $response2 = $this->makeRequest('GET', '/users');

        //var_dump($response1, $response2); // デバッグ用

        $this->assertNotEmpty($response1, "First polling response should not be empty.");
        $this->assertNotEmpty($response2, "Second polling response should not be empty.");
        $this->assertNotEquals($response1, $response2, "Polling response should change over time.");
    }

    public function testPollingCountResetShouldWork(): void
    {
        $this->makeRequest('POST', '/reset_polling');
        sleep(1); // リセット後に少し待つ
        $response1 = $this->makeRequest('GET', '/users');
        sleep(1);
        $response2 = $this->makeRequest('GET', '/users');

        //var_dump($response1, $response2); // デバッグ用にレスポンスを確認

        $this->assertNotEquals($response1, $response2, "Polling should start over after reset.");
    }

    public function testResponseDelayShouldBeRespected(): void
    {
        $startTime = microtime(true);
        $this->makeRequest('GET', '/users?mock_response=delay');
        $endTime = microtime(true);

        $duration = ($endTime - $startTime) * 1000; // Convert to ms
        //var_dump($duration); // デバッグ用に実際の時間を確認
        $this->assertGreaterThan(900, $duration, "Response should be delayed by at least 900ms.");
    }

    public function testRequestAndResponseShouldBeLogged(): void
    {
        $this->makeRequest('GET', '/users');

        $logRequestFile = __DIR__ . '/../logs/request.log';
        $logResponseFile = __DIR__ . '/../logs/response.log';

        $this->assertFileExists($logRequestFile, "Request log file should exist.");
        $this->assertFileExists($logResponseFile, "Response log file should exist.");

        $requestLog = file_get_contents($logRequestFile);
        $responseLog = file_get_contents($logResponseFile);

        $this->assertStringContainsString('/users', $requestLog, "Request log should contain /users endpoint.");
        $this->assertStringContainsString('id', $responseLog, "Response log should contain user ID.");
    }

    private function makeRequest(string $method, string $endpoint, ?array $data = null): string
    {
        $ch = curl_init();
        $url = $this->baseUrl . $endpoint;

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        // Cookie 管理を追加
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);

        if ($data !== null) {
            $jsonData = json_encode($data, JSON_THROW_ON_ERROR);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);
        }

        $response = curl_exec($ch);

        if ($response === false) {
            throw new RuntimeException('cURL Error: ' . curl_error($ch));
        }

        curl_close($ch);

        return $response;
    }
}
