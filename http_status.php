<?php

/**
 * MockAPI-PHP - HTTP Status Codes Map
 *
 * Returns an associative array mapping HTTP status codes to their corresponding
 * HTTP/1.0 status lines. Used internally to standardize HTTP responses.
 *
 * PHP version 8.3+
 *
 * @author    Katsuhiko Maeno
 * @copyright Copyright (c) 2025 Katsuhiko Maeno
 * @license   MIT License
 * @link      https://github.com/ka215/MockAPI-PHP
 */

return [
    100 => "HTTP/1.0 100 Continue",
    101 => "HTTP/1.0 101 Switching Protocols",
    200 => "HTTP/1.0 200 OK",
    201 => "HTTP/1.0 201 Created",
    204 => "HTTP/1.0 204 No Content",
    301 => "HTTP/1.0 301 Moved Permanently",
    302 => "HTTP/1.0 302 Found",
    400 => "HTTP/1.0 400 Bad Request",
    401 => "HTTP/1.0 401 Unauthorized",
    403 => "HTTP/1.0 403 Forbidden",
    404 => "HTTP/1.0 404 Not Found",
    422 => "HTTP/1.0 422 Unprocessable Entity",
    500 => "HTTP/1.0 500 Internal Server Error",
    503 => "HTTP/1.0 503 Service Unavailable",
];
