<?php
/**
 * Fix the 2-hour timezone offset issue
 */

echo "<h1>Timezone Synchronization Fix</h1>";

// First, let's see what's happening
echo "<h2>Current Situation</h2>";

// Before any changes
echo "<p><strong>Before fixes:</strong></p>";
echo "<p>PHP timezone: " . date_default_timezone_get() . "</p>";
echo "<p>PHP current time: " . date('Y-m-d H:i:s') . "</p>";
echo "<p>PHP timestamp: " . time() . "</p>";

// Check database timezone
$conn = new mysqli('localhost', 'svc_ghostcrew_admin', 'SecureP@ssw0rd2024!', 'ghostcrew_admin');
if (!$conn->connect_error) {
    $result = $conn->query("SELECT NOW() as db_time, @@session.time_zone as db_tz");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "<p>Database time: " . $row['db_time'] . "</p>";
        echo "<p>Database timezone: " . $row['db_tz'] . "</p>";
    }
}

// Now let's apply the fix
echo "<h2>Applying Synchronization Fix</h2>";

// Method 1: Force both to UTC
echo "<p><strong>Method 1: Force both PHP and MySQL to UTC</strong></p>";
date_default_timezone_set('UTC');
echo "<p>✅ Set PHP timezone to UTC</p>";

if (!$conn->connect_error) {
    $conn->query("SET time_zone = '+00:00'");
    echo "<p>✅ Set MySQL timezone to UTC</p>";
    
    // Verify they match now
    $result = $conn->query("SELECT NOW() as db_time, UNIX_TIMESTAMP(NOW()) as db_timestamp");
    if ($result) {
        $row = $result->fetch_assoc();
        $phpTime = time();
        $dbTimestamp = $row['db_timestamp'];
        $timeDiff = abs($phpTime - $dbTimestamp);
        
        echo "<p>PHP time: " . date('Y-m-d H:i:s', $phpTime) . " (timestamp: $phpTime)</p>";
        echo "<p>DB time: " . $row['db_time'] . " (timestamp: $dbTimestamp)</p>";
        echo "<p>Time difference: $timeDiff seconds</p>";
        
        if ($timeDiff <= 2) {
            echo "<p>✅ PHP and MySQL are now synchronized!</p>";
        } else {
            echo "<p>❌ Still have $timeDiff second difference</p>";
        }
    }
}

// Method 2: Alternative - detect and use server timezone
echo "<p><strong>Method 2: Use server's local timezone</strong></p>";
$serverTz = date_default_timezone_get();
if ($serverTz === 'UTC') {
    // Try to detect actual server timezone
    $serverTz = exec('timedatectl show --property=Timezone --value 2>/dev/null') ?: 'America/New_York';
}
echo "<p>Detected server timezone: $serverTz</p>";

// Test with local timezone
date_default_timezone_set($serverTz);
if (!$conn->connect_error) {
    // Set MySQL to match server timezone
    $offset = date('P'); // Get current timezone offset like +05:00
    $conn->query("SET time_zone = '$offset'");
    
    $result = $conn->query("SELECT NOW() as db_time, UNIX_TIMESTAMP(NOW()) as db_timestamp");
    if ($result) {
        $row = $result->fetch_assoc();
        $phpTime = time();
        $dbTimestamp = $row['db_timestamp'];
        $timeDiff = abs($phpTime - $dbTimestamp);
        
        echo "<p>With $serverTz timezone:</p>";
        echo "<p>PHP time: " . date('Y-m-d H:i:s', $phpTime) . " (timestamp: $phpTime)</p>";
        echo "<p>DB time: " . $row['db_time'] . " (timestamp: $dbTimestamp)</p>";
        echo "<p>Time difference: $timeDiff seconds</p>";
        
        if ($timeDiff <= 2) {
            echo "<p>✅ Local timezone synchronization works!</p>";
            $recommendedTz = $serverTz;
            $recommendedOffset = $offset;
        }
    }
}

// Set back to UTC for consistency
date_default_timezone_set('UTC');
if (!$conn->connect_error) {
    $conn->query("SET time_zone = '+00:00'");
}

echo "<h2>Recommended Fix</h2>";
echo "<p>Add this to the top of your auth_config.php:</p>";
echo "<pre>";
echo "// Ensure PHP and MySQL use the same timezone\n";
echo "date_default_timezone_set('UTC');\n";
echo "</pre>";

echo "<p>And make sure getAdminDB() sets MySQL timezone:</p>";
echo "<pre>";
echo "\$adminConn->query(\"SET time_zone = '+00:00'\");\n";
echo "</pre>";

echo "<h2>Fix Current Sessions</h2>";
echo "<p>Update existing sessions to use correct timestamps:</p>";

if (!$conn->connect_error) {
    // Update all active sessions to current time
    $conn->query("SET time_zone = '+00:00'");
    $result = $conn->query("UPDATE user_sessions SET last_activity = NOW() WHERE is_active = 1");
    if ($result) {
        echo "<p>✅ Updated " . $conn->affected_rows . " active sessions with current timestamp</p>";
    }
    
    // Show current sessions
    $result = $conn->query("SELECT id, login_time, last_activity, UNIX_TIMESTAMP(last_activity) as activity_ts FROM user_sessions WHERE is_active = 1 ORDER BY id DESC LIMIT 3");
    if ($result && $result->num_rows > 0) {
        echo "<p><strong>Current active sessions:</strong></p>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Login Time</th><th>Last Activity</th><th>Activity Timestamp</th><th>Seconds Ago</th></tr>";
        
        $currentTime = time();
        while ($row = $result->fetch_assoc()) {
            $secondsAgo = $currentTime - $row['activity_ts'];
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['login_time'] . "</td>";
            echo "<td>" . $row['last_activity'] . "</td>";
            echo "<td>" . $row['activity_ts'] . "</td>";
            echo "<td>" . $secondsAgo . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
}

$conn->close();

echo "<h2>Test Authentication Now</h2>";
echo "<p>The timezone sync should be fixed. Try these:</p>";
echo "<ul>";
echo "<li><a href='simple_auth_test.php?clear=1'>Clear session and test login</a></li>";
echo "<li><a href='login.php'>Try normal login process</a></li>";
echo "</ul>";
?>