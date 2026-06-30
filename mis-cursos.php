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

<h1 class="text-2xl font-extrabold text-gray-900 mb-6">Mis Cursos</h1>

<?php if (empty($courses)): ?>
  <div class="bg-white rounded-2xl border-2 border-dashed border-gray-200 p-12 text-center">
    <div class="text-5xl mb-4">📚</div>
    <p class="text-gray-500 font-medium mb-2">No estás inscrito en ningún curso.</p>
    <a href="<?= BASE_URL ?>/catalogo.php" class="text-selcap-600 font-semibold text-sm hover:underline">Explorar catálogo</a>
  </div>
<?php else: ?>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <?php foreach ($courses as $c): $pct = $c['total_lessons'] > 0 ? round($c['done_lessons'] / $c['total_lessons'] * 100) : 0; ?>
      <a href="<?= BASE_URL ?>/curso.php?id=<?= $c['id'] ?>" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 hover:shadow-md hover:border-selcap-200 transition-all group">
        <div class="flex items-start gap-4 mb-3">
          <div class="w-12 h-12 bg-selcap-50 rounded-xl flex items-center justify-center text-xl shrink-0">📚</div>
          <div>
            <h3 class="font-bold text-gray-900 group-hover:text-selcap-600 transition-colors"><?= htmlspecialchars($c['title']) ?></h3>
            <p class="text-xs text-gray-400">Inscrito <?= date('d/m/Y', strtotime($c['enrolled_at'])) ?></p>
          </div>
        </div>
        <div class="flex items-center gap-3">
          <div class="flex-1 bg-gray-100 rounded-full h-2">
            <div class="h-2 rounded-full <?= $pct === 100 ? 'bg-green-500' : 'bg-selcap-500' ?> transition-all" style="width:<?= $pct ?>%"></div>
          </div>
          <span class="text-xs font-semibold text-gray-500"><?= $pct ?>%</span>
        </div>
        <p class="text-xs text-gray-400 mt-1"><?= $c['done_lessons'] ?>/<?= $c['total_lessons'] ?> lecciones</p>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
