<?php
$db = new PDO("sqlite:messages.db");

if (isset($_POST['delete_id'])) {
    $stmt = $db->prepare("DELETE FROM messages WHERE id = ?");
    $stmt->execute([$_POST['delete_id']]);
    header("Location: messages.php");
    exit();
}

$messages = $db->query("SELECT * FROM messages ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
  <head>
    <title>Messages</title>
     <link rel="stylesheet" href="css/messages.css">
  </head>
  <body>
    
    <div class="box">
      <h2>📬 Contact Messages</h2> <?php foreach ($messages as $m): ?> <div class="msg">
        <strong> <?= htmlspecialchars($m["name"]) ?> </strong> ( <?= htmlspecialchars($m["email"]) ?>) <div> <?= nl2br(htmlspecialchars($m["message"])) ?> </div>
        <div class="time"> <?= $m["created_at"] ?> </div>
        <form method="POST"">
          <input type="hidden" name="delete_id" value="<?= $m["id"] ?>">
          <button type="submit" onclick="return confirm('Delete this message?')">Delete</button>
        </form>
      </div> <?php endforeach; ?> <?php if (count($messages) === 0): ?> <p>No messages yet.</p> <?php endif; ?>
    </div>
  </body>
</html>