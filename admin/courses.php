<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$pdo = db();
$msg = ''; $msgType = '';

// ── Acciones ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_course') {
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $sku = trim($_POST['sku'] ?? '');
        $status = $_POST['status'] ?? 'draft';
        $isSequential = isset($_POST['is_sequential']) ? 1 : 0;
        $passingGrade = (int)($_POST['passing_grade'] ?? 70);

        if ($title) {
            try {
                $stmt = $pdo->prepare('INSERT INTO courses (title, description, sku, is_sequential, passing_grade, status) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$title, $desc, $sku ?: null, $isSequential, $passingGrade, $status]);
                $msg = 'Curso creado.'; $msgType = 'green';
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $msg = 'El SKU ya existe. Usa uno diferente.'; $msgType = 'red';
                } else {
                    $msg = 'Error: ' . $e->getMessage(); $msgType = 'red';
                }
            }
        }
    } elseif ($_POST['action'] === 'update_course') {
        $id = (int)$_POST['id'];
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $sku = trim($_POST['sku'] ?? '');
        $status = $_POST['status'] ?? 'draft';
        $isSequential = isset($_POST['is_sequential']) ? 1 : 0;
        $passingGrade = (int)($_POST['passing_grade'] ?? 70);

        try {
            $pdo->prepare('UPDATE courses SET title=?, description=?, sku=?, is_sequential=?, passing_grade=?, status=? WHERE id=?')
                ->execute([$title, $desc, $sku ?: null, $isSequential, $passingGrade, $status, $id]);
            $msg = 'Curso actualizado.'; $msgType = 'blue';
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $msg = 'El SKU ya está en uso por otro curso.'; $msgType = 'red';
            } else {
                $msg = 'Error: ' . $e->getMessage(); $msgType = 'red';
            }
        }
    } elseif ($_POST['action'] === 'delete_course') {
        $id = (int)$_POST['id'];
        $pdo->prepare('DELETE FROM courses WHERE id = ?')->execute([$id]);
        $msg = 'Curso eliminado.'; $msgType = 'red';
    }
}

// ── Lista de cursos ──
$coursesStmt = $pdo->prepare('SELECT c.*, 
    (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) as student_count,
    (SELECT COUNT(*) FROM sections s WHERE s.course_id = c.id) as section_count
    FROM courses c ORDER BY c.created_at DESC');
$coursesStmt->execute();
$courses = $coursesStmt->fetchAll();

$pageTitle = 'Admin — Cursos';
$currentPage = 'cursos';
require __DIR__ . '/../includes/header.php';
?>

<div class="flex items-center justify-between mb-6">
  <h1 class="text-2xl font-extrabold text-gray-900">Cursos</h1>
  <div class="flex items-center gap-2 text-sm flex-wrap">
    <a href="<?= BASE_URL ?>/admin/courses.php" class="bg-selcap-600 text-white px-4 py-2 rounded-xl font-semibold">Cursos</a>
    <a href="<?= BASE_URL ?>/admin/" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-xl font-semibold transition-colors">Secciones</a>
    <a href="<?= BASE_URL ?>/admin/lessons.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-xl font-semibold transition-colors">Lecciones</a>
    <a href="<?= BASE_URL ?>/admin/evaluations.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-xl font-semibold transition-colors">Evaluaciones</a>
    <a href="<?= BASE_URL ?>/admin/alumnos.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-xl font-semibold transition-colors">Alumnos</a>
    <a href="<?= BASE_URL ?>/admin/reportes.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-xl font-semibold transition-colors">Reportes</a>
  </div>
</div>

<?php if ($msg): ?>
  <div class="bg-<?= $msgType ?>-50 border border-<?= $msgType ?>-200 text-<?= $msgType ?>-700 px-4 py-3 rounded-xl text-sm mb-4"><?= $msg ?></div>
<?php endif; ?>

<!-- Crear curso -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 mb-6">
  <h2 class="font-bold text-gray-800 mb-3">Nuevo curso</h2>
  <form method="POST" class="space-y-3">
    <input type="hidden" name="action" value="create_course">
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
      <input type="text" name="title" placeholder="Título del curso" required
             class="sm:col-span-2 px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500">
      <input type="text" name="sku" placeholder="SKU (WooCommerce)"
             class="px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 font-mono text-sm">
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
      <select name="status" class="px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm">
        <option value="draft">Borrador</option>
        <option value="published" selected>Publicado</option>
      </select>
      <input type="number" name="passing_grade" value="70" min="0" max="100" placeholder="Nota aprobación %"
             class="px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm">
      <label class="flex items-center gap-2 text-sm text-gray-700 px-2">
        <input type="checkbox" name="is_sequential" class="accent-selcap-600 w-4 h-4">
        Secuencial
      </label>
    </div>
    <textarea name="description" placeholder="Descripción" rows="2"
              class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500"></textarea>
    <button type="submit" class="bg-selcap-600 hover:bg-selcap-700 text-white font-semibold px-5 py-2.5 rounded-xl transition-colors text-sm">
      Crear curso
    </button>
  </form>
</div>

<!-- Lista de cursos -->
<div class="space-y-4">
  <?php foreach ($courses as $c): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
      <form method="POST" class="space-y-3">
        <input type="hidden" name="action" value="update_course">
        <input type="hidden" name="id" value="<?= $c['id'] ?>">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <input type="text" name="title" value="<?= htmlspecialchars($c['title']) ?>" required
                 class="sm:col-span-2 px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 font-medium">
          <input type="text" name="sku" value="<?= htmlspecialchars($c['sku'] ?? '') ?>" placeholder="SKU"
                 class="px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 font-mono text-sm">
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <select name="status" class="px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm">
            <option value="draft" <?= $c['status'] === 'draft' ? 'selected' : '' ?>>Borrador</option>
            <option value="published" <?= $c['status'] === 'published' ? 'selected' : '' ?>>Publicado</option>
            <option value="archived" <?= $c['status'] === 'archived' ? 'selected' : '' ?>>Archivado</option>
          </select>
          <input type="number" name="passing_grade" value="<?= $c['passing_grade'] ?? 70 ?>" min="0" max="100"
                 class="px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm">
          <label class="flex items-center gap-2 text-sm text-gray-700 px-2">
            <input type="checkbox" name="is_sequential" <?= $c['is_sequential'] ? 'checked' : '' ?> class="accent-selcap-600 w-4 h-4">
            Secuencial
          </label>
          <div class="flex items-center gap-1 text-xs text-gray-400 px-2">
            <span><?= $c['student_count'] ?> alumnos</span>
            <span>·</span>
            <span><?= $c['section_count'] ?> secciones</span>
          </div>
        </div>
        <textarea name="description" rows="2"
                  class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm"><?= htmlspecialchars($c['description'] ?? '') ?></textarea>
        <div class="flex items-center gap-2">
          <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2 rounded-xl transition-colors text-sm">Guardar</button>
          <a href="<?= BASE_URL ?>/curso.php?id=<?= $c['id'] ?>" target="_blank" class="text-selcap-600 text-sm font-medium hover:underline">Ver →</a>
          <?php if ($c['section_count'] == 0 && $c['student_count'] == 0): ?>
          <form method="POST" onsubmit="return confirm('¿Eliminar este curso?')" class="inline">
            <input type="hidden" name="action" value="delete_course">
            <input type="hidden" name="id" value="<?= $c['id'] ?>">
            <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-semibold">Eliminar</button>
          </form>
          <?php endif; ?>
        </div>
      </form>
    </div>
  <?php endforeach; ?>
</div>

<?php if (empty($courses)): ?>
  <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-12 text-center text-gray-400">
    Crea el primer curso.
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
