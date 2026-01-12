<?php
$dbUrl = getenv('DATABASE_URL');
if (!$dbUrl) {
    echo "DATABASE_URL is not set.";
    exit();
}
$dbopts = parse_url($dbUrl);

$host = $dbopts["host"];
$port = isset($dbopts["port"]) ? $dbopts["port"] : 5432; 
$dbname = ltrim($dbopts["path"], '/');
$user = $dbopts["user"];
$pass = $dbopts["pass"];

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    echo "Error connecting to database: " . $e->getMessage();
    exit();
}
?>