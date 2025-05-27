<?php
/**
 * Debug script for HTA client connection issues
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Connection Debug for HTA Client</h1>";

// Test the API endpoint that the HTA client uses
echo "<h2>Testing API Endpoints</h2>";

// Test 1: Basic API connectivity
echo "<h3>Test 1: Basic API Response</h3>";
try {
    $testData = [
        'action' => 'register_host',
        'host_id' => 'test_host_' . time(),
        'hostname' => 'TestHost',
        'ip_address' => '127.0.0.1',
        'os_info' => 'Test OS',
        'internal_call' => '1' // Bypass auth for testing
    ];
    
    // Simulate what the HTA client does
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost/GhostCrew/api.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($testData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "<p><strong>HTTP Status:</strong> $httpCode</p>";
    echo "<p><strong>Response:</strong></p>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
    
    if ($error) {
        echo "<p><strong>cURL Error:</strong> $error</p>";
    }
    
    // Try to parse JSON
    $jsonData = json_decode($response, true);
    if ($jsonData === null) {
        echo "<p>❌ <strong>JSON Parse Error:</strong> Response is not valid JSON</p>";
        echo "<p>This is likely what's causing the 'Error parsing server response' message</p>";
        
        // Check if response looks like HTML (common error)
        if (strpos($response, '<html>') !== false || strpos($response, '<!DOCTYPE') !== false) {
            echo "<p>⚠️ <strong>Response appears to be HTML instead of JSON</strong></p>";
            echo "<p>This usually means PHP errors or redirects are happening</p>";
        }
    } else {
        echo "<p>✅ <strong>JSON Parse Success:</strong> Response is valid JSON</p>";
        echo "<p>Status: " . ($jsonData['status'] ?? 'unknown') . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ <strong>Exception:</strong> " . $e->getMessage() . "</p>";
}

// Test 2: Direct API file access
echo "<h3>Test 2: Direct API File Test</h3>";
echo "<p>Testing if api.php can be accessed directly...</p>";

// Check if api.php exists and is readable
if (file_exists('api.php')) {
    echo "<p>✅ api.php file exists</p>";
    
    // Test direct inclusion (simulate what happens when accessed)
    ob_start();
    
    // Set up test environment
    $_POST['action'] = 'register_host';
    $_POST['host_id'] = 'test_direct_' . time();
    $_POST['hostname'] = 'DirectTest';
    $_POST['ip_address'] = '127.0.0.1';
    $_POST['os_info'] = 'Direct Test OS';
    $_POST['internal_call'] = '1'; // Bypass auth
    
    try {
        include 'api.php';
        $output = ob_get_contents();
        ob_end_clean();
        
        echo "<p><strong>Direct Include Output:</strong></p>";
        echo "<pre>" . htmlspecialchars($output) . "</pre>";
        
        $jsonTest = json_decode($output, true);
        if ($jsonTest === null) {
            echo "<p>❌ Direct include also returns invalid JSON</p>";
        } else {
            echo "<p>✅ Direct include returns valid JSON</p>";
        }
        
    } catch (Exception $e) {
        ob_end_clean();
        echo "<p>❌ Error during direct include: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>❌ api.php file not found</p>";
}

// Test 3: Check for common issues
echo "<h3>Test 3: Common Issue Checks</h3>";

// Check if authentication is interfering
echo "<p><strong>Authentication Check:</strong></p>";
session_start();
if (isset($_SESSION['user_id'])) {
    echo "<p>Current user logged in: " . ($_SESSION['username'] ?? 'unknown') . "</p>";
    echo "<p>This is good - authentication won't interfere with API calls</p>";
} else {
    echo "<p>No user session found</p>";
}

// Check error logs
echo "<p><strong>PHP Error Check:</strong></p>";
$lastError = error_get_last();
if ($lastError) {
    echo "<p>Last PHP error: " . $lastError['message'] . " in " . $lastError['file'] . " on line " . $lastError['line'] . "</p>";
} else {
    echo "<p>No recent PHP errors</p>";
}

// Check headers
echo "<p><strong>Response Headers Check:</strong></p>";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/GhostCrew/api.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, 'action=register_host&internal_call=1');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, false);

$response = curl_exec($ch);
curl_close($ch);

$headerSize = strpos($response, "\r\n\r\n");
if ($headerSize !== false) {
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize + 4);
    
    echo "<p><strong>Response Headers:</strong></p>";
    echo "<pre>" . htmlspecialchars($headers) . "</pre>";
    
    echo "<p><strong>Response Body:</strong></p>";
    echo "<pre>" . htmlspecialchars($body) . "</pre>";
    
    // Check content type
    if (strpos($headers, 'Content-Type: application/json') !== false) {
        echo "<p>✅ Correct JSON content type header</p>";
    } else {
        echo "<p>❌ Missing or incorrect content type header</p>";
    }
}

// Test 4: Create a simple test endpoint
echo "<h3>Test 4: Simple Test Endpoint</h3>";
echo "<p>Creating a minimal test to isolate the issue...</p>";

file_put_contents('test_api.php', '<?php
header("Content-Type: application/json");
echo json_encode([
    "status" => "success", 
    "message" => "Test endpoint working",
    "timestamp" => time(),
    "post_data" => $_POST
]);
?>');

echo "<p>Created test_api.php - testing it now...</p>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/GhostCrew/test_api.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, 'test=1&action=register_host');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$testResponse = curl_exec($ch);
$testHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<p><strong>Test API Response:</strong></p>";
echo "<p>HTTP Code: $testHttpCode</p>";
echo "<pre>" . htmlspecialchars($testResponse) . "</pre>";

$testJson = json_decode($testResponse, true);
if ($testJson === null) {
    echo "<p>❌ Even simple test API returns invalid JSON</p>";
    echo "<p>This suggests a server configuration issue</p>";
} else {
    echo "<p>✅ Simple test API works - issue is with main api.php</p>";
}

echo "<h2>Diagnosis & Solutions</h2>";

if ($testJson !== null && json_decode($response, true) === null) {
    echo "<p><strong>DIAGNOSIS:</strong> The issue is specifically with api.php, not the server setup.</p>";
    echo "<p><strong>LIKELY CAUSES:</strong></p>";
    echo "<ul>";
    echo "<li>PHP errors or warnings being output before JSON</li>";
    echo "<li>Authentication redirects interfering with API</li>";
    echo "<li>Missing dependencies or includes</li>";
    echo "<li>Session errors</li>";
    echo "</ul>";
} else {
    echo "<p><strong>DIAGNOSIS:</strong> Server configuration issue preventing proper JSON responses.</p>";
}

echo "<p><strong>RECOMMENDED FIXES:</strong></p>";
echo "<ol>";
echo "<li><a href='#' onclick='location.reload()'>Refresh this page</a> to see current status</li>";
echo "<li><a href='fix_api.php'>Run API Fix Script</a> (will create this)</li>";
echo "<li>Check server error logs for PHP errors</li>";
echo "<li>Ensure all required files are present and readable</li>";
echo "</ol>";

// Clean up
unlink('test_api.php');
?>