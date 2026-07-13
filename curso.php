<?php
// curso.php — Vista de curso (diseño Next.js)
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

// Secciones con lecciones y evaluaciones
$sectionsStmt = $pdo->prepare('SELECT * FROM sections WHERE course_id = ? ORDER BY sort_order');
$sectionsStmt->execute([$courseId]);
$sections = $sectionsStmt->fetchAll();

// Lecciones y progreso
$totalItems = 0;
$completedItems = 0;
$lessonsBySection = [];
$lStmt = $pdo->prepare('SELECT l.*, s.id as section_id FROM lessons l JOIN sections s ON l.section_id = s.id WHERE s.course_id = ? ORDER BY s.sort_order, l.sort_order');
$lStmt->execute([$courseId]);
foreach ($lStmt->fetchAll() as $l) {
    $totalItems++;
    $lessonsBySection[$l['section_id']][] = $l;
}

$completedIds = [];
$progStmt = $pdo->prepare('SELECT lp.lesson_id FROM lesson_progress lp
    JOIN lessons l ON lp.lesson_id = l.id JOIN sections s ON l.section_id = s.id
    WHERE lp.user_id = ? AND s.course_id = ? AND lp.completed = 1');
$progStmt->execute([$userId, $courseId]);
foreach ($progStmt->fetchAll() as $r) {
    $completedIds[$r['lesson_id']] = true;
    $completedItems++;
}

// Evaluaciones (vía sections para compatibilidad)
$evalsBySection = [];
$completedEvalIds = [];
$eStmt = $pdo->prepare('SELECT e.* FROM evaluations e WHERE e.course_id = ? AND e.is_active = 1 ORDER BY e.sort_order');
$eStmt->execute([$courseId]);
foreach ($eStmt->fetchAll() as $ev) {
    $totalItems++;
    $key = $ev['section_id'] ?? 0;
    $evalsBySection[$key][] = $ev;
    // Check if passed
    $attStmt = $pdo->prepare('SELECT * FROM evaluation_attempts WHERE user_id = ? AND evaluation_id = ? AND passed = 1 LIMIT 1');
    $attStmt->execute([$userId, $ev['id']]);
    if ($attStmt->fetch()) {
        $completedItems++;
        $completedEvalIds[$ev['id']] = true;
    }
}

// Materiales del curso
$materialsStmt = $pdo->prepare('SELECT * FROM course_materials WHERE course_id = ? ORDER BY created_at DESC');
$materialsStmt->execute([$courseId]);
$materials = $materialsStmt->fetchAll();

$progress = $totalItems > 0 ? round($completedItems / $totalItems * 100) : 0;

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
<nav class="flex items-center gap-2 text-sm text-neutral-400 mb-6">
  <a href="<?= BASE_URL ?>/mis-cursos.php" class="hover:text-primary-600 transition-colors">Mis Cursos</a>
  <span>›</span>
  <span class="text-neutral-800 font-medium truncate"><?= htmlspecialchars($course['title'] ?? '') ?></span>
</nav>

<!-- Course Header Card -->
<div class="bg-white rounded-2xl overflow-hidden shadow-sm border border-neutral-200 mb-6">
  <div class="h-48 md:h-56 bg-gradient-to-br from-primary-900 to-primary-700 relative flex items-center justify-center">
    <svg class="w-24 h-24 text-primary-300 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 14l9-5-9-5-9 5 9 5z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>
    <div class="absolute inset-0 bg-gradient-to-t from-neutral-900/80 to-transparent"></div>
    <div class="absolute bottom-0 left-0 right-0 p-6 md:p-8 text-white">
      <h1 class="text-2xl md:text-3xl font-bold mb-1"><?= htmlspecialchars($course['title']) ?></h1>
      <p class="text-primary-200 text-sm flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
        Profesor: Equipo Selcap
      </p>
    </div>
  </div>

  <!-- Progress + Actions -->
  <div class="p-6 md:p-8 flex flex-col md:flex-row gap-6 items-start md:items-center justify-between">
    <div class="flex-1 w-full max-w-xl">
      <div class="flex justify-between text-sm font-medium mb-2">
        <span class="text-neutral-700">Tu progreso</span>
        <span class="text-primary-600"><?= $progress ?>%</span>
      </div>
      <div class="w-full bg-neutral-100 rounded-full h-3 overflow-hidden">
        <div class="h-3 rounded-full transition-all duration-500 <?= $progress === 100 ? 'bg-green-500' : 'bg-primary-500' ?>" style="width:<?= $progress ?>%"></div>
      </div>
      <p class="text-sm text-neutral-500 mt-2">
        Has completado <?= $completedItems ?> de <?= $totalItems ?> actividades.
      </p>
    </div>
    <div class="shrink-0 w-full md:w-auto">
      <?php if ($progress === 100): ?>
        <a href="<?= BASE_URL ?>/certificados.php" class="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white font-semibold px-6 py-3 rounded-lg transition-colors text-sm w-full md:w-auto justify-center">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
          Ver Certificado
        </a>
      <?php elseif ($nextLesson): ?>
        <a href="<?= BASE_URL ?>/lesson.php?id=<?= $nextLesson['id'] ?>" class="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white font-semibold px-6 py-3 rounded-lg transition-colors text-sm w-full md:w-auto justify-center shadow-sm">
          <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
          Continuar Aprendiendo
        </a>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- 3-col layout: Content (2/3) + Sidebar (1/3) -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
  <!-- Left: Content with Tabs -->
  <div class="md:col-span-2 flex flex-col gap-6">

    <!-- Tab Buttons -->
    <div class="flex gap-1 bg-neutral-100 p-1 rounded-xl">
      <button onclick="switchTab('secciones', this)" class="tab-btn active flex-1 py-2.5 px-3 text-sm font-semibold rounded-lg transition-all text-neutral-900 bg-white shadow-sm">
        📑 Secciones
      </button>
      <button onclick="switchTab('contenido', this)" class="tab-btn flex-1 py-2.5 px-3 text-sm font-semibold rounded-lg transition-all text-neutral-500 hover:text-neutral-700">
        📖 Contenido
      </button>
      <button onclick="switchTab('materiales', this)" class="tab-btn flex-1 py-2.5 px-3 text-sm font-semibold rounded-lg transition-all text-neutral-500 hover:text-neutral-700">
        📁 Materiales<?php if (!empty($materials)): ?> <span class="text-xs bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full"><?= count($materials) ?></span><?php endif; ?>
      </button>
    </div>

    <!-- Tab: Secciones -->
    <div id="tab-secciones" class="tab-content">
      <?php if (empty($sections)): ?>
        <div class="bg-white rounded-2xl p-12 text-center border border-neutral-200 border-dashed">
          <p class="text-neutral-500">El profesor aún está preparando el contenido de este curso.</p>
        </div>
      <?php endif; ?>

      <?php $orphanEvals = $evalsBySection[0] ?? []; ?>
      <?php if (!empty($orphanEvals)): ?>
        <div class="bg-white rounded-2xl shadow-sm border border-neutral-200 overflow-hidden mb-4 border-l-4 border-l-amber-400">
          <div class="bg-neutral-50 p-4 border-b border-neutral-200 flex items-center gap-4">
            <div class="w-8 h-8 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center font-bold text-sm shrink-0">📋</div>
            <div>
              <h3 class="font-bold text-neutral-900">Evaluaciones del curso</h3>
              <p class="text-sm text-neutral-500">Sin sección asignada</p>
            </div>
          </div>
          <div class="flex flex-col divide-y divide-neutral-100">
            <?php foreach ($orphanEvals as $ev):
              $isDone = isset($completedEvalIds[$ev['id']]);
            ?>
              <a href="<?= BASE_URL ?>/evaluation.php?id=<?= $ev['id'] ?>"
                 class="flex items-center gap-4 p-4 hover:bg-neutral-50 transition-colors group">
                <div class="shrink-0">
                  <?php if ($isDone): ?>
                    <svg class="w-6 h-6 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  <?php else: ?>
                    <div class="w-6 h-6 rounded-full border-2 border-neutral-300 group-hover:border-primary-400 transition-colors"></div>
                  <?php endif; ?>
                </div>
                <div class="flex-1">
                  <p class="font-medium text-neutral-900 group-hover:text-primary-600 transition-colors"><?= htmlspecialchars($ev['title']) ?></p>
                  <div class="flex items-center gap-2 mt-1 text-xs text-neutral-500">
                    <svg class="w-3.5 h-3.5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg> Evaluación
                  </div>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php foreach ($sections as $secIdx => $sec):
        $lessons = $lessonsBySection[$sec['id']] ?? [];
        $evals = $evalsBySection[$sec['id']] ?? [];
      ?>
        <div class="bg-white rounded-2xl shadow-sm border border-neutral-200 overflow-hidden mb-4">
          <!-- Section header -->
          <div class="bg-neutral-50 p-4 border-b border-neutral-200 flex items-center gap-4">
            <div class="w-8 h-8 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center font-bold text-sm shrink-0">
              <?= $secIdx + 1 ?>
            </div>
            <div>
              <h3 class="font-bold text-neutral-900"><?= htmlspecialchars($sec['title']) ?></h3>
              <?php if ($sec['description']): ?>
                <p class="text-sm text-neutral-500"><?= $sec['description'] ?></p>
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

          <!-- Lessons + Evaluations -->
          <div class="flex flex-col divide-y divide-neutral-100">
            <?php if (empty($lessons) && empty($evals)): ?>
              <div class="p-6 text-center text-sm text-neutral-400">
                <svg class="w-8 h-8 mx-auto mb-2 text-neutral-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                Sin contenido aún — el profesor está preparando esta sección.
              </div>
            <?php endif; ?>
            <?php foreach ($lessons as $l):
              $isDone = isset($completedIds[$l['id']]);
            ?>
              <a href="<?= BASE_URL ?>/lesson.php?id=<?= $l['id'] ?>"
                 class="flex items-center gap-4 p-4 hover:bg-neutral-50 transition-colors group">
                <div class="shrink-0">
                  <?php if ($isDone): ?>
                    <svg class="w-6 h-6 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  <?php else: ?>
                    <div class="w-6 h-6 rounded-full border-2 border-neutral-300 group-hover:border-primary-400 transition-colors"></div>
                  <?php endif; ?>
                </div>
                <div class="flex-1">
                  <p class="font-medium text-neutral-900 group-hover:text-primary-600 transition-colors"><?= htmlspecialchars($l['title']) ?></p>
                  <div class="flex items-center gap-2 mt-1 text-xs text-neutral-500">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg> Lección
                  </div>
                </div>
              </a>
            <?php endforeach; ?>

            <?php foreach ($evals as $ev):
              $isDone = isset($completedEvalIds[$ev['id']]);
            ?>
              <a href="<?= BASE_URL ?>/evaluation.php?id=<?= $ev['id'] ?>"
                 class="flex items-center gap-4 p-4 hover:bg-neutral-50 transition-colors group">
                <div class="shrink-0">
                  <?php if ($isDone): ?>
                    <svg class="w-6 h-6 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  <?php else: ?>
                    <div class="w-6 h-6 rounded-full border-2 border-neutral-300 group-hover:border-primary-400 transition-colors"></div>
                  <?php endif; ?>
                </div>
                <div class="flex-1">
                  <p class="font-medium text-neutral-900 group-hover:text-primary-600 transition-colors"><?= htmlspecialchars($ev['title']) ?></p>
                  <div class="flex items-center gap-2 mt-1 text-xs text-neutral-500">
                    <svg class="w-3.5 h-3.5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg> Evaluación
                  </div>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Tab: Contenido -->
    <div id="tab-contenido" class="tab-content" style="display:none;">
      <?php if ($course['description']): ?>
      <div class="bg-white rounded-2xl p-6 md:p-8 shadow-sm border border-neutral-200">
        <h2 class="text-lg font-bold text-neutral-900 mb-4">Acerca de este curso</h2>
        <div class="text-neutral-600 text-sm leading-relaxed lesson-content">
          <?= $course['description'] ?>
        </div>
      </div>
      <?php else: ?>
      <div class="bg-white rounded-2xl p-12 text-center border border-neutral-200 border-dashed">
        <p class="text-neutral-500">El profesor no ha agregado una descripción todavía.</p>
      </div>
      <?php endif; ?>
    </div>

    <!-- Tab: Materiales -->
    <div id="tab-materiales" class="tab-content" style="display:none;">
      <?php if (!empty($materials)): ?>
      <div class="bg-white rounded-2xl shadow-sm border border-neutral-200 p-6">
        <div class="flex flex-col gap-2">
          <?php foreach ($materials as $m): ?>
            <a href="<?= htmlspecialchars($m['file_url']) ?>" target="_blank"
               class="flex items-center gap-3 p-3 rounded-xl hover:bg-neutral-50 transition-colors group border border-neutral-100">
              <div class="w-10 h-10 rounded-lg bg-amber-100 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
              </div>
              <div class="flex-1 min-w-0">
                <p class="font-medium text-neutral-900 truncate group-hover:text-primary-600 transition-colors"><?= htmlspecialchars($m['file_name']) ?></p>
                <p class="text-xs text-neutral-500"><?= round($m['file_size']/1024, 1) ?> KB · <?= htmlspecialchars($m['file_type'] ?? 'Desconocido') ?></p>
              </div>
              <svg class="w-5 h-5 text-neutral-300 group-hover:text-primary-500 transition-colors shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php else: ?>
      <div class="bg-white rounded-2xl p-12 text-center border border-neutral-200 border-dashed">
        <p class="text-neutral-500">No hay materiales todavía. El profesor subirá archivos pronto.</p>
      </div>
      <?php endif; ?>
    </div>

  </div>

  <!-- Right: Sidebar Details -->
  <div class="flex flex-col gap-6">
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-neutral-200">
      <h3 class="font-bold text-neutral-900 mb-4">Detalles</h3>
      <div class="flex flex-col gap-4">
        <div class="flex items-center gap-3 text-sm">
          <div class="w-8 h-8 rounded-full bg-neutral-100 flex items-center justify-center shrink-0">
            <svg class="w-4 h-4 text-neutral-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          </div>
          <div>
            <p class="font-medium text-neutral-900">Modo de avance</p>
            <p class="text-neutral-500"><?= $course['is_sequential'] ? 'Secuencial (Paso a paso)' : 'Libre' ?></p>
          </div>
        </div>
        <div class="flex items-center gap-3 text-sm">
          <div class="w-8 h-8 rounded-full bg-neutral-100 flex items-center justify-center shrink-0">
            <svg class="w-4 h-4 text-neutral-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
          </div>
          <div>
            <p class="font-medium text-neutral-900">Nota mínima aprobatoria</p>
            <p class="text-neutral-500"><?= $course['passing_grade'] ?? 70 ?>%</p>
          </div>
        </div>
        <div class="flex items-center gap-3 text-sm">
          <div class="w-8 h-8 rounded-full bg-neutral-100 flex items-center justify-center shrink-0">
            <svg class="w-4 h-4 text-neutral-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
          </div>
          <div>
            <p class="font-medium text-neutral-900">Total actividades</p>
            <p class="text-neutral-500"><?= $totalItems ?></p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function switchTab(name, btn) {
  document.querySelectorAll('.tab-content').forEach(t => t.style.display = 'none');
  document.getElementById('tab-' + name).style.display = 'block';
  document.querySelectorAll('.tab-btn').forEach(b => {
    b.classList.remove('active', 'bg-white', 'shadow-sm', 'text-neutral-900');
    b.classList.add('text-neutral-500');
  });
  btn.classList.add('active', 'bg-white', 'shadow-sm', 'text-neutral-900');
  btn.classList.remove('text-neutral-500');
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
