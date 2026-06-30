<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$pdo = db();

// ── Acciones ──
$msg = '';
$msgType = '';

// Crear sección
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_section') {
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if ($title) {
            $pdo->prepare('INSERT INTO sections (course_id, title, description, sort_order) VALUES (?, ?, ?, ?)')
                ->execute([ACTIVE_COURSE_ID, $title, $desc, $_POST['sort_order'] ?? 0]);
            $msg = 'Sección creada.';
            $msgType = 'green';
        }
    } elseif ($_POST['action'] === 'delete_section') {
        $pdo->prepare('DELETE FROM sections WHERE id = ? AND course_id = ?')
            ->execute([(int)$_POST['id'], ACTIVE_COURSE_ID]);
        $msg = 'Sección eliminada.';
        $msgType = 'red';
    } elseif ($_POST['action'] === 'update_section') {
        $pdo->prepare('UPDATE sections SET title = ?, description = ?, sort_order = ? WHERE id = ? AND course_id = ?')
            ->execute([trim($_POST['title']), trim($_POST['description'] ?? ''), $_POST['sort_order'] ?? 0, (int)$_POST['id'], ACTIVE_COURSE_ID]);
        $msg = 'Sección actualizada.';
        $msgType = 'blue';
    }
}

// Obtener secciones
$sectionsStmt = $pdo->prepare('SELECT s.*, 
    (SELECT COUNT(*) FROM lessons l WHERE l.section_id = s.id) as lesson_count,
    (SELECT COUNT(*) FROM evaluations e WHERE e.section_id = s.id) as eval_count
    FROM sections s WHERE s.course_id = ? ORDER BY s.sort_order');
$sectionsStmt->execute([ACTIVE_COURSE_ID]);
$sections = $sectionsStmt->fetchAll();

$pageTitle = 'Admin — Secciones';
$currentPage = 'admin';
require __DIR__ . '/../includes/header.php';
?>

<div class="flex items-center justify-between mb-6">
  <div>
    <h1 class="text-2xl font-extrabold text-gray-900">Panel Admin</h1>
    <p class="text-gray-500 text-sm">Gestionar secciones, lecciones y evaluaciones</p>
  </div>
  <div class="flex items-center gap-2 text-sm">
    <a href="<?= BASE_URL ?>/admin/" class="bg-selcap-600 text-white px-4 py-2 rounded-xl font-semibold">Secciones</a>
    <a href="<?= BASE_URL ?>/admin/lessons.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-xl font-semibold transition-colors">Lecciones</a>
    <a href="<?= BASE_URL ?>/admin/evaluations.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-xl font-semibold transition-colors">Evaluaciones</a>
    <a href="<?= BASE_URL ?>/admin/alumnos.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-xl font-semibold transition-colors">Alumnos</a>
    <a href="<?= BASE_URL ?>/admin/reportes.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-xl font-semibold transition-colors">Reportes</a>
  </div>
</div>

<?php if ($msg): ?>
  <div class="bg-<?= $msgType ?>-50 border border-<?= $msgType ?>-200 text-<?= $msgType ?>-700 px-4 py-3 rounded-xl text-sm mb-4"><?= $msg ?></div>
<?php endif; ?>

<!-- Crear sección -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 mb-6">
  <h2 class="font-bold text-gray-800 mb-3">Nueva sección</h2>
  <form method="POST" class="space-y-3">
    <input type="hidden" name="action" value="create_section">
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
      <input type="text" name="title" placeholder="Título de la sección" required
             class="sm:col-span-2 px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500">
      <input type="number" name="sort_order" placeholder="Orden" value="0"
             class="px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500">
    </div>
    <textarea name="description" placeholder="Descripción (opcional)" rows="2"
              class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500"></textarea>
    <button type="submit" class="bg-selcap-600 hover:bg-selcap-700 text-white font-semibold px-5 py-2.5 rounded-xl transition-colors text-sm">
      Crear sección
    </button>
  </form>
</div>

<!-- Lista de secciones -->
<div class="space-y-4">
  <?php foreach ($sections as $sec): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
      <form method="POST" class="space-y-3">
        <input type="hidden" name="action" value="update_section">
        <input type="hidden" name="id" value="<?= $sec['id'] ?>">
        <div class="flex items-start justify-between gap-4">
          <div class="flex-1 space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <input type="text" name="title" value="<?= htmlspecialchars($sec['title']) ?>"
                     class="sm:col-span-2 px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 font-medium">
              <input type="number" name="sort_order" value="<?= $sec['sort_order'] ?>"
                     class="px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500">
            </div>
            <textarea name="description" rows="2"
                      class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm"><?= htmlspecialchars($sec['description'] ?? '') ?></textarea>
          </div>
        </div>
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-4 text-xs text-gray-400">
            <span><?= $sec['lesson_count'] ?> lecciones</span>
            <span><?= $sec['eval_count'] ?> evaluaciones</span>
            <a href="<?= BASE_URL ?>/admin/lessons.php?section_id=<?= $sec['id'] ?>" class="text-selcap-600 font-medium hover:underline">Gestionar lecciones →</a>
            <a href="<?= BASE_URL ?>/admin/evaluations.php?section_id=<?= $sec['id'] ?>" class="text-selcap-600 font-medium hover:underline">Gestionar evaluaciones →</a>
          </div>
          <div class="flex items-center gap-2">
            <button type="submit" name="action" value="update_section" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2 rounded-xl transition-colors text-sm">Guardar</button>
          </div>
        </form>
        <div class="flex items-center gap-2 mt-2">
          <form method="POST" onsubmit="return confirm('¿Eliminar esta sección y todo su contenido?')" class="inline">
            <input type="hidden" name="action" value="delete_section">
            <input type="hidden" name="id" value="<?= $sec['id'] ?>">
            <button type="submit" class="bg-red-100 hover:bg-red-200 text-red-700 font-semibold px-4 py-2 rounded-xl transition-colors text-sm">Eliminar</button>
          </form>
        </div>
        </div>
      </form>
    </div>
  <?php endforeach; ?>
</div>

<?php if (empty($sections)): ?>
  <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-12 text-center text-gray-400">
    Crea la primera sección del curso.
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
