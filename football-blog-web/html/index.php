<?php
session_start();
if (isset($_SESSION['username'])) {
    header("Location: home.php");
    exit();
}
?>
    <title>Footy</title>
<link rel="stylesheet" href="css/signup-login.css">
<div class="viss">
  <div class="container">
    <h1>Welcome!</h1>
    <a href="signup.php">Signup</a> | <a href="login.php">Login</a>
  </div>
</div>