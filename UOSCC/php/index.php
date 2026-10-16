<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
session_set_cookie_params([
    'httponly' => true,
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax',
    'path' => '/',
]);
session_start();

try {
    $pdo = database();
    if (!empty($_SESSION['user_id'])) {
        $sessionUserQuery = $pdo->prepare('SELECT id, email, role, organization, supporter_id FROM users WHERE id = ?');
        $sessionUserQuery->execute([(int) $_SESSION['user_id']]);
        $sessionUser = $sessionUserQuery->fetch();
        if ($sessionUser) {
            $_SESSION['email'] = $sessionUser['email'];
            $_SESSION['role'] = $sessionUser['role'];
            $_SESSION['organization'] = $sessionUser['organization'];
            $_SESSION['supporter_id'] = $sessionUser['supporter_id'];
        } else {
            $_SESSION = [];
            session_destroy();
        }
    }
    $action = (string) ($_GET['action'] ?? '');
    $entity = (string) ($_GET['entity'] ?? '');
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($action === 'register-supporter' && $method === 'POST') {
        $input = request_data();
        $name = trim((string) ($input['name'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        $type = (string) ($input['type'] ?? 'Pessoa');
        if ($name === '' || strlen($name) > 200 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12 || !in_array($type, ['Pessoa', 'Empresa'], true)) {
            respond(['error' => 'Informe nome, e-mail válido, tipo e senha com pelo menos 12 caracteres.'], 422);
        }
        $pdo->beginTransaction();
        $supporterInsert = $pdo->prepare('INSERT INTO supporters (name, type, public_token) VALUES (?, ?, ?)');
        $supporterInsert->execute([$name, $type, bin2hex(random_bytes(16))]);
        $supporterId = (int) $pdo->lastInsertId();
        $userInsert = $pdo->prepare('INSERT INTO users (email, password_hash, role, supporter_id) VALUES (?, ?, ?, ?)');
        $userInsert->execute([$email, password_hash($password, PASSWORD_DEFAULT), 'supporter', $supporterId]);
        $pdo->commit();
        respond(['created' => true], 201);
    }

    if ($action === 'login' && $method === 'POST') {
        $input = request_data();
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $statement = $pdo->prepare('SELECT id, email, password_hash, role, organization, supporter_id FROM users WHERE email = ?');
        $statement->execute([$email]);
        $user = $statement->fetch();
        if (!$user || !password_verify((string) ($input['password'] ?? ''), $user['password_hash'])) {
            respond(['error' => 'E-mail ou senha inválidos.'], 401);
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['organization'] = $user['organization'];
        $_SESSION['supporter_id'] = $user['supporter_id'];
        respond(['user' => ['email' => $user['email'], 'role' => $user['role'], 'organization' => $user['organization']]]);
    }

    if ($action === 'logout' && $method === 'POST') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        respond(['ok' => true]);
    }

    if ($action === 'upload' && $method === 'POST') {
        require_role(['admin', 'osc']);
        $file = $_FILES['file'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 25 * 1024 * 1024) {
            respond(['error' => 'Selecione um arquivo válido de até 25 MB.'], 422);
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $extensions = [
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif',
            'image/webp' => 'webp', 'video/mp4' => 'mp4', 'video/webm' => 'webm',
            'audio/mpeg' => 'mp3', 'audio/mp4' => 'm4a', 'audio/wav' => 'wav',
            'audio/ogg' => 'ogg', 'text/plain' => 'txt', 'application/pdf' => 'pdf',
        ];
        if (!isset($extensions[$mime])) {
            respond(['error' => 'Tipo não permitido. Use imagem, vídeo, áudio, texto simples ou PDF.'], 422);
        }
        $uploadDirectory = dirname(__DIR__) . '/uploads/evidence';
        if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0770, true) && !is_dir($uploadDirectory)) {
            throw new RuntimeException('Não foi possível criar a pasta de evidências.');
        }
        $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
        if (!move_uploaded_file($file['tmp_name'], $uploadDirectory . '/' . $filename)) {
            throw new RuntimeException('Não foi possível armazenar a evidência.');
        }
        respond(['url' => '../uploads/evidence/' . $filename], 201);
    }

    if ($action === 'me') {
        $user = empty($_SESSION['user_id']) ? null : current_user();
        if ($user) {
            unset($user['id'], $user['supporter_id']);
        }
        respond(['user' => $user]);
    }

    if ($entity === 'public' && $method === 'GET') {
        $publicResources = $pdo->query("SELECT r.date, p.name AS project, r.type, r.quantity, r.`usage` AS `usage`
            FROM resources r LEFT JOIN projects p ON p.id = r.project_id
            ORDER BY r.date DESC, r.id DESC")->fetchAll();
        $publicEvidences = $pdo->query('SELECT e.date, p.name AS project, e.title, e.type, e.url, e.description
            FROM evidences e JOIN projects p ON p.id = e.project_id ORDER BY e.date DESC, e.id DESC')->fetchAll();
        $donation = null;
        $token = (string) ($_GET['token'] ?? '');
        if ($token !== '') {
            if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
                respond(['error' => 'Código de consulta inválido.'], 422);
            }
            $supporterQuery = $pdo->prepare('SELECT id, name FROM supporters WHERE public_token = ?');
            $supporterQuery->execute([$token]);
            $supporter = $supporterQuery->fetch();
            if ($supporter) {
                $donationsQuery = $pdo->prepare("SELECT r.date, p.name AS project, r.type, r.quantity, r.`usage` AS `usage`
                    FROM resources r LEFT JOIN projects p ON p.id = r.project_id
                    WHERE r.supporter_id = ? ORDER BY r.date DESC, r.id DESC");
                $donationsQuery->execute([$supporter['id']]);
                $donation = ['name' => $supporter['name'], 'resources' => $donationsQuery->fetchAll()];
            }
        }
        respond([
            'summary' => dashboard_data($pdo),
            'projects' => entity_rows($pdo, 'projects'),
            'resources' => $publicResources,
            'evidences' => $publicEvidences,
            'donation' => $donation,
        ]);
    }

    require_login();

    if ($action === 'create-osc-account' && $method === 'POST') {
        require_role(['admin']);
        $input = request_data();
        $name = trim((string) ($input['name'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        if (!in_array($name, osc_areas(), true) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
            respond(['error' => 'Selecione uma das seis áreas, informe e-mail válido e senha com pelo menos 12 caracteres.'], 422);
        }
        $organizationExists = $pdo->prepare('SELECT COUNT(*) FROM users WHERE role = ? AND organization = ?');
        $organizationExists->execute(['osc', $name]);
        if ((int) $organizationExists->fetchColumn() > 0) {
            respond(['error' => 'Já existe uma conta OSC vinculada a essa área.'], 409);
        }
        $statement = $pdo->prepare('INSERT INTO users (email, password_hash, role, organization) VALUES (?, ?, ?, ?)');
        $statement->execute([$email, password_hash($password, PASSWORD_DEFAULT), 'osc', $name]);
        respond(['created' => true], 201);
    }

    if ($action === 'create-admin-account' && $method === 'POST') {
        require_role(['admin']);
        $input = request_data();
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
            respond(['error' => 'Informe e-mail válido e senha com pelo menos 12 caracteres.'], 422);
        }
        $statement = $pdo->prepare('INSERT INTO users (email, password_hash, role) VALUES (?, ?, ?)');
        $statement->execute([$email, password_hash($password, PASSWORD_DEFAULT), 'admin']);
        respond(['created' => true], 201);
    }

    if ($action === 'supporter-dashboard' && $method === 'GET') {
        require_role(['supporter']);
        $supporterId = current_user()['supporter_id'];
        $statement = $pdo->prepare("SELECT r.date, p.name AS project, r.type, r.quantity, r.usage
            FROM resources r LEFT JOIN projects p ON p.id = r.project_id
            WHERE r.supporter_id = ? ORDER BY r.date DESC, r.id DESC");
        $statement->execute([$supporterId]);
        $resources = $statement->fetchAll();
        respond(['resources' => $resources, 'total' => count($resources)]);
    }

    if ($action === 'organizations' && $method === 'GET') {
        require_role(['admin']);
        respond($pdo->query('SELECT DISTINCT organization FROM projects ORDER BY organization')->fetchAll(PDO::FETCH_COLUMN));
    }
    if ($action === 'dashboard' && $method === 'GET') {
        require_role(['admin', 'osc']);
        respond(dashboard_data($pdo, scoped_filters([])));
    }
    if ($action === 'save-report-note' && $method === 'POST') {
        require_role(['admin']);
        $input = request_data();
        $organization = trim((string) ($input['organization'] ?? ''));
        $periodStart = trim((string) ($input['from'] ?? ''));
        $periodEnd = trim((string) ($input['to'] ?? ''));
        $title = trim((string) ($input['title'] ?? ''));
        $narrative = trim((string) ($input['narrative'] ?? ''));
        foreach ([$periodStart, $periodEnd] as $value) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            $errors = DateTimeImmutable::getLastErrors();
            if (!$date || ($errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('Y-m-d') !== $value) {
                respond(['error' => 'Informe um período válido para salvar o relatório.'], 422);
            }
        }
        if ($periodStart > $periodEnd || ($organization !== '' && strlen($organization) > 200) || $title === '' || strlen($title) > 200 || $narrative === '' || strlen($narrative) > 50000) {
            respond(['error' => 'Informe OSC, título e texto do relatório válidos para o período.'], 422);
        }
        if ($organization !== '') {
            $organizationCheck = $pdo->prepare('SELECT COUNT(*) FROM projects WHERE organization = ?');
            $organizationCheck->execute([$organization]);
            if ((int) $organizationCheck->fetchColumn() === 0) {
                respond(['error' => 'A OSC selecionada não possui projetos cadastrados.'], 422);
            }
        }
        $saveSql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? 'INSERT INTO report_notes (organization, period_start, period_end, title, narrative, author_id) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE title = VALUES(title), narrative = VALUES(narrative), author_id = VALUES(author_id), updated_at = CURRENT_TIMESTAMP'
            : 'INSERT INTO report_notes (organization, period_start, period_end, title, narrative, author_id) VALUES (?, ?, ?, ?, ?, ?) ON CONFLICT(organization, period_start, period_end) DO UPDATE SET title = excluded.title, narrative = excluded.narrative, author_id = excluded.author_id, updated_at = CURRENT_TIMESTAMP';
        $save = $pdo->prepare($saveSql);
        $save->execute([$organization, $periodStart, $periodEnd, $title, $narrative, current_user()['id']]);
        respond(['saved' => true]);
    }
    if ($action === 'report' && $method === 'GET') {
        require_role(['admin', 'osc']);
        $filters = [];
        foreach (['from', 'to'] as $field) {
            $value = trim((string) ($_GET[$field] ?? ''));
            if ($value !== '') {
                $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
                $errors = DateTimeImmutable::getLastErrors();
                if (!$date || ($errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('Y-m-d') !== $value) {
                    respond(['error' => 'Informe um período válido.'], 422);
                }
                $filters[$field] = $value;
            }
        }
        if (isset($filters['from'], $filters['to']) && $filters['from'] > $filters['to']) {
            respond(['error' => 'A data inicial deve ser anterior à data final.'], 422);
        }
        $filters['organization'] = trim((string) ($_GET['organization'] ?? ''));
        $filters = scoped_filters($filters);
        $reportNote = null;
        if (isset($filters['from'], $filters['to'])) {
            $noteQuery = $pdo->prepare('SELECT title, narrative, updated_at FROM report_notes WHERE organization = ? AND period_start = ? AND period_end = ?');
            $noteQuery->execute([$filters['organization'] ?? '', $filters['from'], $filters['to']]);
            $reportNote = $noteQuery->fetch() ?: null;
        }
        respond([
            'filters' => $filters,
            'reportNote' => $reportNote,
            'summary' => dashboard_data($pdo, $filters),
            'projects' => entity_rows($pdo, 'projects', $filters),
            'resources' => entity_rows($pdo, 'resources', $filters),
            'indicators' => entity_rows($pdo, 'indicators', $filters),
            'evidences' => entity_rows($pdo, 'evidences', $filters),
        ]);
    }

    $definitions = entity_fields();
    if (!isset($definitions[$entity])) {
        respond(['error' => 'Recurso não encontrado.'], 404);
    }
    $definition = $definitions[$entity];
    $role = current_user()['role'];
    if ($role === 'supporter') {
        respond(['error' => 'Use a área de acompanhamento do apoiador.'], 403);
    }
    if ($role === 'osc' && !in_array($entity, ['projects', 'resources', 'indicators', 'evidences'], true)) {
        respond(['error' => 'Sua conta OSC não tem acesso a este cadastro.'], 403);
    }

    if ($method === 'GET') {
        respond(entity_rows($pdo, $entity, scoped_filters([])));
    }

    if ($method === 'POST' || $method === 'PUT') {
        $input = request_data();
        $values = [];
        foreach ($definition['fields'] as $field) {
            $value = $input[$field] ?? null;
            if ($role === 'osc' && $entity === 'projects' && $field === 'organization') {
                $value = current_user()['organization'];
            }
            if (in_array($field, ['project_id', 'supporter_id'], true)) {
                $value = $value === '' || $value === null ? null : filter_var($value, FILTER_VALIDATE_INT);
                if ($value === false) {
                    respond(['error' => "O campo {$field} precisa ser um identificador válido."], 422);
                }
            } elseif (in_array($field, ['target', 'achieved', 'quantity'], true)) {
                $value = $value === '' || $value === null ? null : filter_var($value, FILTER_VALIDATE_FLOAT);
                if ($value !== null && ($value === false || $value < 0)) {
                    respond(['error' => "O campo {$field} precisa ser um número igual ou maior que zero."], 422);
                }
            } elseif ($value !== null) {
                $value = trim((string) $value);
            }
            if (in_array($field, ['date'], true) && $value !== null) {
                $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
                $errors = DateTimeImmutable::getLastErrors();
                if (!$date || ($errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('Y-m-d') !== $value) {
                    respond(['error' => 'Informe uma data válida.'], 422);
                }
            }
            if (in_array($field, $definition['required'], true) && ($value === null || $value === '' || $value === false)) {
                respond(['error' => "O campo {$field} é obrigatório ou inválido."], 422);
            }
            if (in_array($field, ['target', 'achieved', 'quantity'], true) && $value !== null && $value === false) {
                respond(['error' => "O campo {$field} precisa ser numérico."], 422);
            }
            $values[$field] = $value;
        }

        if ($role === 'osc' && $entity === 'projects') {
            $values['organization'] = current_user()['organization'];
        }
        if ($role === 'osc' && $entity === 'resources') {
            $values['supporter_id'] = null;
        }
        if ($entity === 'projects' && !in_array($values['organization'], osc_areas(), true)) {
            respond(['error' => 'Selecione uma das seis áreas de atuação cadastradas.'], 422);
        }
        if ($role === 'osc' && in_array($entity, ['resources', 'indicators', 'evidences'], true)) {
            $projectCheck = $pdo->prepare('SELECT COUNT(*) FROM projects WHERE id = ? AND organization = ?');
            $projectCheck->execute([$values['project_id'], current_user()['organization']]);
            if ((int) $projectCheck->fetchColumn() !== 1) {
                respond(['error' => 'O projeto não pertence à sua OSC.'], 403);
            }
        }

        if ($method === 'POST') {
            if ($entity === 'supporters') {
                $values['public_token'] = bin2hex(random_bytes(16));
            }
            $columns = array_keys($values);
            $quotedColumns = array_map(static fn (string $column): string => '`' . $column . '`', $columns);
            $sql = 'INSERT INTO ' . $definition['table'] . ' (' . implode(', ', $quotedColumns) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
            $statement = $pdo->prepare($sql);
            $statement->execute(array_values($values));
            respond([
                'id' => (int) $pdo->lastInsertId(),
                ...($entity === 'supporters' ? ['publicToken' => $values['public_token']] : []),
            ], 201);
        }

        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) {
            respond(['error' => 'Identificador inválido.'], 400);
        }
        $assignments = array_map(static fn (string $field): string => '`' . $field . '` = ?', array_keys($values));
        $scopeSql = '';
        $scopeValues = [];
        if ($role === 'osc') {
            $scopeSql = $entity === 'projects'
                ? ' AND organization = ?'
                : ' AND project_id IN (SELECT id FROM projects WHERE organization = ?)';
            $scopeValues[] = current_user()['organization'];
        }
        $statement = $pdo->prepare('UPDATE ' . $definition['table'] . ' SET ' . implode(', ', $assignments) . ' WHERE id = ?' . $scopeSql);
        $statement->execute([...array_values($values), $id, ...$scopeValues]);
        respond(['updated' => $statement->rowCount() > 0]);
    }

    if ($method === 'DELETE') {
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) {
            respond(['error' => 'Identificador inválido.'], 400);
        }
        $scopeSql = '';
        $scopeValues = [];
        if ($role === 'osc') {
            $scopeSql = $entity === 'projects'
                ? ' AND organization = ?'
                : ' AND project_id IN (SELECT id FROM projects WHERE organization = ?)';
            $scopeValues[] = current_user()['organization'];
        }
        $statement = $pdo->prepare('DELETE FROM ' . $definition['table'] . ' WHERE id = ?' . $scopeSql);
        $statement->execute([$id, ...$scopeValues]);
        respond(['deleted' => $statement->rowCount() > 0]);
    }

    respond(['error' => 'Método não permitido.'], 405);
} catch (PDOException $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    respond(['error' => 'Não foi possível salvar os dados. Verifique os vínculos e campos informados.'], 409);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    respond(['error' => 'Erro interno do servidor.'], 500);
}