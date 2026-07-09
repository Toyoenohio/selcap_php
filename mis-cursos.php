<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
$userId = $_SESSION['user_id'];
$pdo = db();

$coursesStmt = $pdo->prepare('SELECT c.*, e.enrolled_at,
    (SELECT COUNT(*) FROM lessons l JOIN sections s ON l.section_id = s.id WHERE s.course_id = c.id) as total_lessons,
    (SELECT COUNT(*) FROM lesson_progress lp JOIN lessons l ON lp.lesson_id = l.id JOIN sections s ON l.section_id = s.id WHERE lp.user_id = ? AND s.course_id = c.id AND lp.completed = 1) as done_lessons
    FROM courses c 
    JOIN enrollments e ON e.course_id = c.id 
    WHERE e.user_id = ? AND e.status = "active"
    ORDER BY e.enrolled_at DESC');
$coursesStmt->execute([$userId, $userId]);
$courses = $coursesStmt->fetchAll();

$currentPage = 'mis-cursos';
$pageTitle = 'Mis Cursos';
require __DIR__ . '/includes/header.php';
?>

<div class="flex flex-col gap-2 mb-6">
  <h1 class="text-2xl font-bold text-neutral-900">Mis Cursos</h1>
  <p class="text-neutral-500">Aquí puedes ver y continuar todos los cursos en los que estás inscrito.</p>
</div>

<?php if (empty($courses)): ?>
  <div class="bg-white rounded-2xl border-2 border-dashed border-neutral-200 p-12 text-center flex flex-col items-center">
    <div class="bg-neutral-100 p-4 rounded-full mb-4">
      <svg class="w-8 h-8 text-neutral-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
    </div>
    <h3 class="text-lg font-semibold text-neutral-900 mb-2">No tienes cursos</h3>
    <p class="text-neutral-500 mb-6 max-w-md">
      Parece que aún no estás inscrito en ningún curso. Visita nuestro catálogo para encontrar el curso perfecto para ti.
    </p>
    <a href="<?= BASE_URL ?>/catalogo.php" class="inline-flex items-center px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white font-medium rounded-lg transition-colors text-sm">
      Explorar catálogo
    </a>
  </div>
<?php else: ?>
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php foreach ($courses as $c): 
      $pct = $c['total_lessons'] > 0 ? round($c['done_lessons'] / $c['total_lessons'] * 100) : 0;
      $initials = mb_substr($c['title'], 0, 2);
      $status = $pct === 100 ? 'Completado' : ($pct > 0 ? 'En progreso' : 'No iniciado');
    ?>
      <a href="<?= BASE_URL ?>/curso.php?id=<?= $c['id'] ?>" 
         class="bg-white rounded-2xl shadow-sm border border-neutral-200 overflow-hidden hover:shadow-md transition-shadow h-full flex flex-col group">
        <!-- Header gradient -->
        <div class="h-40 bg-gradient-to-br from-primary-100 to-primary-200 flex items-center justify-center relative">
          <span class="text-5xl font-bold text-primary-300/50 group-hover:scale-110 transition-transform duration-300 select-none">
            <?= htmlspecialchars(strtoupper($initials)) ?>
          </span>
          <div class="absolute top-3 right-3">
            <?php if ($pct === 100): ?>
              <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700">Completado</span>
            <?php elseif ($pct > 0): ?>
              <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-primary-100 text-primary-700">En progreso</span>
            <?php else: ?>
              <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-neutral-100 text-neutral-600">No iniciado</span>
            <?php endif; ?>
          </div>
        </div>

        <!-- Card body -->
        <div class="p-5 flex flex-col flex-1 gap-3">
          <?php if ($c['sku']): ?>
            <span class="text-xs font-semibold uppercase tracking-wider text-primary-600">
              <?= htmlspecialchars($c['sku']) ?>
            </span>
          <?php endif; ?>
          <h3 class="font-bold text-neutral-900 text-lg leading-tight line-clamp-2">
            <?= htmlspecialchars($c['title']) ?>
          </h3>
          <p class="text-sm text-neutral-500">
            Instructor: Equipo Selcap
          </p>
          <div class="mt-auto pt-4">
            <div class="flex justify-between text-sm mb-1.5">
              <span class="text-neutral-500 flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Progreso
              </span>
              <span class="font-medium text-neutral-900"><?= $pct ?>%</span>
            </div>
            <div class="w-full bg-neutral-100 rounded-full h-2 overflow-hidden">
              <div class="h-2 rounded-full transition-all <?= $pct === 100 ? 'bg-green-500' : 'bg-primary-500' ?>" style="width:<?= $pct ?>%"></div>
            </div>
          </div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
