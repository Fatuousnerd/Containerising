<?php

declare(strict_types=1);

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$databasePath = getenv('DATABASE_PATH') ?: '/var/www/data/app.db';
$databaseDirectory = dirname($databasePath);
if (!is_dir($databaseDirectory)) {
    mkdir($databaseDirectory, 0775, true);
}

$database = new SQLite3($databasePath);
$database->enableExceptions(true);
$database->busyTimeout(5000);
$database->exec('PRAGMA journal_mode = WAL');
$database->exec(
    'CREATE TABLE IF NOT EXISTS entries (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        message TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )'
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'add') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $message = trim((string) ($_POST['message'] ?? ''));
        if ($name !== '' && $message !== '') {
            $statement = $database->prepare(
                'INSERT INTO entries (name, message) VALUES (:name, :message)'
            );
            $statement->bindValue(':name', $name, SQLITE3_TEXT);
            $statement->bindValue(':message', $message, SQLITE3_TEXT);
            $statement->execute();
            $statement->close();
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $statement = $database->prepare('DELETE FROM entries WHERE id = :id');
            $statement->bindValue(':id', $id, SQLITE3_INTEGER);
            $statement->execute();
            $statement->close();
        }
    }

    $database->close();
    header('Location: /', true, 303);
    exit;
}

$result = $database->query(
    'SELECT id, name, message, created_at FROM entries ORDER BY id DESC'
);
$entries = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $entries[] = $row;
}
$database->close();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Guestbook</title>
  <style>
    * { box-sizing: border-box; }
    body {
      margin: 0;
      background: #f6f6f6;
      color: #111;
      font: 16px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif;
    }
    main {
      width: min(640px, calc(100% - 2rem));
      margin: 3rem auto;
    }
    h1 { margin: 0 0 .25rem; font-size: 1.75rem; }
    .subtitle { margin: 0 0 2rem; color: #555; }
    .card {
      background: #fff;
      border: 1px solid #d8d8d8;
      padding: 1rem;
      margin-bottom: 1rem;
    }
    form { display: grid; gap: .75rem; }
    label { display: grid; gap: .25rem; font-weight: 600; }
    input, textarea {
      width: 100%;
      border: 1px solid #bbb;
      background: #fff;
      color: #111;
      padding: .55rem .65rem;
      font: inherit;
    }
    textarea { min-height: 6rem; resize: vertical; }
    button {
      justify-self: start;
      border: 1px solid #111;
      background: #111;
      color: #fff;
      padding: .5rem .9rem;
      font: inherit;
      cursor: pointer;
    }
    .entry h2 { margin: 0 0 .25rem; font-size: 1rem; }
    .entry p { margin: 0 0 .5rem; white-space: pre-wrap; }
    .entry time { color: #666; font-size: .8rem; }
    .entry .delete {
      border: 1px solid #bbb;
      background: #fff;
      color: #111;
      padding: .2rem .5rem;
      font-size: .8rem;
    }
    .empty { color: #666; }
  </style>
</head>
<body>
  <main>
    <h1>Guestbook</h1>
    <p class="subtitle">Leave a note for everyone who visits.</p>

    <section class="card">
      <form method="post" action="/">
        <input type="hidden" name="action" value="add">
        <label>
          Name
          <input name="name" maxlength="80" required>
        </label>
        <label>
          Message
          <textarea name="message" maxlength="500" required></textarea>
        </label>
        <button type="submit">Post message</button>
      </form>
    </section>

    <?php foreach ($entries as $entry): ?>
      <article class="card entry">
        <h2><?= e((string) $entry['name']) ?></h2>
        <p><?= e((string) $entry['message']) ?></p>
        <time><?= e((string) $entry['created_at']) ?></time>
        <form method="post" action="/">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= e((string) $entry['id']) ?>">
          <button class="delete" type="submit">Delete</button>
        </form>
      </article>
    <?php endforeach; ?>

    <?php if ($entries === []): ?>
      <p class="empty">No messages yet.</p>
    <?php endif; ?>
  </main>
</body>
</html>
