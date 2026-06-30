<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$userId = $_SESSION['user_id'];
$pdo = db();
$user = currentUser();

// Stats
$enrolledStmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM enrollments WHERE user_id = ? AND status = "active"');
$enrolledStmt->execute([$userId]);
$enrolled = (int) $enrolledStmt->fetch()['cnt'];

$lessonsStmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM lesson_progress WHERE user_id = ? AND completed = 1');
$lessonsStmt->execute([$userId]);
$completedLessons = (int) $lessonsStmt->fetch()['cnt'];

$certsStmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM evaluation_attempts WHERE user_id = ? AND passed = 1');
$certsStmt->execute([$userId]);
$certs = (int) $certsStmt->fetch()['cnt'];

// Cursos enrolados
$coursesStmt = $pdo->prepare('SELECT c.*, e.status as enroll_status, 
    (SELECT COUNT(*) FROM lessons l JOIN sections s ON l.section_id = s.id WHERE s.course_id = c.id) as total_lessons,
    (SELECT COUNT(*) FROM lesson_progress lp JOIN lessons l ON lp.lesson_id = l.id JOIN sections s ON l.section_id = s.id WHERE lp.user_id = ? AND s.course_id = c.id AND lp.completed = 1) as done_lessons
    FROM courses c 
    JOIN enrollments e ON e.course_id = c.id 
    WHERE e.user_id = ? AND e.status = "active"
    ORDER BY e.enrolled_at DESC');
$coursesStmt->execute([$userId, $userId]);
$courses = $coursesStmt->fetchAll();

$currentPage = 'dashboard';
$pageTitle = 'Panel';
require __DIR__ . '/includes/header.php';
?>

<h1 class="text-2xl lg:text-3xl font-extrabold text-gray-900 mb-1">¡Hola, <?= htmlspecialchars($user['first_name']) ?>!</h1>
<p class="text-gray-500 text-sm mb-6">Bienvenido al aula virtual. Aquí tienes un resumen de tu progreso.</p>

<!-- Stats -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
  <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 flex items-center gap-4">
    <div class="w-12 h-12 bg-selcap-100 rounded-xl flex items-center justify-center shrink-0">
      <svg class="w-6 h-6 text-selcap-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
    </div>
    <div>
      <p class="text-2xl font-extrabold text-gray-900"><?= $enrolled ?></p>
      <p class="text-sm text-gray-500">Cursos Inscritos</p>
    </div>
  </div>
  <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 flex items-center gap-4">
    <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center shrink-0">
      <svg class="w-6 h-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    </div>
    <div>
      <p class="text-2xl font-extrabold text-gray-900"><?= $completedLessons ?></p>
      <p class="text-sm text-gray-500">Lecciones Completadas</p>
    </div>
  </div>
  <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 flex items-center gap-4">
    <div class="w-12 h-12 bg-amber-100 rounded-xl flex items-center justify-center shrink-0">
      <svg class="w-6 h-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
    </div>
    <div>
      <p class="text-2xl font-extrabold text-gray-900"><?= $certs ?></p>
      <p class="text-sm text-gray-500">Evaluaciones Aprobadas</p>
    </div>
  </div>
</div>

<!-- Continuar aprendiendo -->
<div class="mb-8">
  <div class="flex items-center justify-between mb-4">
    <h2 class="text-lg font-bold text-gray-900">Continuar aprendiendo</h2>
    <a href="<?= BASE_URL ?>/catalogo.php" class="text-sm font-semibold text-selcap-600 hover:text-selcap-700">Ver todos los cursos →</a>
  </div>

  <?php if (empty($courses)): ?>
    <div class="bg-white rounded-2xl border-2 border-dashed border-gray-200 p-12 text-center">
      <div class="w-16 h-16 mx-auto mb-4 bg-gray-100 rounded-full flex items-center justify-center">
        <svg class="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
      </div>
      <p class="text-gray-500 font-medium mb-2">Aún no estás inscrito en ningún curso.</p>
      <a href="<?= BASE_URL ?>/catalogo.php" class="text-selcap-600 font-semibold text-sm hover:underline">Explorar catálogo de cursos</a>
    </div>
  <?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <?php foreach ($courses as $course): 
        $pct = $course['total_lessons'] > 0 ? round($course['done_lessons'] / $course['total_lessons'] * 100) : 0;
      ?>
        <a href="<?= BASE_URL ?>/curso.php?id=<?= $course['id'] ?>" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 hover:shadow-md hover:border-selcap-200 transition-all group">
          <div class="flex items-start gap-4">
            <div class="w-16 h-16 bg-selcap-50 rounded-xl flex items-center justify-center shrink-0 text-2xl">
              📚
            </div>
            <div class="flex-1 min-w-0">
              <h3 class="font-bold text-gray-900 mb-1 group-hover:text-selcap-600 transition-colors"><?= htmlspecialchars($course['title']) ?></h3>
              <p class="text-sm text-gray-500 line-clamp-2 mb-3"><?= htmlspecialchars($course['description'] ?? '') ?></p>
              <div class="flex items-center gap-3">
                <div class="flex-1 bg-gray-100 rounded-full h-2">
                  <div class="h-2 rounded-full bg-selcap-500 transition-all" style="width:<?= $pct ?>%"></div>
                </div>
                <span class="text-xs font-semibold text-gray-500 shrink-0"><?= $pct ?>%</span>
              </div>
              <p class="text-xs text-gray-400 mt-1"><?= $course['done_lessons'] ?>/<?= $course['total_lessons'] ?> lecciones</p>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
