<?php
// Start session first, before any output
session_start();

// Generate new CSRF token for testing
$_SESSION['csrf_token'] = md5(uniqid(rand(), true));

// Debug script for registration process
// Access this via: http://localhost/WebCourses/app/debug_registration.php

// Turn on error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Registration Debugging Tool</h1>";

// System Information
echo "<h2>System Information</h2>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Server: " . $_SERVER['SERVER_SOFTWARE'] . "</p>";
echo "<p>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p>Request URI: " . $_SERVER['REQUEST_URI'] . "</p>";

// Check database connection
echo "<h2>Database Connection Check</h2>";
require_once __DIR__ . '/config/connect.php';

if ($conn && !$conn->connect_error) {
    echo "<p style='color:green'>✓ Database connection successful</p>";
    
    // Show database info
    $db_name = $conn->query("SELECT DATABASE()")->fetch_row()[0];
    echo "<p>Connected to database: <strong>" . $db_name . "</strong></p>";
    
    // List tables
    echo "<h3>Tables in database:</h3>";
    $tables = $conn->query("SHOW TABLES");
    if ($tables->num_rows > 0) {
        echo "<ul>";
        while ($table = $tables->fetch_row()) {
            echo "<li>" . $table[0];
            
            // Get table structure
            $columns = $conn->query("DESCRIBE " . $table[0]);
            if ($columns->num_rows > 0) {
                echo " (";
                $columnNames = [];
                while ($column = $columns->fetch_assoc()) {
                    $columnNames[] = $column['Field'] . " (" . $column['Type'] . ")";
                }
                echo implode(", ", $columnNames);
                echo ")";
            }
            
            // Count rows
            $count = $conn->query("SELECT COUNT(*) FROM " . $table[0])->fetch_row()[0];
            echo " - <strong>" . $count . " rows</strong>";
            
            // If it's users table, show sample data (without sensitive info)
            if (strtolower($table[0]) == 'users') {
                echo "<br>Sample users (up to 5):";
                $users = $conn->query("SELECT user_id, username, email, role, created_at FROM " . $table[0] . " LIMIT 5");
                if ($users->num_rows > 0) {
                    echo "<ul>";
                    while ($user = $users->fetch_assoc()) {
                        echo "<li>ID: " . $user['user_id'] . 
                             ", Username: " . $user['username'] . 
                             ", Email: " . $user['email'] . 
                             ", Role: " . $user['role'] . 
                             ", Created: " . $user['created_at'] . "</li>";
                    }
                    echo "</ul>";
                }
            }
            
            echo "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>No tables found in the database.</p>";
    }
    
    // Try to create a test user directly in the database
    echo "<h3>Testing direct user creation:</h3>";
    $test_username = "test_user_" . time();
    $test_email = "test_" . time() . "@example.com";
    $test_password = password_hash("password123", PASSWORD_DEFAULT);
    
    // Get the exact table name with correct case
    $users_table = "users"; // Default
    $tables = $conn->query("SHOW TABLES");
    while($table = $tables->fetch_row()) {
        if (strtolower($table[0]) == 'users') {
            $users_table = $table[0];
            break;
        }
    }
    
    echo "<p>Using table name: <strong>" . $users_table . "</strong> for test</p>";
    
    // Try with prepared statement
    $stmt = $conn->prepare("INSERT INTO $users_table (username, email, password, role, created_at) VALUES (?, ?, ?, 'student', NOW())");
    if ($stmt) {
        $stmt->bind_param("sss", $test_username, $test_email, $test_password);
        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;
            echo "<p style='color:green'>✓ Test user created successfully with ID: " . $user_id . "</p>";
            
            // Clean up the test user
            $conn->query("DELETE FROM $users_table WHERE user_id = " . $user_id);
            echo "<p>Test user deleted.</p>";
        } else {
            echo "<p style='color:red'>✗ Failed to create test user with prepared statement: " . $stmt->error . "</p>";
            
            // Try direct query as fallback
            echo "<p>Trying direct query...</p>";
            $username_esc = $conn->real_escape_string($test_username);
            $email_esc = $conn->real_escape_string($test_email);
            $password_esc = $conn->real_escape_string($test_password);
            
            $sql = "INSERT INTO $users_table (username, email, password, role, created_at) VALUES ('$username_esc', '$email_esc', '$password_esc', 'student', NOW())";
            $result = $conn->query($sql);
            
            if ($result) {
                $user_id = $conn->insert_id;
                echo "<p style='color:green'>✓ Test user created successfully with direct query, ID: " . $user_id . "</p>";
                // Clean up the test user
                $conn->query("DELETE FROM $users_table WHERE user_id = " . $user_id);
                echo "<p>Test user deleted.</p>";
            } else {
                echo "<p style='color:red'>✗ Failed to create test user with direct query: " . $conn->error . "</p>";
            }
        }
        $stmt->close();
    } else {
        echo "<p style='color:red'>✗ Failed to prepare statement for test user: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color:red'>✗ Database connection failed: " . ($conn ? $conn->connect_error : "Unknown error") . "</p>";
}

// Check form submission path
echo "<h2>Form Submission Path Check</h2>";
$expected_path = __DIR__ . "/controllers/auth.php";
if (file_exists($expected_path)) {
    echo "<p style='color:green'>✓ Auth controller exists at: " . $expected_path . "</p>";
} else {
    echo "<p style='color:red'>✗ Auth controller NOT found at: " . $expected_path . "</p>";
}

$expected_path = __DIR__ . "/controllers/AuthController.php";
if (file_exists($expected_path)) {
    echo "<p style='color:green'>✓ AuthController exists at: " . $expected_path . "</p>";
} else {
    echo "<p style='color:red'>✗ AuthController NOT found at: " . $expected_path . "</p>";
}

// Check User model
$expected_path = __DIR__ . "/models/User.php";
if (file_exists($expected_path)) {
    echo "<p style='color:green'>✓ User model exists at: " . $expected_path . "</p>";
} else {
    echo "<p style='color:red'>✗ User model NOT found at: " . $expected_path . "</p>";
}

// Display session information
echo "<h2>Session Information</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Check error log
echo "<h2>Recent Error Log</h2>";
$log_path = ini_get('error_log');
echo "<p>Error log path: " . ($log_path ? $log_path : "Not configured") . "</p>";

if ($log_path && file_exists($log_path)) {
    $log_content = shell_exec("tail -n 50 " . escapeshellarg($log_path));
    echo "<pre style='background-color:#f0f0f0; padding:10px; overflow:auto; max-height:400px'>";
    echo htmlspecialchars($log_content);
    echo "</pre>";
} else {
    echo "<p>Could not access error log file.</p>";
}

// Include a registration form for testing
echo "<h2>Test Registration Form</h2>";
echo "<form method='POST' action='controllers/auth.php?action=register'>";
echo "<input type='hidden' name='csrf_token' value='" . $_SESSION['csrf_token'] . "'>";
echo "<input type='hidden' name='register' value='1'>";
echo "<div><label>Username: <input type='text' name='username' required></label></div>";
echo "<div><label>Email: <input type='email' name='email' required></label></div>";
echo "<div><label>Password: <input type='password' name='password' required minlength='8'></label></div>";
echo "<div><label>Role: <select name='role'>
        <option value='student'>Student</option>
        <option value='instructor'>Instructor</option>
      </select></label></div>";
echo "<div><button type='submit'>Register</button></div>";
echo "</form>";

echo "<hr><p><i>End of debugging information</i></p>";
?> 