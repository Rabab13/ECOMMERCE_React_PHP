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
      'https://rococo-puppy-56bad8.netlify.app', // Your Netlify frontend URL
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

// Load App Dependencies
require_once __DIR__ . '/../src/Database/Database.php';
require_once __DIR__ . '/../src/GraphQL/Schema.php';

use App\Database\Database;
use App\GraphQL\Schema;
use GraphQL\GraphQL;

// Get DB connection (Singleton)
$db = Database::getInstance()->getConnection();

// Create GraphQL schema
$schema = Schema::create($db);

// Read GraphQL request
// Read GraphQL request
$rawInput = file_get_contents('php://input');
error_log("Raw Input: " . $rawInput);

if (!$rawInput) {
      error_log("Empty request body received");
      http_response_code(400);
      echo json_encode(['error' => 'Empty request body']);
      exit;
}

// Decode JSON input
$input = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
      error_log("JSON Decode Error: " . json_last_error_msg());
      http_response_code(400);
      echo json_encode(['error' => 'Invalid JSON format']);
      exit;
}

error_log("Parsed Input: " . print_r($input, true));

if (!isset($input['query'])) {
      error_log("Missing query field");
      http_response_code(400);
      echo json_encode(['error' => 'Missing GraphQL query']);
      exit;
}

// Proceed with GraphQL query processing
$graphqlQuery = $input['query'];
error_log("GraphQL Query: " . $graphqlQuery);


$graphqlQuery = $input['query'];
error_log("GraphQL Query: " . $graphqlQuery);

// Validate the query
if (empty($query)) {
      http_response_code(400); // Bad Request
      echo json_encode(['error' => 'No GraphQL query provided']);
      exit;
}



// Execute GraphQL query
$variableValues = $input['variables'] ?? [];
try {
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
