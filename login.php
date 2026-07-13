<?php
require_once __DIR__ . '/includes/auth.php';
if (isLoggedIn()) { header('Location: ' . BASE_URL . '/dashboard.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = loginUser($_POST['email'] ?? '', $_POST['password'] ?? '');
    if ($result['ok']) {
        try {
            $pdo = db();
            $pdo->prepare('INSERT INTO audit_log (user_id, action, entity_type, details, ip_address) VALUES (?, ?, ?, ?, ?)')
                ->execute([$_SESSION['user_id'], 'login', 'user', json_encode([]), $_SERVER['REMOTE_ADDR'] ?? '']);
        } catch (Exception $e) {}
        header('Location: ' . BASE_URL . '/dashboard.php'); exit;
    }
    $error = $result['error'];
}
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Aula Virtual SELCAP — Iniciar sesión</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{colors:{selcap:{50:'#eff6ff',100:'#dbeafe',200:'#bfdbfe',400:'#60a5fa',500:'#3b82f6',600:'#1d4ed8',700:'#1e40af',800:'#1e3a8a',900:'#172554'}}}}}</script>
<style>
  body { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
</style>
</head>
<body class="min-h-screen flex flex-col lg:flex-row">

<!-- ── Izquierda: Branding ── -->
<div class="lg:w-1/2 bg-gradient-to-br from-[#1a3a6b] via-[#1d4ed8] to-[#2563eb] flex flex-col justify-center p-8 sm:p-12 lg:p-16 text-white">
  <div class="max-w-md mx-auto lg:mx-0">
    <!-- Logo -->
    <div class="flex items-center gap-3 mb-12">
      <div class="w-10 h-10 bg-white/20 backdrop-blur rounded-lg flex items-center justify-center">
        <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
        </svg>
      </div>
      <span class="text-xl font-bold tracking-tight">Aula Virtual SELCAP</span>
    </div>

    <!-- Headline -->
    <h1 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold leading-tight mb-6">
      Aprende a tu<br>propio ritmo
    </h1>

    <!-- Description -->
    <p class="text-base sm:text-lg text-blue-100 leading-relaxed max-w-md">
      Accede a cientos de cursos, completa evaluaciones y obtén certificados para impulsar tu carrera profesional.
    </p>
  </div>
</div>

<!-- ── Derecha: Login ── -->
<div class="lg:w-1/2 bg-gray-50 flex items-center justify-center p-8 sm:p-12">
  <div class="w-full max-w-sm">

    <!-- Header -->
    <div class="mb-8">
      <h2 class="text-2xl font-bold text-gray-900">¡Bienvenido de vuelta!</h2>
      <p class="text-gray-500 text-sm mt-1.5">Ingresa tus credenciales para acceder a tu cuenta.</p>
    </div>

    <?php if ($error): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm mb-5">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <!-- Form -->
    <form method="POST" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Correo Electrónico</label>
        <input type="email" name="email" required autofocus
               class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-shadow"
               placeholder="tu@email.com">
      </div>
      <div>
        <div class="flex items-center justify-between mb-1.5">
          <label class="text-sm font-medium text-gray-700">Contraseña</label>
          <a href="#" class="text-sm text-blue-600 hover:text-blue-700 font-medium">¿Olvidaste tu contraseña?</a>
        </div>
        <input type="password" name="password" required
               class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-shadow">
      </div>
      <button type="submit"
              class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-xl transition-colors shadow-sm">
        Iniciar Sesión
      </button>
    </form>

    <!-- Register -->
    <p class="text-center text-sm text-gray-500 mt-6">
      ¿No tienes una cuenta? <a href="<?= BASE_URL ?>/register.php" class="text-blue-600 hover:underline font-medium">Regístrate aquí</a>
    </p>
  </div>
</div>

</body>
</html>
