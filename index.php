<?php
require_once __DIR__ . '/includes/auth.php';
header('Location: ' . BASE_URL . (isLoggedIn() ? '/dashboard.php' : '/login.php'));
