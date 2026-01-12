<?php
session_start();
include 'db_pg.php';

if (isset($_SESSION['username'])) {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
        $stmt->execute([$username, $password]);
        $_SESSION['username'] = $username;
        header("Location: home.php");
        exit();
    } catch (PDOException $e) {
        if ($e->getCode() == 23505) { 
            $error = "Username already exists!";
        } else {
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>
    <title>Sign up</title>
<link rel="stylesheet" href="css/signup-login.css">
<div class="viss">
  <div class="container">
    <h2>Signup</h2> <?php if(isset($error)) echo "
                        <p class='error'>$error</p>"; ?> <form method="POST">
      <input type="text" name="username" placeholder="Username" required>
      <br>
      <input type="password" name="password" placeholder="Password" required>
      <br>
      <button type="submit" class="button-23">Signup</button>
    </form>
    <p>Already have an account? <a href="login.php">Login here</a>
    </p>
  </div>
</div>