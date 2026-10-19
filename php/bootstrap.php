<?php
declare(strict_types=1);

function database(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = getenv('UOSCC_DB_DSN');
    if ($dsn !== false && $dsn !== '') {
        $pdo = new PDO($dsn, getenv('UOSCC_DB_USER') ?: null, getenv('UOSCC_DB_PASSWORD') ?: null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        if (str_starts_with($dsn, 'sqlite:')) {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
        return $pdo;
    }

    $databasePath = getenv('UOSCC_DB_PATH') ?: dirname(__DIR__, 2) . '/var/uoscc.sqlite';
    $directory = dirname($databasePath);
    if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
        throw new RuntimeException('Não foi possível criar a pasta do banco de dados.');
    }

    $pdo = new PDO('sqlite:' . $databasePath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    return $pdo;
}

function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function request_data(): array
{
    $data = json_decode((string) file_get_contents('php://input'), true);
    return is_array($data) ? $data : [];
}

function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        respond(['error' => 'Faça login para continuar.'], 401);
    }
}

function current_user(): array
{
    return [
        'id' => (int) ($_SESSION['user_id'] ?? 0),
        'email' => (string) ($_SESSION['email'] ?? ''),
        'name' => (string) ($_SESSION['display_name'] ?? $_SESSION['email'] ?? ''),
        'role' => (string) ($_SESSION['role'] ?? ''),
        'organization' => $_SESSION['organization'] ?? null,
        'supporter_id' => isset($_SESSION['supporter_id']) ? (int) $_SESSION['supporter_id'] : null,
    ];
}

function require_role(array $roles): void
{
    require_login();
    if (!in_array(current_user()['role'], $roles, true)) {
        respond(['error' => 'Seu perfil não tem permissão para esta operação.'], 403);
    }
}

function osc_areas(): array
{
    return [
        'Educação e formação cidadã',
        'Meio ambiente e sustentabilidade',
        'Saúde e bem-estar comunitário',
        'Assistência social e apoio às famílias',
        'Direitos humanos e inclusão social',
        'Cultura, esporte e lazer',
    ];
}

function scoped_filters(array $filters): array
{
    $user = current_user();
    if ($user['role'] === 'osc') {
        $filters['organization'] = $user['organization'];
    }
    return $filters;
}

function entity_fields(): array
{
    return [
        'projects' => [
            'table' => 'projects',
            'fields' => ['name', 'organization'],
            'required' => ['name', 'organization'],
        ],
        'supporters' => [
            'table' => 'supporters',
            'fields' => ['name', 'type'],
            'required' => ['name', 'type'],
        ],
        'resources' => [
            'table' => 'resources',
            'fields' => ['date', 'project_id', 'type', 'quantity', 'supporter_id', 'indicator_id', 'usage'],
            'required' => ['date', 'project_id', 'type', 'quantity'],
        ],
        'indicators' => [
            'table' => 'indicators',
            'fields' => ['project_id', 'name', 'date', 'target', 'achieved', 'people_when_met'],
            'required' => ['project_id', 'name', 'date', 'target', 'achieved', 'people_when_met'],
        ],
        'people_served' => [
            'table' => 'people_served',
            'fields' => ['project_id', 'date', 'quantity', 'description'],
            'required' => ['project_id', 'date', 'quantity'],
        ],
        'evidences' => [
            'table' => 'evidences',
            'fields' => ['project_id', 'title', 'type', 'url', 'date', 'description'],
            'required' => ['project_id', 'title', 'type', 'url', 'date'],
        ],
    ];
}

function date_filters(string $alias, array $filters): array
{
    $conditions = [];
    $values = [];
    foreach (['from' => '>=', 'to' => '<='] as $field => $operator) {
        if (!empty($filters[$field])) {
            $conditions[] = "{$alias}.date {$operator} ?";
            $values[] = $filters[$field];
        }
    }
    return [$conditions, $values];
}

function organization_filter(string $alias, array $filters): array
{
    return empty($filters['organization']) ? [[], []] : [[$alias . '.organization = ?'], [$filters['organization']]];
}

function entity_rows(PDO $pdo, string $entity, array $filters = []): array
{
    $supporterColumns = current_user()['role'] === 'admin'
        ? 'r.supporter_id, s.name AS supporter'
        : 'NULL AS supporter_id, NULL AS supporter';
    [$projectIndicatorDates, $projectIndicatorValues] = date_filters('i', $filters);
    [$projectResourceDates, $projectResourceValues] = date_filters('r', $filters);
    $projectIndicatorWhere = $projectIndicatorDates ? ' AND ' . implode(' AND ', $projectIndicatorDates) : '';
    $projectResourceWhere = $projectResourceDates ? ' AND ' . implode(' AND ', $projectResourceDates) : '';
    [$linkedResourceDates, $linkedResourceValues] = date_filters('lr', $filters);
    $linkedResourceWhere = $linkedResourceDates ? ' WHERE ' . implode(' AND ', $linkedResourceDates) : '';
    $where = [];
    $values = [];
    if ($entity === 'projects') {
        [$where, $values] = organization_filter('p', $filters);
    } elseif (in_array($entity, ['resources', 'indicators', 'evidences', 'people_served'], true)) {
        $dateAlias = ['resources' => 'r', 'indicators' => 'i', 'evidences' => 'e', 'people_served' => 'ps'][$entity];
        [$where, $values] = date_filters($dateAlias, $filters);
        [$organizationWhere, $organizationValues] = organization_filter('p', $filters);
        $where = [...$where, ...$organizationWhere];
        $values = [...$values, ...$organizationValues];
        if ($entity === 'indicators' && !empty($filters['project_id'])) {
            $where[] = 'i.project_id = ?';
            $values[] = (int) $filters['project_id'];
        }
    }
    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $queries = [
        'projects' => "SELECT p.id, p.name, p.organization,
            (SELECT COUNT(*) FROM indicators i WHERE i.project_id = p.id{$projectIndicatorWhere}) AS indicators,
            (SELECT COALESCE(SUM(r.quantity), 0) FROM resources r WHERE r.project_id = p.id AND r.type = 'Dinheiro'{$projectResourceWhere}) AS received
            FROM projects p{$whereSql} ORDER BY p.name",
        'supporters' => "SELECT s.id, s.name, s.type,
            s.public_token, COALESCE(SUM(r.quantity), 0) AS total
            FROM supporters s LEFT JOIN resources r ON r.supporter_id = s.id AND r.type = 'Dinheiro'
            GROUP BY s.id ORDER BY s.name",
        'resources' => "SELECT r.id, r.date, r.project_id, p.name AS project, r.type, r.quantity,
            r.indicator_id, i.name AS indicator, {$supporterColumns}, r.`usage` AS `usage`
            FROM resources r LEFT JOIN projects p ON p.id = r.project_id
            LEFT JOIN supporters s ON s.id = r.supporter_id
            LEFT JOIN indicators i ON i.id = r.indicator_id{$whereSql} ORDER BY r.date DESC, r.id DESC",
        'indicators' => "SELECT i.id, i.project_id, p.name AS project, i.name, i.date, i.target,
            i.achieved AS manualAchieved, i.achieved + COALESCE(linked.total, 0) AS achieved,
            i.people_when_met,
            CASE WHEN i.target = 0 THEN 0 ELSE ROUND((i.achieved + COALESCE(linked.total, 0)) * 100.0 / i.target) END AS progress
            FROM indicators i LEFT JOIN projects p ON p.id = i.project_id{$whereSql} ORDER BY p.name, i.name",
        'people_served' => "SELECT ps.id, ps.project_id, p.name AS project, p.organization, ps.date, ps.quantity, ps.description
            FROM people_served ps JOIN projects p ON p.id = ps.project_id{$whereSql} ORDER BY ps.date DESC, ps.id DESC",
        'evidences' => "SELECT e.id, e.project_id, p.name AS project, e.title, e.type, e.url, e.date, e.description
            FROM evidences e LEFT JOIN projects p ON p.id = e.project_id{$whereSql} ORDER BY e.date DESC, e.id DESC",
    ];

    if (!isset($queries[$entity])) {
        respond(['error' => 'Recurso não encontrado.'], 404);
    }
    if ($entity === 'indicators') {
        $queries['indicators'] = str_replace(
            'FROM indicators i LEFT JOIN projects p',
            "FROM indicators i LEFT JOIN (SELECT lr.indicator_id, SUM(lr.quantity) AS total FROM resources lr{$linkedResourceWhere} GROUP BY lr.indicator_id) linked ON linked.indicator_id = i.id LEFT JOIN projects p",
            $queries['indicators'],
        );
    }
    $statement = $pdo->prepare($queries[$entity]);
    $statement->execute($entity === 'projects'
        ? [...$projectIndicatorValues, ...$projectResourceValues, ...$values]
        : ($entity === 'indicators' ? [...$linkedResourceValues, ...$values] : $values));
    return $statement->fetchAll();
}

function dashboard_data(PDO $pdo, array $filters = []): array
{
    [$resourceDates, $resourceValues] = date_filters('r', $filters);
    [$resourceOrganization, $resourceOrganizationValues] = organization_filter('p', $filters);
    $resourceWhere = [...$resourceDates, ...$resourceOrganization];
    $resourceWhereSql = $resourceWhere ? ' AND ' . implode(' AND ', $resourceWhere) : '';
    [$indicatorDates, $indicatorValues] = date_filters('i', $filters);
    [$linkedResourceDates, $linkedResourceValues] = date_filters('lr', $filters);
    $linkedResourceWhere = $linkedResourceDates ? ' AND ' . implode(' AND ', $linkedResourceDates) : '';
    [$indicatorOrganization, $indicatorOrganizationValues] = organization_filter('p', $filters);
    $indicatorWhere = [...$indicatorDates, ...$indicatorOrganization];
    $indicatorWhereSql = $indicatorWhere ? ' WHERE ' . implode(' AND ', $indicatorWhere) : '';
    $indicatorAndSql = $indicatorWhere ? ' AND ' . implode(' AND ', $indicatorWhere) : '';
    [$evidenceDates, $evidenceValues] = date_filters('e', $filters);
    [$evidenceOrganization, $evidenceOrganizationValues] = organization_filter('p', $filters);
    $evidenceWhere = [...$evidenceDates, ...$evidenceOrganization];
    $evidenceWhereSql = $evidenceWhere ? ' WHERE ' . implode(' AND ', $evidenceWhere) : '';

    $moneyQuery = $pdo->prepare("SELECT COALESCE(SUM(r.quantity), 0) FROM resources r LEFT JOIN projects p ON p.id = r.project_id WHERE r.type = 'Dinheiro'{$resourceWhereSql}");
    $moneyQuery->execute([...$resourceValues, ...$resourceOrganizationValues]);
    $volunteerQuery = $pdo->prepare("SELECT COALESCE(SUM(r.quantity), 0) FROM resources r LEFT JOIN projects p ON p.id = r.project_id WHERE r.type = 'Voluntariado'{$resourceWhereSql}");
    $volunteerQuery->execute([...$resourceValues, ...$resourceOrganizationValues]);
    [$servedDates, $servedValues] = date_filters('ps', $filters);
    [$servedOrganization, $servedOrganizationValues] = organization_filter('p', $filters);
    $servedWhere = [...$servedDates, ...$servedOrganization];
    $servedWhereSql = $servedWhere ? ' WHERE ' . implode(' AND ', $servedWhere) : '';
    $servedQuery = $pdo->prepare("SELECT COALESCE(SUM(ps.quantity), 0) FROM people_served ps JOIN projects p ON p.id = ps.project_id{$servedWhereSql}");
    $servedQuery->execute([...$servedValues, ...$servedOrganizationValues]);
    $legacyPeopleQuery = $pdo->prepare("SELECT COALESCE(SUM(i.achieved + COALESCE((SELECT SUM(lr.quantity) FROM resources lr WHERE lr.indicator_id = i.id{$linkedResourceWhere}), 0)), 0) FROM indicators i JOIN projects p ON p.id = i.project_id WHERE lower(i.name) LIKE '%pessoa%'{$indicatorAndSql}");
    $legacyPeopleQuery->execute([...$linkedResourceValues, ...$indicatorValues, ...$indicatorOrganizationValues]);
    $goalPeopleQuery = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN i.achieved + COALESCE((SELECT SUM(lr.quantity) FROM resources lr WHERE lr.indicator_id = i.id{$linkedResourceWhere}), 0) >= i.target THEN i.people_when_met ELSE 0 END), 0) FROM indicators i JOIN projects p ON p.id = i.project_id{$indicatorWhereSql}");
    $goalPeopleQuery->execute([...$linkedResourceValues, ...$indicatorValues, ...$indicatorOrganizationValues]);
    $goalsQuery = $pdo->prepare("SELECT COUNT(*), SUM(CASE WHEN i.achieved + COALESCE((SELECT SUM(lr.quantity) FROM resources lr WHERE lr.indicator_id = i.id{$linkedResourceWhere}), 0) >= i.target THEN 1 ELSE 0 END) FROM indicators i JOIN projects p ON p.id = i.project_id{$indicatorWhereSql}");
    $goalsQuery->execute([...$linkedResourceValues, ...$indicatorValues, ...$indicatorOrganizationValues]);
    $goals = $goalsQuery->fetch(PDO::FETCH_NUM);
    $evidenceQuery = $pdo->prepare("SELECT COUNT(*) FROM evidences e JOIN projects p ON p.id = e.project_id{$evidenceWhereSql}");
    $evidenceQuery->execute([...$evidenceValues, ...$evidenceOrganizationValues]);
    return [
        'money' => (float) $moneyQuery->fetchColumn(),
        'volunteer' => (float) $volunteerQuery->fetchColumn(),
        'people' => (int) $servedQuery->fetchColumn() + (int) $legacyPeopleQuery->fetchColumn() + (int) $goalPeopleQuery->fetchColumn(),
        'completedGoals' => (int) ($goals[1] ?? 0),
        'totalGoals' => (int) ($goals[0] ?? 0),
        'evidences' => (int) $evidenceQuery->fetchColumn(),
    ];
}