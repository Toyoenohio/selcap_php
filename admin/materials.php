<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$pdo = db();
$msg = ''; $msgType = '';

$courseId = (int)($_GET['course_id'] ?? 0);
if (!$courseId) { header('Location: ' . BASE_URL . '/admin/courses.php'); exit; }

$courseStmt = $pdo->prepare('SELECT * FROM courses WHERE id=?');
$courseStmt->execute([$courseId]);
$course = $courseStmt->fetch();
if (!$course) { header('Location: ' . BASE_URL . '/admin/courses.php'); exit; }

// ── Upload ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'upload' && !empty($_FILES['file']['name'])) {
        $file = $_FILES['file'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $dir = UPLOADS_DIR . '/course_' . $courseId;
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
            $dest = $dir . '/' . $name;
            move_uploaded_file($file['tmp_name'], $dest);
            $url = UPLOADS_URL . '/course_' . $courseId . '/' . $name;
            $pdo->prepare('INSERT INTO course_materials (course_id, file_name, file_url, file_type, file_size) VALUES (?, ?, ?, ?, ?)')
                ->execute([$courseId, $file['name'], $url, $file['type'], $file['size']]);
            $msg = 'Archivo subido.'; $msgType = 'green';
        }
    } elseif ($_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        $mat = $pdo->prepare('SELECT file_url FROM course_materials WHERE id=? AND course_id=?');
        $mat->execute([$id, $courseId]);
        $row = $mat->fetch();
        if ($row) {
            $path = UPLOADS_DIR . '/course_' . $courseId . '/' . basename($row['file_url']);
            if (file_exists($path)) unlink($path);
            $pdo->prepare('DELETE FROM course_materials WHERE id=?')->execute([$id]);
            $msg = 'Archivo eliminado.'; $msgType = 'red';
        }
    }
}

// ── Lista de materiales ──
$matStmt = $pdo->prepare('SELECT * FROM course_materials WHERE course_id=? ORDER BY created_at DESC');
$matStmt->execute([$courseId]);
$materials = $matStmt->fetchAll();

$pageTitle = 'Materiales — ' . htmlspecialchars($course['title']);
$currentPage = 'cursos';
require __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
  <a href="<?= BASE_URL ?>/admin/courses.php" class="text-sm text-selcap-600 font-medium hover:underline">← Cursos</a>
</div>

<div class="flex items-center justify-between mb-6">
  <div>
    <h1 class="text-2xl font-extrabold text-gray-900">📁 Materiales</h1>
    <p class="text-sm text-gray-400"><?= htmlspecialchars($course['title']) ?> · <?= count($materials) ?> archivos</p>
  </div>
</div>

<?php if ($msg): ?>
  <div class="bg-<?= $msgType ?>-50 border border-<?= $msgType ?>-200 text-<?= $msgType ?>-700 px-4 py-3 rounded-xl text-sm mb-4"><?= $msg ?></div>
<?php endif; ?>

<!-- Subir -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 mb-6">
  <h2 class="font-bold text-gray-800 mb-3">Subir archivo</h2>
  <form method="POST" enctype="multipart/form-data" class="flex items-end gap-3 flex-wrap">
    <input type="hidden" name="action" value="upload">
    <input type="file" name="file" required
           class="text-sm text-gray-600 file:mr-3 file:py-2.5 file:px-5 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-selcap-50 file:text-selcap-700 hover:file:bg-selcap-100">
    <button type="submit" class="bg-selcap-600 hover:bg-selcap-700 text-white font-semibold px-5 py-2.5 rounded-xl transition-colors text-sm">Subir</button>
  </form>
  <p class="text-xs text-gray-400 mt-2">PDF, imágenes, documentos, presentaciones — máx 256MB. Se guardan en <code class="bg-gray-100 px-1 rounded">uploads/course_<?= $courseId ?>/</code></p>
</div>

<!-- Lista -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
  <?php if ($materials): ?>
    <div class="divide-y divide-gray-50">
      <?php foreach ($materials as $m): 
        $icon = '📁';
        $type = $m['file_type'] ?? '';
        if (strpos($type, 'pdf') !== false) $icon = '📄';
        elseif (strpos($type, 'image') !== false) $icon = '🖼️';
        elseif (strpos($type, 'presentation') !== false || strpos($m['file_name'], '.ppt') !== false) $icon = '📊';
        elseif (strpos($type, 'spreadsheet') !== false || strpos($m['file_name'], '.xls') !== false) $icon = '📈';
        elseif (strpos($type, 'video') !== false) $icon = '🎬';
        $size = $m['file_size'] > 1048576 ? round($m['file_size']/1048576,1).' MB' : round($m['file_size']/1024,1).' KB';
      ?>
        <div class="flex items-center justify-between px-5 py-4 hover:bg-gray-50 transition-colors">
          <div class="flex items-center gap-3 min-w-0">
            <span class="text-2xl shrink-0"><?= $icon ?></span>
            <div class="min-w-0">
              <p class="text-sm font-medium text-gray-800 truncate"><?= htmlspecialchars($m['file_name']) ?></p>
              <p class="text-xs text-gray-400"><?= $size ?> · <?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></p>
            </div>
          </div>
          <div class="flex items-center gap-3 shrink-0">
            <a href="<?= htmlspecialchars($m['file_url']) ?>" download class="text-selcap-600 hover:text-selcap-700 text-sm font-medium">Descargar</a>
            <form method="POST" onsubmit="return confirm('¿Eliminar este archivo?')" class="inline">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $m['id'] ?>">
              <button type="submit" class="text-red-400 hover:text-red-600 text-sm font-medium">Eliminar</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p class="text-center text-gray-400 py-16">No hay materiales todavía. Subí el primer archivo.</p>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
