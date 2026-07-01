-- ═══════════════════════════════════════════════
-- Selcap AV — Aula Virtual (PHP/MySQL)
-- Ejecutar en: cPanel → phpMyAdmin → SQL
-- ═══════════════════════════════════════════════

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    role ENUM('admin','student') DEFAULT 'student',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    thumbnail_url VARCHAR(500),
    sku VARCHAR(100) UNIQUE,
    is_sequential TINYINT(1) DEFAULT 0,
    passing_grade INT DEFAULT 70,
    status ENUM('draft','published','archived') DEFAULT 'published',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE lessons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content_html LONGTEXT DEFAULT '',
    video_url VARCHAR(500),
    live_url VARCHAR(500),
    sort_order INT DEFAULT 0,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE lesson_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lesson_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_url VARCHAR(500) NOT NULL,
    file_type VARCHAR(100),
    file_size INT DEFAULT 0,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE evaluations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    max_attempts INT DEFAULT 1,
    passing_score INT DEFAULT 80,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evaluation_id INT NOT NULL,
    text TEXT NOT NULL,
    type ENUM('multiple_choice') DEFAULT 'multiple_choice',
    points INT DEFAULT 1,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (evaluation_id) REFERENCES evaluations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    text TEXT NOT NULL,
    is_correct TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    course_id INT NOT NULL,
    status ENUM('active','completed','cancelled') DEFAULT 'active',
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    UNIQUE KEY (user_id, course_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE lesson_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    lesson_id INT NOT NULL,
    completed TINYINT(1) DEFAULT 0,
    completed_at TIMESTAMP NULL,
    UNIQUE KEY (user_id, lesson_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE evaluation_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    evaluation_id INT NOT NULL,
    attempt_number INT DEFAULT 1,
    score FLOAT DEFAULT 0,
    passed TINYINT(1) DEFAULT 0,
    answers_snapshot JSON,
    feedback TEXT,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    submitted_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (evaluation_id) REFERENCES evaluations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ═══════════ SEED DATA ═══════════

-- Admin user (password: admin123)
INSERT INTO users (email, password_hash, first_name, last_name, role) VALUES
('admin@selcap.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'Selcap', 'admin');

-- Curso demo
INSERT INTO courses (id, title, description, sku, is_sequential, passing_grade, status) VALUES
(1, 'Fundamentos de Seguridad Industrial', 'Curso introductorio sobre normas básicas de seguridad en el trabajo. Aprende a identificar riesgos, usar EPP y actuar ante emergencias.', 'FUND-SEG-IND', 1, 70, 'published');

-- Secciones
INSERT INTO sections (id, course_id, title, description, sort_order) VALUES
(1, 1, 'Módulo 1: Introducción a la Seguridad', 'Conceptos básicos y marco normativo', 1),
(2, 1, 'Módulo 2: Equipos de Protección Personal', 'Tipos de EPP y su uso correcto', 2),
(3, 1, 'Módulo 3: Respuesta ante Emergencias', 'Protocolos de evacuación y primeros auxilios', 3);

-- Lecciones Módulo 1
INSERT INTO lessons (id, section_id, title, content_html, sort_order) VALUES
(1, 1, '¿Qué es la seguridad industrial?', '<h2>Seguridad Industrial</h2><p>La seguridad industrial es el conjunto de normas, procedimientos y estrategias destinados a <strong>preservar la integridad física</strong> de los trabajadores en su entorno laboral.</p><h3>Objetivos principales</h3><ul><li>Prevenir accidentes laborales</li><li>Reducir riesgos en el ambiente de trabajo</li><li>Garantizar el cumplimiento de normativas legales</li><li>Promover una cultura de prevención</li></ul>', 1),
(2, 1, 'Marco legal venezolano', '<h2>Marco Legal en Venezuela</h2><p>La seguridad industrial en Venezuela está regulada principalmente por:</p><ul><li><strong>LOPCYMAT</strong> — Ley Orgánica de Prevención, Condiciones y Medio Ambiente de Trabajo</li><li><strong>Reglamento de las Condiciones de Higiene y Seguridad en el Trabajo</strong></li><li><strong>Normas COVENIN</strong> — Comisión Venezolana de Normas Industriales</li></ul><p>Todo empleador está obligado a garantizar condiciones óptimas de seguridad.</p>', 2);

-- Lecciones Módulo 2
INSERT INTO lessons (id, section_id, title, content_html, sort_order) VALUES
(3, 2, 'Tipos de EPP', '<h2>Equipos de Protección Personal</h2><p>Los EPP son dispositivos diseñados para proteger al trabajador de riesgos específicos.</p><h3>Clasificación por zona corporal</h3><table border="1" cellpadding="8" style="border-collapse:collapse;width:100%"><tr><th>Zona</th><th>EPP</th></tr><tr><td>Cabeza</td><td>Casco de seguridad</td></tr><tr><td>Ojos</td><td>Lentes protectores, careta</td></tr><tr><td>Oídos</td><td>Tapones, orejeras</td></tr><tr><td>Vías respiratorias</td><td>Mascarilla, respirador</td></tr><tr><td>Manos</td><td>Guantes (según riesgo)</td></tr><tr><td>Pies</td><td>Botas de seguridad (punta de acero)</td></tr><tr><td>Cuerpo</td><td>Overol, chaleco reflectivo</td></tr></table>', 1);

-- Evaluación Módulo 1
INSERT INTO evaluations (id, section_id, title, description, max_attempts, sort_order) VALUES
(1, 1, 'Evaluación Módulo 1', 'Responde las siguientes preguntas sobre seguridad industrial y marco legal venezolano.', 3, 1);

-- Preguntas
INSERT INTO questions (id, evaluation_id, text, type, points, sort_order) VALUES
(1, 1, '¿Cuál es el principal objetivo de la seguridad industrial?', 'multiple_choice', 1, 1),
(2, 1, '¿Qué significa LOPCYMAT?', 'multiple_choice', 1, 2),
(3, 1, '¿Quién es responsable de garantizar condiciones óptimas de seguridad según la ley venezolana?', 'multiple_choice', 1, 3);

-- Respuestas pregunta 1
INSERT INTO answers (question_id, text, is_correct, sort_order) VALUES
(1, 'Prevenir accidentes y proteger la integridad del trabajador', 1, 1),
(1, 'Aumentar la productividad de la empresa', 0, 2),
(1, 'Reducir costos operativos', 0, 3);

-- Respuestas pregunta 2
INSERT INTO answers (question_id, text, is_correct, sort_order) VALUES
(2, 'Ley Orgánica de Prevención, Condiciones y Medio Ambiente de Trabajo', 1, 1),
(2, 'Ley Orgánica de Protección Civil y Medio Ambiente', 0, 2),
(2, 'Ley Ordinaria de Protección al Consumidor', 0, 3);

-- Respuestas pregunta 3
INSERT INTO answers (question_id, text, is_correct, sort_order) VALUES
(3, 'El empleador', 1, 1),
(3, 'El trabajador', 0, 2),
(3, 'El gobierno', 0, 3);

-- ═══════════ AUDITORÍA ═══════════

CREATE TABLE audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    details JSON,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ═══════════ MATERIALES ═══════════

CREATE TABLE course_materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_url VARCHAR(500) NOT NULL,
    file_type VARCHAR(100),
    file_size INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ═══════════ MIGRACIÓN v2 (ejecutar si la BD ya existe) ═══════════
-- ALTER TABLE evaluations ADD COLUMN passing_score INT DEFAULT 80 AFTER max_attempts;
-- ALTER TABLE evaluations MODIFY max_attempts INT DEFAULT 1;
-- ALTER TABLE evaluation_attempts ADD COLUMN feedback TEXT AFTER answers_snapshot;

-- ═══════════ MIGRACIÓN v3 — SKU para cursos ═══════════
-- ALTER TABLE courses ADD COLUMN sku VARCHAR(100) UNIQUE AFTER thumbnail_url;
-- UPDATE courses SET sku = 'FUND-SEG-IND' WHERE id = 1 AND sku IS NULL;

-- ═══════════ MIGRACIÓN v4 — Clases en vivo ═══════════
-- ALTER TABLE lessons ADD COLUMN live_url VARCHAR(500) AFTER video_url;

-- ═══════════ MIGRACIÓN v5 — Materiales de curso ═══════════
-- CREATE TABLE IF NOT EXISTS course_materials (
--     id INT AUTO_INCREMENT PRIMARY KEY,
--     course_id INT NOT NULL,
--     file_name VARCHAR(255) NOT NULL,
--     file_url VARCHAR(500) NOT NULL,
--     file_type VARCHAR(100),
--     file_size INT DEFAULT 0,
--     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
--     FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
