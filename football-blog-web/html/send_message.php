<?php
header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Invalid request"]);
    exit;
}

$name = trim($_POST["name"]);
$email = trim($_POST["email"]);
$message = trim($_POST["message"]);

if (!$name || !$email || !$message) {
    echo json_encode(["success" => false, "message" => "All fields required"]);
    exit;
}

$db = new PDO("sqlite:messages.db");
$stmt = $db->prepare("INSERT INTO messages (name, email, message) VALUES (?, ?, ?)");
$stmt->execute([$name, $email, $message]);

echo json_encode(["success" => true, "message" => "✅ Message sent!"]);