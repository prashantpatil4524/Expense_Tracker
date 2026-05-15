<?php
// config.php - Database Configuration

define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // Change to your MySQL user
define('DB_PASS', '');              // Change to your MySQL password
define('DB_NAME', 'expense');

define('ADMIN_SECRET_KEY', 'prashant@patil'); // Admin registration key

function getDB() {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die('<div style="padding:30px;font-family:monospace;color:red;">
                <h3>Database Connection Failed</h3>
                <p>' . htmlspecialchars($conn->connect_error) . '</p>
                <p>Please ensure XAMPP MySQL is running and run <code>db_setup.sql</code> first.</p>
            </div>');
        }
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}
?>
