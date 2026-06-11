<?php
// Replace these values with your real Railway keys!
$host = 'PASTE_YOUR_MYSQLHOST_HERE'; 
$port = 'PASTE_YOUR_MYSQLPORT_HERE';        
$db   = 'railway';                  
$user = 'root';                     
$pass = 'PASTE_YOUR_MYSQLPASSWORD_HERE';    
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRORS => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>
