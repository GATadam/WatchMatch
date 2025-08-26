<?php
$user_id = $_GET['user_id']; // szám
$providers = $_GET['providers']; // lista
$two_users = $_GET['two_users'] == "true"; // bool
$other_user = $_GET['other_user']; // szám
if( $user_id=="" || $providers=="" || ($two_users==true && $other_user=="") ) {
    die("Missing parameters");
} else {
    if (file_exists(__DIR__ . '/.env')) {
        $lines = file(__DIR__ . '/.env');
        foreach ($lines as $line) {
            if (trim($line) && strpos($line, '=') !== false) {
                putenv(trim($line));
            }
        }
    }
    $user_id = intval($user_id);
    $providers = explode(",", $providers);
    if ($two_users) {
        $other_user = intval($other_user);
        // TODO
    } else {
        $dsn = 'mysql:host=' . getenv('DB_HOST') . ';dbname=' . getenv('DB_NAME') . ';charset=utf8mb4';
        $user = getenv('DB_USER_NAME');
        $pass = getenv('DB_PASSWORD');
    }
    
}
?>