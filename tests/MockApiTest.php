<?php

/**
 * MockAPI-PHP - PHPUnit Test Suite
 *
 * This test suite verifies the behavior of the mock API endpoints, including:
 *  - Basic HTTP methods (GET, POST, PUT, DELETE)
 *  - Query parameter handling
 *  - Polling response changes
 *  - Logging functionality
 *  - OpenAPI schema generation in JSON/YAML
 *
 * PHP version 8.3+
 *
 * @author    Katsuhiko Maeno
 * @copyright Copyright (c) 2025 Katsuhiko Maeno
 * @license   MIT License
 * @link      https://github.com/ka215/MockAPI-PHP
 */

declare(strict_types=1);

namespace Tests;

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
        $logRequestFile = self::$logPath . '/request.log';
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

    public function testOpenApiSchemaGenerationJsonShouldWork(): void
    {
        $scriptPath = realpath(__DIR__ . '/../generate-schema.php');
        $schemaPath = __DIR__ . '/../schema/openapi.json';
        $expectedExample = [
            'id' => 3,
            'name' => 'Mike Born',
            'email' => 'mike@example.com',
        ];

        $this->assertNotFalse($scriptPath, 'Invalid path for generate-schema.php.');

        // スキーマ出力ファイルがあれば一旦削除
        if (file_exists($schemaPath)) {
            unlink($schemaPath);
        }

        // Execution format: CLI (format: json)
        $cmd = escapeshellcmd("php {$scriptPath} json");
        $output = shell_exec($cmd);
        $this->assertFileExists($schemaPath, "Schema files are not generated: {$schemaPath}");

        $schema = json_decode(file_get_contents($schemaPath), true, 512, JSON_THROW_ON_ERROR);

        // Check the basic structure of your OpenAPI schema
        $this->assertArrayHasKey('paths', $schema, "The JSON schema does not contain paths.");
        $this->assertArrayHasKey('/users', $schema['paths'], "The JSON schema does not contain /users path.");
        $this->assertArrayHasKey('get', $schema['paths']['/users'], "The JSON schema does not contain GET method.");

        $response = $schema['paths']['/users']['get']['responses']['default'] ?? null;
        $this->assertNotNull($response, "No Response as HTTP 200 code.");

        $example = $schema['paths']['/users']['get']['responses']['default']['content']['application/json']['example']
            ?? null;
        $this->assertNotNull($example, "The example field is not included.");

        // Verify that the contents of default.json match
        $this->assertEquals($expectedExample, $example, "The content of example does not match default.json.");
    }

    public function testOpenApiSchemaGenerationYamlShouldWork(): void
    {
        $scriptPath = realpath(__DIR__ . '/../generate-schema.php');
        $yamlPath = __DIR__ . '/../schema' . '/openapi.yaml';
        $expectedExample = [
            'id' => 3,
            'name' => 'Mike Born',
            'email' => 'mike@example.com',
        ];

        $this->assertNotFalse($scriptPath, 'Invalid path for generate-schema.php.');

        if (file_exists($yamlPath)) {
            unlink($yamlPath);
        }

        // Generate a schema in yaml format
        $cmd = escapeshellcmd("php {$scriptPath} yaml");
        $output = shell_exec($cmd);
        $this->assertFileExists($yamlPath, "YAML schema file was not generated: {$yamlPath}");

        // Convert yaml to array and validate
        $yaml = file_get_contents($yamlPath);
        $this->assertNotFalse($yaml, "Failed to read openapi.yaml.");

        if (!class_exists(\Symfony\Component\Yaml\Yaml::class)) {
            $this->markTestSkipped('symfony/yaml is not installed.');
        }

        $schema = \Symfony\Component\Yaml\Yaml::parse($yaml);

        // Check the basic structure of your OpenAPI schema
        $this->assertArrayHasKey('paths', $schema, "The yaml schema does not contain paths.");
        $this->assertArrayHasKey('/users', $schema['paths'], "The yaml schema does not contain /users path.");
        $this->assertArrayHasKey('get', $schema['paths']['/users'], "The yaml schema does not contain GET method.");

        $example = $schema['paths']['/users']['get']['responses']['default']['content']['application/json']['example']
            ?? null;
        $this->assertNotNull($example, "The example field is not included.");

        // Verify that the contents of default.json match
        $this->assertEquals($expectedExample, $example, "The content of example does not match default.json.");
    }

    /**
     * @param array<string, mixed> $data
     */
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
