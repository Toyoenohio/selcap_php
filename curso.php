<?php
// curso.php — Vista de curso rediseñada
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$courseId = (int) ($_GET['id'] ?? 0);
$userId = $_SESSION['user_id'];
$pdo = db();

// Verificar enrolamiento
$enrStmt = $pdo->prepare('SELECT * FROM enrollments WHERE user_id = ? AND course_id = ?');
$enrStmt->execute([$userId, $courseId]);
$enrollment = $enrStmt->fetch();
if (!$enrollment) {
    header('Location: ' . BASE_URL . '/catalogo.php');
    exit;
}

$courseStmt = $pdo->prepare('SELECT * FROM courses WHERE id = ?');
$courseStmt->execute([$courseId]);
$course = $courseStmt->fetch();

// Secciones
$sectionsStmt = $pdo->prepare('SELECT * FROM sections WHERE course_id = ? ORDER BY sort_order');
$sectionsStmt->execute([$courseId]);
$sections = $sectionsStmt->fetchAll();

// Lecciones y progreso
$totalLessons = 0;
$completedLessons = 0;
$lessonsBySection = [];
$lStmt = $pdo->prepare('SELECT l.*, s.id as section_id FROM lessons l JOIN sections s ON l.section_id = s.id WHERE s.course_id = ? ORDER BY s.sort_order, l.sort_order');
$lStmt->execute([$courseId]);
foreach ($lStmt->fetchAll() as $l) {
    $totalLessons++;
    $lessonsBySection[$l['section_id']][] = $l;
}

$completedIds = [];
$progStmt = $pdo->prepare('SELECT lp.lesson_id FROM lesson_progress lp
    JOIN lessons l ON lp.lesson_id = l.id JOIN sections s ON l.section_id = s.id
    WHERE lp.user_id = ? AND s.course_id = ? AND lp.completed = 1');
$progStmt->execute([$userId, $courseId]);
foreach ($progStmt->fetchAll() as $r) {
    $completedIds[$r['lesson_id']] = true;
    $completedLessons++;
}
$progressPct = $totalLessons > 0 ? round($completedLessons / $totalLessons * 100) : 0;

// Evaluaciones
$evalsBySection = [];
$eStmt = $pdo->prepare('SELECT e.* FROM evaluations e WHERE e.course_id = ? ORDER BY e.sort_order');
$eStmt->execute([$courseId]);
foreach ($eStmt->fetchAll() as $ev) {
    $attStmt = $pdo->prepare('SELECT * FROM evaluation_attempts WHERE user_id = ? AND evaluation_id = ? ORDER BY attempt_number DESC LIMIT 1');
    $attStmt->execute([$userId, $ev['id']]);
    $ev['last_attempt'] = $attStmt->fetch();
    $cntStmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM evaluation_attempts WHERE user_id = ? AND evaluation_id = ?');
    $cntStmt->execute([$userId, $ev['id']]);
    $ev['attempt_count'] = (int) $cntStmt->fetch()['cnt'];
    // Asociar a la sección o dejarlo como standalone
    $secId = $ev['section_id'] ?? 0;
    if ($secId) $evalsBySection[$secId][] = $ev;
    else $evalsBySection['_global'][] = $ev;
}

// Siguiente lección por hacer
$nextLesson = null;
foreach ($sections as $sec) {
    $lessons = $lessonsBySection[$sec['id']] ?? [];
    foreach ($lessons as $l) {
        if (!isset($completedIds[$l['id']])) {
            $nextLesson = $l;
            break 2;
        }
    }
}

$pageTitle = $course['title'] ?? 'Curso';
$currentPage = 'mis-cursos';
require __DIR__ . '/includes/header.php';
?>

<!-- Breadcrumb -->
<nav class="flex items-center gap-2 text-sm text-gray-400 mb-6">
  <a href="<?= BASE_URL ?>/mis-cursos.php" class="hover:text-selcap-600 transition-colors">Mis Cursos</a>
  <span>›</span>
  <span class="text-gray-800 font-medium truncate"><?= htmlspecialchars($course['title'] ?? '') ?></span>
</nav>

<!-- Course Header Card -->
<div class="relative bg-gradient-to-br from-selcap-600 to-selcap-700 rounded-2xl p-6 md:p-8 mb-6 overflow-hidden shadow-lg">
  <div class="absolute right-0 top-0 opacity-10 select-none pointer-events-none">
    <svg width="200" height="200" viewBox="0 0 24 24" fill="white"><path d="M12 14l9-5-9-5-9 5 9 5z"/><path d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>
  </div>
  <div class="relative z-10">
    <h1 class="text-2xl md:text-3xl font-extrabold text-white mb-2"><?= htmlspecialchars($course['title']) ?></h1>
    <p class="text-white/70 text-sm">Profesor: Equipo Selcap</p>
  </div>
</div>

<!-- Progress Section -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 md:p-6 mb-6">
  <div class="flex items-center justify-between">
    <div>
      <p class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Tu progreso</p>
      <p class="text-3xl font-extrabold text-gray-900 mt-1"><?= $progressPct ?>%</p>
      <p class="text-sm text-gray-400 mt-1">
        Has completado <?= $completedLessons ?> de <?= $totalLessons ?> <?= $totalLessons === 1 ? 'actividad' : 'actividades' ?>.
      </p>
    </div>
    <?php if ($nextLesson): ?>
      <a href="<?= BASE_URL ?>/lesson.php?id=<?= $nextLesson['id'] ?>"
         class="inline-flex items-center gap-2 bg-selcap-600 hover:bg-selcap-700 text-white font-semibold px-5 py-3 rounded-xl transition-colors text-sm shadow-md shadow-selcap-200">
        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
        Continuar Aprendiendo
      </a>
    <?php elseif ($progressPct === 100): ?>
      <span class="inline-flex items-center gap-2 bg-green-100 text-green-700 font-semibold px-5 py-3 rounded-xl text-sm">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        ¡Curso completado!
      </span>
    <?php endif; ?>
  </div>
  <div class="w-full bg-gray-100 rounded-full h-3 mt-4 overflow-hidden">
    <div class="h-3 rounded-full transition-all duration-500 <?= $progressPct === 100 ? 'bg-green-500' : 'bg-selcap-500' ?>" style="width:<?= $progressPct ?>%"></div>
  </div>
</div>

<!-- Info Cards -->
<div class="grid md:grid-cols-2 gap-6 mb-6">
  <!-- About -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 md:p-6">
    <h3 class="font-bold text-gray-900 mb-3">Acerca de este curso</h3>
    <p class="text-gray-600 text-sm leading-relaxed"><?= htmlspecialchars($course['description'] ?? 'Sin descripción') ?></p>
  </div>

  <!-- Details -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 md:p-6">
    <h3 class="font-bold text-gray-900 mb-4">Detalles</h3>
    <div class="space-y-4">
      <div class="flex items-center gap-3 text-sm">
        <span class="w-9 h-9 rounded-lg bg-blue-50 flex items-center justify-center shrink-0">
          <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </span>
        <div>
          <p class="font-medium text-gray-800">Modo de avance</p>
          <p class="text-xs text-gray-400"><?= $course['is_sequential'] ? 'Secuencial (Paso a paso)' : 'Libre' ?></p>
        </div>
      </div>
      <div class="flex items-center gap-3 text-sm">
        <span class="w-9 h-9 rounded-lg bg-amber-50 flex items-center justify-center shrink-0">
          <svg class="w-5 h-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
        </span>
        <div>
          <p class="font-medium text-gray-800">Nota mínima aprobatoria</p>
          <p class="text-xs text-gray-400"><?= $course['passing_grade'] ?? 70 ?>%</p>
        </div>
      </div>
      <div class="flex items-center gap-3 text-sm">
        <span class="w-9 h-9 rounded-lg bg-purple-50 flex items-center justify-center shrink-0">
          <svg class="w-5 h-5 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
        </span>
        <div>
          <p class="font-medium text-gray-800">Total lecciones</p>
          <p class="text-xs text-gray-400"><?= $totalLessons ?></p>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Course Content -->
<div class="mb-8">
  <h2 class="text-xl font-extrabold text-gray-900 mb-4">Contenido del Curso</h2>

  <?php foreach ($sections as $secIdx => $sec):
    $lessons = $lessonsBySection[$sec['id']] ?? [];
    $evals = $evalsBySection[$sec['id']] ?? [];
    if (empty($lessons) && empty($evals)) continue;
    $secDone = 0;
    foreach ($lessons as $l) if (isset($completedIds[$l['id']])) $secDone++;
    $secTotal = count($lessons);
  ?>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 mb-4 overflow-hidden">
      <!-- Section header -->
      <div class="p-5 border-b border-gray-100">
        <div class="flex items-center gap-3">
          <span class="w-8 h-8 rounded-lg bg-selcap-100 flex items-center justify-center text-selcap-700 font-bold text-sm shrink-0">
            <?= $secIdx + 1 ?>
          </span>
          <div>
            <h3 class="font-bold text-gray-900"><?= htmlspecialchars($sec['title']) ?></h3>
            <?php if ($sec['description']): ?>
              <p class="text-sm text-gray-500"><?= htmlspecialchars($sec['description']) ?></p>
            <?php endif; ?>
          </div>
          <?php if ($secTotal > 0): ?>
            <span class="ml-auto text-xs font-semibold <?= $secDone === $secTotal ? 'text-green-600' : 'text-gray-400' ?>">
              <?= $secDone ?>/<?= $secTotal ?>
            </span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Live URL -->
      <?php if (!empty($sec['live_url'])):
        $isZoomMeet = strpos($sec['live_url'], 'zoom.us') !== false || strpos($sec['live_url'], 'meet.google.com') !== false;
      ?>
        <div class="mx-4 my-3 rounded-xl overflow-hidden border-2 border-red-200 bg-red-50/30">
          <div class="px-3 py-1.5 bg-red-500 text-white flex items-center gap-2 text-xs font-semibold">
            <span class="w-2 h-2 bg-white rounded-full animate-pulse"></span> 🔴 CLASE EN VIVO
          </div>
          <?php if ($isZoomMeet): ?>
            <div class="p-4 text-center">
              <a href="<?= htmlspecialchars($sec['live_url']) ?>" target="_blank" rel="noopener"
                 class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white font-bold px-5 py-2.5 rounded-xl transition-colors text-sm">
                🔴 Abrir en pestaña nueva →
              </a>
            </div>
          <?php else: ?>
            <div class="aspect-video">
              <iframe src="<?= htmlspecialchars($sec['live_url']) ?>" class="w-full h-full" frameborder="0" allow="camera; microphone; fullscreen; display-capture" allowfullscreen></iframe>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <!-- Lessons -->
      <div class="divide-y divide-gray-50">
        <?php foreach ($lessons as $l):
          $isDone = isset($completedIds[$l['id']]);
        ?>
          <a href="<?= BASE_URL ?>/lesson.php?id=<?= $l['id'] ?>"
             class="flex items-center gap-3 px-5 py-3 hover:bg-gray-50 transition-colors group">
            <?php if ($isDone): ?>
              <span class="w-7 h-7 rounded-full bg-green-100 flex items-center justify-center shrink-0">
                <svg class="w-4 h-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
              </span>
              <span class="text-sm text-gray-500 line-through"><?= htmlspecialchars($l['title']) ?></span>
              <span class="ml-auto text-xs text-gray-300">Lección</span>
            <?php else: ?>
              <span class="w-7 h-7 rounded-full bg-gray-100 flex items-center justify-center shrink-0 group-hover:bg-selcap-100 group-hover:text-selcap-600 transition-colors">
                <svg class="w-4 h-4 text-gray-400 group-hover:text-selcap-600" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
              </span>
              <span class="text-sm font-medium text-gray-800"><?= htmlspecialchars($l['title']) ?></span>
              <span class="ml-auto text-xs text-gray-300">Lección</span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>

      <!-- Evaluations for this section -->
      <?php if ($evals): ?>
        <div class="border-t border-gray-100 px-5 py-3 bg-gray-50/50">
          <?php foreach ($evals as $ev):
            $last = $ev['last_attempt'];
            $maxed = $ev['attempt_count'] >= $ev['max_attempts'];
          ?>
            <div class="flex items-center justify-between py-1">
              <div class="flex items-center gap-2">
                <span class="w-6 h-6 rounded bg-amber-100 flex items-center justify-center text-xs shrink-0">📝</span>
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
                <a href="<?= BASE_URL ?>/evaluation.php?id=<?= $ev['id'] ?>"
                   class="text-xs font-semibold text-selcap-600 hover:underline">
                  <?= $ev['attempt_count'] > 0 ? 'Reintentar' : 'Comenzar' ?> (<?= $ev['max_attempts'] - $ev['attempt_count'] ?>)
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

  <!-- Global evaluations (sin sección) -->
  <?php if (!empty($evalsBySection['_global'])): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 mb-4">
      <h3 class="font-bold text-gray-900 mb-3">Evaluaciones del Curso</h3>
      <?php foreach ($evalsBySection['_global'] as $ev):
        $last = $ev['last_attempt'];
        $maxed = $ev['attempt_count'] >= $ev['max_attempts'];
      ?>
        <div class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
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
            <a href="<?= BASE_URL ?>/evaluation.php?id=<?= $ev['id'] ?>"
               class="text-xs font-semibold text-selcap-600 hover:underline">
              <?= $ev['attempt_count'] > 0 ? 'Reintentar' : 'Comenzar' ?>
            </a>
          <?php else: ?>
            <span class="text-xs text-gray-400">Sin intentos</span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
