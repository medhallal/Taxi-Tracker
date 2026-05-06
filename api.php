<?php
// =============================================================
// Taxi Financial Tracker – REST API
// =============================================================
// All requests go through this single entry-point.
// The desired operation is selected via the `action` query-string
// parameter (e.g. api.php?action=incomes).
//
// HTTP method determines the CRUD operation:
//   GET    → read
//   POST   → create
//   PUT    → update  (?id=X)
//   DELETE → delete  (?id=X)
// =============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

session_name(SESSION_NAME);
session_start();

// ── Headers ──────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Handle pre-flight CORS requests (useful during local development)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ── Helpers ──────────────────────────────────────────────────

function respond(mixed $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function requireAuth(): void
{
    if (empty($_SESSION['user'])) {
        respond(['error' => 'Unauthorized – please log in'], 401);
    }
}

function requireAdmin(): void
{
    requireAuth();
    if ($_SESSION['user']['role'] !== 'admin') {
        respond(['error' => 'Forbidden – Admin access required'], 403);
    }
}

/**
 * Inserts a row into audit_logs.
 *
 * @param PDO        $db           Active database connection.
 * @param string     $actionType   'INSERT' | 'UPDATE' | 'DELETE'
 * @param string     $tableName    Name of the affected table.
 * @param int|null   $recordId     Primary key of the affected row.
 * @param mixed      $originalData Data before the change (null for INSERT).
 * @param mixed      $modifiedData Data after the change (null for DELETE).
 */
function logAction(
    PDO    $db,
    string $actionType,
    string $tableName,
    ?int   $recordId,
    mixed  $originalData = null,
    mixed  $modifiedData = null
): void {
    if (empty($_SESSION['user'])) {
        return;
    }

    $user = $_SESSION['user'];

    $stmt = $db->prepare(
        'INSERT INTO audit_logs
             (action_type, table_name, record_id, original_data, modified_data,
              performed_by, performed_by_username)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );

    $stmt->execute([
        $actionType,
        $tableName,
        $recordId,
        $originalData !== null ? json_encode($originalData) : null,
        $modifiedData !== null ? json_encode($modifiedData) : null,
        $user['id'],
        $user['username'],
    ]);
}

// ── Router ───────────────────────────────────────────────────

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Read JSON body once
$rawInput = file_get_contents('php://input');
$body     = json_decode($rawInput, true) ?? [];

switch ($action) {
    case 'login':       handleLogin();      break;
    case 'logout':      handleLogout();     break;
    case 'me':          handleMe();         break;
    case 'config':      handleConfig();     break;
    case 'dashboard':   handleDashboard();  break;
    case 'incomes':     handleIncomes();    break;
    case 'outcomes':    handleOutcomes();   break;
    case 'transfers':   handleTransfers();  break;
    case 'users':       handleUsers();      break;
    case 'audit_logs':  handleAuditLogs();  break;
    default:
        respond(['error' => "Unknown action: $action"], 400);
}

// =============================================================
// AUTH
// =============================================================

function handleLogin(): void
{
    global $method, $body;

    if ($method !== 'POST') {
        respond(['error' => 'Method not allowed'], 405);
    }

    $username = trim($body['username'] ?? '');
    $password = $body['password'] ?? '';

    if ($username === '' || $password === '') {
        respond(['error' => 'Username and password are required'], 400);
    }

    $db   = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        respond(['error' => 'Invalid credentials'], 401);
    }

    unset($user['password']);
    $_SESSION['user'] = $user;

    respond(['success' => true, 'user' => $user]);
}

function handleLogout(): void
{
    session_unset();
    session_destroy();
    respond(['success' => true]);
}

function handleMe(): void
{
    if (empty($_SESSION['user'])) {
        respond(['authenticated' => false, 'user' => null]);
    }
    respond(['authenticated' => true, 'user' => $_SESSION['user']]);
}

function handleConfig(): void
{
    respond(['allow_user_modifications' => ALLOW_USER_MODIFICATIONS]);
}

// =============================================================
// DASHBOARD
// =============================================================

function handleDashboard(): void
{
    global $method;

    requireAuth();

    if ($method !== 'GET') {
        respond(['error' => 'Method not allowed'], 405);
    }

    $db = getDB();

    $incomeTotal   = (float) $db->query('SELECT COALESCE(SUM(amount),0) FROM incomes')->fetchColumn();
    $outcomeTotal  = (float) $db->query('SELECT COALESCE(SUM(amount),0) FROM outcomes')->fetchColumn();
    $transferTotal = (float) $db->query('SELECT COALESCE(SUM(amount),0) FROM transfers')->fetchColumn();

    $caisse = $incomeTotal - $outcomeTotal - $transferTotal;

    $recentIncomes = $db->query(
        'SELECT i.*, u.username AS created_by_username
         FROM incomes i
         LEFT JOIN users u ON i.created_by = u.id
         ORDER BY i.date DESC, i.created_at DESC LIMIT 5'
    )->fetchAll();

    $recentOutcomes = $db->query(
        'SELECT o.*, u.username AS created_by_username
         FROM outcomes o
         LEFT JOIN users u ON o.created_by = u.id
         ORDER BY o.date DESC, o.created_at DESC LIMIT 5'
    )->fetchAll();

    $recentTransfers = $db->query(
        'SELECT t.*, u.username AS created_by_username
         FROM transfers t
         LEFT JOIN users u ON t.created_by = u.id
         ORDER BY t.created_at DESC LIMIT 5'
    )->fetchAll();

    respond([
        'caisse'           => $caisse,
        'income_total'     => $incomeTotal,
        'outcome_total'    => $outcomeTotal,
        'transfer_total'   => $transferTotal,
        'recent_incomes'   => $recentIncomes,
        'recent_outcomes'  => $recentOutcomes,
        'recent_transfers' => $recentTransfers,
    ]);
}

// =============================================================
// INCOMES
// =============================================================

function handleIncomes(): void
{
    global $method, $body;

    requireAuth();

    $db = getDB();

    if ($method === 'GET') {
        $dateFrom = $_GET['date_from'] ?? '';
        $dateTo   = $_GET['date_to']   ?? '';

        $sql    = 'SELECT i.*, u.username AS created_by_username
                   FROM incomes i
                   LEFT JOIN users u ON i.created_by = u.id
                   WHERE 1=1';
        $params = [];

        if ($dateFrom !== '') {
            $sql     .= ' AND i.date >= ?';
            $params[] = $dateFrom;
        }
        if ($dateTo !== '') {
            $sql     .= ' AND i.date <= ?';
            $params[] = $dateTo;
        }

        $sql .= ' ORDER BY i.date DESC, i.created_at DESC';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        respond($stmt->fetchAll());
    }

    if ($method === 'POST') {
        requireAdmin();

        $amount      = $body['amount']      ?? null;
        $description = trim($body['description'] ?? '');
        $date        = $body['date']        ?? date('Y-m-d');

        if (!is_numeric($amount) || (float)$amount <= 0) {
            respond(['error' => 'A positive amount is required'], 400);
        }
        if (!validateDate($date)) {
            respond(['error' => 'A valid date (YYYY-MM-DD) is required'], 400);
        }

        $stmt = $db->prepare(
            'INSERT INTO incomes (amount, description, date, created_by) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([(float)$amount, $description ?: null, $date, $_SESSION['user']['id']]);
        $id = (int)$db->lastInsertId();

        $record = fetchById($db, 'incomes', $id);
        logAction($db, 'INSERT', 'incomes', $id, null, $record);

        respond(['success' => true, 'id' => $id, 'record' => $record], 201);
    }

    if ($method === 'PUT') {
        requireAdmin();

        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            respond(['error' => 'Record ID is required'], 400);
        }

        $original = fetchById($db, 'incomes', $id);
        if (!$original) {
            respond(['error' => 'Record not found'], 404);
        }

        $amount      = $body['amount']      ?? $original['amount'];
        $description = $body['description'] ?? $original['description'];
        $date        = $body['date']        ?? $original['date'];

        if (!is_numeric($amount) || (float)$amount <= 0) {
            respond(['error' => 'A positive amount is required'], 400);
        }
        if (!validateDate($date)) {
            respond(['error' => 'A valid date (YYYY-MM-DD) is required'], 400);
        }

        $stmt = $db->prepare(
            'UPDATE incomes SET amount = ?, description = ?, date = ? WHERE id = ?'
        );
        $stmt->execute([(float)$amount, $description ?: null, $date, $id]);

        $updated = fetchById($db, 'incomes', $id);
        logAction($db, 'UPDATE', 'incomes', $id, $original, $updated);

        respond(['success' => true, 'record' => $updated]);
    }

    if ($method === 'DELETE') {
        requireAdmin();

        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            respond(['error' => 'Record ID is required'], 400);
        }

        $original = fetchById($db, 'incomes', $id);
        if (!$original) {
            respond(['error' => 'Record not found'], 404);
        }

        $db->prepare('DELETE FROM incomes WHERE id = ?')->execute([$id]);
        logAction($db, 'DELETE', 'incomes', $id, $original, null);

        respond(['success' => true]);
    }

    respond(['error' => 'Method not allowed'], 405);
}

// =============================================================
// OUTCOMES
// =============================================================

function handleOutcomes(): void
{
    global $method, $body;

    requireAuth();

    $db = getDB();

    if ($method === 'GET') {
        $dateFrom = $_GET['date_from'] ?? '';
        $dateTo   = $_GET['date_to']   ?? '';

        $sql    = 'SELECT o.*, u.username AS created_by_username
                   FROM outcomes o
                   LEFT JOIN users u ON o.created_by = u.id
                   WHERE 1=1';
        $params = [];

        if ($dateFrom !== '') {
            $sql     .= ' AND o.date >= ?';
            $params[] = $dateFrom;
        }
        if ($dateTo !== '') {
            $sql     .= ' AND o.date <= ?';
            $params[] = $dateTo;
        }

        $sql .= ' ORDER BY o.date DESC, o.created_at DESC';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        respond($stmt->fetchAll());
    }

    if ($method === 'POST') {
        requireAdmin();

        $amount      = $body['amount']      ?? null;
        $description = trim($body['description'] ?? '');
        $date        = $body['date']        ?? date('Y-m-d');

        if (!is_numeric($amount) || (float)$amount <= 0) {
            respond(['error' => 'A positive amount is required'], 400);
        }
        if ($description === '') {
            respond(['error' => 'Description is required for outcomes'], 400);
        }
        if (!validateDate($date)) {
            respond(['error' => 'A valid date (YYYY-MM-DD) is required'], 400);
        }

        $stmt = $db->prepare(
            'INSERT INTO outcomes (amount, description, date, created_by) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([(float)$amount, $description, $date, $_SESSION['user']['id']]);
        $id = (int)$db->lastInsertId();

        $record = fetchById($db, 'outcomes', $id);
        logAction($db, 'INSERT', 'outcomes', $id, null, $record);

        respond(['success' => true, 'id' => $id, 'record' => $record], 201);
    }

    if ($method === 'PUT') {
        requireAdmin();

        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            respond(['error' => 'Record ID is required'], 400);
        }

        $original = fetchById($db, 'outcomes', $id);
        if (!$original) {
            respond(['error' => 'Record not found'], 404);
        }

        $amount      = $body['amount']      ?? $original['amount'];
        $description = $body['description'] ?? $original['description'];
        $date        = $body['date']        ?? $original['date'];

        if (!is_numeric($amount) || (float)$amount <= 0) {
            respond(['error' => 'A positive amount is required'], 400);
        }
        if (trim($description) === '') {
            respond(['error' => 'Description is required for outcomes'], 400);
        }
        if (!validateDate($date)) {
            respond(['error' => 'A valid date (YYYY-MM-DD) is required'], 400);
        }

        $stmt = $db->prepare(
            'UPDATE outcomes SET amount = ?, description = ?, date = ? WHERE id = ?'
        );
        $stmt->execute([(float)$amount, $description, $date, $id]);

        $updated = fetchById($db, 'outcomes', $id);
        logAction($db, 'UPDATE', 'outcomes', $id, $original, $updated);

        respond(['success' => true, 'record' => $updated]);
    }

    if ($method === 'DELETE') {
        requireAdmin();

        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            respond(['error' => 'Record ID is required'], 400);
        }

        $original = fetchById($db, 'outcomes', $id);
        if (!$original) {
            respond(['error' => 'Record not found'], 404);
        }

        $db->prepare('DELETE FROM outcomes WHERE id = ?')->execute([$id]);
        logAction($db, 'DELETE', 'outcomes', $id, $original, null);

        respond(['success' => true]);
    }

    respond(['error' => 'Method not allowed'], 405);
}

// =============================================================
// TRANSFERS
// =============================================================

function handleTransfers(): void
{
    global $method, $body;

    requireAuth();

    $db   = getDB();
    $user = $_SESSION['user'];

    if ($method === 'GET') {
        if ($user['role'] === 'admin') {
            $sql    = 'SELECT t.*, u.username AS created_by_username
                       FROM transfers t
                       LEFT JOIN users u ON t.created_by = u.id
                       ORDER BY t.created_at DESC';
            $params = [];
        } else {
            // Partners see only transfers directed at them
            $sql    = 'SELECT t.*, u.username AS created_by_username
                       FROM transfers t
                       LEFT JOIN users u ON t.created_by = u.id
                       WHERE t.partner_id = ?
                       ORDER BY t.created_at DESC';
            $params = [$user['id']];
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        respond($stmt->fetchAll());
    }

    if ($method === 'POST') {
        requireAdmin();

        $amount      = $body['amount']       ?? null;
        $partnerId   = $body['partner_id']   ?? null;
        $partnerName = trim($body['partner_name'] ?? '');
        $notes       = trim($body['notes']   ?? '');

        if (!is_numeric($amount) || (float)$amount <= 0) {
            respond(['error' => 'A positive amount is required'], 400);
        }
        if ($partnerName === '') {
            respond(['error' => 'Partner name is required'], 400);
        }

        $stmt = $db->prepare(
            'INSERT INTO transfers (amount, partner_id, partner_name, notes, created_by)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (float)$amount,
            $partnerId ?: null,
            $partnerName,
            $notes ?: null,
            $user['id'],
        ]);
        $id = (int)$db->lastInsertId();

        $record = fetchById($db, 'transfers', $id);
        logAction($db, 'INSERT', 'transfers', $id, null, $record);

        respond(['success' => true, 'id' => $id, 'record' => $record], 201);
    }

    if ($method === 'PUT') {
        requireAdmin();

        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            respond(['error' => 'Record ID is required'], 400);
        }

        $original = fetchById($db, 'transfers', $id);
        if (!$original) {
            respond(['error' => 'Record not found'], 404);
        }

        $amount      = $body['amount']       ?? $original['amount'];
        $partnerId   = $body['partner_id']   ?? $original['partner_id'];
        $partnerName = $body['partner_name'] ?? $original['partner_name'];
        $notes       = $body['notes']        ?? $original['notes'];

        if (!is_numeric($amount) || (float)$amount <= 0) {
            respond(['error' => 'A positive amount is required'], 400);
        }
        if (trim($partnerName) === '') {
            respond(['error' => 'Partner name is required'], 400);
        }

        $stmt = $db->prepare(
            'UPDATE transfers SET amount = ?, partner_id = ?, partner_name = ?, notes = ? WHERE id = ?'
        );
        $stmt->execute([
            (float)$amount,
            $partnerId ?: null,
            $partnerName,
            $notes ?: null,
            $id,
        ]);

        $updated = fetchById($db, 'transfers', $id);
        logAction($db, 'UPDATE', 'transfers', $id, $original, $updated);

        respond(['success' => true, 'record' => $updated]);
    }

    if ($method === 'DELETE') {
        requireAdmin();

        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            respond(['error' => 'Record ID is required'], 400);
        }

        $original = fetchById($db, 'transfers', $id);
        if (!$original) {
            respond(['error' => 'Record not found'], 404);
        }

        $db->prepare('DELETE FROM transfers WHERE id = ?')->execute([$id]);
        logAction($db, 'DELETE', 'transfers', $id, $original, null);

        respond(['success' => true]);
    }

    respond(['error' => 'Method not allowed'], 405);
}

// =============================================================
// USERS
// =============================================================

function handleUsers(): void
{
    global $method, $body;

    requireAdmin();

    $db = getDB();

    if ($method === 'GET') {
        $rows = $db->query(
            'SELECT id, username, role, full_name, created_at, updated_at FROM users ORDER BY id ASC'
        )->fetchAll();
        respond($rows);
    }

    // All write operations require ALLOW_USER_MODIFICATIONS
    if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
        if (!ALLOW_USER_MODIFICATIONS) {
            respond(['error' => 'User modifications are disabled by configuration'], 403);
        }
    }

    if ($method === 'POST') {
        $username  = trim($body['username']  ?? '');
        $password  = $body['password']  ?? '';
        $role      = $body['role']      ?? 'partner';
        $fullName  = trim($body['full_name']  ?? '');

        if ($username === '') {
            respond(['error' => 'Username is required'], 400);
        }
        if (strlen($password) < 6) {
            respond(['error' => 'Password must be at least 6 characters'], 400);
        }
        if (!in_array($role, ['admin', 'partner'], true)) {
            respond(['error' => 'Role must be "admin" or "partner"'], 400);
        }

        // Check for duplicate username
        $stmt = $db->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            respond(['error' => 'Username already exists'], 409);
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $db->prepare(
            'INSERT INTO users (username, password, role, full_name) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$username, $hash, $role, $fullName ?: null]);
        $id = (int)$db->lastInsertId();

        $record = $db->prepare(
            'SELECT id, username, role, full_name, created_at FROM users WHERE id = ?'
        );
        $record->execute([$id]);
        $newUser = $record->fetch();

        logAction($db, 'INSERT', 'users', $id, null, $newUser);

        respond(['success' => true, 'id' => $id, 'record' => $newUser], 201);
    }

    if ($method === 'PUT') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            respond(['error' => 'User ID is required'], 400);
        }

        $stmt = $db->prepare(
            'SELECT id, username, role, full_name FROM users WHERE id = ?'
        );
        $stmt->execute([$id]);
        $original = $stmt->fetch();

        if (!$original) {
            respond(['error' => 'User not found'], 404);
        }

        $username = isset($body['username']) ? trim($body['username']) : $original['username'];
        $role     = $body['role']      ?? $original['role'];
        $fullName = isset($body['full_name']) ? trim($body['full_name']) : $original['full_name'];

        if ($username === '') {
            respond(['error' => 'Username is required'], 400);
        }
        if (!in_array($role, ['admin', 'partner'], true)) {
            respond(['error' => 'Role must be "admin" or "partner"'], 400);
        }

        // Check username conflict (exclude current user)
        $stmt = $db->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
        $stmt->execute([$username, $id]);
        if ($stmt->fetch()) {
            respond(['error' => 'Username already taken'], 409);
        }

        if (!empty($body['password'])) {
            if (strlen($body['password']) < 6) {
                respond(['error' => 'Password must be at least 6 characters'], 400);
            }
            $hash = password_hash($body['password'], PASSWORD_DEFAULT);
            $db->prepare(
                'UPDATE users SET username = ?, role = ?, full_name = ?, password = ? WHERE id = ?'
            )->execute([$username, $role, $fullName ?: null, $hash, $id]);
        } else {
            $db->prepare(
                'UPDATE users SET username = ?, role = ?, full_name = ? WHERE id = ?'
            )->execute([$username, $role, $fullName ?: null, $id]);
        }

        $stmt = $db->prepare(
            'SELECT id, username, role, full_name, updated_at FROM users WHERE id = ?'
        );
        $stmt->execute([$id]);
        $updated = $stmt->fetch();

        logAction($db, 'UPDATE', 'users', $id, $original, $updated);

        respond(['success' => true, 'record' => $updated]);
    }

    if ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            respond(['error' => 'User ID is required'], 400);
        }

        // Prevent deleting your own account
        if ($id === (int)$_SESSION['user']['id']) {
            respond(['error' => 'You cannot delete your own account'], 400);
        }

        $stmt = $db->prepare(
            'SELECT id, username, role, full_name FROM users WHERE id = ?'
        );
        $stmt->execute([$id]);
        $original = $stmt->fetch();

        if (!$original) {
            respond(['error' => 'User not found'], 404);
        }

        $db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
        logAction($db, 'DELETE', 'users', $id, $original, null);

        respond(['success' => true]);
    }

    respond(['error' => 'Method not allowed'], 405);
}

// =============================================================
// AUDIT LOGS
// =============================================================

function handleAuditLogs(): void
{
    global $method;

    requireAuth();

    if ($method !== 'GET') {
        respond(['error' => 'Method not allowed'], 405);
    }

    $db = getDB();

    $limit  = min((int)($_GET['limit']  ?? 100), 500);
    $offset = max((int)($_GET['offset'] ?? 0),   0);

    $stmt = $db->prepare(
        'SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT ? OFFSET ?'
    );
    $stmt->execute([$limit, $offset]);

    respond($stmt->fetchAll());
}

// =============================================================
// SHARED UTILITIES
// =============================================================

/** Fetches a single row by primary key from any table. */
function fetchById(PDO $db, string $table, int $id): array|false
{
    $stmt = $db->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

/** Returns true if $date matches YYYY-MM-DD format and is a real calendar date. */
function validateDate(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}
