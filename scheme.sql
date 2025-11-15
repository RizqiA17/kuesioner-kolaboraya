-- ===========================================================
-- 1. Tabel: users (peserta)
-- ===========================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    organization VARCHAR(150) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS open_questions_schedule (
    id INT PRIMARY KEY AUTO_INCREMENT,
    start_time DATETIME DEFAULT NULL,
    end_time DATETIME DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS open_questions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    is_open BOOLEAN DEFAULT TRUE
);

INSERT INTO open_questions (is_open) VALUES (TRUE);

-- Tambahkan kolom 'organization' jika belum ada
SET
    @org_exists := (
        SELECT
            COUNT(*)
        FROM
            INFORMATION_SCHEMA.COLUMNS
        WHERE
            TABLE_NAME = 'users'
            AND COLUMN_NAME = 'organization'
    );

SET
    @sql_org := IF(
        @org_exists = 0,
        'ALTER TABLE users ADD COLUMN organization VARCHAR(150) DEFAULT NULL;',
        'SELECT "Kolom organization sudah ada";'
    );

PREPARE stmt_org
FROM
    @sql_org;

EXECUTE stmt_org;

DEALLOCATE PREPARE stmt_org;

-- Tambahkan kolom 'phone' jika belum ada
SET
    @phone_exists := (
        SELECT
            COUNT(*)
        FROM
            INFORMATION_SCHEMA.COLUMNS
        WHERE
            TABLE_NAME = 'users'
            AND COLUMN_NAME = 'phone'
    );

SET
    @sql_phone := IF(
        @phone_exists = 0,
        'ALTER TABLE users ADD COLUMN phone VARCHAR(20) DEFAULT NULL;',
        'SELECT "Kolom phone sudah ada";'
    );

PREPARE stmt_phone
FROM
    @sql_phone;

EXECUTE stmt_phone;

DEALLOCATE PREPARE stmt_phone;

-- ===========================================================
-- 2. Tabel: responses (jawaban peserta)
-- ===========================================================
CREATE TABLE responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    q1 INT,
    q2 INT,
    q3 INT,
    q4 INT,
    q5 INT,
    q6 INT,
    q7 INT,
    q8 INT,
    q9 INT,
    q10 INT,
    q11 INT,
    q12 INT,
    q13 INT,
    q14 INT,
    q15 INT,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ===========================================================
-- 3. Tabel: scores (hasil penilaian peserta)
-- ===========================================================
CREATE TABLE scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    core_score INT NOT NULL,
    integrity_status ENUM('LULUS', 'GAGAL') NOT NULL,
    ranking INT DEFAULT NULL,
    status ENUM('LULUS', 'TIDAK LOLOS') DEFAULT 'TIDAK LOLOS',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ===========================================================
-- 4. Tabel: facilitators (akun fasilitator)
-- ===========================================================
CREATE TABLE facilitators (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);

-- Tambahkan akun awal (username: admin / password: admin123)
INSERT INTO
    facilitators (username, password)
VALUES
    (
        'admin',
        '$2y$10$2UplcXbB7WRjX86gFb0wDOFxDAGl8kx.zlw9W2D7HZFXfnYbCQZc6'
    );

-- ===========================================================
-- 5. Tabel: quota_settings (batas kuota peserta lolos)
-- ===========================================================
CREATE TABLE quota_settings (
    id INT PRIMARY KEY DEFAULT 1,
    quota INT DEFAULT 150
);

INSERT INTO
    quota_settings (id, quota)
VALUES
    (1, 150);

-- ===========================================================
-- 6. Tabel: questions (pertanyaan kuesioner)
-- ===========================================================
CREATE TABLE questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_text TEXT NOT NULL,
    reversed BOOLEAN DEFAULT FALSE,
    category VARCHAR(100) DEFAULT NULL
);

-- ===========================================================
-- 7. Data pertanyaan (diambil dari dokumen)
-- ===========================================================
INSERT INTO
    questions (id, question_text, reversed, category)
VALUES
    (
        1,
        'Saya memiliki tujuan kerja yang jelas dan berusaha mencapainya dengan konsisten.',
        FALSE,
        'Orientasi Tujuan'
    ),
    (
        2,
        'Saya berusaha memahami kebutuhan dan pandangan orang lain sebelum mengambil keputusan.',
        FALSE,
        'Empati dan Kolaborasi'
    ),
    (
        3,
        'Saya terbuka terhadap ide dan cara baru dalam bekerja.',
        FALSE,
        'Inovasi dan Adaptabilitas'
    ),
    (
        4,
        'Saya mampu bertanggung jawab penuh terhadap hasil kerja saya.',
        FALSE,
        'Akuntabilitas'
    ),
    (
        5,
        'Saya berusaha mencari solusi atas masalah daripada menyalahkan keadaan.',
        FALSE,
        'Problem Solving'
    ),
    (
        6,
        'Saya mampu bekerja dengan disiplin tanpa pengawasan ketat.',
        FALSE,
        'Disiplin Diri'
    ),
    (
        7,
        'Saya mudah merasa cemas ketika menghadapi tekanan tinggi.',
        TRUE,
        'Stabilitas Emosi / Neurotisisme'
    ),
    (
        8,
        'Saya senang mencari cara baru untuk meningkatkan efektivitas kerja.',
        FALSE,
        'Kreativitas'
    ),
    (
        9,
        'Saya mampu menyesuaikan diri dengan cepat terhadap perubahan mendadak.',
        FALSE,
        'Fleksibilitas'
    ),
    (
        10,
        'Saya cenderung menunda-nunda pekerjaan yang terasa rumit.',
        TRUE,
        'Respon terhadap Kompleksitas'
    ),
    (
        11,
        'Saya merasa sangat tidak nyaman dan stres saat menghadapi situasi tidak pasti.',
        TRUE,
        'Respon terhadap Ketidakpastian'
    ),
    (
        12,
        'Saya mau belajar dari kesalahan dan terbuka terhadap masukan.',
        FALSE,
        'Sikap Belajar'
    ),
    (
        13,
        'Sejujurnya, saya merasa pekerjaan lebih cepat selesai jika saya kerjakan sendiri.',
        TRUE,
        'Kerja Kolaboratif'
    ),
    (
        14,
        'Saya merasa kesulitan meyakinkan banyak pihak untuk bekerja menuju tujuan bersama.',
        TRUE,
        'Aksi Kolektif / Mobilisasi'
    ),
    (
        15,
        'Saya tidak akan pernah mentolerir ketidakjujuran atau kompromi etis dalam pekerjaan saya, apa pun alasannya.',
        FALSE,
        'Integritas (Faktor Kritis)'
    );

ALTER TABLE scores 
ADD COLUMN manual_override ENUM('YES','NO') DEFAULT 'NO',
ADD COLUMN manual_status ENUM('LULUS','TIDAK LOLOS') DEFAULT NULL;

-- ===========================================================
-- END OF FILE
-- ===========================================================
