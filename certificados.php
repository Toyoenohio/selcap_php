<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
$userId = $_SESSION['user_id'];
$pdo = db();

$certsStmt = $pdo->prepare('SELECT ea.*, e.title as eval_title, c.title as course_title, c.id as course_id
    FROM evaluation_attempts ea
    JOIN evaluations e ON ea.evaluation_id = e.id
    JOIN courses c ON e.course_id = c.id
    WHERE ea.user_id = ? AND ea.passed = 1
    ORDER BY ea.submitted_at DESC');
$certsStmt->execute([$userId]);
$certificates = $certsStmt->fetchAll();

$currentPage = 'certificados';
$pageTitle = 'Certificados';
require __DIR__ . '/includes/header.php';
?>

<h1 class="text-2xl font-extrabold text-gray-900 mb-6">Certificados</h1>

<?php if (empty($certificates)): ?>
  <div class="bg-white rounded-2xl border-2 border-dashed border-gray-200 p-12 text-center">
    <div class="text-5xl mb-4">🏅</div>
    <p class="text-gray-500 font-medium mb-2">Aún no tienes certificados.</p>
    <p class="text-gray-400 text-sm">Completa las evaluaciones de tus cursos para obtenerlos.</p>
    <a href="<?= BASE_URL ?>/mis-cursos.php" class="text-selcap-600 font-semibold text-sm mt-4 inline-block hover:underline">Ir a mis cursos</a>
  </div>
<?php else: ?>
  <div class="space-y-4">
    <?php foreach ($certificates as $cert): ?>
      <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 flex items-center gap-4">
        <div class="w-12 h-12 bg-amber-100 rounded-xl flex items-center justify-center text-xl shrink-0">🏅</div>
        <div class="flex-1 min-w-0">
          <h3 class="font-bold text-gray-900"><?= htmlspecialchars($cert['eval_title']) ?></h3>
          <p class="text-sm text-gray-500"><?= htmlspecialchars($cert['course_title']) ?></p>
          <p class="text-xs text-gray-400">Aprobado el <?= date('d/m/Y', strtotime($cert['submitted_at'])) ?> con <?= round($cert['score']) ?>%</p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <a href="<?= BASE_URL ?>/certificado.php?id=<?= $cert['id'] ?>" class="bg-amber-100 hover:bg-amber-200 text-amber-800 font-semibold px-3 py-1.5 rounded-lg transition-colors text-sm">🎓 Ver certificado</a>
          <a href="<?= BASE_URL ?>/curso.php?id=<?= $cert['course_id'] ?>" class="text-selcap-600 text-sm font-semibold hover:underline">Ver curso →</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
