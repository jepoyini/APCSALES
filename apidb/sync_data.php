<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('max_execution_time', 0);
set_time_limit(0);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, Cache-Control, Pragma, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'test ok',
    'method' => $_SERVER['REQUEST_METHOD'],
    'site' => $_GET['site'] ?? ''
]);
exit;

// if (!function_exists('writeline')) {
//     function writeline($text)
//     {
//         echo $text . (php_sapi_name() === 'cli' ? "\n" : "<br>");
//     }
// }

// $requestedSite = isset($_GET['site']) ? strtolower(trim($_GET['site'])) : '';

// $allowedSites = ['mp', 'apc', 'pnp'];

// if ($requestedSite !== '' && !in_array($requestedSite, $allowedSites, true)) {
//     die("Invalid site parameter. Allowed values: mp, apc, pnp");
// }

// $baseDir = __DIR__;

// $syncFiles = [
//     'sync_customers.php',
//     'sync_products.php',
//     'sync_orders.php',
// ];

// writeline("======================================");
// writeline("MASTER SYNC STARTED");
// writeline("Time: " . date('Y-m-d H:i:s'));

// if ($requestedSite !== '') {
//     writeline("Site filter: " . strtoupper($requestedSite));
// } else {
//     writeline("Site filter: ALL");
// }

// writeline("======================================");
// writeline("");

// foreach ($syncFiles as $file) {
//     $fullPath = $baseDir . DIRECTORY_SEPARATOR . $file;

//     if (!file_exists($fullPath)) {
//         writeline("File not found: " . $file);
//         writeline("");
//         continue;
//     }

//     writeline("Running: " . $file);
//     writeline(str_repeat("-", 40));

//     $queryString = '';
//     if ($requestedSite !== '') {
//         $queryString = '?site=' . urlencode($requestedSite);
//     }

//     $url = $fullPath . $queryString;

//     // Make $_GET['site'] available to included file
//     $_GET['site'] = $requestedSite;

//     ob_start();
//     include $fullPath;
//     $output = ob_get_clean();

//     echo $output;

//     writeline(str_repeat("-", 40));
//     writeline("Finished: " . $file);
//     writeline("");
// }

// writeline("======================================");
// writeline("MASTER SYNC FINISHED");
// writeline("Time: " . date('Y-m-d H:i:s'));
// writeline("======================================");