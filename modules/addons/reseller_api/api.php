<?php
/**
 * REST API Entry Point
 */

define('WHMCS_API_EXEC', true);
require_once dirname(__DIR__, 3) . '/init.php';
require_once __DIR__ . '/lib/Security/SecurityPipeline.php';
require_once __DIR__ . '/lib/Http/Router.php';

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\ResellerApi\Security\SecurityPipeline;
use WHMCS\Module\Addon\ResellerApi\Security\ApiError;
use WHMCS\Module\Addon\ResellerApi\Security\IdempotentResponseException;
use WHMCS\Module\Addon\ResellerApi\Http\Router;

header('Content-Type: application/json; charset=utf-8');

try {
    // 1. Gather Settings
    $rawSettings = Capsule::table('mod_rapi_settings')->get();
    $settings = [];
    foreach ($rawSettings as $s) {
        $settings[$s->name] = $s->value;
    }

    // 2. Build Request Object
    $route = $_GET['route'] ?? '';
    // Strip trailing slashes
    $route = rtrim($route, '/');
    $path = '/reseller-api/v1' . ($route ? '/' . $route : '');

    $headers = [];
    foreach ($_SERVER as $k => $v) {
        if (strpos($k, 'HTTP_') === 0) {
            $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($k, 5)))));
            $headers[$name] = $v;
        }
    }

    $query = $_SERVER['QUERY_STRING'] ?? '';
    $query = preg_replace('/^route=[^&]*&?/', '', $query); // Remove our rewrite param

    $rawBody = file_get_contents('php://input');

    $request = [
        'method' => $_SERVER['REQUEST_METHOD'],
        'path' => $path,
        'query' => $query,
        'headers' => $headers,
        'raw_body' => $rawBody,
        'parsed_body' => $rawBody ? json_decode($rawBody, true) : [],
        'id' => 'req_' . strtoupper(substr(uniqid(), 0, 16)) // Simple ULID-like
    ];

    if ($rawBody && json_last_error() !== JSON_ERROR_NONE) {
        throw new ApiError(400, 'BAD_REQUEST', 'Malformed JSON');
    }

    // 3. Setup Router
    $router = new Router();
    $routeInfo = $router->match($request['method'], $route);

    if (!$routeInfo) {
        throw new ApiError(404, 'NOT_FOUND', 'Endpoint not found');
    }

    $request['required_scope'] = $routeInfo['scope'];
    $request['params'] = $routeInfo['params'];

    // 4. Run Security Pipeline
    $pipeline = new SecurityPipeline($request, $settings);
    $key = $pipeline->run();

    // 5. Execute Controller
    $controllerClass = $routeInfo['controller'];
    $method = $routeInfo['method'];

    require_once __DIR__ . '/lib/Controllers/' . $controllerClass . '.php';
    $fullClass = '\\WHMCS\\Module\\Addon\\ResellerApi\\Controllers\\' . $controllerClass;
    $controller = new $fullClass($request, $key, $settings);

    $response = $controller->$method();

    // Format Success
    $output = [
        'success' => true,
        'data' => $response['data'] ?? [],
        'meta' => [
            'request_id' => $request['id'],
            'api_version' => '1.0',
            'timestamp' => time()
        ]
    ];

    http_response_code($response['status'] ?? 200);
    $responseJson = json_encode($output);
    echo $responseJson;

    // Log and finalize Idempotency
    logRequest($request, clone $key, $response['status'] ?? 200, null, $responseJson);
    finalizeIdempotency($request, $key, clone $response['status'] ?? 200, $responseJson);

} catch (IdempotentResponseException $e) {
    http_response_code($e->statusCode);
    echo $e->body;
    // Do not log idempotency hits to avoid clutter, or log as a distinct action.
} catch (ApiError $e) {
    $output = [
        'success' => false,
        'error' => [
            'code' => $e->errorCode,
            'message' => $e->getMessage()
        ],
        'meta' => [
            'request_id' => $request['id'] ?? 'unknown',
            'api_version' => '1.0',
            'timestamp' => time()
        ]
    ];
    http_response_code($e->statusCode);
    $responseJson = json_encode($output);
    echo $responseJson;

    logRequest($request ?? [], $key ?? null, $e->statusCode, $e->errorCode, $responseJson);
    if (isset($request['idem_key']) && isset($key)) {
        // Clear in-progress idempotency
        Capsule::table('mod_rapi_idempotency')
            ->where('key_id', $key->id)
            ->where('idem_key', $request['idem_key'])
            ->delete();
    }
} catch (\Exception $e) {
    $output = [
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => 'An unexpected error occurred. Quote request_id to support.'
        ],
        'meta' => [
            'request_id' => $request['id'] ?? 'unknown',
            'api_version' => '1.0',
            'timestamp' => time()
        ]
    ];
    http_response_code(500);
    $responseJson = json_encode($output);
    echo $responseJson;

    logRequest($request ?? [], $key ?? null, 500, 'INTERNAL_ERROR', $responseJson);
}

function logRequest($request, $key, $status, $errorCode, $responseBody) {
    if (empty($request['id'])) return;

    Capsule::table('mod_rapi_request_log')->insert([
        'request_id' => $request['id'],
        'reseller_id' => $key ? $key->reseller_id : null,
        'key_id' => $key ? $key->id : null,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        'method' => $request['method'] ?? 'GET',
        'path' => $request['path'] ?? '/',
        'status' => $status,
        'error_code' => $errorCode,
        'request_body' => (isset($request['raw_body']) && $request['raw_body']) ? redact($request['raw_body']) : null,
        'response_body' => redact($responseBody),
        'latency_ms' => round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000),
        'created_at' => date('Y-m-d H:i:s')
    ]);
}

function finalizeIdempotency($request, $key, $status, $responseBody) {
    if (!isset($request['idem_key']) || !$key) return;

    Capsule::table('mod_rapi_idempotency')
        ->where('key_id', $key->id)
        ->where('idem_key', $request['idem_key'])
        ->update([
            'status' => 'done',
            'response_code' => $status,
            'response_body' => $responseBody
        ]);
}

function redact($json) {
    // Simple redaction logic
    $data = json_decode($json, true);
    if (!$data) return $json;

    array_walk_recursive($data, function(&$value, $key) {
        if (in_array(strtolower($key), ['password', 'secret', 'root_password', 'rescue_password'])) {
            $value = '***REDACTED***';
        }
    });

    return json_encode($data);
}
