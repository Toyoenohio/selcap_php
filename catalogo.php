<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
$userId = $_SESSION['user_id'];
$pdo = db();

$enrolledIds = [];
$enrStmt = $pdo->prepare('SELECT course_id FROM enrollments WHERE user_id = ?');
$enrStmt->execute([$userId]);
foreach ($enrStmt->fetchAll() as $e) $enrolledIds[] = $e['course_id'];

$coursesStmt = $pdo->prepare('SELECT * FROM courses WHERE status = "published" ORDER BY created_at DESC');
$coursesStmt->execute();
$courses = $coursesStmt->fetchAll();

$currentPage = 'catalogo';
$pageTitle = 'Catálogo de Cursos';
require __DIR__ . '/includes/header.php';
?>

<h1 class="text-2xl font-extrabold text-gray-900 mb-6">Catálogo de Cursos</h1>

<?php if (empty($courses)): ?>
  <div class="bg-white rounded-2xl border-2 border-dashed border-gray-200 p-12 text-center text-gray-400">
    No hay cursos disponibles aún.
  </div>
<?php else: ?>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <?php foreach ($courses as $c): $enrolled = in_array($c['id'], $enrolledIds); ?>
      <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-start gap-4 mb-4">
          <div class="w-14 h-14 bg-selcap-50 rounded-xl flex items-center justify-center text-2xl shrink-0">📚</div>
          <div>
            <h2 class="font-bold text-gray-900 text-lg"><?= htmlspecialchars($c['title']) ?></h2>
            <p class="text-sm text-gray-500 mt-1"><?= htmlspecialchars($c['description'] ?? '') ?></p>
          </div>
        </div>
        <?php if ($enrolled): ?>
          <a href="<?= BASE_URL ?>/curso.php?id=<?= $c['id'] ?>" class="block w-full text-center bg-selcap-600 hover:bg-selcap-700 text-white font-semibold py-2.5 rounded-xl transition-colors text-sm">
            Continuar curso →
          </a>
        <?php else: ?>
          <form method="POST" action="<?= BASE_URL ?>/enroll.php">
            <input type="hidden" name="course_id" value="<?= $c['id'] ?>">
            <button type="submit" class="w-full bg-selcap-600 hover:bg-selcap-700 text-white font-semibold py-2.5 rounded-xl transition-colors text-sm">
              Inscribirme
            </button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
