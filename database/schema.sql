-- Schema do Banco de Dados Aquabeat Relatórios
-- Compatível com WordPress para migração futura

-- Tabela de Vendas (todos os 25 campos do CSV)
CREATE TABLE IF NOT EXISTS `aq_vendas` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `titulo_id` VARCHAR(50) NOT NULL COMMENT 'SFA-XXXX',
  `produto_original` VARCHAR(255) DEFAULT NULL,
  `produto_atual` VARCHAR(255) DEFAULT NULL,
  `alterou_vagas` VARCHAR(10) DEFAULT NULL,
  `categoria` VARCHAR(100) DEFAULT NULL,
  `data_cadastro` DATETIME DEFAULT NULL,
  `data_venda` DATETIME DEFAULT NULL,
  `origem_venda` VARCHAR(100) DEFAULT NULL,
  `telefone` VARCHAR(50) DEFAULT NULL,
  `status` VARCHAR(50) DEFAULT NULL,
  `consultor` VARCHAR(255) DEFAULT NULL,
  `gerente` VARCHAR(255) DEFAULT NULL,
  `titular` VARCHAR(255) DEFAULT NULL,
  `cpf` VARCHAR(14) DEFAULT NULL,
  `cpf_limpo` VARCHAR(11) DEFAULT NULL,
  `quantidade_parcelas_venda` INT(11) DEFAULT 0,
  `parcelas` TEXT DEFAULT NULL,
  `valores_pagos` TEXT DEFAULT NULL,
  `forma_pagamento` VARCHAR(100) DEFAULT NULL,
  `parcelas_pagas` INT(11) DEFAULT 0,
  `tipo_pagamento` VARCHAR(50) DEFAULT NULL,
  `valor_parcela` DECIMAL(10,2) DEFAULT 0.00,
  `valor_total` DECIMAL(10,2) DEFAULT 0.00,
  `valor_pago` DECIMAL(10,2) DEFAULT 0.00,
  `valor_restante` DECIMAL(10,2) DEFAULT 0.00,
  `parcelas_restantes` INT(11) DEFAULT 0,

  -- Campos calculados
  `num_vagas` INT(11) DEFAULT 0,
  `e_vista` TINYINT(1) DEFAULT 0,
  `primeira_parcela_paga` TINYINT(1) DEFAULT 0,
  `produto_alterado` TINYINT(1) DEFAULT 0,
  `data_para_filtro` DATETIME DEFAULT NULL,
  `data_para_pontuacao` DATETIME DEFAULT NULL,

  -- Campos de controle
  `mes_referencia` VARCHAR(7) DEFAULT NULL COMMENT 'YYYY-MM',
  `arquivo_csv_id` BIGINT(20) UNSIGNED DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `titulo_id` (`titulo_id`),
  KEY `consultor` (`consultor`),
  KEY `status` (`status`),
  KEY `data_venda` (`data_venda`),
  KEY `data_cadastro` (`data_cadastro`),
  KEY `mes_referencia` (`mes_referencia`),
  KEY `cpf_limpo` (`cpf_limpo`),
  KEY `arquivo_csv_id` (`arquivo_csv_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Promotores/Consultores
CREATE TABLE IF NOT EXISTS `aq_promotores` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(255) NOT NULL,
  `cpf` VARCHAR(14) DEFAULT NULL,
  `telefone` VARCHAR(50) DEFAULT NULL,
  `email` VARCHAR(255) DEFAULT NULL,
  `gerente` VARCHAR(255) DEFAULT NULL,
  `status` VARCHAR(50) DEFAULT 'Ativo',
  `data_admissao` DATE DEFAULT NULL,

  -- Campos de controle
  `arquivo_csv_id` BIGINT(20) UNSIGNED DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `cpf` (`cpf`),
  KEY `nome` (`nome`),
  KEY `status` (`status`),
  KEY `arquivo_csv_id` (`arquivo_csv_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de PINs dos Consultores
CREATE TABLE IF NOT EXISTS `aq_pins_consultores` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `consultor_nome` VARCHAR(255) NOT NULL,
  `pin` VARCHAR(6) NOT NULL,
  `cpf_titular` VARCHAR(11) DEFAULT NULL,
  `telefone_titular` VARCHAR(20) DEFAULT NULL,
  `valido_ate` DATETIME DEFAULT NULL,
  `usado` TINYINT(1) DEFAULT 0,
  `data_uso` DATETIME DEFAULT NULL,
  `ip_criacao` VARCHAR(45) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `consultor_nome` (`consultor_nome`),
  KEY `pin` (`pin`),
  KEY `valido_ate` (`valido_ate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Usuários (Admin)
CREATE TABLE IF NOT EXISTS `aq_usuarios` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(60) NOT NULL,
  `password` VARCHAR(255) NOT NULL COMMENT 'password_hash',
  `nome_completo` VARCHAR(255) DEFAULT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `nivel_acesso` VARCHAR(50) DEFAULT 'admin' COMMENT 'admin, gestor, consultor',
  `ativo` TINYINT(1) DEFAULT 1,
  `ultimo_login` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `nivel_acesso` (`nivel_acesso`),
  KEY `ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Arquivos CSV (metadados)
CREATE TABLE IF NOT EXISTS `aq_arquivos_csv` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome_arquivo` VARCHAR(255) NOT NULL,
  `nome_amigavel` VARCHAR(255) DEFAULT NULL,
  `slug` VARCHAR(255) DEFAULT NULL,
  `tipo` VARCHAR(20) NOT NULL COMMENT 'vendas, promotores',
  `mes_referencia` VARCHAR(7) DEFAULT NULL COMMENT 'YYYY-MM',
  `caminho` VARCHAR(500) DEFAULT NULL,
  `tamanho_bytes` BIGINT(20) DEFAULT 0,
  `total_linhas` INT(11) DEFAULT 0,
  `total_processadas` INT(11) DEFAULT 0,
  `total_ignoradas` INT(11) DEFAULT 0,
  `status_import` VARCHAR(50) DEFAULT 'pendente' COMMENT 'pendente, processando, completo, erro',
  `data_upload` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `data_processamento` DATETIME DEFAULT NULL,
  `usuario_id` BIGINT(20) UNSIGNED DEFAULT NULL,

  PRIMARY KEY (`id`),
  KEY `tipo` (`tipo`),
  KEY `mes_referencia` (`mes_referencia`),
  KEY `slug` (`slug`),
  KEY `status_import` (`status_import`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Configurações
CREATE TABLE IF NOT EXISTS `aq_configuracoes` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `chave` VARCHAR(255) NOT NULL,
  `valor` LONGTEXT DEFAULT NULL,
  `tipo` VARCHAR(50) DEFAULT 'string' COMMENT 'string, json, int, bool',
  `descricao` TEXT DEFAULT NULL,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `chave` (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Ranges de Pontuação
CREATE TABLE IF NOT EXISTS `aq_ranges_pontuacao` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(255) NOT NULL,
  `data_inicio` DATE NOT NULL,
  `data_fim` DATE NOT NULL,
  `ativo` TINYINT(1) DEFAULT 1,
  `pontos_config` JSON DEFAULT NULL COMMENT 'Configuração de pontos por vaga',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `ativo` (`ativo`),
  KEY `data_inicio` (`data_inicio`),
  KEY `data_fim` (`data_fim`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Log de Importações
CREATE TABLE IF NOT EXISTS `aq_log_imports` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `arquivo_csv_id` BIGINT(20) UNSIGNED NOT NULL,
  `tipo_operacao` VARCHAR(50) NOT NULL COMMENT 'import, update, delete',
  `total_processados` INT(11) DEFAULT 0,
  `total_sucesso` INT(11) DEFAULT 0,
  `total_erros` INT(11) DEFAULT 0,
  `detalhes` JSON DEFAULT NULL,
  `tempo_execucao` DECIMAL(10,2) DEFAULT NULL COMMENT 'em segundos',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `arquivo_csv_id` (`arquivo_csv_id`),
  KEY `tipo_operacao` (`tipo_operacao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insere configurações padrão
INSERT INTO `aq_configuracoes` (`chave`, `valor`, `tipo`, `descricao`) VALUES
('pontos_por_vaga', '3', 'int', 'Pontos padrão por vaga'),
('pontos_venda_vista', '2', 'int', 'Pontos extras para venda à vista'),
('vendas_para_sap', '21', 'int', 'Vendas necessárias para SAP'),
('vendas_para_dip', '500', 'int', 'Valor total para DIP'),
('senha_filtro', 'liberarfiltros', 'string', 'Senha para liberar filtros'),
('senha_godmode', 'sign@3DS', 'string', 'Senha para modo god'),
('dia_limite_primeira_parcela', '7', 'int', 'Dia limite para primeira parcela (premiação)')
ON DUPLICATE KEY UPDATE valor=VALUES(valor);

-- Cria usuário admin padrão (senha: admin123)
INSERT INTO `aq_usuarios` (`username`, `password`, `nome_completo`, `nivel_acesso`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador', 'admin')
ON DUPLICATE KEY UPDATE username=VALUES(username);
