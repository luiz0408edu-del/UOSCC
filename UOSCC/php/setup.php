<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if ($argc !== 3 || !filter_var($argv[1], FILTER_VALIDATE_EMAIL) || strlen($argv[2]) < 12) {
    fwrite(STDERR, "Uso: php UOSCC/php/setup.php admin@exemplo.org 'senha-com-12-ou-mais-caracteres'\n");
    exit(1);
}

$pdo = database();
if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
    $schema = file_get_contents(__DIR__ . '/schema.sql');
    foreach (array_filter(array_map('trim', explode(';', (string) $schema))) as $statementSql) {
        $pdo->exec($statementSql);
    }
} else {
    $pdo->exec('CREATE TABLE IF NOT EXISTS supporters (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        type TEXT NOT NULL,
        public_token TEXT UNIQUE
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT \'admin\',
        organization TEXT,
        supporter_id INTEGER UNIQUE REFERENCES supporters(id) ON DELETE CASCADE,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS report_notes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        organization TEXT NOT NULL DEFAULT \'\',
        period_start TEXT NOT NULL,
        period_end TEXT NOT NULL,
        title TEXT NOT NULL,
        narrative TEXT NOT NULL,
        author_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (organization, period_start, period_end)
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS projects (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        organization TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS resources (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        date TEXT NOT NULL,
        project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE RESTRICT,
        type TEXT NOT NULL,
        quantity REAL NOT NULL,
        supporter_id INTEGER REFERENCES supporters(id) ON DELETE SET NULL,
        usage TEXT NOT NULL DEFAULT \'\'
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS indicators (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
        name TEXT NOT NULL,
        date TEXT NOT NULL DEFAULT CURRENT_DATE,
        target REAL NOT NULL,
        achieved REAL NOT NULL DEFAULT 0
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS evidences (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
        title TEXT NOT NULL,
        type TEXT NOT NULL,
        url TEXT NOT NULL,
        description TEXT NOT NULL DEFAULT \'\',
        date TEXT NOT NULL DEFAULT CURRENT_DATE
    )');

    $supporterColumns = $pdo->query('PRAGMA table_info(supporters)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('public_token', $supporterColumns, true)) {
        $pdo->exec('ALTER TABLE supporters ADD COLUMN public_token TEXT');
        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS supporters_public_token ON supporters(public_token)');
    }
    $userColumns = $pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('role', $userColumns, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN role TEXT NOT NULL DEFAULT 'admin'");
    }
    if (!in_array('organization', $userColumns, true)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN organization TEXT');
    }
    if (!in_array('supporter_id', $userColumns, true)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN supporter_id INTEGER');
        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS users_supporter_id ON users(supporter_id)');
    }
    $evidenceColumns = $pdo->query('PRAGMA table_info(evidences)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('date', $evidenceColumns, true)) {
        $pdo->exec("ALTER TABLE evidences ADD COLUMN date TEXT NOT NULL DEFAULT ''");
        $pdo->exec("UPDATE evidences SET date = date('now') WHERE date = ''");
    }
    $indicatorColumns = $pdo->query('PRAGMA table_info(indicators)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('date', $indicatorColumns, true)) {
        $pdo->exec("ALTER TABLE indicators ADD COLUMN date TEXT NOT NULL DEFAULT ''");
        $pdo->exec("UPDATE indicators SET date = date('now') WHERE date = ''");
    }
    $missingTokens = $pdo->query('SELECT id FROM supporters WHERE public_token IS NULL OR public_token = ""')->fetchAll(PDO::FETCH_COLUMN);
    $updateToken = $pdo->prepare('UPDATE supporters SET public_token = ? WHERE id = ?');
    foreach ($missingTokens as $supporterId) {
        $updateToken->execute([bin2hex(random_bytes(16)), $supporterId]);
    }
}

if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
    $columns = [
        'users' => ['role' => "VARCHAR(20) NOT NULL DEFAULT 'admin'", 'organization' => 'VARCHAR(200) NULL', 'supporter_id' => 'BIGINT UNSIGNED NULL UNIQUE'],
        'supporters' => ['public_token' => 'CHAR(32) NULL'],
        'evidences' => ['date' => 'DATE NULL'],
        'indicators' => ['date' => 'DATE NULL'],
    ];
    foreach ($columns as $table => $tableColumns) {
        foreach ($tableColumns as $column => $definition) {
        $check = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $check->execute([$table, $column]);
        if ((int) $check->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        }
        }
    }
    $pdo->exec('UPDATE evidences SET date = CURRENT_DATE WHERE date IS NULL');
    $pdo->exec('UPDATE indicators SET date = CURRENT_DATE WHERE date IS NULL');
    $missingTokenIds = $pdo->query('SELECT id FROM supporters WHERE public_token IS NULL OR public_token = ""')->fetchAll(PDO::FETCH_COLUMN);
    $updateToken = $pdo->prepare('UPDATE supporters SET public_token = ? WHERE id = ?');
    foreach ($missingTokenIds as $supporterId) {
        $updateToken->execute([bin2hex(random_bytes(16)), $supporterId]);
    }
    $pdo->exec('ALTER TABLE supporters MODIFY public_token CHAR(32) NOT NULL');
    $pdo->exec('ALTER TABLE evidences MODIFY date DATE NOT NULL');
    $pdo->exec('ALTER TABLE indicators MODIFY date DATE NOT NULL');
}

$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
$upsertUserSql = $driver === 'mysql'
    ? 'INSERT INTO users (email, password_hash, role) VALUES (?, ?, \'admin\') ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), role = \'admin\', organization = NULL, supporter_id = NULL'
    : 'INSERT INTO users (email, password_hash, role, organization, supporter_id) VALUES (?, ?, \'admin\', NULL, NULL) ON CONFLICT(email) DO UPDATE SET password_hash = excluded.password_hash, role = \'admin\', organization = NULL, supporter_id = NULL';
$statement = $pdo->prepare($upsertUserSql);
$statement->execute([strtolower($argv[1]), password_hash($argv[2], PASSWORD_DEFAULT)]);
fwrite(STDOUT, "Banco inicializado e administrador configurado para {$argv[1]}.\n");