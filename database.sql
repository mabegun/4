-- ProKB Database Schema
-- Проектное Бюро - Система управления проектами

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- ПОЛЬЗОВАТЕЛИ
-- ============================================

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('director', 'gip', 'employee') DEFAULT 'employee',
    position VARCHAR(255),
    phone VARCHAR(50),
    competencies JSON,
    avatar VARCHAR(255),
    token VARCHAR(64),
    is_archived TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- ПРОЕКТЫ
-- ============================================

CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50),
    address TEXT,
    type ENUM('construction', 'capital_repair', 'reconstruction', 'modernization', 'demolition') DEFAULT 'construction',
    deadline DATE,
    description TEXT,
    status ENUM('in_work', 'in_expertise', 'completed', 'archived') DEFAULT 'in_work',
    gip_id INT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (gip_id) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- РАЗДЕЛЫ ПРОЕКТА
-- ============================================

CREATE TABLE IF NOT EXISTS sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    code VARCHAR(20) NOT NULL,
    description VARCHAR(500),
    assignee_id INT,
    status ENUM('not_started', 'in_progress', 'completed', 'revision') DEFAULT 'not_started',
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    expertise_status ENUM('uploaded_for_review', 'remarks_received', 'remarks_in_progress', 'accepted_by_expert') NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (assignee_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- ФАЙЛЫ РАЗДЕЛОВ
-- ============================================

CREATE TABLE IF NOT EXISTS section_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    path VARCHAR(500) NOT NULL,
    is_completed TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- СТАНДАРТНЫЕ ИЗЫСКАНИЯ
-- ============================================

CREATE TABLE IF NOT EXISTS standard_investigations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- ИЗЫСКАНИЯ ПРОЕКТОВ
-- ============================================

CREATE TABLE IF NOT EXISTS investigations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    standard_id INT,
    name VARCHAR(255) NOT NULL,
    status ENUM('not_started', 'in_progress', 'completed') DEFAULT 'not_started',
    contractor_name VARCHAR(255),
    contractor_contact VARCHAR(255),
    contractor_phone VARCHAR(50),
    contractor_email VARCHAR(255),
    contract_number VARCHAR(100),
    contract_date DATE,
    contract_file VARCHAR(500),
    start_date DATE,
    end_date DATE,
    result_file VARCHAR(500),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (standard_id) REFERENCES standard_investigations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- КОНТАКТНЫЕ ЛИЦА
-- ============================================

CREATE TABLE IF NOT EXISTS contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    position VARCHAR(255),
    company VARCHAR(255),
    phone VARCHAR(50),
    email VARCHAR(255),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- ВВОДНАЯ ИНФОРМАЦИЯ
-- ============================================

CREATE TABLE IF NOT EXISTS intro_blocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    type ENUM('text', 'file') DEFAULT 'text',
    title VARCHAR(255) NOT NULL,
    content TEXT,
    file_path VARCHAR(500),
    file_name VARCHAR(255),
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- ЗАДАЧИ
-- ============================================

CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    project_id INT,
    assignee_id INT,
    created_by INT,
    deadline DATE,
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    status ENUM('not_started', 'in_progress', 'completed', 'cancelled') DEFAULT 'not_started',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    FOREIGN KEY (assignee_id) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- КОММЕНТАРИИ К ЗАДАЧАМ
-- ============================================

CREATE TABLE IF NOT EXISTS task_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- СООБЩЕНИЯ (РАЗДЕЛЫ)
-- ============================================

CREATE TABLE IF NOT EXISTS section_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    is_critical TINYINT DEFAULT 0,
    is_resolved TINYINT DEFAULT 0,
    parent_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (parent_id) REFERENCES section_messages(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- РАЗДЕЛЫ ПРОЕКТИРОВАНИЯ (НАСТРАИВАЕМЫЕ)
-- ============================================

CREATE TABLE IF NOT EXISTS design_sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    is_active TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- ЭТАПЫ ЭКСПЕРТИЗЫ
-- ============================================

CREATE TABLE IF NOT EXISTS expertise_stages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- ЭКСПЕРТИЗЫ ПРОЕКТОВ
-- ============================================

CREATE TABLE IF NOT EXISTS project_expertises (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    stage_id INT,
    expert_name VARCHAR(255),
    expert_contact VARCHAR(255),
    expert_phone VARCHAR(50),
    expert_email VARCHAR(255),
    start_date DATE,
    end_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (stage_id) REFERENCES expertise_stages(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- ЗАМЕЧАНИЯ ЭКСПЕРТИЗЫ
-- ============================================

CREATE TABLE IF NOT EXISTS expertise_remarks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    expertise_id INT NOT NULL,
    section_id INT,
    content TEXT NOT NULL,
    file_path VARCHAR(500),
    file_name VARCHAR(255),
    is_resolved TINYINT DEFAULT 0,
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (expertise_id) REFERENCES project_expertises(id) ON DELETE CASCADE,
    FOREIGN KEY (section_id) REFERENCES sections(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- ДЕМО-ДАННЫЕ
-- ============================================

-- Директор
INSERT INTO users (name, email, password, role, position) VALUES
('Иванов Иван Иванович', 'ivanov@prokb.ru', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'director', 'Директор');

-- ГИП
INSERT INTO users (name, email, password, role, position) VALUES
('Сидорова Анна Петровна', 'sidorova@prokb.ru', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'gip', 'Главный инженер проекта');

-- Сотрудник
INSERT INTO users (name, email, password, role, position, competencies) VALUES
('Петров Пётр Сергеевич', 'petrov@prokb.ru', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'employee', 'Инженер', '["ГП", "АР", "КР"]');

-- Стандартные изыскания
INSERT INTO standard_investigations (name) VALUES
('Инженерно-геодезические изыскания'),
('Инженерно-геологические изыскания'),
('Инженерно-гидрометеорологические изыскания'),
('Инженерно-экологические изыскания'),
('Геотехнические исследования'),
('Обследование зданий и сооружений');

-- Разделы проектирования
INSERT INTO design_sections (code, name) VALUES
('ГП', 'Генеральный план'),
('АР', 'Архитектурные решения'),
('КР', 'Конструктивные решения'),
('КЖ', 'Конструкции железобетонные'),
('ОВ', 'Отопление, вентиляция и кондиционирование'),
('ВК', 'Водоснабжение и канализация'),
('ЭОМ', 'Электроснабжение и электрооборудование'),
('ЭС', 'Электроснабжение'),
('ТС', 'Теплоснабжение'),
('ГСВ', 'Газоснабжение (внутреннее)'),
('ГСН', 'Газоснабжение (наружное)'),
('СС', 'Слаботочные системы'),
('ПОС', 'Проект организации строительства'),
('ПОД', 'Проект организации дорожного движения');

-- Этапы экспертизы
INSERT INTO expertise_stages (name, sort_order) VALUES
('Предварительная экспертиза', 1),
('Основная экспертиза', 2),
('Повторная экспертиза', 3);

SET FOREIGN_KEY_CHECKS = 1;
