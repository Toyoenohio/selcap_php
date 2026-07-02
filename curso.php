<?php
// curso.php — Ver curso individual (secciones, lecciones, evaluaciones)
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$courseId = (int) ($_GET['id'] ?? 0);
$userId = $_SESSION['user_id'];
$pdo = db();

// Verificar enrolamiento
$enrStmt = $pdo->prepare('SELECT * FROM enrollments WHERE user_id = ? AND course_id = ?');
$enrStmt->execute([$userId, $courseId]);
if (!$enrStmt->fetch()) {
    header('Location: ' . BASE_URL . '/catalogo.php');
    exit;
}

$courseStmt = $pdo->prepare('SELECT * FROM courses WHERE id = ?');
$courseStmt->execute([$courseId]);
$course = $courseStmt->fetch();

$sectionsStmt = $pdo->prepare('SELECT * FROM sections WHERE course_id = ? ORDER BY sort_order');
$sectionsStmt->execute([$courseId]);
$sections = $sectionsStmt->fetchAll();

// Progreso
$completedLessons = [];
$progStmt = $pdo->prepare('SELECT lp.lesson_id, lp.completed FROM lesson_progress lp
    JOIN lessons l ON lp.lesson_id = l.id JOIN sections s ON l.section_id = s.id
    WHERE lp.user_id = ? AND s.course_id = ?');
$progStmt->execute([$userId, $courseId]);
foreach ($progStmt->fetchAll() as $r) { if ($r['completed']) $completedLessons[$r['lesson_id']] = true; }

// Lecciones y evaluaciones por sección
$lessonsBySection = []; $evalsBySection = [];
$lStmt = $pdo->prepare('SELECT l.*, s.id as section_id FROM lessons l JOIN sections s ON l.section_id = s.id WHERE s.course_id = ? ORDER BY s.sort_order, l.sort_order');
$lStmt->execute([$courseId]);
foreach ($lStmt->fetchAll() as $l) { $lessonsBySection[$l['section_id']][] = $l; }

$eStmt = $pdo->prepare('SELECT e.*, s.id as section_id FROM evaluations e JOIN sections s ON e.section_id = s.id WHERE s.course_id = ? ORDER BY s.sort_order, e.sort_order');
$eStmt->execute([$courseId]);
foreach ($eStmt->fetchAll() as $e) {
    $attStmt = $pdo->prepare('SELECT * FROM evaluation_attempts WHERE user_id = ? AND evaluation_id = ? ORDER BY attempt_number DESC LIMIT 1');
    $attStmt->execute([$userId, $e['id']]);
    $e['last_attempt'] = $attStmt->fetch();
    $evalsBySection[$e['section_id']][] = $e;
}

$currentPage = 'mis-cursos';
$pageTitle = $course['title'] ?? 'Curso';
require __DIR__ . '/includes/header.php';
?>

<nav class="flex items-center gap-2 text-sm text-gray-400 mb-4">
  <a href="<?= BASE_URL ?>/mis-cursos.php" class="hover:text-selcap-600 transition-colors">Mis Cursos</a>
  <span>›</span>
  <span class="text-gray-800 font-medium"><?= htmlspecialchars($course['title'] ?? '') ?></span>
</nav>

<h1 class="text-2xl font-extrabold text-gray-900 mb-1"><?= htmlspecialchars($course['title'] ?? '') ?></h1>
<p class="text-gray-500 text-sm mb-6"><?= htmlspecialchars($course['description'] ?? '') ?></p>

<div class="space-y-4">
  <?php foreach ($sections as $sec):
    $lessons = $lessonsBySection[$sec['id']] ?? [];
    $evals = $evalsBySection[$sec['id']] ?? [];
    $totalL = count($lessons);
    $doneL = 0;
    foreach ($lessons as $l) { if (isset($completedLessons[$l['id']])) $doneL++; }
    $pct = $totalL > 0 ? round($doneL / $totalL * 100) : 0;
  ?>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
      <div class="flex items-center justify-between mb-3">
        <h2 class="font-bold text-gray-900 text-lg"><?= htmlspecialchars($sec['title']) ?></h2>
        <span class="text-xs font-semibold <?= $pct === 100 ? 'text-green-600' : 'text-selcap-600' ?>"><?= $doneL ?>/<?= $totalL ?></span>
      </div>
      <?php if ($sec['description']): ?>
        <p class="text-gray-500 text-sm mb-3"><?= htmlspecialchars($sec['description']) ?></p>
      <?php endif; ?>
      <?php if (!empty($sec['live_url'])): ?>
        <div class="mb-4 rounded-xl overflow-hidden border-2 border-red-200 bg-red-50/30">
          <div class="px-3 py-1.5 bg-red-500 text-white flex items-center gap-2 text-xs font-semibold">
            <span class="w-2 h-2 bg-white rounded-full animate-pulse"></span> 🔴 CLASE EN VIVO
          </div>
          <div class="aspect-video">
            <iframe src="<?= htmlspecialchars($sec['live_url']) ?>" class="w-full h-full" frameborder="0" allow="camera; microphone; fullscreen; display-capture" allowfullscreen></iframe>
          </div>
        </div>
      <?php endif; ?>
      <div class="w-full bg-gray-100 rounded-full h-2 mb-4">
        <div class="h-2 rounded-full transition-all <?= $pct === 100 ? 'bg-green-500' : 'bg-selcap-500' ?>" style="width:<?= $pct ?>%"></div>
      </div>
      <div class="space-y-1.5">
        <?php foreach ($lessons as $l): $isDone = isset($completedLessons[$l['id']]); ?>
          <a href="<?= BASE_URL ?>/lesson.php?id=<?= $l['id'] ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-gray-50 transition-colors group">
            <span class="w-7 h-7 rounded-full flex items-center justify-center text-sm shrink-0
              <?= $isDone ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-400 group-hover:bg-selcap-100 group-hover:text-selcap-600' ?>">
              <?= $isDone ? '✓' : '▶' ?>
            </span>
            <span class="text-sm font-medium <?= $isDone ? 'text-gray-500' : 'text-gray-800' ?>"><?= htmlspecialchars($l['title']) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
      <?php if ($evals): ?>
        <div class="mt-4 pt-3 border-t border-gray-100">
          <?php foreach ($evals as $ev):
            $last = $ev['last_attempt'];
            $cntStmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM evaluation_attempts WHERE user_id = ? AND evaluation_id = ?');
            $cntStmt->execute([$userId, $ev['id']]);
            $attemptCount = (int) $cntStmt->fetch()['cnt'];
            $maxed = $attemptCount >= $ev['max_attempts'];
          ?>
            <div class="flex items-center justify-between px-3 py-2">
              <div class="flex items-center gap-2">
                <span>📝</span>
                <span class="text-sm font-medium text-gray-700"><?= htmlspecialchars($ev['title']) ?></span>
                <?php if ($last): ?>
                  <span class="text-xs <?= $last['passed'] ? 'text-green-600 bg-green-50' : 'text-red-600 bg-red-50' ?> px-2 py-0.5 rounded-full font-semibold">
                    <?= round($last['score']) ?>% <?= $last['passed'] ? '✓' : '✗' ?>
                  </span>
                <?php endif; ?>
              </div>
              <?php if ($last && $last['passed']): ?>
                <span class="text-xs text-green-600 font-medium">Aprobada</span>
              <?php elseif (!$maxed): ?>
                <a href="<?= BASE_URL ?>/evaluation.php?id=<?= $ev['id'] ?>" class="text-xs font-semibold text-selcap-600 hover:underline">
                  <?= $attemptCount > 0 ? 'Reintentar' : 'Comenzar' ?> (<?= $ev['max_attempts'] - $attemptCount ?>)
                </a>
              <?php else: ?>
                <span class="text-xs text-gray-400">Sin intentos</span>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
