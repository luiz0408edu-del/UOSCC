-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 26/10/2026 às 18:37
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `uoscc`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `evidences`
--

CREATE TABLE `evidences` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `project_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `type` varchar(40) NOT NULL,
  `url` varchar(500) NOT NULL,
  `description` text NOT NULL,
  `date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `evidences`
--

INSERT INTO `evidences` (`id`, `project_id`, `title`, `type`, `url`, `description`, `date`) VALUES
(2, 11, 'crianças sendo alimentadas', 'Foto', '../uploads/evidence/741e5a3472084b8fb85d446500b6bf71.png', 'o ato realizado', '2026-10-23');

-- --------------------------------------------------------

--
-- Estrutura para tabela `indicators`
--

CREATE TABLE `indicators` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `project_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(200) NOT NULL,
  `date` date NOT NULL,
  `target` decimal(15,2) NOT NULL,
  `achieved` decimal(15,2) NOT NULL DEFAULT 0.00,
  `people_when_met` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `indicators`
--

INSERT INTO `indicators` (`id`, `project_id`, `name`, `date`, `target`, `achieved`, `people_when_met`) VALUES
(3, 11, 'Indicador teste', '2026-10-23', 1000.00, 0.00, 1000);

-- --------------------------------------------------------

--
-- Estrutura para tabela `people_served`
--

CREATE TABLE `people_served` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `project_id` bigint(20) UNSIGNED NOT NULL,
  `date` date NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL,
  `description` varchar(500) NOT NULL DEFAULT '',
  `created_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `projects`
--

CREATE TABLE `projects` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(200) NOT NULL,
  `organization` varchar(200) NOT NULL,
  `accepted_resources` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `projects`
--

INSERT INTO `projects` (`id`, `name`, `organization`, `accepted_resources`) VALUES
(11, 'Projeto teste', 'Educação e formação cidadã', '[\"Dinheiro\",\"Material\",\"Voluntariado\"]');

-- --------------------------------------------------------

--
-- Estrutura para tabela `report_notes`
--

CREATE TABLE `report_notes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `organization` varchar(200) NOT NULL DEFAULT '',
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `title` varchar(200) NOT NULL,
  `narrative` text NOT NULL,
  `author_id` bigint(20) UNSIGNED NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `resources`
--

CREATE TABLE `resources` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `date` date NOT NULL,
  `project_id` bigint(20) UNSIGNED NOT NULL,
  `type` varchar(40) NOT NULL,
  `quantity` decimal(15,2) NOT NULL,
  `supporter_id` bigint(20) UNSIGNED DEFAULT NULL,
  `usage` text NOT NULL,
  `indicator_id` bigint(20) UNSIGNED DEFAULT NULL,
  `material_name` varchar(200) DEFAULT NULL,
  `unit` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `resources`
--

INSERT INTO `resources` (`id`, `date`, `project_id`, `type`, `quantity`, `supporter_id`, `usage`, `indicator_id`, `material_name`, `unit`) VALUES
(15, '2026-10-23', 11, 'Dinheiro', 100.00, 25, '100 reais no pix', 3, NULL, NULL),
(16, '2026-10-23', 11, 'Voluntariado', 6.00, 26, 'catando lixo', 3, NULL, NULL),
(17, '2026-10-23', 11, 'Material', 1.00, 25, 'sofa de couro', 3, 'sofa', 'unidade');

-- --------------------------------------------------------

--
-- Estrutura para tabela `supporters`
--

CREATE TABLE `supporters` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(200) NOT NULL,
  `type` varchar(50) NOT NULL,
  `public_token` char(32) NOT NULL,
  `public_name_allowed` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `supporters`
--

INSERT INTO `supporters` (`id`, `name`, `type`, `public_token`, `public_name_allowed`) VALUES
(25, 'Rian', 'Pessoa', 'b99904e46657452457ab95be3c8ade6d', 1),
(26, 'Micao', 'Pessoa', '969e4320675fc64a818d730eadfe20fc', 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `support_requests`
--

CREATE TABLE `support_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `supporter_id` bigint(20) UNSIGNED NOT NULL,
  `project_id` bigint(20) UNSIGNED NOT NULL,
  `indicator_id` bigint(20) UNSIGNED DEFAULT NULL,
  `type` varchar(40) NOT NULL,
  `quantity` decimal(15,2) NOT NULL,
  `description` text NOT NULL,
  `evidence_url` varchar(500) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `resource_id` bigint(20) UNSIGNED DEFAULT NULL,
  `reviewed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `material_name` varchar(200) DEFAULT NULL,
  `unit` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `support_requests`
--

INSERT INTO `support_requests` (`id`, `supporter_id`, `project_id`, `indicator_id`, `type`, `quantity`, `description`, `evidence_url`, `status`, `resource_id`, `reviewed_by`, `created_at`, `reviewed_at`, `material_name`, `unit`) VALUES
(11, 25, 11, 3, 'Dinheiro', 100.00, '100 reais no pix', '1308313da298b1a19c8d724233328b74.png', 'approved', 15, 5, '2026-10-23 19:38:21', '2026-10-23 19:43:58', NULL, NULL),
(12, 25, 11, 3, 'Material', 1.00, 'sofa de couro', 'c3d68fbc899fa2ac0084ff24a7c9a51c.png', 'approved', 17, 5, '2026-10-23 19:40:44', '2026-10-23 20:15:26', 'sofa', 'unidade'),
(13, 26, 11, 3, 'Voluntariado', 6.00, 'catando lixo', '88d90365f0ac7aa74e3b166e28a49afc.png', 'approved', 16, 5, '2026-10-23 19:41:29', '2026-10-23 19:44:14', NULL, NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `email` varchar(254) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'admin',
  `display_name` varchar(200) DEFAULT NULL,
  `organization` varchar(200) DEFAULT NULL,
  `supporter_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `preferences` text DEFAULT NULL,
  `avatar_url` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `users`
--

INSERT INTO `users` (`id`, `email`, `password_hash`, `role`, `display_name`, `organization`, `supporter_id`, `created_at`, `preferences`, `avatar_url`) VALUES
(1, 'luiz4edu@outlook.com', '$2y$10$oPqs1A0LeqJt1Pvdxn8nW.P35H5SIwD9.SqR1eCclwbirdwv026TW', 'admin', 'luiz4edu', NULL, NULL, '2026-10-15 19:59:32', '{}', NULL),
(5, 'admin@uoscc.local', '$2y$10$S.oHDiHBMHZcIvMMwApxQupT4DsuzPXXXDxG9E7TPfEdV8/EKqzDS', 'admin', 'admin', NULL, NULL, '2026-10-19 18:56:43', '{\"theme\":\"light\",\"hiddenCards\":[],\"menuOrder\":[\"dashboard\",\"apoiador\",\"projetos\",\"apoiadores\",\"financeiro\",\"metas\",\"evidencias\",\"relatorios\",\"publico\",\"contas\",\"configuracoes\",\"conta\"],\"cardOrder\":[\"dinheiro\",\"voluntariado\",\"pessoas\",\"metas\",\"evidencias\"]}', NULL),
(33, 'rian456@outlook.com', '$2y$10$wrodzsmU8JOspkHVCU.uJOLBTrLvywtBqdPvLQtsRNZ0NZHnoOcQS', 'supporter', 'Rian', NULL, 25, '2026-10-23 18:32:20', '{\"theme\":\"light\",\"hiddenCards\":[],\"menuOrder\":[\"apoiador\",\"dashboard\",\"projetos\",\"apoiadores\",\"financeiro\",\"metas\",\"evidencias\",\"relatorios\",\"conta\",\"publico\",\"configuracoes\",\"contas\"],\"cardOrder\":[]}', '../uploads/avatars/7ccc323b10354b915abbbe014762e568.jpg'),
(34, 'micao456@gmail.com', '$2y$10$ABpzO5frLVzXl8jRDGV5teCxZAvMbzMiCQHjqvRdA6Qf.VFDaAIaq', 'supporter', 'Micao', NULL, 26, '2026-10-23 18:32:34', NULL, '../uploads/avatars/5a37e187c2ec067b0ffa6f72aee5ac12.png');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `evidences`
--
ALTER TABLE `evidences`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_evidences_project` (`project_id`),
  ADD KEY `idx_evidences_date` (`date`);

--
-- Índices de tabela `indicators`
--
ALTER TABLE `indicators`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_indicators_project` (`project_id`);

--
-- Índices de tabela `people_served`
--
ALTER TABLE `people_served`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_people_served_project_date` (`project_id`,`date`),
  ADD KEY `fk_people_served_creator` (`created_by`);

--
-- Índices de tabela `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_projects_organization` (`organization`);

--
-- Índices de tabela `report_notes`
--
ALTER TABLE `report_notes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_report_notes_period` (`organization`,`period_start`,`period_end`),
  ADD KEY `fk_report_notes_author` (`author_id`);

--
-- Índices de tabela `resources`
--
ALTER TABLE `resources`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_resources_date` (`date`),
  ADD KEY `idx_resources_project` (`project_id`),
  ADD KEY `idx_resources_supporter` (`supporter_id`),
  ADD KEY `idx_resources_indicator` (`indicator_id`);

--
-- Índices de tabela `supporters`
--
ALTER TABLE `supporters`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `public_token` (`public_token`);

--
-- Índices de tabela `support_requests`
--
ALTER TABLE `support_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_support_requests_status` (`status`),
  ADD KEY `idx_support_requests_project` (`project_id`),
  ADD KEY `fk_support_requests_supporter` (`supporter_id`),
  ADD KEY `fk_support_requests_indicator` (`indicator_id`),
  ADD KEY `fk_support_requests_resource` (`resource_id`),
  ADD KEY `fk_support_requests_reviewer` (`reviewed_by`);

--
-- Índices de tabela `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `supporter_id` (`supporter_id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `evidences`
--
ALTER TABLE `evidences`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `indicators`
--
ALTER TABLE `indicators`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `people_served`
--
ALTER TABLE `people_served`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `projects`
--
ALTER TABLE `projects`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de tabela `report_notes`
--
ALTER TABLE `report_notes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `resources`
--
ALTER TABLE `resources`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de tabela `supporters`
--
ALTER TABLE `supporters`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT de tabela `support_requests`
--
ALTER TABLE `support_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de tabela `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `evidences`
--
ALTER TABLE `evidences`
  ADD CONSTRAINT `fk_evidences_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `indicators`
--
ALTER TABLE `indicators`
  ADD CONSTRAINT `fk_indicators_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `people_served`
--
ALTER TABLE `people_served`
  ADD CONSTRAINT `fk_people_served_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_people_served_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `report_notes`
--
ALTER TABLE `report_notes`
  ADD CONSTRAINT `fk_report_notes_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `resources`
--
ALTER TABLE `resources`
  ADD CONSTRAINT `fk_resources_indicator` FOREIGN KEY (`indicator_id`) REFERENCES `indicators` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_resources_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
  ADD CONSTRAINT `fk_resources_supporter` FOREIGN KEY (`supporter_id`) REFERENCES `supporters` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `support_requests`
--
ALTER TABLE `support_requests`
  ADD CONSTRAINT `fk_support_requests_indicator` FOREIGN KEY (`indicator_id`) REFERENCES `indicators` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_support_requests_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_support_requests_resource` FOREIGN KEY (`resource_id`) REFERENCES `resources` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_support_requests_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_support_requests_supporter` FOREIGN KEY (`supporter_id`) REFERENCES `supporters` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_supporter` FOREIGN KEY (`supporter_id`) REFERENCES `supporters` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
