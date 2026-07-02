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
                $msg = $e->getCode() == 23000 ? 'El SKU ya existe.' : 'Error: ' . $e->getMessage();
                $msgType = 'red';
            }
        }
    } elseif ($_POST['action'] === 'update_course') {
        $id = (int)$_POST['id'];
        try {
            $pdo->prepare('UPDATE courses SET title=?, description=?, sku=?, is_sequential=?, passing_grade=?, status=? WHERE id=?')
                ->execute([trim($_POST['title']), trim($_POST['description'] ?? ''), trim($_POST['sku'] ?? '') ?: null,
                    isset($_POST['is_sequential']) ? 1 : 0, (int)$_POST['passing_grade'], $_POST['status'], $id]);
            $msg = 'Curso actualizado.'; $msgType = 'blue';
        } catch (PDOException $e) {
            $msg = $e->getCode() == 23000 ? 'SKU duplicado.' : 'Error: ' . $e->getMessage();
            $msgType = 'red';
        }
    } elseif ($_POST['action'] === 'delete_course') {
        $id = (int)$_POST['id'];
        // Solo si no tiene secciones ni alumnos
        $chk = $pdo->prepare('SELECT (SELECT COUNT(*) FROM sections WHERE course_id=?) + (SELECT COUNT(*) FROM enrollments WHERE course_id=?) as cnt');
        $chk->execute([$id, $id]);
        if ((int)$chk->fetch()['cnt'] === 0) {
            $pdo->prepare('DELETE FROM courses WHERE id=?')->execute([$id]);
            $msg = 'Curso eliminado.'; $msgType = 'red';
        } else {
            $msg = 'No se puede eliminar: tiene secciones o alumnos.'; $msgType = 'red';
        }
    } elseif ($_POST['action'] === 'clone_course') {
        $srcId = (int)$_POST['id'];
        $srcStmt = $pdo->prepare('SELECT * FROM courses WHERE id=?');
        $srcStmt->execute([$srcId]);
        $src = $srcStmt->fetch();
        if (!$src) { $msg = 'Curso no encontrado.'; $msgType = 'red'; }
        else {
            $pdo->beginTransaction();
            try {
                // Copiar curso
                $newTitle = trim($_POST['clone_title'] ?? '') ?: $src['title'] . ' (copia)';
                $pdo->prepare('INSERT INTO courses (title, description, sku, is_sequential, passing_grade, status) VALUES (?, ?, NULL, ?, ?, "draft")')
                    ->execute([$newTitle, $src['description'], $src['is_sequential'], $src['passing_grade']]);
                $newCourseId = (int)$pdo->lastInsertId();

                // Copiar secciones → lecciones → attachments → evaluaciones → preguntas → respuestas
                $secStmt = $pdo->prepare('SELECT * FROM sections WHERE course_id=? ORDER BY sort_order');
                $secStmt->execute([$srcId]);
                foreach ($secStmt->fetchAll() as $sec) {
                    $pdo->prepare('INSERT INTO sections (course_id, title, description, live_url, sort_order) VALUES (?, ?, ?, ?, ?)')
                        ->execute([$newCourseId, $sec['title'], $sec['description'], $sec['live_url'], $sec['sort_order']]);
                    $newSecId = (int)$pdo->lastInsertId();

                    // Lecciones
                    $lesStmt = $pdo->prepare('SELECT * FROM lessons WHERE section_id=? ORDER BY sort_order');
                    $lesStmt->execute([$sec['id']]);
                    foreach ($lesStmt->fetchAll() as $les) {
                        $pdo->prepare('INSERT INTO lessons (section_id, title, content_html, video_url, live_url, sort_order) VALUES (?, ?, ?, ?, ?, ?)')
                            ->execute([$newSecId, $les['title'], $les['content_html'], $les['video_url'], $les['live_url'], $les['sort_order']]);
                        $newLesId = (int)$pdo->lastInsertId();

                        // Attachments (compartir URL — misma file_url, no duplicar archivos)
                        $attStmt = $pdo->prepare('SELECT * FROM lesson_attachments WHERE lesson_id=?');
                        $attStmt->execute([$les['id']]);
                        foreach ($attStmt->fetchAll() as $att) {
                            $pdo->prepare('INSERT INTO lesson_attachments (lesson_id, file_name, file_url, file_type, file_size) VALUES (?, ?, ?, ?, ?)')
                                ->execute([$newLesId, $att['file_name'], $att['file_url'], $att['file_type'], $att['file_size']]);
                        }
                    }

                    // Evaluaciones
                    $evStmt = $pdo->prepare('SELECT * FROM evaluations WHERE section_id=? ORDER BY sort_order');
                    $evStmt->execute([$sec['id']]);
                    foreach ($evStmt->fetchAll() as $ev) {
                        $pdo->prepare('INSERT INTO evaluations (section_id, title, description, max_attempts, passing_score, sort_order) VALUES (?, ?, ?, ?, ?, ?)')
                            ->execute([$newSecId, $ev['title'], $ev['description'], $ev['max_attempts'], $ev['passing_score'], $ev['sort_order']]);
                        $newEvId = (int)$pdo->lastInsertId();

                        // Preguntas
                        $qStmt = $pdo->prepare('SELECT * FROM questions WHERE evaluation_id=? ORDER BY sort_order');
                        $qStmt->execute([$ev['id']]);
                        foreach ($qStmt->fetchAll() as $q) {
                            $pdo->prepare('INSERT INTO questions (evaluation_id, text, type, points, sort_order) VALUES (?, ?, ?, ?, ?)')
                                ->execute([$newEvId, $q['text'], $q['type'], $q['points'], $q['sort_order']]);
                            $newQId = (int)$pdo->lastInsertId();

                            // Respuestas
                            $aStmt = $pdo->prepare('SELECT * FROM answers WHERE question_id=? ORDER BY sort_order');
                            $aStmt->execute([$q['id']]);
                            foreach ($aStmt->fetchAll() as $a) {
                                $pdo->prepare('INSERT INTO answers (question_id, text, is_correct, sort_order) VALUES (?, ?, ?, ?)')
                                    ->execute([$newQId, $a['text'], $a['is_correct'], $a['sort_order']]);
                            }
                        }
                    }
                }
                // Copiar materiales del curso (compartir archivos — misma URL)
                $matStmt = $pdo->prepare('SELECT * FROM course_materials WHERE course_id=?');
                $matStmt->execute([$srcId]);
                foreach ($matStmt->fetchAll() as $mat) {
                    $pdo->prepare('INSERT INTO course_materials (course_id, file_name, file_url, file_type, file_size) VALUES (?, ?, ?, ?, ?)')
                        ->execute([$newCourseId, $mat['file_name'], $mat['file_url'], $mat['file_type'], $mat['file_size']]);
                }
                $pdo->commit();
                $msg = "Curso duplicado: $newTitle"; $msgType = 'green';
            } catch (Exception $e) {
                $pdo->rollBack();
                $msg = 'Error al duplicar: ' . $e->getMessage(); $msgType = 'red';
            }
        }
    }
}

// ── Lista de cursos ──
$coursesStmt = $pdo->prepare('SELECT c.*, 
    (SELECT COUNT(*) FROM enrollments WHERE course_id=c.id) as students,
    (SELECT COUNT(*) FROM sections WHERE course_id=c.id) as sections_cnt,
    (SELECT COUNT(*) FROM lessons l JOIN sections s ON l.section_id=s.id WHERE s.course_id=c.id) as lessons_cnt
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
    <a href="<?= BASE_URL ?>/admin/alumnos.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-xl font-semibold transition-colors">Alumnos</a>
    <a href="<?= BASE_URL ?>/admin/reportes.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-xl font-semibold transition-colors">Reportes</a>
  </div>
</div>

<?php if ($msg): ?>
  <div class="bg-<?= $msgType ?>-50 border border-<?= $msgType ?>-200 text-<?= $msgType ?>-700 px-4 py-3 rounded-xl text-sm mb-4"><?= $msg ?></div>
<?php endif; ?>

<!-- Crear curso -->
<details class="bg-white rounded-2xl shadow-sm border border-gray-200 mb-6" <?= empty($courses) ? 'open' : '' ?>>
  <summary class="p-5 cursor-pointer font-bold text-gray-800 hover:text-selcap-600 transition-colors select-none">+ Nuevo curso</summary>
  <form method="POST" class="px-5 pb-5 space-y-3">
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
      <input type="number" name="passing_grade" value="70" min="0" max="100" placeholder="% Aprobación"
             class="px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm">
      <label class="flex items-center gap-2 text-sm text-gray-700 px-2">
        <input type="checkbox" name="is_sequential" class="accent-selcap-600 w-4 h-4"> Secuencial
      </label>
    </div>
    <textarea name="description" placeholder="Descripción" rows="2"
              class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 wysiwyg-sm"></textarea>
    <button type="submit" class="bg-selcap-600 hover:bg-selcap-700 text-white font-semibold px-5 py-2.5 rounded-xl transition-colors text-sm">Crear curso</button>
  </form>
</details>

<!-- Lista de cursos -->
<div class="space-y-4">
  <?php foreach ($courses as $c): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
      <!-- Cabecera -->
      <div class="p-5 flex items-start justify-between gap-4">
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-3 mb-1">
            <h2 class="text-lg font-bold text-gray-900 truncate"><?= htmlspecialchars($c['title']) ?></h2>
            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold shrink-0
              <?= $c['status'] === 'published' ? 'bg-green-100 text-green-700' : ($c['status'] === 'draft' ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-500') ?>">
              <?= $c['status'] === 'published' ? 'Publicado' : ($c['status'] === 'draft' ? 'Borrador' : 'Archivado') ?>
            </span>
          </div>
          <div class="flex items-center gap-3 text-xs text-gray-400 mt-1 flex-wrap">
            <span><?= $c['sections_cnt'] ?> secciones</span>
            <span>·</span>
            <span><?= $c['lessons_cnt'] ?> lecciones</span>
            <span>·</span>
            <span><?= $c['students'] ?> alumnos</span>
            <?php if ($c['sku']): ?>
              <span>·</span>
              <span class="font-mono bg-gray-100 px-1.5 py-0.5 rounded text-gray-500"><?= htmlspecialchars($c['sku']) ?></span>
            <?php endif; ?>
          </div>
          <?php if ($c['description']): ?>
            <p class="text-sm text-gray-500 mt-2 line-clamp-2"><?= htmlspecialchars($c['description']) ?></p>
          <?php endif; ?>
        </div>
      </div>

      <!-- Acciones -->
      <div class="border-t border-gray-100 px-5 py-3 flex items-center gap-2 flex-wrap">
        <button onclick="toggleEdit(<?= $c['id'] ?>)" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-3 py-1.5 rounded-lg transition-colors text-xs">Editar</button>
        <a href="<?= BASE_URL ?>/admin/sections.php?course_id=<?= $c['id'] ?>" class="bg-selcap-600 hover:bg-selcap-700 text-white font-semibold px-3 py-1.5 rounded-lg transition-colors text-xs">Secciones →</a>
        <a href="<?= BASE_URL ?>/admin/materials.php?course_id=<?= $c['id'] ?>" class="bg-amber-500 hover:bg-amber-600 text-white font-semibold px-3 py-1.5 rounded-lg transition-colors text-xs">📁 Materiales</a>
        <a href="<?= BASE_URL ?>/curso.php?id=<?= $c['id'] ?>" target="_blank" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold px-3 py-1.5 rounded-lg transition-colors text-xs">Ver curso</a>

        <!-- Duplicar -->
        <form method="POST" class="inline" onsubmit="return duplicateCourse(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['title'])) ?>')">
          <input type="hidden" name="action" value="clone_course">
          <input type="hidden" name="id" value="<?= $c['id'] ?>">
          <input type="hidden" name="clone_title" id="clone_title_<?= $c['id'] ?>" value="">
          <button type="submit" class="bg-purple-100 hover:bg-purple-200 text-purple-700 font-semibold px-3 py-1.5 rounded-lg transition-colors text-xs">Duplicar</button>
        </form>

        <?php if ($c['sections_cnt'] == 0 && $c['students'] == 0): ?>
        <form method="POST" onsubmit="return confirm('¿Eliminar este curso?')" class="inline">
          <input type="hidden" name="action" value="delete_course">
          <input type="hidden" name="id" value="<?= $c['id'] ?>">
          <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-semibold px-2">Eliminar</button>
        </form>
        <?php endif; ?>
      </div>

      <!-- Formulario de edición (oculto) -->
      <div id="edit_<?= $c['id'] ?>" class="hidden border-t border-gray-100 px-5 py-4 bg-gray-50/50">
        <form method="POST" class="space-y-3">
          <input type="hidden" name="action" value="update_course">
          <input type="hidden" name="id" value="<?= $c['id'] ?>">
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <input type="text" name="title" value="<?= htmlspecialchars($c['title']) ?>" required
                   class="sm:col-span-2 px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 font-medium">
            <input type="text" name="sku" value="<?= htmlspecialchars($c['sku'] ?? '') ?>" placeholder="SKU"
                   class="px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 font-mono text-sm">
          </div>
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            <select name="status" class="px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm">
              <option value="draft" <?= $c['status']==='draft'?'selected':'' ?>>Borrador</option>
              <option value="published" <?= $c['status']==='published'?'selected':'' ?>>Publicado</option>
              <option value="archived" <?= $c['status']==='archived'?'selected':'' ?>>Archivado</option>
            </select>
            <input type="number" name="passing_grade" value="<?= $c['passing_grade']??70 ?>" min="0" max="100"
                   class="px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm">
            <label class="flex items-center gap-2 text-sm text-gray-700 px-2">
              <input type="checkbox" name="is_sequential" <?= $c['is_sequential']?'checked':'' ?> class="accent-selcap-600 w-4 h-4"> Secuencial
            </label>
          </div>
          <textarea name="description" rows="2"
                    class="w-full px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm wysiwyg-sm"><?= htmlspecialchars($c['description']??'') ?></textarea>
          <div class="flex gap-2">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2 rounded-xl transition-colors text-sm">Guardar cambios</button>
            <button type="button" onclick="toggleEdit(<?= $c['id'] ?>)" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold px-4 py-2 rounded-xl transition-colors text-sm">Cancelar</button>
          </div>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php if (empty($courses)): ?>
  <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-12 text-center text-gray-400">Crea el primer curso.</div>
<?php endif; ?>

<script>
function toggleEdit(id) { document.getElementById('edit_'+id).classList.toggle('hidden'); }
function duplicateCourse(id, title) {
    var name = prompt('Nombre para la copia:', title + ' (copia)');
    if (!name) return false;
    document.getElementById('clone_title_'+id).value = name;
    return true;
}
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
