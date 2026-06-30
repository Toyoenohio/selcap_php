<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
$user = currentUser();
$pdo = db();
$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';

    if ($firstName && $lastName) {
        if (!empty($newPassword)) {
            // Verificar password actual
            $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $row = $stmt->fetch();
            if (!password_verify($currentPassword, $row['password_hash'])) {
                $msg = 'Contraseña actual incorrecta.'; $msgType = 'red';
            } else {
                $hash = password_hash($newPassword, PASSWORD_BCRYPT);
                $pdo->prepare('UPDATE users SET first_name = ?, last_name = ?, password_hash = ? WHERE id = ?')->execute([$firstName, $lastName, $hash, $_SESSION['user_id']]);
                $msg = 'Perfil actualizado.'; $msgType = 'green';
            }
        } else {
            $pdo->prepare('UPDATE users SET first_name = ?, last_name = ? WHERE id = ?')->execute([$firstName, $lastName, $_SESSION['user_id']]);
            $msg = 'Perfil actualizado.'; $msgType = 'green';
        }
        $user = currentUser();
    }
}

$currentPage = 'perfil';
$pageTitle = 'Perfil';
require __DIR__ . '/includes/header.php';
?>

<h1 class="text-2xl font-extrabold text-gray-900 mb-6">Perfil</h1>

<?php if ($msg): ?>
  <div class="bg-<?= $msgType ?>-50 border border-<?= $msgType ?>-200 text-<?= $msgType ?>-700 px-4 py-3 rounded-xl text-sm mb-4"><?= $msg ?></div>
<?php endif; ?>

<div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 max-w-lg">
  <div class="flex items-center gap-4 mb-6">
    <div class="w-16 h-16 bg-selcap-100 rounded-full flex items-center justify-center text-selcap-700 font-bold text-xl">
      <?= strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? '', 0, 1)) ?>
    </div>
    <div>
      <h2 class="font-bold text-gray-900 text-lg"><?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?></h2>
      <p class="text-sm text-gray-500"><?= $user['role'] === 'admin' ? 'Administrador' : 'Estudiante' ?></p>
    </div>
  </div>

  <form method="POST" class="space-y-4">
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Correo electrónico</label>
      <input type="email" disabled value="<?= htmlspecialchars($user['email']) ?>"
             class="w-full px-4 py-3 bg-gray-100 border border-gray-200 rounded-xl text-gray-500 cursor-not-allowed">
    </div>
    <div class="grid grid-cols-2 gap-3">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
        <input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>"
               class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Apellido</label>
        <input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>"
               class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500">
      </div>
    </div>

    <hr class="border-gray-100">

    <p class="text-sm font-medium text-gray-700">Cambiar contraseña (dejar en blanco para no cambiar)</p>
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Contraseña actual</label>
      <input type="password" name="current_password"
             class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500">
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Nueva contraseña</label>
      <input type="password" name="new_password" minlength="6"
             class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500">
    </div>

    <button type="submit" class="w-full bg-selcap-600 hover:bg-selcap-700 text-white font-semibold py-3 rounded-xl transition-colors">
      Guardar cambios
    </button>
  </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
