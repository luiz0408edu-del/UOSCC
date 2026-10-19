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
        $sessionUserQuery = $pdo->prepare('SELECT id, email, role, display_name, organization, supporter_id FROM users WHERE id = ?');
        $sessionUserQuery->execute([(int) $_SESSION['user_id']]);
        $sessionUser = $sessionUserQuery->fetch();
        if ($sessionUser) {
            $_SESSION['email'] = $sessionUser['email'];
            $_SESSION['display_name'] = $sessionUser['display_name'] ?: $sessionUser['email'];
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
        $userInsert = $pdo->prepare('INSERT INTO users (email, password_hash, role, display_name, supporter_id) VALUES (?, ?, ?, ?, ?)');
        $userInsert->execute([$email, password_hash($password, PASSWORD_DEFAULT), 'supporter', $name, $supporterId]);
        $pdo->commit();
        respond(['created' => true], 201);
    }

    if ($action === 'login' && $method === 'POST') {
        $input = request_data();
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $statement = $pdo->prepare('SELECT id, email, password_hash, role, display_name, organization, supporter_id FROM users WHERE email = ?');
        $statement->execute([$email]);
        $user = $statement->fetch();
        if (!$user || !password_verify((string) ($input['password'] ?? ''), $user['password_hash'])) {
            respond(['error' => 'E-mail ou senha inválidos.'], 401);
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['display_name'] = $user['display_name'] ?: $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['organization'] = $user['organization'];
        $_SESSION['supporter_id'] = $user['supporter_id'];
        respond(['user' => ['email' => $user['email'], 'name' => $user['display_name'] ?: $user['email'], 'role' => $user['role'], 'organization' => $user['organization']]]);
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
            $nameQuery = $pdo->prepare('SELECT display_name FROM users WHERE id = ?');
            $nameQuery->execute([$user['id']]);
            $user['name'] = (string) ($nameQuery->fetchColumn() ?: $user['email']);
            if ($user['role'] === 'supporter') {
                $supporterNameQuery = $pdo->prepare('SELECT name FROM supporters WHERE id = ?');
                $supporterNameQuery->execute([$user['supporter_id']]);
                $user['name'] = (string) ($supporterNameQuery->fetchColumn() ?: $user['name']);
            }
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

    if ($action === 'preferences' && $method === 'GET') {
        $preferencesQuery = $pdo->prepare('SELECT preferences FROM users WHERE id = ?');
        $preferencesQuery->execute([current_user()['id']]);
        $preferences = json_decode((string) $preferencesQuery->fetchColumn(), true);
        respond(is_array($preferences) ? $preferences : []);
    }
    if ($action === 'preferences' && $method === 'POST') {
        $input = request_data();
        $allowedPages = ['dashboard', 'projetos', 'apoiadores', 'financeiro', 'metas', 'evidencias', 'relatorios', 'publico', 'apoiador', 'contas', 'configuracoes'];
        $allowedCards = ['dinheiro', 'voluntariado', 'pessoas', 'metas', 'evidencias'];
        $preferences = [
            'theme' => ($input['theme'] ?? '') === 'dark' ? 'dark' : 'light',
            'hiddenCards' => array_values(array_intersect((array) ($input['hiddenCards'] ?? []), $allowedCards)),
            'menuOrder' => [],
            'cardOrder' => array_values(array_unique(array_intersect((array) ($input['cardOrder'] ?? []), $allowedCards))),
        ];
        $menuGroups = [
            ['apoiador', 'dashboard'],
            ['projetos', 'apoiadores', 'financeiro', 'metas', 'evidencias', 'relatorios'],
            ['publico', 'contas', 'configuracoes'],
        ];
        foreach ($menuGroups as $group) {
            $available = array_values(array_intersect($group, $allowedPages));
            $requested = array_values(array_unique(array_intersect((array) ($input['menuOrder'] ?? []), $available)));
            $preferences['menuOrder'] = [...$preferences['menuOrder'], ...$requested, ...array_values(array_diff($available, $requested))];
        }
        $savePreferences = $pdo->prepare('UPDATE users SET preferences = ? WHERE id = ?');
        $savePreferences->execute([json_encode($preferences, JSON_UNESCAPED_UNICODE), current_user()['id']]);
        respond($preferences);
    }
    if ($action === 'account' && $method === 'POST') {
        $input = request_data();
        $operation = (string) ($input['operation'] ?? '');
        $user = current_user();
        if ($operation === 'profile') {
            $name = trim((string) ($input['name'] ?? ''));
            if ($name === '' || strlen($name) > 200) {
                respond(['error' => 'Informe um nome com até 200 caracteres.'], 422);
            }
            $updateName = $pdo->prepare('UPDATE users SET display_name = ? WHERE id = ?');
            $updateName->execute([$name, $user['id']]);
            if ($user['role'] === 'supporter') {
                $updateSupporter = $pdo->prepare('UPDATE supporters SET name = ? WHERE id = ?');
                $updateSupporter->execute([$name, $user['supporter_id']]);
            }
            $_SESSION['display_name'] = $name;
            respond(['name' => $name]);
        }
        if (!in_array($operation, ['password', 'delete'], true)) {
            respond(['error' => 'Operação de conta inválida.'], 422);
        }
        $passwordQuery = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $passwordQuery->execute([$user['id']]);
        if (!password_verify((string) ($input['current_password'] ?? ''), (string) $passwordQuery->fetchColumn())) {
            respond(['error' => 'A senha atual está incorreta.'], 403);
        }
        if ($operation === 'password') {
            $newPassword = (string) ($input['new_password'] ?? '');
            if (strlen($newPassword) < 12) {
                respond(['error' => 'A nova senha deve ter pelo menos 12 caracteres.'], 422);
            }
            $updatePassword = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $updatePassword->execute([password_hash($newPassword, PASSWORD_DEFAULT), $user['id']]);
            session_regenerate_id(true);
            respond(['updated' => true]);
        }

        $pdo->beginTransaction();
        $adminLockSql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? 'SELECT id FROM users WHERE role = ? FOR UPDATE'
            : 'SELECT id FROM users WHERE role = ?';
        $adminQuery = $pdo->prepare($adminLockSql);
        $adminQuery->execute(['admin']);
        $adminIds = $adminQuery->fetchAll(PDO::FETCH_COLUMN);
        if ($user['role'] === 'admin' && count($adminIds) <= 1) {
            $pdo->rollBack();
            respond(['error' => 'Não é possível excluir a última conta de administrador.'], 409);
        }
        if ($user['role'] === 'admin') {
            $otherAdminId = (int) reset($adminIds);
            if ($otherAdminId === $user['id']) {
                $otherAdminId = (int) $adminIds[1];
            }
            $preserveReports = $pdo->prepare('UPDATE report_notes SET author_id = ? WHERE author_id = ?');
            $preserveReports->execute([$otherAdminId, $user['id']]);
        }
        $deleteUser = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $deleteUser->execute([$user['id']]);
        if ($user['role'] === 'supporter' && $user['supporter_id'] !== null) {
            $deleteSupporter = $pdo->prepare('DELETE FROM supporters WHERE id = ?');
            $deleteSupporter->execute([$user['supporter_id']]);
        }
        $pdo->commit();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        respond(['deleted' => true]);
    }
    if ($action === 'increment-people-served' && $method === 'POST') {
        require_role(['admin', 'osc']);
        $user = current_user();
        $input = request_data();
        $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
        $increment = filter_var($input['increment'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1 || $increment === false || $increment < 1 || $increment > 4294967295) {
            respond(['error' => 'Informe um registro válido e uma quantidade inteira maior que zero.'], 422);
        }
        $sql = 'UPDATE people_served SET quantity = quantity + ? WHERE id = ? AND quantity <= 4294967295 - ?';
        $values = [$increment, $id, $increment];
        if ($user['role'] === 'osc') {
            $sql .= ' AND project_id IN (SELECT id FROM projects WHERE organization = ?)';
            $values[] = $user['organization'];
        }
        $incrementQuery = $pdo->prepare($sql);
        $incrementQuery->execute($values);
        if ($incrementQuery->rowCount() !== 1) {
            respond(['error' => 'O registro não existe, não pertence à sua OSC ou excederia o limite permitido.'], 404);
        }
        respond(['updated' => true]);
    }

    if ($action === 'support-request-file' && $method === 'GET') {
        require_role(['admin', 'osc', 'supporter']);
        $requestId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$requestId) {
            respond(['error' => 'Solicitação inválida.'], 422);
        }
        $fileQuery = $pdo->prepare('SELECT sr.supporter_id, sr.evidence_url, p.organization FROM support_requests sr JOIN projects p ON p.id = sr.project_id WHERE sr.id = ?');
        $fileQuery->execute([$requestId]);
        $request = $fileQuery->fetch();
        if (!$request) {
            respond(['error' => 'Comprovante não encontrado.'], 404);
        }
        $user = current_user();
        if (($user['role'] === 'supporter' && $user['supporter_id'] !== (int) $request['supporter_id']) || ($user['role'] === 'osc' && $user['organization'] !== $request['organization'])) {
            respond(['error' => 'Você não tem acesso a este comprovante.'], 403);
        }
        $filename = (string) $request['evidence_url'];
        if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|gif|webp|pdf)$/', $filename)) {
            respond(['error' => 'Comprovante inválido.'], 404);
        }
        $filePath = dirname(__DIR__, 2) . '/var/support-evidence/' . $filename;
        if (!is_file($filePath)) {
            respond(['error' => 'Arquivo do comprovante não encontrado.'], 404);
        }
        $mime = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'pdf' => 'application/pdf'][pathinfo($filename, PATHINFO_EXTENSION)];
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="comprovante.' . pathinfo($filename, PATHINFO_EXTENSION) . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($filePath);
        exit;
    }

    if ($action === 'submit-support-request' && $method === 'POST') {
        require_role(['supporter']);
        $projectId = filter_var($_POST['project_id'] ?? null, FILTER_VALIDATE_INT);
        $indicatorInput = trim((string) ($_POST['indicator_id'] ?? ''));
        $indicatorId = $indicatorInput === '' ? null : filter_var($indicatorInput, FILTER_VALIDATE_INT);
        $type = trim((string) ($_POST['type'] ?? ''));
        $quantity = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_FLOAT);
        $description = trim((string) ($_POST['description'] ?? ''));
        if (!$projectId || $indicatorId === false || !in_array($type, ['Dinheiro', 'Material', 'Voluntariado'], true) || $quantity === false || !is_finite((float) $quantity) || $quantity <= 0 || strlen($description) > 2000) {
            respond(['error' => 'Confira o projeto, tipo, quantidade e descrição do apoio.'], 422);
        }
        $projectCheck = $pdo->prepare('SELECT COUNT(*) FROM projects WHERE id = ?');
        $projectCheck->execute([$projectId]);
        if ((int) $projectCheck->fetchColumn() !== 1) {
            respond(['error' => 'Selecione um projeto válido.'], 422);
        }
        if ($indicatorId !== null) {
            $indicatorCheck = $pdo->prepare('SELECT COUNT(*) FROM indicators WHERE id = ? AND project_id = ?');
            $indicatorCheck->execute([$indicatorId, $projectId]);
            if ((int) $indicatorCheck->fetchColumn() !== 1) {
                respond(['error' => 'O indicador selecionado não pertence ao projeto.'], 422);
            }
        }
        $file = $_FILES['file'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 25 * 1024 * 1024) {
            respond(['error' => 'Anexe um comprovante válido de até 25 MB.'], 422);
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
        if (!isset($extensions[$mime])) {
            respond(['error' => 'O comprovante deve ser uma imagem ou PDF.'], 422);
        }
        $uploadDirectory = dirname(__DIR__, 2) . '/var/support-evidence';
        if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0770, true) && !is_dir($uploadDirectory)) {
            throw new RuntimeException('Não foi possível criar a pasta de comprovantes.');
        }
        $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
        if (!move_uploaded_file($file['tmp_name'], $uploadDirectory . '/' . $filename)) {
            throw new RuntimeException('Não foi possível armazenar o comprovante.');
        }
        $insert = $pdo->prepare('INSERT INTO support_requests (supporter_id, project_id, indicator_id, type, quantity, description, evidence_url) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $insert->execute([current_user()['supporter_id'], $projectId, $indicatorId, $type, $quantity, $description, $filename]);
        respond(['created' => true, 'id' => (int) $pdo->lastInsertId()], 201);
    }

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
        $supporterQuery = $pdo->prepare('SELECT name FROM supporters WHERE id = ?');
        $supporterQuery->execute([$supporterId]);
        $statement = $pdo->prepare("SELECT r.date, p.name AS project, r.type, r.quantity, r.usage
            FROM resources r LEFT JOIN projects p ON p.id = r.project_id
            WHERE r.supporter_id = ? ORDER BY r.date DESC, r.id DESC");
        $statement->execute([$supporterId]);
        $resources = $statement->fetchAll();
        $requestsQuery = $pdo->prepare('SELECT sr.id, p.name AS project, sr.type, sr.quantity, sr.description, sr.evidence_url, sr.status, sr.created_at, i.name AS indicator FROM support_requests sr JOIN projects p ON p.id = sr.project_id LEFT JOIN indicators i ON i.id = sr.indicator_id WHERE sr.supporter_id = ? ORDER BY sr.created_at DESC, sr.id DESC');
        $requestsQuery->execute([$supporterId]);
        $projects = $pdo->query('SELECT p.id, p.name, p.organization FROM projects p ORDER BY p.name')->fetchAll();
        $indicators = $pdo->query('SELECT i.id, i.project_id, i.name FROM indicators i JOIN projects p ON p.id = i.project_id ORDER BY p.name, i.name')->fetchAll();
        $requests = $requestsQuery->fetchAll();
        foreach ($requests as &$request) {
            $request['evidence_url'] = '../php/index.php?action=support-request-file&id=' . $request['id'];
        }
        unset($request);
        respond(['name' => $supporterQuery->fetchColumn(), 'resources' => $resources, 'total' => count($resources), 'requests' => $requests, 'projects' => $projects, 'indicators' => $indicators]);
    }

    if ($action === 'support-requests' && $method === 'GET') {
        require_role(['admin', 'osc']);
        $query = 'SELECT sr.id, sr.project_id, sr.indicator_id, sr.type, sr.quantity, sr.description, sr.evidence_url, sr.created_at, s.name AS supporter, p.name AS project, p.organization, i.name AS indicator FROM support_requests sr JOIN supporters s ON s.id = sr.supporter_id JOIN projects p ON p.id = sr.project_id LEFT JOIN indicators i ON i.id = sr.indicator_id WHERE sr.status = ?';
        $values = ['pending'];
        if (current_user()['role'] === 'osc') {
            $query .= ' AND p.organization = ?';
            $values[] = current_user()['organization'];
        }
        $query .= ' ORDER BY sr.created_at, sr.id';
        $requests = $pdo->prepare($query);
        $requests->execute($values);
        $pendingRequests = $requests->fetchAll();
        foreach ($pendingRequests as &$pendingRequest) {
            $pendingRequest['evidence_url'] = '../php/index.php?action=support-request-file&id=' . $pendingRequest['id'];
        }
        unset($pendingRequest);
        respond($pendingRequests);
    }

    if ($action === 'review-support-request' && $method === 'POST') {
        require_role(['admin', 'osc']);
        $input = request_data();
        $requestId = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
        $decision = (string) ($input['decision'] ?? '');
        if (!$requestId || !in_array($decision, ['approve', 'reject'], true)) {
            respond(['error' => 'Solicitação ou decisão inválida.'], 422);
        }
        $pdo->beginTransaction();
        $requestQuery = $pdo->prepare('SELECT sr.*, p.organization FROM support_requests sr JOIN projects p ON p.id = sr.project_id WHERE sr.id = ? AND sr.status = ?');
        $requestQuery->execute([$requestId, 'pending']);
        $supportRequest = $requestQuery->fetch();
        if (!$supportRequest) {
            $pdo->rollBack();
            respond(['error' => 'Esta solicitação não está mais pendente.'], 409);
        }
        if (current_user()['role'] === 'osc' && $supportRequest['organization'] !== current_user()['organization']) {
            $pdo->rollBack();
            respond(['error' => 'Esta solicitação pertence a outra OSC.'], 403);
        }
        $resourceId = null;
        if ($decision === 'approve') {
            $resourceInsert = $pdo->prepare('INSERT INTO resources (date, project_id, type, quantity, supporter_id, indicator_id, `usage`) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $resourceInsert->execute([date('Y-m-d'), $supportRequest['project_id'], $supportRequest['type'], $supportRequest['quantity'], $supportRequest['supporter_id'], $supportRequest['indicator_id'], $supportRequest['description']]);
            $resourceId = (int) $pdo->lastInsertId();
        }
        $status = $decision === 'approve' ? 'approved' : 'rejected';
        $update = $pdo->prepare('UPDATE support_requests SET status = ?, resource_id = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP WHERE id = ? AND status = ?');
        $update->execute([$status, $resourceId, current_user()['id'], $requestId, 'pending']);
        if ($update->rowCount() !== 1) {
            $pdo->rollBack();
            respond(['error' => 'Esta solicitação já foi analisada.'], 409);
        }
        $pdo->commit();
        respond(['status' => $status]);
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
            'peopleServed' => entity_rows($pdo, 'people_served', $filters),
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
    if ($role === 'osc' && !in_array($entity, ['projects', 'resources', 'indicators', 'people_served', 'evidences'], true)) {
        respond(['error' => 'Sua conta OSC não tem acesso a este cadastro.'], 403);
    }

    if ($method === 'GET') {
        $filters = [];
        if ($entity === 'indicators' && isset($_GET['project_id'])) {
            $projectId = filter_var($_GET['project_id'], FILTER_VALIDATE_INT);
            if ($projectId === false || $projectId < 1) {
                respond(['error' => 'Identificador de projeto inválido.'], 422);
            }
            $filters['project_id'] = $projectId;
        }
        respond(entity_rows($pdo, $entity, scoped_filters($filters)));
    }

    if ($method === 'POST' || $method === 'PUT') {
        $input = request_data();
        $values = [];
        foreach ($definition['fields'] as $field) {
            $value = $input[$field] ?? null;
            if ($role === 'osc' && $entity === 'projects' && $field === 'organization') {
                $value = current_user()['organization'];
            }
            if (in_array($field, ['project_id', 'supporter_id', 'indicator_id'], true)) {
                $value = $value === '' || $value === null ? null : filter_var($value, FILTER_VALIDATE_INT);
                if ($value === false) {
                    respond(['error' => "O campo {$field} precisa ser um identificador válido."], 422);
                }
            } elseif ($field === 'people_when_met' || ($entity === 'people_served' && $field === 'quantity')) {
                $value = $value === '' || $value === null ? null : filter_var($value, FILTER_VALIDATE_INT);
                if ($value !== null && ($value === false || $value < 0 || ($entity === 'people_served' && $field === 'quantity' && $value < 1))) {
                    respond(['error' => "O campo {$field} precisa ser um número inteiro válido."], 422);
                }
            } elseif (in_array($field, ['target', 'achieved', 'quantity'], true)) {
                $value = $value === '' || $value === null ? null : filter_var($value, FILTER_VALIDATE_FLOAT);
                if ($value !== null && ($value === false || $value < 0)) {
                    respond(['error' => "O campo {$field} precisa ser um número igual ou maior que zero."], 422);
                }
            } elseif ($value !== null) {
                $value = trim((string) $value);
            }
            if ($entity === 'people_served' && $field === 'description') {
                $value = trim((string) ($value ?? ''));
                if (strlen($value) > 500) {
                    respond(['error' => 'A observação pode ter no máximo 500 caracteres.'], 422);
                }
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
        if ($entity === 'resources' && $values['indicator_id'] !== null) {
            $indicatorCheck = $pdo->prepare('SELECT COUNT(*) FROM indicators WHERE id = ? AND project_id = ?');
            $indicatorCheck->execute([$values['indicator_id'], $values['project_id']]);
            if ((int) $indicatorCheck->fetchColumn() !== 1) {
                respond(['error' => 'O indicador selecionado não pertence ao projeto.'], 422);
            }
        }
        if ($role === 'osc' && in_array($entity, ['resources', 'indicators', 'evidences', 'people_served'], true)) {
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
            if ($entity === 'people_served') {
                $values['created_by'] = current_user()['id'];
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