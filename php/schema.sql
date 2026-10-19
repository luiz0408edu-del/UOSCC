CREATE TABLE IF NOT EXISTS supporters (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    type VARCHAR(50) NOT NULL,
    public_token CHAR(32) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(254) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'admin',
    display_name VARCHAR(200) NULL,
    organization VARCHAR(200) NULL,
    supporter_id BIGINT UNSIGNED NULL UNIQUE,
    preferences TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_supporter FOREIGN KEY (supporter_id) REFERENCES supporters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_notes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    organization VARCHAR(200) NOT NULL DEFAULT '',
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    title VARCHAR(200) NOT NULL,
    narrative TEXT NOT NULL,
    author_id BIGINT UNSIGNED NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_report_notes_period (organization, period_start, period_end),
    CONSTRAINT fk_report_notes_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS projects (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    organization VARCHAR(200) NOT NULL,
    INDEX idx_projects_organization (organization)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS people_served (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    project_id BIGINT UNSIGNED NOT NULL,
    date DATE NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    description VARCHAR(500) NOT NULL DEFAULT '',
    created_by BIGINT UNSIGNED NULL,
    INDEX idx_people_served_project_date (project_id, date),
    CONSTRAINT fk_people_served_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_people_served_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS indicators (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    project_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(200) NOT NULL,
    date DATE NOT NULL,
    target DECIMAL(15, 2) NOT NULL,
    achieved DECIMAL(15, 2) NOT NULL DEFAULT 0,
    people_when_met INT UNSIGNED NOT NULL DEFAULT 0,
    INDEX idx_indicators_project (project_id),
    CONSTRAINT fk_indicators_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS resources (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    project_id BIGINT UNSIGNED NOT NULL,
    type VARCHAR(40) NOT NULL,
    quantity DECIMAL(15, 2) NOT NULL,
    supporter_id BIGINT UNSIGNED NULL,
    indicator_id BIGINT UNSIGNED NULL,
    `usage` TEXT NOT NULL,
    INDEX idx_resources_date (date),
    INDEX idx_resources_project (project_id),
    INDEX idx_resources_supporter (supporter_id),
    INDEX idx_resources_indicator (indicator_id),
    CONSTRAINT fk_resources_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE RESTRICT,
    CONSTRAINT fk_resources_supporter FOREIGN KEY (supporter_id) REFERENCES supporters(id) ON DELETE SET NULL,
    CONSTRAINT fk_resources_indicator FOREIGN KEY (indicator_id) REFERENCES indicators(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evidences (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    project_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    type VARCHAR(40) NOT NULL,
    url VARCHAR(500) NOT NULL,
    description TEXT NOT NULL,
    date DATE NOT NULL,
    INDEX idx_evidences_project (project_id),
    INDEX idx_evidences_date (date),
    CONSTRAINT fk_evidences_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    supporter_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NOT NULL,
    indicator_id BIGINT UNSIGNED NULL,
    type VARCHAR(40) NOT NULL,
    quantity DECIMAL(15, 2) NOT NULL,
    description TEXT NOT NULL,
    evidence_url VARCHAR(500) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    resource_id BIGINT UNSIGNED NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    INDEX idx_support_requests_status (status),
    INDEX idx_support_requests_project (project_id),
    CONSTRAINT fk_support_requests_supporter FOREIGN KEY (supporter_id) REFERENCES supporters(id) ON DELETE CASCADE,
    CONSTRAINT fk_support_requests_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_support_requests_indicator FOREIGN KEY (indicator_id) REFERENCES indicators(id) ON DELETE SET NULL,
    CONSTRAINT fk_support_requests_resource FOREIGN KEY (resource_id) REFERENCES resources(id) ON DELETE SET NULL,
    CONSTRAINT fk_support_requests_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
