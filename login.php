<?php
require_once __DIR__ . '/includes/auth.php';
if (isLoggedIn()) { header('Location: ' . BASE_URL . '/dashboard.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = loginUser($_POST['email'] ?? '', $_POST['password'] ?? '');
    if ($result['ok']) { header('Location: ' . BASE_URL . '/dashboard.php'); exit; }
    $error = $result['error'];
}
$pageTitle = 'Iniciar sesión';
require __DIR__ . '/includes/header.php';
?>

<div class="min-h-[80vh] flex items-center justify-center">
  <div class="w-full max-w-md bg-white rounded-2xl shadow-sm border border-gray-200 p-8">
    <div class="text-center mb-6">
      <div class="w-14 h-14 bg-selcap-500 rounded-xl flex items-center justify-center mx-auto mb-3">
        <span class="text-white font-extrabold text-xl">AV</span>
      </div>
      <h1 class="text-2xl font-extrabold text-gray-900">Iniciar sesión</h1>
      <p class="text-gray-500 text-sm mt-1">Aula Virtual Selcap</p>
    </div>

    <?php if ($error): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm mb-4"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Correo electrónico</label>
        <input type="email" name="email" required autofocus
               class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 focus:border-transparent">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Contraseña</label>
        <input type="password" name="password" required
               class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 focus:border-transparent">
      </div>
      <button type="submit" class="w-full bg-selcap-600 hover:bg-selcap-700 text-white font-semibold py-3 rounded-xl transition-colors">
        Entrar
      </button>
    </form>

    <p class="text-center text-sm text-gray-500 mt-6">
      ¿No tienes cuenta? <a href="<?= BASE_URL ?>/register.php" class="text-selcap-600 font-medium hover:underline">Regístrate</a>
    </p>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
