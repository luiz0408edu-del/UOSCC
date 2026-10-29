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
        public_token TEXT UNIQUE,
        public_name_allowed INTEGER NOT NULL DEFAULT 0
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT \'admin\',
        display_name TEXT,
        avatar_url TEXT,
        organization TEXT,
        preferences TEXT NOT NULL DEFAULT \'{}\',
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
        organization TEXT NOT NULL,
        accepted_resources TEXT
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS resources (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        date TEXT NOT NULL,
        project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE RESTRICT,
        type TEXT NOT NULL,
        quantity REAL NOT NULL,
        supporter_id INTEGER REFERENCES supporters(id) ON DELETE SET NULL,
        indicator_id INTEGER REFERENCES indicators(id) ON DELETE SET NULL,
        material_name TEXT,
        unit TEXT,
        usage TEXT NOT NULL DEFAULT \'\'
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS resource_allocations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
        date TEXT NOT NULL,
        type TEXT NOT NULL,
        quantity REAL NOT NULL CHECK (quantity > 0),
        material_name TEXT,
        unit TEXT,
        description TEXT NOT NULL,
        created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS indicators (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
        name TEXT NOT NULL,
        date TEXT NOT NULL DEFAULT CURRENT_DATE,
        target REAL NOT NULL,
        achieved REAL NOT NULL DEFAULT 0,
        people_when_met INTEGER NOT NULL DEFAULT 0
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
    $pdo->exec('CREATE TABLE IF NOT EXISTS people_served (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
        date TEXT NOT NULL,
        quantity INTEGER NOT NULL CHECK (quantity > 0),
        description TEXT NOT NULL,
        created_by INTEGER REFERENCES users(id) ON DELETE SET NULL
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS support_requests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        supporter_id INTEGER NOT NULL REFERENCES supporters(id) ON DELETE CASCADE,
        project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
        indicator_id INTEGER REFERENCES indicators(id) ON DELETE SET NULL,
        type TEXT NOT NULL,
        quantity REAL NOT NULL,
        material_name TEXT,
        unit TEXT,
        description TEXT NOT NULL DEFAULT \'\',
        evidence_url TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT \'pending\',
        resource_id INTEGER REFERENCES resources(id) ON DELETE SET NULL,
        reviewed_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        reviewed_at TEXT
    )');

    $resourceColumns = $pdo->query('PRAGMA table_info(resources)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('indicator_id', $resourceColumns, true)) {
        $pdo->exec('ALTER TABLE resources ADD COLUMN indicator_id INTEGER REFERENCES indicators(id) ON DELETE SET NULL');
    }
    $pdo->exec('CREATE INDEX IF NOT EXISTS resources_indicator_id ON resources(indicator_id)');
    $supportRequestColumns = $pdo->query('PRAGMA table_info(support_requests)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('indicator_id', $supportRequestColumns, true)) {
        $pdo->exec('ALTER TABLE support_requests ADD COLUMN indicator_id INTEGER REFERENCES indicators(id) ON DELETE SET NULL');
    }
    $supporterColumns = $pdo->query('PRAGMA table_info(supporters)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('public_token', $supporterColumns, true)) {
        $pdo->exec('ALTER TABLE supporters ADD COLUMN public_token TEXT');
        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS supporters_public_token ON supporters(public_token)');
    }
    if (!in_array('public_name_allowed', $supporterColumns, true)) {
        $pdo->exec('ALTER TABLE supporters ADD COLUMN public_name_allowed INTEGER NOT NULL DEFAULT 0');
    }
    $userColumns = $pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('avatar_url', $userColumns, true)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN avatar_url TEXT');
    }
    $projectColumns = $pdo->query('PRAGMA table_info(projects)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('accepted_resources', $projectColumns, true)) {
        $pdo->exec('ALTER TABLE projects ADD COLUMN accepted_resources TEXT');
    }
    $pdo->exec("UPDATE projects SET accepted_resources = '[\"Dinheiro\",\"Material\",\"Voluntariado\"]' WHERE accepted_resources IS NULL OR accepted_resources = ''");
    $resourceColumns = $pdo->query('PRAGMA table_info(resources)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('material_name', $resourceColumns, true)) {
        $pdo->exec('ALTER TABLE resources ADD COLUMN material_name TEXT');
    }
    if (!in_array('unit', $resourceColumns, true)) {
        $pdo->exec('ALTER TABLE resources ADD COLUMN unit TEXT');
    }
    if (!in_array('material_name', $supportRequestColumns, true)) {
        $pdo->exec('ALTER TABLE support_requests ADD COLUMN material_name TEXT');
    }
    if (!in_array('unit', $supportRequestColumns, true)) {
        $pdo->exec('ALTER TABLE support_requests ADD COLUMN unit TEXT');
    }
    $userColumns = $pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('role', $userColumns, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN role TEXT NOT NULL DEFAULT 'admin'");
    }
    if (!in_array('organization', $userColumns, true)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN organization TEXT');
    }
    if (!in_array('display_name', $userColumns, true)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN display_name TEXT');
    }
    if (!in_array('supporter_id', $userColumns, true)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN supporter_id INTEGER');
        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS users_supporter_id ON users(supporter_id)');
    }
    if (!in_array('preferences', $userColumns, true)) {
        $pdo->exec('ALTER TABLE users ADD COLUMN preferences TEXT NULL');
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
    if (!in_array('people_when_met', $indicatorColumns, true)) {
        $pdo->exec('ALTER TABLE indicators ADD COLUMN people_when_met INTEGER NOT NULL DEFAULT 0');
    }
    $missingTokens = $pdo->query('SELECT id FROM supporters WHERE public_token IS NULL OR public_token = ""')->fetchAll(PDO::FETCH_COLUMN);
    $updateToken = $pdo->prepare('UPDATE supporters SET public_token = ? WHERE id = ?');
    foreach ($missingTokens as $supporterId) {
        $updateToken->execute([bin2hex(random_bytes(16)), $supporterId]);
    }
}

if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
    $columns = [
        'users' => ['role' => "VARCHAR(20) NOT NULL DEFAULT 'admin'", 'display_name' => 'VARCHAR(200) NULL', 'avatar_url' => 'VARCHAR(255) NULL', 'organization' => 'VARCHAR(200) NULL', 'supporter_id' => 'BIGINT UNSIGNED NULL UNIQUE', 'preferences' => 'TEXT NULL'],
        'supporters' => ['public_token' => 'CHAR(32) NULL', 'public_name_allowed' => 'TINYINT(1) NOT NULL DEFAULT 0'],
        'projects' => ['accepted_resources' => 'TEXT NULL'],
        'evidences' => ['date' => 'DATE NULL'],
        'indicators' => ['date' => 'DATE NULL', 'people_when_met' => 'INT UNSIGNED NOT NULL DEFAULT 0'],
        'resources' => ['indicator_id' => 'BIGINT UNSIGNED NULL', 'material_name' => 'VARCHAR(200) NULL', 'unit' => 'VARCHAR(20) NULL'],
        'support_requests' => ['material_name' => 'VARCHAR(200) NULL', 'unit' => 'VARCHAR(20) NULL'],
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
    $pdo->exec("UPDATE users SET display_name = COALESCE(NULLIF(display_name, ''), SUBSTRING_INDEX(email, '@', 1))");
    $pdo->exec("UPDATE users SET preferences = '{}' WHERE preferences IS NULL");
    $pdo->exec("UPDATE projects SET accepted_resources = '[\"Dinheiro\",\"Material\",\"Voluntariado\"]' WHERE accepted_resources IS NULL OR accepted_resources = ''");
    $indicatorIndex = $pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'resources' AND INDEX_NAME = 'idx_resources_indicator'");
    if ((int) $indicatorIndex->fetchColumn() === 0) {
        $pdo->exec('CREATE INDEX idx_resources_indicator ON resources(indicator_id)');
    }
    $indicatorForeignKey = $pdo->query("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'resources' AND COLUMN_NAME = 'indicator_id' AND REFERENCED_TABLE_NAME = 'indicators'");
    if ((int) $indicatorForeignKey->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE resources ADD CONSTRAINT fk_resources_indicator FOREIGN KEY (indicator_id) REFERENCES indicators(id) ON DELETE SET NULL');
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