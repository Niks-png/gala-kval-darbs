<?php
session_start();
include 'db_pg.php';

if (isset($_SESSION['username'])) {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['username'] = $user['username'];
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Invalid username or password";
    }
}
?>
    <title>Login</title>
<link rel="stylesheet" href="css/signup-login.css">
<div class="viss">
  <div class="container">
    <h2>Login</h2> <?php if(isset($error)) echo "
      <p class='error'>$error</p>"; ?> <form method="POST">
      <input type="text" name="username" placeholder="Username" required>
      <br>
      <input type="password" name="password" placeholder="Password" required>
      <br>
      <button type="submit" class="button-23">Login</button>
    </form>
    <p>Don't have an account? <a href="signup.php">Signup here</a>
    </p>
  </div>
</div>