<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$userId = $_SESSION['user_id'];
$pdo = db();

// Curso activo
$courseStmt = $pdo->prepare('SELECT * FROM courses WHERE id = ?');
$courseStmt->execute([ACTIVE_COURSE_ID]);
$course = $courseStmt->fetch();

// Enrolamiento
$enrStmt = $pdo->prepare('SELECT * FROM enrollments WHERE user_id = ? AND course_id = ?');
$enrStmt->execute([$userId, ACTIVE_COURSE_ID]);
$enrollment = $enrStmt->fetch();

// Secciones con lecciones
$sectionsStmt = $pdo->prepare('SELECT * FROM sections WHERE course_id = ? ORDER BY sort_order');
$sectionsStmt->execute([ACTIVE_COURSE_ID]);
$sections = $sectionsStmt->fetchAll();

// Progreso por sección
$progressStmt = $pdo->prepare('SELECT lp.lesson_id, lp.completed FROM lesson_progress lp
    JOIN lessons l ON lp.lesson_id = l.id
    JOIN sections s ON l.section_id = s.id
    WHERE lp.user_id = ? AND s.course_id = ?');
$progressStmt->execute([$userId, ACTIVE_COURSE_ID]);
$completedLessons = [];
foreach ($progressStmt->fetchAll() as $row) {
    if ($row['completed']) $completedLessons[$row['lesson_id']] = true;
}

// Lecciones por sección (1 query para evitar N+1)
$lessonsStmt = $pdo->prepare('SELECT l.*, s.id as section_id FROM lessons l
    JOIN sections s ON l.section_id = s.id
    WHERE s.course_id = ?
    ORDER BY s.sort_order, l.sort_order');
$lessonsStmt->execute([ACTIVE_COURSE_ID]);
$lessonsBySection = [];
foreach ($lessonsStmt->fetchAll() as $l) {
    $lessonsBySection[$l['section_id']][] = $l;
}

// Evaluaciones por sección
$evalsStmt = $pdo->prepare('SELECT e.*, s.id as section_id FROM evaluations e
    JOIN sections s ON e.section_id = s.id
    WHERE s.course_id = ?
    ORDER BY s.sort_order, e.sort_order');
$evalsStmt->execute([ACTIVE_COURSE_ID]);
$evalsBySection = [];
foreach ($evalsStmt->fetchAll() as $e) {
    // Último intento
    $attStmt = $pdo->prepare('SELECT * FROM evaluation_attempts WHERE user_id = ? AND evaluation_id = ? ORDER BY attempt_number DESC LIMIT 1');
    $attStmt->execute([$userId, $e['id']]);
    $e['last_attempt'] = $attStmt->fetch();
    $evalsBySection[$e['section_id']][] = $e;
}

$pageTitle = $course['title'] ?? 'Aula Virtual';
require __DIR__ . '/includes/header.php';
?>

<div class="mb-6">
  <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900"><?= htmlspecialchars($course['title'] ?? 'Aula Virtual') ?></h1>
  <p class="text-gray-500 text-sm mt-1"><?= htmlspecialchars($course['description'] ?? '') ?></p>
</div>

<?php if (!$enrollment): ?>
  <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-center text-sm">
    <form method="POST" action="<?= BASE_URL ?>/enroll.php">
      <button type="submit" class="bg-selcap-600 hover:bg-selcap-700 text-white font-semibold px-6 py-2.5 rounded-xl transition-colors">
        Inscribirme al curso
      </button>
    </form>
  </div>
<?php else: ?>

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
          <span class="text-xs font-semibold <?= $pct === 100 ? 'text-green-600' : 'text-selcap-600' ?>">
            <?= $doneL ?>/<?= $totalL ?>
          </span>
        </div>

        <?php if ($sec['description']): ?>
          <p class="text-gray-500 text-sm mb-3"><?= htmlspecialchars($sec['description']) ?></p>
        <?php endif; ?>

        <!-- Barra de progreso -->
        <div class="w-full bg-gray-100 rounded-full h-2 mb-4">
          <div class="h-2 rounded-full transition-all duration-500 <?= $pct === 100 ? 'bg-green-500' : 'bg-selcap-500' ?>" style="width:<?= $pct ?>%"></div>
        </div>

        <!-- Lecciones -->
        <div class="space-y-1.5">
          <?php foreach ($lessons as $l): 
            $isDone = isset($completedLessons[$l['id']]);
          ?>
            <a href="<?= BASE_URL ?>/lesson.php?id=<?= $l['id'] ?>"
               class="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-gray-50 transition-colors group">
              <span class="w-7 h-7 rounded-full flex items-center justify-center text-sm shrink-0
                <?= $isDone ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-400 group-hover:bg-selcap-100 group-hover:text-selcap-600' ?>">
                <?= $isDone ? '✓' : ($course['is_sequential'] && $l['sort_order'] > 1 && !isset($completedLessons[$lessons[$l['sort_order']-2]['id']]) ? '🔒' : '▶') ?>
              </span>
              <span class="text-sm font-medium <?= $isDone ? 'text-gray-500' : 'text-gray-800' ?>">
                <?= htmlspecialchars($l['title']) ?>
              </span>
            </a>
          <?php endforeach; ?>
        </div>

        <!-- Evaluaciones -->
        <?php if ($evals): ?>
          <div class="mt-4 pt-3 border-t border-gray-100">
            <?php foreach ($evals as $ev): 
              $last = $ev['last_attempt'];
              $attempts = $last ? $pdo->prepare('SELECT COUNT(*) FROM evaluation_attempts WHERE user_id = ? AND evaluation_id = ?')->execute([$userId, $ev['id']]) : 0;
              // Contar intentos
              $cntStmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM evaluation_attempts WHERE user_id = ? AND evaluation_id = ?');
              $cntStmt->execute([$userId, $ev['id']]);
              $attemptCount = (int) $cntStmt->fetch()['cnt'];
              $maxed = $attemptCount >= $ev['max_attempts'];
            ?>
              <div class="flex items-center justify-between px-3 py-2">
                <div class="flex items-center gap-2">
                  <span class="text-sm">📝</span>
                  <span class="text-sm font-medium text-gray-700"><?= htmlspecialchars($ev['title']) ?></span>
                  <?php if ($last): ?>
                    <span class="text-xs <?= $last['passed'] ? 'text-green-600 bg-green-50' : 'text-red-600 bg-red-50' ?> px-2 py-0.5 rounded-full font-semibold">
                      <?= round($last['score']) ?>% <?= $last['passed'] ? '✓' : '✗' ?>
                    </span>
                  <?php endif; ?>
                </div>
                <?php if (!$maxed || ($last && !$last['passed'])): ?>
                  <a href="<?= BASE_URL ?>/evaluation.php?id=<?= $ev['id'] ?>" class="text-xs font-semibold text-selcap-600 hover:underline">
                    <?= $attemptCount > 0 ? 'Reintentar' : 'Comenzar' ?> (<?= $ev['max_attempts'] - $attemptCount ?> disponible<?= $ev['max_attempts'] - $attemptCount !== 1 ? 's' : '' ?>)
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

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
