# GitHub Copilot Instructions for MockAPI-PHP

## Project Context

You are working on **MockAPI-PHP**, a lightweight mock API server for PHP developers. This project enables rapid prototyping and testing by simulating RESTful API responses using file-based configurations.

### Key Information
- **Language**: PHP 8.3+
- **Architecture**: File-based routing with automatic endpoint registration
- **License**: MIT
- **Current Version**: 1.3.1

## Core Components

### 1. Main Entry Point (`index.php`)
- Handles all HTTP requests through a single entry point
- Implements routing, authorization, logging, and response delivery
- Uses session-based polling per client
- Supports dynamic routes with parameter extraction

### 2. Response Management
- Responses stored in `responses/` directory matching endpoint structure
- Supports both `.json` and `.txt` file formats
- Priority order: custom response → polling response → default response
- Special `mockDelay` property in JSON for simulating latency

### 3. Custom Hooks (`hooks/`)
- Naming convention: `{method}_{endpoint}.php` (e.g., `get_users.php`)
- Can override default response behavior
- Has access to `$request_data` and `$response_file` variables
- Use helper functions from `libs/utils.php`

### 4. OpenAPI Schema Generation (`generate-schema.php`)
- Auto-generates OpenAPI 3.0 specs from response files
- Supports both YAML and JSON output formats
- Validates schemas using `opis/json-schema`
- Outputs to `schema/` directory

## Coding Standards

### Style Guide
- **Standard**: PSR-12
- **Static Analysis**: PHPStan level 6
- **Naming Conventions**:
  - Functions: `camelCase`
  - Variables: `$snake_case` (uppercase for globals)
  - Files: `kebab-case.php`

### Code Quality Tools
```bash
# Run tests
./vendor/bin/phpunit

# Static analysis
./vendor/bin/phpstan analyse

# Code style check
./vendor/bin/phpcs
```

## Common Patterns

### Adding a New Endpoint

When creating a new endpoint, follow this pattern:

1. **Create directory structure**:
   ```
   responses/{endpoint}/{method}/
   ```

2. **Add response files**:
   ```php
   // responses/users/get/default.json
   {
     "success": true,
     "data": []
   }
   ```

3. **Optional: Add custom hook**:
   ```php
   // hooks/get_users.php
   $response_data = json_decode(file_get_contents($response_file), true);
   // Modify $response_data as needed
   returnResponse($response_data);
   ```

### Creating Helper Functions

Add reusable utilities to `libs/utils.php`:

```php
if (!function_exists('myHelperFunction')) {
    function myHelperFunction(mixed $param): mixed {
        // Implementation
    }
}
```

### Logging Best Practices

Use existing logging functions:

```php
logRequest($request_id, $method, $path, $client_id, $request_data);
logResponse($request_id, $response_content);
logError($request_id, "Error message");
logAuthFailure($request_id, "Auth error message");
```

All logs use JSON format and are linked by `request_id`.

### Custom Hook Template

```php
<?php
// hooks/{method}_{endpoint}.php

// Access request data
$query_params = $request_data['query_params'] ?? [];
$body_params = $request_data['body'] ?? [];
$dynamic_params = array_filter($request_data, fn($k) => str_starts_with($k, 'dynamicParam'), ARRAY_FILTER_USE_KEY);

// Load default response
$response_data = json_decode(file_get_contents($response_file), true);

// Modify response
$response_data['custom_field'] = 'custom_value';

// Return response
returnResponse($response_data, 200);
```

## Environment Configuration

Key environment variables in `.env`:

```bash
PORT=3030                    # Server port
BASE_PATH=/api               # API base path
LOG_DIR=./logs               # Log directory
TEMP_DIR=./temp              # Temporary files directory
TIMEZONE=Asia/Tokyo          # Timezone setting

# CORS Configuration
CORS_ALLOW_ORIGIN=*
CORS_ALLOW_METHODS=GET, POST, DELETE, PATCH, PUT, OPTIONS
CORS_ALLOW_HEADERS=Origin, Content-Type, Accept

# Authentication (optional)
API_KEY=your_api_key_here
CREDENTIAL=your_credential_here

# OpenAPI Schema Generation
SCHEMA_DIR=./schema
SCHEMA_FORMAT=yaml
SCHEMA_TITLE=My API
SCHEMA_VERSION=1.0.0
```

## Testing Guidelines

### Writing Unit Tests

Place tests in `tests/` directory:

```php
<?php
use PHPUnit\Framework\TestCase;

class MyFeatureTest extends TestCase
{
    public function testMyFeature(): void
    {
        // Arrange
        $expected = 'expected_value';
        
        // Act
        $result = myFunction();
        
        // Assert
        $this->assertEquals($expected, $result);
    }
}
```

### Test Coverage Areas
- Response file detection logic
- Dynamic route parsing
- Custom hook execution
- OpenAPI schema generation
- Authorization logic

## Security Considerations

1. **Input Sanitization**: Always use `filter_input_array()` for query parameters
2. **API Key Management**: Store secrets in `.env`, never in code
3. **File Access**: Restrict access to `responses/` directory only
4. **Logging**: Avoid logging sensitive data (passwords, tokens)

## Special Request Parameters

- `mock_response`: Specify custom response file (e.g., `?mock_response=success`)
- `mock_content_type`: Override Content-Type header (e.g., `?mock_content_type=application/xml`)

## Troubleshooting Tips

### Common Issues

1. **404 Not Found**:
   - Check `responses/` directory structure matches endpoint path
   - Verify `BASE_PATH` environment variable

2. **Authentication Errors**:
   - Review `logs/auth.log`
   - Verify `Authorization` header format

3. **Schema Generation Errors**:
   - Check `logs/validation-error.log`
   - Validate JSON syntax in response files

### Debug Mode

Enable verbose logging by checking log files:

```bash
tail -f logs/request.log | jq .
tail -f logs/error.log | jq .
```

## Dependencies

### Production
- `vlucas/phpdotenv`: Environment variable management
- `symfony/yaml`: YAML parsing for schema generation
- `opis/json-schema`: JSON schema validation

### Development
- `phpunit/phpunit`: Unit testing framework
- `phpstan/phpstan`: Static analysis tool
- `squizlabs/php_codesniffer`: Code style checker

## Best Practices

1. **Always validate input** using PHP's filter functions
2. **Use type hints** for function parameters and return types
3. **Document complex logic** with inline comments
4. **Keep functions small** and focused on single responsibility
5. **Test edge cases** especially for dynamic routing
6. **Follow PSR-12** coding standards
7. **Use existing helper functions** from `libs/utils.php` before creating new ones
8. **Log important events** using appropriate log functions

## File Structure Conventions

```
responses/
  ├── {endpoint}/              # Endpoint name (e.g., users, products)
  │   ├── get/                 # HTTP method directory
  │   │   ├── default.json     # Default response
  │   │   ├── 1.json           # First polling response
  │   │   ├── 2.json           # Second polling response
  │   │   └── custom.json      # Custom response (via ?mock_response=custom)
  │   ├── post/
  │   ├── put/
  │   ├── patch/
  │   └── delete/
  └── errors/                  # Error responses
      ├── 404.json
      ├── 500.json
      └── 401.json
```

## When Making Changes

1. **Before modifying core files**: Run existing tests
2. **After changes**: Run PHPStan and PHPCS
3. **For new features**: Add corresponding tests
4. **Update documentation**: Modify README if API changes
5. **Version bump**: Update `version.json` and `CHANGELOG.md`

## Useful Commands

```bash
# Start development server
php start_server.php

# Generate OpenAPI schema
php generate-schema.php yaml "API Title" "1.0.0"

# Run all quality checks
./vendor/bin/phpunit && ./vendor/bin/phpstan analyse && ./vendor/bin/phpcs

# Reset polling for testing
curl -X POST http://localhost:3030/api/reset_polling

# Check API version
curl http://localhost:3030/api/version
```

## Reference Documentation

- [Developer Summary](../DEVELOPER_SUMMARY.md)
- [README (Japanese)](../README_JP.md)
- [README (English)](../README.md)
- [Changelog](../CHANGELOG.md)
