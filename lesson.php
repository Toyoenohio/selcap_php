<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$lessonId = (int) ($_GET['id'] ?? 0);
$userId = $_SESSION['user_id'];
$pdo = db();

$stmt = $pdo->prepare('SELECT l.*, s.id as section_id, s.title as section_title, s.course_id, c.title as course_title, c.is_sequential
    FROM lessons l
    JOIN sections s ON l.section_id = s.id
    JOIN courses c ON s.course_id = c.id
    WHERE l.id = ?');
$stmt->execute([$lessonId]);
$lesson = $stmt->fetch();

if (!$lesson) {
    http_response_code(404);
    $pageTitle = 'Lección no encontrada';
    require __DIR__ . '/includes/header.php';
    echo '<div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-12 text-center"><p class="text-gray-500">Lección no encontrada.</p><a href="' . BASE_URL . '/dashboard.php" class="text-selcap-600 font-medium text-sm mt-4 inline-block">Volver al curso</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

// Secuencial: verificar lección anterior completada
if ($lesson['is_sequential'] && $lesson['sort_order'] > 1) {
    $prevStmt = $pdo->prepare('SELECT l.id FROM lessons l WHERE l.section_id = ? AND l.sort_order = ?');
    $prevStmt->execute([$lesson['section_id'], $lesson['sort_order'] - 1]);
    $prev = $prevStmt->fetch();
    if ($prev) {
        $progStmt = $pdo->prepare('SELECT completed FROM lesson_progress WHERE user_id = ? AND lesson_id = ?');
        $progStmt->execute([$userId, $prev['id']]);
        $prevProg = $progStmt->fetch();
        if (!$prevProg || !$prevProg['completed']) {
            header('Location: ' . BASE_URL . '/dashboard.php');
            exit;
        }
    }
}

// Marcar como completada
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete'])) {
    $upsert = $pdo->prepare('INSERT INTO lesson_progress (user_id, lesson_id, completed, completed_at)
        VALUES (?, ?, 1, NOW())
        ON DUPLICATE KEY UPDATE completed = 1, completed_at = NOW()');
    $upsert->execute([$userId, $lessonId]);
    header('Location: ' . BASE_URL . '/lesson.php?id=' . $lessonId . '&done=1');
    exit;
}

// Progreso actual
$progStmt = $pdo->prepare('SELECT completed FROM lesson_progress WHERE user_id = ? AND lesson_id = ?');
$progStmt->execute([$userId, $lessonId]);
$progress = $progStmt->fetch();
$isDone = $progress && $progress['completed'];

// Archivos adjuntos
$attStmt = $pdo->prepare('SELECT * FROM lesson_attachments WHERE lesson_id = ? ORDER BY id');
$attStmt->execute([$lessonId]);
$attachments = $attStmt->fetchAll();

// Lecciones del curso en orden (para navegación global)
$allLessons = $pdo->prepare('SELECT l.id, l.title FROM lessons l JOIN sections s ON l.section_id = s.id WHERE s.course_id = ? ORDER BY s.sort_order, l.sort_order');
$allLessons->execute([$lesson['course_id']]);
$lessonList = $allLessons->fetchAll();

$currentIdx = -1;
$prevLesson = null;
$nextLesson = null;
foreach ($lessonList as $i => $l) {
    if ((int)$l['id'] === $lessonId) {
        $currentIdx = $i;
        break;
    }
}
if ($currentIdx > 0) $prevLesson = $lessonList[$currentIdx - 1];
if ($currentIdx < count($lessonList) - 1) $nextLesson = $lessonList[$currentIdx + 1];

$pageTitle = $lesson['title'];
require __DIR__ . '/includes/header.php';
?>

<!-- Breadcrumb -->
<nav class="flex items-center gap-2 text-sm text-gray-400 mb-4 flex-wrap">
  <a href="<?= BASE_URL ?>/dashboard.php" class="hover:text-selcap-600 transition-colors"><?= htmlspecialchars($lesson['course_title']) ?></a>
  <span>›</span>
  <span class="text-gray-600"><?= htmlspecialchars($lesson['section_title']) ?></span>
  <span>›</span>
  <span class="text-gray-800 font-medium"><?= htmlspecialchars($lesson['title']) ?></span>
  <?php if ($isDone): ?>
    <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-semibold ml-1">Completada</span>
  <?php endif; ?>
</nav>

<?php if (isset($_GET['done'])): ?>
  <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl text-sm mb-4 flex items-center gap-2">
    ✅ Lección completada
  </div>
<?php endif; ?>

<div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 sm:p-8">
  <!-- Video -->
  <?php if ($lesson['video_url']): ?>
    <div class="mb-6 aspect-video rounded-xl overflow-hidden bg-black">
      <?php if (strpos($lesson['video_url'], 'youtube.com') !== false || strpos($lesson['video_url'], 'youtu.be') !== false): ?>
        <?php 
          preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([\w-]+)/', $lesson['video_url'], $m);
          $ytId = $m[1] ?? '';
        ?>
        <?php if ($ytId): ?>
          <iframe src="https://www.youtube.com/embed/<?= htmlspecialchars($ytId) ?>" class="w-full h-full" frameborder="0" allowfullscreen></iframe>
        <?php endif; ?>
      <?php else: ?>
        <video src="<?= htmlspecialchars($lesson['video_url']) ?>" controls class="w-full h-full"></video>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <!-- Clase en vivo -->
  <?php if (!empty($lesson['live_url'])): 
    $isZoomMeet = strpos($lesson['live_url'], 'zoom.us') !== false || strpos($lesson['live_url'], 'meet.google.com') !== false;
  ?>
    <div class="mb-6 rounded-xl overflow-hidden border-2 border-red-200 bg-red-50/30">
      <div class="px-4 py-2 bg-red-500 text-white flex items-center gap-2 text-sm font-semibold">
        <span class="w-2 h-2 bg-white rounded-full animate-pulse"></span>
        🔴 EN VIVO
      </div>
      <?php if ($isZoomMeet): ?>
        <div class="p-6 text-center">
          <p class="text-sm text-gray-600 mb-4">Zoom / Meet no permite incrustar. Usa el botón:</p>
          <a href="<?= htmlspecialchars($lesson['live_url']) ?>" target="_blank" rel="noopener"
             class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white font-bold px-6 py-3 rounded-xl transition-colors text-base">
            🔴 Unirse a la clase en vivo →
          </a>
        </div>
      <?php else: ?>
        <div class="aspect-video">
          <iframe src="<?= htmlspecialchars($lesson['live_url']) ?>" class="w-full h-full" frameborder="0" allow="camera; microphone; fullscreen; display-capture" allowfullscreen></iframe>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <!-- Contenido HTML -->
  <div class="lesson-content prose max-w-none">
    <?= $lesson['content_html'] ?>
  </div>

  <!-- Archivos adjuntos -->
  <?php if ($attachments): ?>
    <div class="mt-6 pt-4 border-t border-gray-100">
      <h3 class="font-semibold text-gray-700 text-sm mb-3">📎 Archivos adjuntos</h3>
      <div class="space-y-2">
        <?php foreach ($attachments as $att): 
          $icon = '📁';
          if (strpos($att['file_type'], 'pdf') !== false) $icon = '📄';
          elseif (strpos($att['file_type'], 'image') !== false) $icon = '🖼️';
          elseif (strpos($att['file_type'], 'video') !== false) $icon = '🎬';
          $size = $att['file_size'] > 1024*1024 ? round($att['file_size']/1024/1024, 1).' MB' : round($att['file_size']/1024, 1).' KB';
        ?>
          <a href="<?= htmlspecialchars($att['file_url']) ?>" download
             class="flex items-center gap-3 px-4 py-3 bg-gray-50 hover:bg-selcap-50 rounded-xl transition-colors group">
            <span class="text-xl"><?= $icon ?></span>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-gray-800 truncate"><?= htmlspecialchars($att['file_name']) ?></p>
              <p class="text-xs text-gray-400"><?= $size ?></p>
            </div>
            <span class="text-selcap-600 opacity-0 group-hover:opacity-100 transition-opacity text-sm font-medium">Descargar ↓</span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <!-- Acciones -->
  <div class="mt-8 flex items-center justify-between gap-4 flex-wrap">
    <div class="flex items-center gap-3">
      <?php if ($prevLesson): ?>
        <a href="<?= BASE_URL ?>/lesson.php?id=<?= $prevLesson['id'] ?>"
           class="flex items-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold px-4 py-3 rounded-xl transition-colors text-sm">
          ← Anterior
        </a>
      <?php endif; ?>

      <form method="POST">
        <?php if (!$isDone): ?>
          <button type="submit" name="complete" value="1"
                  class="bg-green-600 hover:bg-green-700 text-white font-semibold px-5 py-3 rounded-xl transition-colors text-sm">
            ✓ Marcar como completada
          </button>
        <?php else: ?>
          <span class="text-sm text-green-600 font-medium px-2">✓ Lección completada</span>
        <?php endif; ?>
      </form>
    </div>

    <?php if ($nextLesson): ?>
      <a href="<?= BASE_URL ?>/lesson.php?id=<?= $nextLesson['id'] ?>"
         class="flex items-center gap-2 bg-selcap-600 hover:bg-selcap-700 text-white font-semibold px-5 py-3 rounded-xl transition-colors text-sm">
        Siguiente →
      </a>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
