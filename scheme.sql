-- ===========================================================
-- 1. Tabel: users (peserta)
-- ===========================================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

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

-- ===========================================================
-- END OF FILE
-- ===========================================================