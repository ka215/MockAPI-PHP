<?php

if (!function_exists('getRequestParam')) {
    function getRequestParam(string $keyName, array $requestBody = []): mixed {
        if (!isset($requestBody) || empty($requestBody) || !array_key_exists($keyName, $requestBody)) {
            return false;
        }
        return $requestBody[$keyName];
    }
}

if (!function_exists('extractRouteParams')) {
    /**
     * @usage:
     * ```
     * $params = extractRouteParams('/foo/:id/bar/:name', '/foo/123/bar/abc');
     * // result: ['id' => '123', 'name' => 'abc']
     * ```
     */
    function extractRouteParams(string $routePattern, string $requestPath): array {
        // :param名 を (?P<param名>[^/]+) へ置換
        $regex = preg_replace('#:([\w]+)#', '(?P<$1>[^/]+)', $routePattern);
        $regex = '#^' . $regex . '$#';
        if (preg_match($regex, $requestPath, $matches)) {
            // 連想配列だけ抜き出し
            return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
        }
        return [];
    }
}

if (!function_exists('returnResponse')) {
    /**
     * Output the JSON response and exit
     */
    function returnResponse(array $response, int $code = 200): void {
        header('Content-Type: application/json');
        if ($code !== 200) {
            http_response_code($code);
        }
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit;
    }
}

if (!function_exists('returnError')) {
    /**
     * Generate error data and respond
     */
    function returnError(string $message, int $code = 200): void {
        returnResponse([
            'success' => false,
            'message' => $message,
        ], $code);
    }
}
