<?php

/**
 * MockAPI-PHP - OpenAPI Schema Generator
 *
 * This script generates an OpenAPI schema (JSON/YAML) based on mock response files.
 * It validates the structure of each response, infers types, and exports the result
 * to the `schema/` directory in the specified format.
 *
 * PHP version 8.3+
 *
 * @author    Katsuhiko Maeno
 * @copyright Copyright (c) 2025 Katsuhiko Maeno
 * @license   MIT License
 * @link      https://github.com/ka215/MockAPI-PHP
 */

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
use Dotenv\Dotenv;
use Symfony\Component\Yaml\Yaml;
use Opis\JsonSchema\{
    Validator,
    ValidationResult,
    SchemaLoader,
    Helper,
    Errors\ErrorFormatter,
};

// Load .env file
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

$LOG_DIR = isset($_ENV['LOG_DIR']) ? trim($_ENV['LOG_DIR']) : __DIR__ . '/logs';
$SCHEMA_DIR = isset($_ENV['SCHEMA_DIR']) ? trim($_ENV['SCHEMA_DIR']) : __DIR__ . '/schema';
$SCHEMA_FORMAT = isset($_ENV['SCHEMA_FORMAT']) ? strtolower(trim($_ENV['SCHEMA_FORMAT'])) : 'yaml';
$SCHEMA_TITLE = isset($_ENV['SCHEMA_TITLE']) ? trim($_ENV['SCHEMA_TITLE']) : 'MockAPI-PHP Auto Schema';
$SCHEMA_VERSION = isset($_ENV['SCHEMA_VERSION']) ? trim($_ENV['SCHEMA_VERSION']) : '1.0.0';

// Create log directory if it doesn't exist
if (!is_dir($LOG_DIR)) {
    mkdir($LOG_DIR, 0777, true);
}

// -----------------------------------------------------------------------------

function getOptions(): array
{
    global $SCHEMA_FORMAT, $SCHEMA_TITLE, $SCHEMA_VERSION;

    $defaultOptions = [
        'format' => $SCHEMA_FORMAT,
        'title' => $SCHEMA_TITLE,
        'version' => $SCHEMA_VERSION,
    ];

    if (PHP_SAPI === 'cli') {
        global $argv;
        $format = strtolower($argv[1] ?? $defaultOptions['format']);
        if (!in_array($format, ['json', 'yaml'], true)) {
            echo "Unsupported format: $format. Defaulting to 'yaml'.\n";
            $format = 'yaml';
        }
        return [
            'format' => $format,
            'title' => $argv[2] ?? $defaultOptions['title'],
            'version' => $argv[3] ?? $defaultOptions['version'],
        ];
    } else {
        $format = filter_input(INPUT_GET, 'format', FILTER_SANITIZE_SPECIAL_CHARS) ?: $defaultOptions['format'];
        $title = filter_input(INPUT_GET, 'title', FILTER_SANITIZE_SPECIAL_CHARS) ?: $defaultOptions['title'];
        $version = filter_input(INPUT_GET, 'version', FILTER_SANITIZE_SPECIAL_CHARS) ?: $defaultOptions['version'];

        return [
            'format' => strtolower($format) === 'json' ? 'json' : 'yaml',
            'title' => $title,
            'version' => $version,
        ];
    }
}

function mapTypes(array $types): string
{
    $normalized = array_map(fn($t) => match ($t) {
        'integer' => 'integer',
        'double'  => 'number',
        'string'  => 'string',
        'boolean' => 'boolean',
        default => 'string',
    }, $types);

    $unique = array_values(array_filter(array_unique($normalized), fn($v) => is_string($v)));

    // In case of integer + number, unify to number
    if (in_array('integer', $unique, true) && in_array('number', $unique, true)) {
        $unique = array_values(array_diff($unique, ['integer']));
    }

    if (count($unique) === 1) {
        return $unique[0];
    } elseif (count($unique) === 0) {
        // fallback: nothing found, default to 'string'
        return 'string';
    } else {
        // multiple types found, default to 'string'
        return 'string';
    }
}

function getJsonSchemaType(mixed $value): string
{
    return match (gettype($value)) {
        'integer' => 'integer',
        'double'  => 'number',
        'string'  => 'string',
        'boolean' => 'boolean',
        'NULL'    => 'null',
        default   => 'string',
    };
}

function jsonToSchema(mixed $data): array
{
    $type = gettype($data);

    switch ($type) {
        case 'array':
            // Array: Branches between one-dimensional scalar array and object array
            if (array_keys($data) === range(0, count($data) - 1)) {
                if (count($data) === 0) {
                    return [
                        'type' => 'array',
                        'items' => ['type' => 'string'] // fallback
                    ];
                }

                $firstItem = $data[0];

                if (is_scalar($firstItem)) {
                    // Scalar array:
                    $types = array_map('gettype', $data);
                    return [
                        'type' => 'array',
                        'items' => ['type' => mapTypes($types)]
                    ];
                } elseif (is_array($firstItem)) {
                    // Object array: merge all elements into one
                    $merged = [];
                    foreach ($data as $item) {
                        if (is_array($item)) {
                            $merged = array_merge($merged, $item);
                        }
                    }
                    return [
                        'type' => 'array',
                        'items' => jsonToSchema($merged)
                    ];
                } else {
                    // Others (including null)
                    return [
                        'type' => 'array',
                        'items' => ['type' => getJsonSchemaType($firstItem)]
                    ];
                }
            } else {
                // Associative array: object and interpretations
                return jsonToSchema((object) $data);
            }

        case 'object':
            $properties = [];
            foreach (get_object_vars($data) as $key => $value) {
                $properties[$key] = jsonToSchema($value);
            }
            return [
                'type' => 'object',
                'properties' => $properties
            ];
        case 'boolean':
        case 'integer':
        case 'double':
        case 'string':
            return ['type' => getJsonSchemaType($data)];

        case 'NULL':
            return ['type' => 'null'];

        default:
            return ['type' => 'string']; // fallback
    }
}

function validateJson(array $data): void
{
    global $LOG_DIR;
    $validator = new Validator(new SchemaLoader());
    $validator->setMaxErrors(10);
    $validator->setStopAtFirstError(false);

    $schema = Helper::toJson(jsonToSchema($data));
    $converted = Helper::toJson($data);

    /** @var ValidationResult $result */
    $result = $validator->validate($converted, $schema);
    if (!$result->isValid()) {
        if ($result->hasError()) {
            $error = $result->error();
            $log = "Validation error: {$error->message()}\n";
            $log .= json_encode((new ErrorFormatter())->format($error), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        } else {
            $log = "Validation error: Unknown error\n";
        }
        file_put_contents("$LOG_DIR/validation-error.log", $log);
        echo "Validation error occurred. Please see schema/validation-error.log for details.\n";
        if (PHP_SAPI !== 'cli') {
            http_response_code(500);
        }
        exit(1);
    }
}

function scanResponses(string $baseDir, string $title, string $version): array
{
    $openapi = [
        'openapi' => '3.0.3',
        'info' => [
            'title' => $title,
            'version' => $version
        ],
        'paths' => []
    ];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($baseDir)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'json') {
            continue;
        }

        $relativePath = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
        // Exclusion criteria: Exclude responses/errors below
        if (str_starts_with($relativePath, 'errors' . DIRECTORY_SEPARATOR)) {
            continue;
        }
        $parts = explode(DIRECTORY_SEPARATOR, $relativePath);

        if (count($parts) < 3) {
            continue;
        }

        $responseFile = array_pop($parts);
        $method = array_pop($parts);
        $endpointParts = array_map(fn($p) => '/' . $p, $parts);
        $endpoint = implode('', $endpointParts);
        $statusName = pathinfo($responseFile, PATHINFO_FILENAME) ?: 'default';

        $raw = file_get_contents($file->getPathname());
        $json = json_decode($raw, true);
        if ($json === null) {
            echo "JSON Error: {$file->getPathname()} is invalid.\n";
            if (PHP_SAPI !== 'cli') {
                http_response_code(500);
            }
            exit(1);
        }

        // Validation (immediate interruption on error)
        validateJson($json);

        $schema = jsonToSchema($json);
        $openapi['paths'][$endpoint][strtolower($method)]['responses'][$statusName] = [
            'description' => "Response from $responseFile",
            'content' => [
                'application/json' => [
                    'schema' => $schema,
                    'example' => trimExample($json),
                ]
            ]
        ];
    }

    return $openapi;
}

function trimExample(mixed $data): mixed
{
    if (is_array($data)) {
        // In the case of an array (sequential index): Only the first item
        if (array_keys($data) === range(0, count($data) - 1)) {
            return isset($data[0]) ? [trimExample($data[0])] : [];
        }

        // Recursive processing for associative arrays (object-like)
        $result = [];
        foreach ($data as $key => $value) {
            $result[$key] = trimExample($value);
        }
        return $result;
    } elseif (is_object($data)) {
        // Recursion in stdClass
        $result = new stdClass();
        foreach (get_object_vars($data) as $key => $value) {
            $result->$key = trimExample($value);
        }
        return $result;
    }

    // Scalars are left as is
    return $data;
}

function outputSchema(array $schema, string $format): void
{
    global $SCHEMA_DIR;
    if (!is_dir($SCHEMA_DIR)) {
        mkdir($SCHEMA_DIR, 0777, true);
    }

    if ($format === 'json') {
        $json = json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents("$SCHEMA_DIR/openapi.json", $json);
        if (PHP_SAPI !== 'cli') {
            header('Content-Type: application/json');
            echo $json;
        }
    } else {
        $yaml = Yaml::dump($schema, 10, 2);
        file_put_contents("$SCHEMA_DIR/openapi.yaml", $yaml);
        if (PHP_SAPI !== 'cli') {
            header('Content-Type: text/yaml');
            echo $yaml;
        }
    }

    if (PHP_SAPI === 'cli') {
        echo "The OpenAPI schema has outputted to schema/ in the format specified by $format.\n";
    }
}

// Execution
$options = getOptions();
$responsesDir = __DIR__ . '/responses';
$schema = scanResponses($responsesDir, $options['title'], $options['version']);
outputSchema($schema, $options['format']);
