<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$pdo = db();
$msg = ''; $msgType = '';

// Acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_lesson') {
        $stmt = $pdo->prepare('INSERT INTO lessons (section_id, title, content_html, video_url, sort_order)
            VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([
            (int)$_POST['section_id'],
            trim($_POST['title']),
            $_POST['content_html'] ?? '',
            $_POST['video_url'] ?? '',
            $_POST['sort_order'] ?? 0
        ]);
        $lessonId = (int) $pdo->lastInsertId();

        // Subir archivo
        if (!empty($_FILES['attachment']['name'])) {
            handleUpload($lessonId, $_FILES['attachment']);
        }
        $msg = 'Lección creada.'; $msgType = 'green';
    } elseif ($_POST['action'] === 'update_lesson') {
        $pdo->prepare('UPDATE lessons SET section_id = ?, title = ?, content_html = ?, video_url = ?, sort_order = ? WHERE id = ?')
            ->execute([(int)$_POST['section_id'], trim($_POST['title']), $_POST['content_html'] ?? '', $_POST['video_url'] ?? '', $_POST['sort_order'] ?? 0, (int)$_POST['id']]);
        $msg = 'Lección actualizada.'; $msgType = 'blue';
    } elseif ($_POST['action'] === 'delete_lesson') {
        $id = (int)$_POST['id'];
        // Borrar archivos físicos
        $atts = $pdo->prepare('SELECT file_url FROM lesson_attachments WHERE lesson_id = ?');
        $atts->execute([$id]);
        foreach ($atts->fetchAll() as $att) {
            $path = UPLOADS_DIR . '/' . basename($att['file_url']);
            if (file_exists($path)) unlink($path);
        }
        $pdo->prepare('DELETE FROM lessons WHERE id = ?')->execute([$id]);
        $msg = 'Lección eliminada.'; $msgType = 'red';
    } elseif ($_POST['action'] === 'upload_attachment') {
        handleUpload((int)$_POST['lesson_id'], $_FILES['attachment']);
        $msg = 'Archivo subido.'; $msgType = 'green';
    } elseif ($_POST['action'] === 'delete_attachment') {
        $attId = (int)$_POST['att_id'];
        $att = $pdo->prepare('SELECT file_url FROM lesson_attachments WHERE id = ?');
        $att->execute([$attId]);
        $file = $att->fetch();
        if ($file) {
            $path = UPLOADS_DIR . '/' . basename($file['file_url']);
            if (file_exists($path)) unlink($path);
        }
        $pdo->prepare('DELETE FROM lesson_attachments WHERE id = ?')->execute([$attId]);
        $msg = 'Archivo eliminado.'; $msgType = 'red';
    }
}

function handleUpload(int $lessonId, array $file): void {
    if ($file['error'] !== UPLOAD_ERR_OK) return;
    $name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
    $dest = UPLOADS_DIR . '/' . $name;
    if (!is_dir(UPLOADS_DIR)) mkdir(UPLOADS_DIR, 0755, true);
    move_uploaded_file($file['tmp_name'], $dest);

    $pdo = db();
    $pdo->prepare('INSERT INTO lesson_attachments (lesson_id, file_name, file_url, file_type, file_size)
        VALUES (?, ?, ?, ?, ?)')
        ->execute([$lessonId, $file['name'], UPLOADS_URL . '/' . $name, $file['type'], $file['size']]);
}

// Secciones para filtro
$sectionsStmt = $pdo->prepare('SELECT * FROM sections WHERE course_id = ? ORDER BY sort_order');
$sectionsStmt->execute([ACTIVE_COURSE_ID]);
$sections = $sectionsStmt->fetchAll();

// Filtro
$filterSection = $_GET['section_id'] ?? '';
$where = $filterSection ? 'WHERE s.id = ' . (int)$filterSection : '';

$lessonsStmt = $pdo->prepare("SELECT l.*, s.title as section_title FROM lessons l
    JOIN sections s ON l.section_id = s.id $where ORDER BY s.sort_order, l.sort_order");
$lessonsStmt->execute();
$lessons = $lessonsStmt->fetchAll();

// Adjuntos por lección
$attStmt = $pdo->prepare('SELECT * FROM lesson_attachments WHERE lesson_id IN (SELECT id FROM lessons l JOIN sections s ON l.section_id = s.id ' . ($filterSection ? 'WHERE s.id = ' . (int)$filterSection : '') . ') ORDER BY id');
$attStmt->execute();
$attachments = [];
foreach ($attStmt->fetchAll() as $a) {
    $attachments[$a['lesson_id']][] = $a;
}

$pageTitle = 'Admin — Lecciones';
require __DIR__ . '/../includes/header.php';
?>

<div class="flex items-center justify-between mb-6">
  <h1 class="text-2xl font-extrabold text-gray-900">Lecciones</h1>
  <div class="flex items-center gap-2 text-sm">
    <a href="<?= BASE_URL ?>/admin/courses.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-xl font-semibold transition-colors">Cursos</a>
    <a href="<?= BASE_URL ?>/admin/" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-xl font-semibold transition-colors">Secciones</a>
    <a href="<?= BASE_URL ?>/admin/lessons.php" class="bg-selcap-600 text-white px-4 py-2 rounded-xl font-semibold">Lecciones</a>
    <a href="<?= BASE_URL ?>/admin/evaluations.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-xl font-semibold transition-colors">Evaluaciones</a>
    <a href="<?= BASE_URL ?>/admin/alumnos.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-xl font-semibold transition-colors">Alumnos</a>
    <a href="<?= BASE_URL ?>/admin/reportes.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-xl font-semibold transition-colors">Reportes</a>
  </div>
</div>

<?php if ($msg): ?>
  <div class="bg-<?= $msgType ?>-50 border border-<?= $msgType ?>-200 text-<?= $msgType ?>-700 px-4 py-3 rounded-xl text-sm mb-4"><?= $msg ?></div>
<?php endif; ?>

<?php if ($filterSection): ?>
  <a href="<?= BASE_URL ?>/admin/lessons.php" class="text-selcap-600 text-sm font-medium hover:underline mb-4 inline-block">← Todas las secciones</a>
<?php endif; ?>

<!-- Crear lección -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 mb-6">
  <h2 class="font-bold text-gray-800 mb-3">Nueva lección</h2>
  <form method="POST" enctype="multipart/form-data" class="space-y-3">
    <input type="hidden" name="action" value="create_lesson">
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
      <select name="section_id" required class="px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500">
        <option value="">Seleccionar sección</option>
        <?php foreach ($sections as $s): ?>
          <option value="<?= $s['id'] ?>" <?= $filterSection == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['title']) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="text" name="title" placeholder="Título de la lección" required
             class="sm:col-span-2 px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500">
    </div>
    <textarea name="content_html" placeholder="Contenido HTML" rows="6"
              class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 font-mono text-sm"></textarea>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <input type="text" name="video_url" placeholder="URL del video (YouTube o directo)"
             class="px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500">
      <input type="number" name="sort_order" placeholder="Orden" value="0"
             class="px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500">
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-600 mb-1">Archivo adjunto (opcional)</label>
      <input type="file" name="attachment" class="text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-selcap-50 file:text-selcap-700 hover:file:bg-selcap-100">
    </div>
    <button type="submit" class="bg-selcap-600 hover:bg-selcap-700 text-white font-semibold px-5 py-2.5 rounded-xl transition-colors text-sm">
      Crear lección
    </button>
  </form>
</div>

<!-- Lista de lecciones -->
<div class="space-y-4">
  <?php foreach ($lessons as $l): 
    $lessonAtts = $attachments[$l['id']] ?? [];
  ?>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
      <form method="POST" class="space-y-3">
        <input type="hidden" name="action" value="update_lesson">
        <input type="hidden" name="id" value="<?= $l['id'] ?>">
        <div class="space-y-3">
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <select name="section_id" class="px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm">
              <?php foreach ($sections as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $s['id'] == $l['section_id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['title']) ?></option>
              <?php endforeach; ?>
            </select>
            <input type="text" name="title" value="<?= htmlspecialchars($l['title']) ?>"
                   class="sm:col-span-2 px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 font-medium">
          </div>
          <textarea name="content_html" rows="4"
                    class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 font-mono text-xs"><?= htmlspecialchars($l['content_html'] ?? '') ?></textarea>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <input type="text" name="video_url" value="<?= htmlspecialchars($l['video_url'] ?? '') ?>"
                   class="px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm">
            <input type="number" name="sort_order" value="<?= $l['sort_order'] ?>"
                   class="px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm">
          </div>

          <!-- Adjuntos -->
          <?php if ($lessonAtts): ?>
            <div class="space-y-1.5">
              <?php foreach ($lessonAtts as $att): ?>
                <div class="flex items-center justify-between px-3 py-2 bg-gray-50 rounded-xl text-sm">
                  <span><?= htmlspecialchars($att['file_name']) ?> <span class="text-gray-400">(<?= round($att['file_size']/1024, 1) ?> KB)</span></span>
                  <form method="POST" class="inline" onsubmit="return confirm('¿Eliminar este archivo?')">
                    <input type="hidden" name="action" value="delete_attachment">
                    <input type="hidden" name="att_id" value="<?= $att['id'] ?>">
                    <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-semibold">Eliminar</button>
                  </form>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <form method="POST" enctype="multipart/form-data" class="inline-flex items-center gap-2">
              <input type="hidden" name="action" value="upload_attachment">
              <input type="hidden" name="lesson_id" value="<?= $l['id'] ?>">
              <input type="file" name="attachment" required
                     class="text-xs text-gray-600 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-selcap-50 file:text-selcap-700">
              <button type="submit" class="text-xs font-medium text-selcap-600 hover:underline">Subir</button>
            </form>
          </div>
          <div class="flex items-center gap-2">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2 rounded-xl transition-colors text-sm">Guardar</button>
            <form method="POST" onsubmit="return confirm('¿Eliminar esta lección?')" class="inline">
              <input type="hidden" name="action" value="delete_lesson">
              <input type="hidden" name="id" value="<?= $l['id'] ?>">
              <button type="submit" class="bg-red-100 hover:bg-red-200 text-red-700 font-semibold px-4 py-2 rounded-xl transition-colors text-sm">Eliminar</button>
            </form>
          </div>
        </div>
      </form>
    </div>
  <?php endforeach; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
