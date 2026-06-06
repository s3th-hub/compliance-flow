<?php
// config/db.php — Database connection singleton

define('DB_HOST', '127.0.0.1');
define('DB_USER', 'bc_user');
define('DB_PASS', 'StrongPass99!');           // Change for production
define('DB_NAME', 'business_compliance');
define('BASE_URL', 'http://192.168.100.25');
define('UPLOAD_DIR', __DIR__ . '/../assets/uploads/');
define('UPLOAD_MAX_MB', 5);

function db(): mysqli {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die('Database connection failed: ' . htmlspecialchars($conn->connect_error));
        }
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}
