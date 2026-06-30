<?php
require_once __DIR__ . '/includes/auth.php';
if (isLoggedIn()) { header('Location: ' . BASE_URL . '/dashboard.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');

    if (!$email || !$password || !$firstName || !$lastName) {
        $error = 'Todos los campos son obligatorios.';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } else {
        $result = registerUser($email, $password, $firstName, $lastName);
        if ($result['ok']) { header('Location: ' . BASE_URL . '/dashboard.php'); exit; }
        $error = $result['error'];
    }
}
$pageTitle = 'Registro';
require __DIR__ . '/includes/header.php';
?>

<div class="min-h-[80vh] flex items-center justify-center">
  <div class="w-full max-w-md bg-white rounded-2xl shadow-sm border border-gray-200 p-8">
    <div class="text-center mb-6">
      <h1 class="text-2xl font-extrabold text-gray-900">Crear cuenta</h1>
      <p class="text-gray-500 text-sm mt-1">Accede al aula virtual</p>
    </div>

    <?php if ($error): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm mb-4"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="space-y-4">
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
          <input type="text" name="first_name" required value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>"
                 class="w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 focus:border-transparent">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Apellido</label>
          <input type="text" name="last_name" required value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>"
                 class="w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 focus:border-transparent">
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Correo electrónico</label>
        <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 focus:border-transparent">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Contraseña</label>
        <input type="password" name="password" required minlength="6"
               class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 focus:border-transparent">
        <p class="text-xs text-gray-400 mt-1">Mínimo 6 caracteres</p>
      </div>
      <button type="submit" class="w-full bg-selcap-600 hover:bg-selcap-700 text-white font-semibold py-3 rounded-xl transition-colors">
        Registrarse
      </button>
    </form>

    <p class="text-center text-sm text-gray-500 mt-6">
      ¿Ya tienes cuenta? <a href="<?= BASE_URL ?>/login.php" class="text-selcap-600 font-medium hover:underline">Inicia sesión</a>
    </p>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
