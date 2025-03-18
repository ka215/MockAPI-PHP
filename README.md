# MockAPI-PHP

![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)
![License](https://img.shields.io/badge/license-MIT-green.svg)
![GitHub release](https://img.shields.io/github/v/release/ka215/MockAPI-PHP)
![GitHub issues](https://img.shields.io/github/issues/ka215/MockAPI-PHP)
![GitHub last commit](https://img.shields.io/github/last-commit/ka215/MockAPI-PHP)

This project is a lightweight mock API server built with PHP.  
It allows request simulation without using real APIs in development and testing environments.  
It supports dynamic responses, polling functionality, and authentication control using environment variables (`.env`).

<div align="right"><small>

[To Japanese Readme](./README_JP.md)

</small></div>

---

## Features

- **Automatic Endpoint Registration**
  - Scans the `responses/` folder and automatically registers endpoints based on the directory structure.
  - The API base path can be configured via environment variables.
  - Supports dynamic parameter parsing such as `GET users/{group}/{limit}`.
- **Dynamic Response Loading**
  - `.json` → Returns JSON response.
  - `.txt` → Returns plain text response.
- **Polling Support**
  - If multiple files such as `1.json`, `2.json`, etc., are prepared, responses will change based on request count.
  - Polling is executed per client, and can be reset via `POST: /reset_polling`.
- **Custom Responses**
  - Use query parameter `mock_response` to dynamically switch responses.
    Example: `GET /users?mock_response=success` → Returns `responses/users/get/success.json`.
  - Use `mock_content_type` to specify response `Content-Type`.
    Example: `GET /others?mock_response=xml&mock_content_type=application/xml` → Returns `responses/others/get/xml.txt` as XML format.
- **Error Responses**
  - Define error responses such as `responses/errors/404.json`.
- **Response Delay**
  - Specify `"mockDelay": 1000` (1 second) in a JSON response file to delay the response.
- **Custom Hooks**
  - Register custom hooks for each method + endpoint request to override response content.
    Example: `GET /users` request can be handled by a custom hook `hooks/get_users.php`.
- **Logging**
  - `request.log` stores request details (headers, query, body).
  - `response.log` stores response details.
  - `auth.log` stores authentication failures, and `error.log` stores generic errors.
  - All logs are linked by a request ID for traceability.
- **Configuration via Environment Variables**
  - Uses `vlucas/phpdotenv` to read environment variables.
  - `.env` can define server settings such as `PORT`, base API path, temporary file storage (`cookies.txt`, etc.), and logging paths.
  - Simple authentication via `API_KEY` and `CREDENTIAL`.

## Directory Structure

Below is an example structure of the `responses` directory. You can freely customize it based on your use case.
```
mock_api_server/
 ├── index.php             # Main script for the mock server
 ├── http_status.php       # HTTP status code definitions
 ├── start_server.php      # Local server startup script
 ├── .env                  # Configuration file (.env.sample provides a template)
 ├── vendor/               # Composer packages
 ├── composer.json         # PHP package manager configuration
 ├── composer.lock         # Composer lock file
 ├── responses/            # Directory for storing response data
 │   ├── users/
 │   │   ├── get/
 │   │   │   ├── 1.json        # First request response
 │   │   │   ├── 2.json        # Second request response
 │   │   │   ├── default.json  # Default response
 │   │   │   └── delay.json    # Delayed response
 │   │   ├── delete/
 │   │   │   └── default.json  # DELETE response
 │   │   └── post/
 │   │        ├── 400.json      # 400 error response
 │   │        ├── failed.json   # Failed POST response
 │   │        └── success.json  # Successful POST response
 │   ├── errors/
 │   │   ├── 404.json           # 404 error response (JSON format)
 │   │   └── 500.txt            # 500 error response (text format)
 │   └── others/
 │        ├── products/
 │        │   └── put/
 │        │        └── default.json # PUT response
 │        └── get/
 │             ├── default.txt   # CSV data as text
 │             └── userlist.txt  # XML data as text
 ├── hooks/                # Custom hook scripts
 ├── tests/                # Unit test cases
 │   └── MockApiTest.php   # Initial test cases
 ├── phpunit.xml           # PHPUnit configuration file
 ├── version.json          # Version information file
 └── logs/                 # Directory for log storage
      ├── auth.log         # Authentication error logs
      ├── error.log        # General error logs
      ├── request.log      # Request logs
      └── response.log     # Response logs
```

## Requirements

- PHP **8.2+** (If you do not use phpunit v12.x, PHP **8.1+** is also possible.)
- Composer

## Usage

1. #### Install Dependencies via Composer
    ```bash
    composer install
    ```
2. #### Starting the Mock API Server
    The mock API server can be started using the following methods.
    ##### Recommended: Using `start_server.php`
    This script automatically applies the `PORT` specified in `.env` and clears temporary files.
    ```bash
    php start_server.php
    ```
    ##### Manually Using PHP Built-in Server
    ```bash
    php -S localhost:3030 -t .
    ```
3. #### API Request Examples
    - **GET Request**
      ```bash
      curl -X GET http://localhost:3030/api/users
      ```
    - **Polling Enabled GET Request**
      ```bash
      curl -b temp/cookies.txt -c temp/cookies.txt -X GET http://localhost:3030/api/users
      ```
    - **POST Request**
      ```bash
      curl -X POST http://localhost:3030/api/users -H "Content-Type: application/json" -d '{"name": "New User"}'
      ```
    - **DELETE Request**
      ```bash
      curl -X DELETE http://localhost:3030/api/users/1
      ```
    - **Custom Response Request**
      ```bash
      curl -X GET "http://localhost:3030/api/users?mock_response=success"
      ```
    - **Check Version**
      ```bash
      curl -X GET http://localhost:3030/api/version
      ```
4. #### `responses/` Configuration
    The mock API responses are stored as JSON or text files in the `responses/` directory.

    - **Example Response Structure**
      ```
      responses/
      ├── products/
      │   ├── get/
      │   │   ├── default.json # Default response (used for 3rd to 8th requests and from the 10th request onward)
      │   │   ├── 1.json # Response for the 1st request
      │   │   ├── 2.json # Response for the 2nd request
      │   │   └── 9.json # Response for the 9th request
      │   ├── post/
      │   │   ├── success.json # Response for a successful product creation
      │   │   └── 400.json # Response for a validation error
      │   ├── patch/
      │   │   └── success.json # Response for a successful product update
      │   ├── delete/
      │   │   └── success.json # Response for a successful product deletion
      │   └─…
      └─…
      ```

    - **Error Response Configuration**
      Example: `responses/errors/404.json`
      ```json
      {
        "error": "Resource not found",
        "code": 404
      }
      ```
      Example: `responses/errors/500.txt`
      ```
      Internal Server Error
      ```

## Environment Variables (.env Configuration)

You can customize various settings by configuring environment variables in the `.env` file.  
A template `.env.sample` is included in the package.

```env
PORT=3030             # Port number for the mock API server
BASE_PATH=/api        # Base path for the API (e.g., /api/v1)
LOG_DIR=./logs        # Directory for log output
TEMP_DIR=./temp       # Directory for temporary files (e.g., cookies.txt)
TIMEZONE=             # Timezone for logging (default is UTC)
API_KEY=              # API key for authentication (long-term authentication for the application)
CREDENTIAL=           # Credential (temporary token for individual user authentication)
```

Note: The `API_KEY` and `CREDENTIAL` options are implemented as a simple authentication mechanism.
If specified, the server will extract the Bearer token from the Authorization header and perform authentication.

## Tips

### Custom Responses
You can dynamically change the response by specifying the `mock_response` query parameter.

| Request | Response File Retrieved |
|---------|-------------------------|
| `GET /users` | `responses/users/get/default.json` |
| `GET /users?mock_response=success` | `responses/users/get/success.json` |
| `POST /users?mock_response=failed` | `responses/users/post/failed.json` |
| `POST /users?mock_response=400` | `responses/users/post/400.json` |

### Response Delay
You can set a delay in the response by specifying `mockDelay` in the JSON file.

Example: `responses/users/get/default.json`
```json
{
    "id": 1,
    "name": "John Doe",
    "mockDelay": 1000
}
```
→ The response will be returned after 1 second.

### Handling Query Parameters
- All query parameters are retrieved and included in the request data (`request_data['query_params']`).
- `mock_response` and `mock_content_type` are handled internally and will not be included in the request data.
- Example: For `GET /users?filter=name&sort=asc`, the request data will be:
```json
{
  "query_params": {
    "filter": "name",
    "sort": "asc"
  },
  "body": {}
}
```

### Custom `Content-Type` Setting
By default, responses are returned as `application/json` or `text/plain`, but you can specify any `Content-Type`.

#### Returning a CSV File
Register the response as `responses/others/get/default.txt` (content example below):
```csv
id,name,email
1,John Doe,john@example.com
2,Jane Doe,jane@example.com
```
Making a request with `GET others?mock_content_type=text/csv` will return `others.csv` (downloadable if accessed via a browser).

#### Returning an XML File
Register the response as `responses/others/get/userlist.txt` (content example below):
```xml
<users>
    <user>
        <id>1</id>
        <name>John Doe</name>
    </user>
    <user>
        <id>2</id>
        <name>Jane Doe</name>
    </user>
</users>
```
Making a request with `GET others?mock_response=userlist&mock_content_type=application/xml` will return XML-formatted data.

### Custom Hooks
This feature allows you to override the predefined response for a specific method + endpoint by using custom hooks. By placing a PHP file in `hooks/{METHOD}_{SNAKE_CASE_ENDPOINT}.php`, you can enable a custom hook.

Example: Custom hook for the `GET users` endpoint `hooks/get_users.php`
```php
<?php

// Example: Hook for GET /users
if (isset($request_data['query_params'])) {
    $filter = $request_data['query_params']['filter'] ?? null;
    // If the `filter` query parameter is specified
    if ($filter) {
        $sort = strtolower($request_data['query_params']['sort']) === 'desc' ? 'desc' : 'asc';
        header('Content-Type: application/json');
        echo json_encode([
            'data' => [
                [
                    'id' => 1,
                    'name' => 'Alice',
                    'age' => 24,
                ],
                [
                    'id' => 2,
                    'name' => 'Bob',
                    'age' => 27,
                ],
            ],
        ]);
        // Terminate script after returning response
        exit;
    }
}
```
For a request like `GET users?filter=name`, the following response will be returned:
```json
{
  "data": [
    {
      "id": 1,
      "name": "Alice",
      "age": 24
    },
    {
      "id": 2,
      "name": "Bob",
      "age": 27
    }
  ]
}
```

### Dynamic Parameters
For requests with dynamic parameters such as `GET users/{group}/{limit}`, parameters are extracted as request parameters if the response root exists at `responses/users/get`. You can use custom hooks to control responses using these extracted parameters.

Example: Custom hook for the `GET users` endpoint `hooks/get_users.php`
```php
<?php

// Example: Hook for dynamic parameters in `GET /users/{group}/{limit}`
$pattern = '/^dynamicParam\d+$/';
$matchingKeys = preg_grep($pattern, array_keys($request_data));
if (!empty($matchingKeys)) {
    // Extract dynamic parameters
    $filteredArray = array_filter($request_data, function ($key) use ($pattern) {
        return preg_match($pattern, $key);
    }, ARRAY_FILTER_USE_KEY);
    extract($filteredArray);
    $group = isset($dynamicParam1) ? $dynamicParam1 : 'default';
    $limit = isset($dynamicParam2) ? (int) $dynamicParam2 : 0;

    header('Content-Type: application/json');
    $response = [
        'group' => $group,
        'limit' => $limit,
        'users' => [],
    ];
    for ($i = 1; $i <= $limit; $i++) {
        $response['users'][] = [
            'id' => $i,
            'name' => "User {$i} (Group: {$group})",
        ];
    }
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}
```
For a request like GET `users/groupA/3`, the following response will be returned:
```json
{
    "group": "groupA",
    "limit": 3,
    "users": [
        {
            "id": 1,
            "name": "User 1 (Group: groupA)"
        },
        {
            "id": 2,
            "name": "User 2 (Group: groupA)"
        },
        {
            "id": 3,
            "name": "User 3 (Group: groupA)"
        }
    ]
}
```

## Unit Tests

Basic functionality is covered by unit tests.  
Additional test cases can be added in `tests/MockApiTest.php`.

**Run Tests:**
```bash
php vendor/bin/phpunit
```

## License

This project is released under the [MIT License](LICENSE).

## Author

- **Name**: Katsuhiko Maeno
- **GitHub**: [github.com/ka215](https://github.com/ka215)
