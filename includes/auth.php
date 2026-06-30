<?php
// ═══════════════════════════════════════════════
// Selcap AV — Autenticación
// ═══════════════════════════════════════════════

require_once __DIR__ . '/config.php';

// ── Helpers de sesión ──

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    try {
        $stmt = db()->prepare('SELECT id, email, first_name, last_name, role FROM users WHERE id = ? AND is_active = 1');
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

function isAdmin(): bool {
    $user = currentUser();
    return $user && $user['role'] === 'admin';
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
    }
}

// ── Registro ──

function registerUser(string $email, string $password, string $firstName, string $lastName): array {
    $pdo = db();

    // Validar email único
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['ok' => false, 'error' => 'Este correo ya está registrado.'];
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, first_name, last_name) VALUES (?, ?, ?, ?)');
    $stmt->execute([$email, $hash, $firstName, $lastName]);
    $userId = (int) $pdo->lastInsertId();

    // Auto-enroll al curso activo
    $stmt = $pdo->prepare('INSERT IGNORE INTO enrollments (user_id, course_id) VALUES (?, ?)');
    $stmt->execute([$userId, ACTIVE_COURSE_ID]);

    // Iniciar sesión
    $_SESSION['user_id'] = $userId;
    return ['ok' => true, 'user_id' => $userId];
}

// ── Login ──

function loginUser(string $email, string $password): array {
    $stmt = db()->prepare('SELECT id, email, password_hash, is_active FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['ok' => false, 'error' => 'Correo no registrado.'];
    }
    if (!$user['is_active']) {
        return ['ok' => false, 'error' => 'Cuenta desactivada.'];
    }
    if (!password_verify($password, $user['password_hash'])) {
        return ['ok' => false, 'error' => 'Contraseña incorrecta.'];
    }

    $_SESSION['user_id'] = (int) $user['id'];

    // Auto-enroll por si acaso
    $stmt = db()->prepare('INSERT IGNORE INTO enrollments (user_id, course_id) VALUES (?, ?)');
    $stmt->execute([$user['id'], ACTIVE_COURSE_ID]);

    return ['ok' => true];
}

// ── Logout ──

function logoutUser(): void {
    session_destroy();
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}
