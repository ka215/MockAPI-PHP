<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Dotenv\Dotenv;

class MockApiTest extends TestCase
{
    private static string $baseUrl;
    private static string $logPath = __DIR__ . '/../logs';
    private static string $cookieFile = __DIR__ . '/../temp/cookies.txt';

    public static function setUpBeforeClass(): void
    {
        if (file_exists(__DIR__ . '/../.env')) {
            $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
            $dotenv->load();
        }

        $basePath = trim($_ENV['BASE_PATH'] ?? '') ?: '/api';
        self::$baseUrl = "http://localhost:3030{$basePath}";
    }

    protected function setUp(): void
    {
        $this->makeRequest('POST', '/reset_polling');
    }

    protected function tearDown(): void
    {
        $logRequestFile = self::$logPath .'/request.log';
        $logResponseFile = self::$logPath . '/response.log';

        if (file_exists($logRequestFile)) {
            unlink($logRequestFile);
        }

        if (file_exists($logResponseFile)) {
            unlink($logResponseFile);
        }
    }

    public function testGetRequestShouldReturnValidResponse(): void
    {
        $response = $this->makeRequest('GET', '/users');
        $this->assertNotFalse($response, "GET /users request failed.");
        $this->assertNotEmpty($response, "GET /users should return a response.");
        $this->assertJson($response, "Response should be in JSON format.");
    }

    public function testGetRequestWithQueryParams(): void
    {
        $response = $this->makeRequest('GET', '/users?mock_response=success&sort=desc&limit=5');
        
        $this->assertNotFalse($response, "GET /users with query params request failed.");
        $this->assertNotEmpty($response, "GET /users with query params should return a response.");
        $this->assertJson($response, "Response should be in JSON format.");

        $decoded = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded, "Response should be an array.");
    }

    public function testPostRequestShouldWorkCorrectly(): void
    {
        $data = ['name' => 'New User'];
        $response = $this->makeRequest('POST', '/users?mock_response=success', $data);

        $this->assertNotFalse($response, "POST /users request failed.");
        $this->assertNotEmpty($response, "POST /users should return a response.");
        $this->assertJson($response, "Response should be in JSON format.");

        $decoded = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded, "Response should be an array.");
        $this->assertArrayHasKey('message', $decoded, "Response should contain 'message' key.");
        $this->assertEquals("User created successfully", $decoded['message'] ?? '', "Message should indicate success.");
        $this->assertArrayHasKey('id', $decoded, "Response should contain 'id' key.");
        $this->assertIsInt($decoded['id'], "ID should be an integer.");
    }

    public function testDeleteRequestShouldWorkCorrectly(): void
    {
        $response = $this->makeRequest('DELETE', '/users');
        $this->assertNotFalse($response, "DELETE /users request failed.");
        $this->assertNotEmpty($response, "DELETE /users should return a response.");
    }

    public function testPutRequestShouldWorkCorrectly(): void
    {
        $response = $this->makeRequest('PUT', '/others/products');
        $this->assertNotFalse($response, "PUT /others/products request failed.");
        $this->assertNotEmpty($response, "PUT /others/products should return a response.");
    }

    public function testInvalidRouteShouldReturnError(): void
    {
        $response = $this->makeRequest('GET', '/invalid_endpoint');
        $this->assertNotFalse($response, "Invalid route request failed.");
        $this->assertJson($response, "Error response should be in JSON format.");

        $decoded = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('error', $decoded, "Response should contain 'error' key.");
    }

    public function testPollingResponsesShouldChangeOverTime(): void
    {
        $response1 = $this->makeRequest('GET', '/users');
        sleep(1);
        $response2 = $this->makeRequest('GET', '/users');

        $this->assertNotFalse($response1, "First polling response request failed.");
        $this->assertNotFalse($response2, "Second polling response request failed.");
        $this->assertNotEmpty($response1, "First polling response should not be empty.");
        $this->assertNotEmpty($response2, "Second polling response should not be empty.");
        $this->assertNotEquals($response1, $response2, "Polling response should change over time.");
    }

    public function testPollingCountResetShouldWork(): void
    {
        $this->makeRequest('POST', '/reset_polling');
        sleep(1);
        $response1 = $this->makeRequest('GET', '/users');
        sleep(1);
        $response2 = $this->makeRequest('GET', '/users');

        $this->assertNotFalse($response1, "First request after reset failed.");
        $this->assertNotFalse($response2, "Second request after reset failed.");
        $this->assertNotEquals($response1, $response2, "Polling should start over after reset.");
    }

    public function testResponseDelayShouldBeRespected(): void
    {
        $startTime = microtime(true);
        $this->makeRequest('GET', '/users?mock_response=delay');
        $endTime = microtime(true);

        $duration = ($endTime - $startTime) * 1000; // Convert to ms
        $this->assertGreaterThan(900, $duration, "Response should be delayed by at least 900ms.");
    }

    public function testRequestAndResponseShouldBeLogged(): void
    {
        $this->makeRequest('GET', '/users');

        $logRequestFile = self::$logPath . '/request.log';
        $logResponseFile = self::$logPath . '/response.log';

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
        $url = self::$baseUrl . $endpoint;

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_COOKIEJAR, self::$cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, self::$cookieFile);

        if ($data !== null) {
            $jsonData = json_encode($data, JSON_THROW_ON_ERROR);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);
        }

        $response = curl_exec($ch);
        if ($response === false) {
            //throw new RuntimeException('cURL Error: ' . curl_error($ch));
            fwrite(STDERR, "cURL Error: " . curl_error($ch) . "\n");
        }

        curl_close($ch);
        return $response;
    }
}
