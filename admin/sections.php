<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$pdo = db();
$msg = ''; $msgType = '';

$courseId = (int)($_GET['course_id'] ?? 0);
if (!$courseId) { header('Location: ' . BASE_URL . '/admin/courses.php'); exit; }

// Verificar curso
$courseStmt = $pdo->prepare('SELECT * FROM courses WHERE id=?');
$courseStmt->execute([$courseId]);
$course = $courseStmt->fetch();
if (!$course) { header('Location: ' . BASE_URL . '/admin/courses.php'); exit; }

// ── Acciones ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_section') {
        $pdo->prepare('INSERT INTO sections (course_id, title, description, live_url, sort_order) VALUES (?, ?, ?, ?, ?)')
            ->execute([$courseId, trim($_POST['title']), trim($_POST['description'] ?? ''), trim($_POST['live_url'] ?? '') ?: null, $_POST['sort_order'] ?? 0]);
        $msg = 'Sección creada.'; $msgType = 'green';
    } elseif ($_POST['action'] === 'update_section') {
        $pdo->prepare('UPDATE sections SET title=?, description=?, live_url=?, sort_order=? WHERE id=? AND course_id=?')
            ->execute([trim($_POST['title']), trim($_POST['description'] ?? ''), trim($_POST['live_url'] ?? '') ?: null, $_POST['sort_order'] ?? 0, (int)$_POST['id'], $courseId]);
        $msg = 'Sección actualizada.'; $msgType = 'blue';
    } elseif ($_POST['action'] === 'delete_section') {
        $pdo->prepare('DELETE FROM sections WHERE id=? AND course_id=?')->execute([(int)$_POST['id'], $courseId]);
        $msg = 'Sección eliminada.'; $msgType = 'red';
    }
}

// Secciones
$sectionsStmt = $pdo->prepare('SELECT s.*, 
    (SELECT COUNT(*) FROM lessons WHERE section_id=s.id) as lesson_count,
    (SELECT COUNT(*) FROM evaluations WHERE section_id=s.id) as eval_count
    FROM sections s WHERE s.course_id=? ORDER BY s.sort_order');
$sectionsStmt->execute([$courseId]);
$sections = $sectionsStmt->fetchAll();

$pageTitle = 'Admin — ' . htmlspecialchars($course['title']);
$currentPage = 'cursos';
require __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
  <a href="<?= BASE_URL ?>/admin/courses.php" class="text-sm text-selcap-600 font-medium hover:underline">← Cursos</a>
</div>

<div class="flex items-center justify-between mb-6">
  <div>
    <h1 class="text-2xl font-extrabold text-gray-900"><?= htmlspecialchars($course['title']) ?></h1>
    <p class="text-sm text-gray-400">Secciones · <?= count($sections) ?> creadas</p>
  </div>
  <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold <?= $course['status']==='published'?'bg-green-100 text-green-700':'bg-amber-100 text-amber-700' ?>">
    <?= $course['status']==='published'?'Publicado':'Borrador' ?>
  </span>
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
              class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 wysiwyg-sm"></textarea>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <input type="text" name="live_url" placeholder="URL clase en vivo (Zoom/Meet) — opcional"
             class="px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm">
    </div>
    <button type="submit" class="bg-selcap-600 hover:bg-selcap-700 text-white font-semibold px-5 py-2.5 rounded-xl transition-colors text-sm">Crear sección</button>
  </form>
</div>

<!-- Lista de secciones -->
<div class="space-y-4">
  <?php foreach ($sections as $sec): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
      <form method="POST" class="space-y-3">
        <input type="hidden" name="action" value="update_section">
        <input type="hidden" name="id" value="<?= $sec['id'] ?>">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <input type="text" name="title" value="<?= htmlspecialchars($sec['title']) ?>"
                 class="sm:col-span-2 px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 font-medium">
          <input type="number" name="sort_order" value="<?= $sec['sort_order'] ?>"
                 class="px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500">
        </div>
        <textarea name="description" rows="2"
                  class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm wysiwyg-sm"><?= htmlspecialchars($sec['description'] ?? '') ?></textarea>
        <input type="text" name="live_url" value="<?= htmlspecialchars($sec['live_url'] ?? '') ?>" placeholder="URL clase en vivo (Zoom/Meet)"
               class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-4 text-xs text-gray-400">
            <span><?= $sec['lesson_count'] ?> lecciones</span>
            <span><?= $sec['eval_count'] ?> evaluaciones</span>
            <a href="<?= BASE_URL ?>/admin/lessons.php?section_id=<?= $sec['id'] ?>" class="text-selcap-600 font-medium hover:underline">Lecciones →</a>
            <a href="<?= BASE_URL ?>/admin/evaluations.php?course_id=<?= $courseId ?>" class="text-selcap-600 font-medium hover:underline">Evaluaciones →</a>
          </div>
          <div class="flex items-center gap-2">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2 rounded-xl transition-colors text-sm">Guardar</button>
          </div>
        </div>
      </form>
      <form method="POST" onsubmit="return confirm('¿Eliminar esta sección?')" class="inline mt-2">
        <input type="hidden" name="action" value="delete_section">
        <input type="hidden" name="id" value="<?= $sec['id'] ?>">
        <button type="submit" class="bg-red-100 hover:bg-red-200 text-red-700 font-semibold px-4 py-2 rounded-xl transition-colors text-sm">Eliminar</button>
      </form>
    </div>
  <?php endforeach; ?>
</div>

<?php if (empty($sections)): ?>
  <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-12 text-center text-gray-400">Crea la primera sección.</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
