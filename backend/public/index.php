<?php

use Dotenv\Dotenv;

// Autoload dependencies first
require_once __DIR__ . '/../vendor/autoload.php';

// Load .env config
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Handle CORS
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed_origins = [
      'https://rococo-puppy-56bad8.netlify.app', // Netlify frontend URL
      'http://localhost:5173',                  // Local dev server
      'https://*.netlify.app',                  // Allow all Netlify preview URLs
];

// Allow any *.netlify.app domain dynamically
$allow_netlify = preg_match('/^https:\/\/[a-z0-9\-]+\.netlify\.app$/', $origin);

// Allow requests with no origin (e.g., from the same origin or non-browser clients)
if (empty($origin) || in_array($origin, $allowed_origins, true) || $allow_netlify) {
      header("Access-Control-Allow-Origin: " . (empty($origin) ? '*' : $origin));
      header("Access-Control-Allow-Credentials: true");
} else {
      error_log("Disallowed Origin: $origin");
      http_response_code(403); // Forbidden
      exit;
}

// Required headers
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
      http_response_code(200);
      exit();
}

// Handle GET requests with helpful message
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
      header('Content-Type: application/json');
      echo json_encode([
            'message' => 'Welcome to the GraphQL API',
            'instructions' => [
                  'This endpoint requires POST requests with JSON payload',
                  'Example:',
                  [
                        'method' => 'POST',
                        'headers' => ['Content-Type' => 'application/json'],
                        'body' => [
                              'query' => '{ yourQuery { field1 field2 } }',
                              'variables' => '{}'
                        ]
                  ],
                  'For Netlify integration, ensure your frontend is configured to send POST requests',
                  'Allowed origins: ' . implode(', ', $allowed_origins)
            ]
      ]);
      exit;
}

// Load App Dependencies
require_once __DIR__ . '/../src/Database/Database.php';
require_once __DIR__ . '/../src/GraphQL/Schema.php';

use App\Database\Database;
use App\GraphQL\Schema;
use GraphQL\GraphQL;

// Get DB connection (Singleton)
try {
      $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
      http_response_code(500);
      echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
      exit;
}

// Read GraphQL request
$rawInput = file_get_contents('php://input');
error_log("Raw Input: " . $rawInput);

// Validate we have input
if (empty($rawInput)) {
      http_response_code(400);
      echo json_encode([
            'error' => 'Empty request body',
            'solution' => 'Send a POST request with a JSON payload containing your GraphQL query'
      ]);
      exit;
}

$input = json_decode($rawInput, true);

// Validate JSON
if (json_last_error() !== JSON_ERROR_NONE) {
      http_response_code(400);
      echo json_encode([
            'error' => 'Invalid JSON format',
            'solution' => 'Ensure your request body contains valid JSON'
      ]);
      exit;
}

$query = $input['query'] ?? null;

// Validate the query
if (empty($query)) {
      http_response_code(400);
      echo json_encode([
            'error' => 'No GraphQL query provided',
            'solution' => 'Include a "query" field in your JSON payload',
            'example' => [
                  'query' => '{ products { id name } }',
                  'variables' => '{}'
            ]
      ]);
      exit;
}

// Execute GraphQL query
$variableValues = $input['variables'] ?? [];
try {
      $schema = Schema::create($db);
      $result = GraphQL::executeQuery($schema, $query, null, null, $variableValues);
      $output = $result->toArray();
} catch (\Exception $e) {
      $output = [
            'errors' => [
                  [
                        'message' => $e->getMessage(),
                        'extensions' => [
                              'file' => $e->getFile(),
                              'line' => $e->getLine(),
                        ],
                  ],
            ],
      ];
}

// Output JSON response
header('Content-Type: application/json');
echo json_encode($output);

// Close DB connection (optional)
$db = null;
