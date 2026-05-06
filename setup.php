<?php
// =============================================================
// Taxi Financial Tracker – One-time Setup Script
// =============================================================
// Open this page ONCE in your browser after uploading the files:
//   http://your-server/taxi-tracker/setup.php
//
// It will:
//   1. Create the database + all tables if they do not exist.
//   2. Create a default admin account (username: admin / password: admin123).
//
// DELETE this file after setup is complete for security reasons.
// =============================================================

require_once __DIR__ . '/config.php';

$messages = [];
$errors   = [];

// ── Connect without selecting a database first ───────────────
try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;charset=%s', DB_HOST, DB_PORT, DB_CHARSET),
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    // 1. Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `" . DB_NAME . "`");
    $messages[] = "✅ Database <strong>" . htmlspecialchars(DB_NAME) . "</strong> ready.";

    // 2. Create tables
    $tables = [
        'users' => "CREATE TABLE IF NOT EXISTS users (
            id         INT            AUTO_INCREMENT PRIMARY KEY,
            username   VARCHAR(100)   NOT NULL UNIQUE,
            password   VARCHAR(255)   NOT NULL,
            role       ENUM('admin','partner') NOT NULL DEFAULT 'partner',
            full_name  VARCHAR(200)   DEFAULT NULL,
            created_at TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'incomes' => "CREATE TABLE IF NOT EXISTS incomes (
            id          INT            AUTO_INCREMENT PRIMARY KEY,
            amount      DECIMAL(10,2)  NOT NULL,
            description VARCHAR(500)   DEFAULT NULL,
            date        DATE           NOT NULL,
            created_by  INT            DEFAULT NULL,
            created_at  TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
            updated_at  TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_incomes_user FOREIGN KEY (created_by)
                REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'outcomes' => "CREATE TABLE IF NOT EXISTS outcomes (
            id          INT            AUTO_INCREMENT PRIMARY KEY,
            amount      DECIMAL(10,2)  NOT NULL,
            description VARCHAR(500)   NOT NULL,
            date        DATE           NOT NULL,
            created_by  INT            DEFAULT NULL,
            created_at  TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
            updated_at  TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_outcomes_user FOREIGN KEY (created_by)
                REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'transfers' => "CREATE TABLE IF NOT EXISTS transfers (
            id           INT            AUTO_INCREMENT PRIMARY KEY,
            amount       DECIMAL(10,2)  NOT NULL,
            partner_id   INT            DEFAULT NULL,
            partner_name VARCHAR(200)   NOT NULL,
            notes        VARCHAR(500)   DEFAULT NULL,
            created_by   INT            DEFAULT NULL,
            created_at   TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
            updated_at   TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_transfers_partner FOREIGN KEY (partner_id)
                REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_transfers_creator FOREIGN KEY (created_by)
                REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'audit_logs' => "CREATE TABLE IF NOT EXISTS audit_logs (
            id                    INT  AUTO_INCREMENT PRIMARY KEY,
            action_type           ENUM('INSERT','UPDATE','DELETE') NOT NULL,
            table_name            VARCHAR(100)  NOT NULL,
            record_id             INT           DEFAULT NULL,
            original_data         TEXT          DEFAULT NULL,
            modified_data         TEXT          DEFAULT NULL,
            performed_by          INT           DEFAULT NULL,
            performed_by_username VARCHAR(100)  DEFAULT NULL,
            created_at            TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];

    foreach ($tables as $name => $sql) {
        $pdo->exec($sql);
        $messages[] = "✅ Table <strong>$name</strong> ready.";
    }

    // 3. Create default admin user if it does not exist
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute(['admin']);

    if ($stmt->fetch()) {
        $messages[] = "ℹ️ Admin account already exists – skipped creation.";
    } else {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $ins  = $pdo->prepare(
            "INSERT INTO users (username, password, role, full_name) VALUES (?, ?, 'admin', 'Administrator')"
        );
        $ins->execute(['admin', $hash]);
        $messages[] = "✅ Default admin account created. <strong>Login: admin / Password: admin123</strong>";
    }

} catch (PDOException $e) {
    $errors[] = "❌ Database error: " . htmlspecialchars($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Taxi Financial Tracker – Setup</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-lg p-8 max-w-lg w-full">
        <h1 class="text-2xl font-bold text-gray-800 mb-2">🚖 Taxi Financial Tracker</h1>
        <h2 class="text-lg font-semibold text-gray-600 mb-6">Database Setup</h2>

        <?php if ($errors): ?>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                <h3 class="font-semibold text-red-700 mb-2">Errors</h3>
                <?php foreach ($errors as $msg): ?>
                    <p class="text-red-600 text-sm"><?= $msg ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($messages): ?>
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4 space-y-1">
                <?php foreach ($messages as $msg): ?>
                    <p class="text-gray-700 text-sm"><?= $msg ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!$errors): ?>
            <div class="bg-yellow-50 border border-yellow-300 rounded-lg p-4 mb-6">
                <p class="text-yellow-800 font-semibold text-sm">⚠️ Security reminder</p>
                <p class="text-yellow-700 text-sm mt-1">
                    Please <strong>delete this file</strong> (setup.php) from your server after setup is complete,
                    and <strong>change the admin password</strong> immediately.
                </p>
            </div>

            <a href="index.html"
               class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg transition-colors">
                Go to the Application →
            </a>
        <?php else: ?>
            <p class="text-sm text-gray-500 mt-4">
                Check your <code class="bg-gray-100 px-1 rounded">config.php</code> file and make sure
                the database credentials are correct, then refresh this page.
            </p>
        <?php endif; ?>
    </div>
</body>
</html>
