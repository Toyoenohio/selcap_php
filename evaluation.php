<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$evalId = (int) ($_GET['id'] ?? 0);
$userId = $_SESSION['user_id'];
$pdo = db();

$stmt = $pdo->prepare('SELECT e.*, s.course_id FROM evaluations e
    JOIN sections s ON e.section_id = s.id
    WHERE e.id = ?');
$stmt->execute([$evalId]);
$evaluation = $stmt->fetch();

if (!$evaluation) {
    http_response_code(404);
    $pageTitle = 'Evaluación no encontrada';
    require __DIR__ . '/includes/header.php';
    echo '<div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-12 text-center"><p class="text-gray-500">Evaluación no encontrada.</p><a href="' . BASE_URL . '/dashboard.php" class="text-selcap-600 font-medium text-sm mt-4 inline-block">Volver</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

// Contar intentos
$cntStmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM evaluation_attempts WHERE user_id = ? AND evaluation_id = ?');
$cntStmt->execute([$userId, $evalId]);
$attemptCount = (int) $cntStmt->fetch()['cnt'];

// Último intento aprobado? → no dejar reintentar
$lastStmt = $pdo->prepare('SELECT * FROM evaluation_attempts WHERE user_id = ? AND evaluation_id = ? ORDER BY attempt_number DESC LIMIT 1');
$lastStmt->execute([$userId, $evalId]);
$lastAttempt = $lastStmt->fetch();

if ($lastAttempt && $lastAttempt['passed']) {
    $pageTitle = 'Evaluación aprobada';
    require __DIR__ . '/includes/header.php';
    echo '<div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-12 text-center">
        <div class="text-5xl mb-4">🎉</div>
        <p class="text-xl font-bold text-green-600 mb-2">¡Ya aprobaste esta evaluación!</p>
        <p class="text-gray-500">Puntaje: ' . round($lastAttempt['score']) . '%</p>
        <a href="' . BASE_URL . '/dashboard.php" class="text-selcap-600 font-medium text-sm mt-4 inline-block">Volver al curso</a>
    </div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

// Sin intentos disponibles
if ($attemptCount >= $evaluation['max_attempts']) {
    $pageTitle = 'Sin intentos';
    require __DIR__ . '/includes/header.php';
    echo '<div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-12 text-center">
        <div class="text-5xl mb-4">⛔</div>
        <p class="text-lg font-bold text-gray-700 mb-2">Sin intentos disponibles</p>
        <p class="text-gray-500">Has usado ' . $attemptCount . ' de ' . $evaluation['max_attempts'] . ' intentos.</p>
        <a href="' . BASE_URL . '/dashboard.php" class="text-selcap-600 font-medium text-sm mt-4 inline-block">Volver al curso</a>
    </div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

// Obtener preguntas
$qStmt = $pdo->prepare('SELECT * FROM questions WHERE evaluation_id = ? ORDER BY sort_order');
$qStmt->execute([$evalId]);
$questions = $qStmt->fetchAll();

// Obtener respuestas
$aStmt = $pdo->prepare('SELECT * FROM answers WHERE question_id IN (SELECT id FROM questions WHERE evaluation_id = ?) ORDER BY sort_order');
$aStmt->execute([$evalId]);
$answersByQ = [];
foreach ($aStmt->fetchAll() as $a) {
    $answersByQ[$a['question_id']][] = $a;
}

$submitted = false;
$score = 0;
$total = 0;
$passed = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_eval'])) {
    $responses = $_POST['answer'] ?? [];
    
    foreach ($questions as $q) {
        $total += $q['points'];
        $chosen = $responses[$q['id']] ?? null;
        $answers = $answersByQ[$q['id']] ?? [];
        foreach ($answers as $a) {
            if ((string) $a['id'] === (string) $chosen && $a['is_correct']) {
                $score += $q['points'];
                break;
            }
        }
    }
    
    $pct = $total > 0 ? round($score / $total * 100, 1) : 0;
    $passed = $pct >= ($evaluation['passing_grade'] ?? 70); // passing_grade from course

    // Obtener passing_grade del curso
    $pgStmt = $pdo->prepare('SELECT c.passing_grade FROM courses c JOIN sections s ON s.course_id = c.id JOIN evaluations e ON e.section_id = s.id WHERE e.id = ?');
    $pgStmt->execute([$evalId]);
    $pg = $pgStmt->fetch()['passing_grade'] ?? 70;
    $passed = $pct >= $pg;

    // Guardar intento
    $insStmt = $pdo->prepare('INSERT INTO evaluation_attempts (user_id, evaluation_id, attempt_number, score, passed, answers_snapshot, submitted_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())');
    $insStmt->execute([$userId, $evalId, $attemptCount + 1, $pct, $passed ? 1 : 0, json_encode($responses)]);

    $submitted = true;
    $pageTitle = 'Resultado';
} else {
    $pageTitle = $evaluation['title'];
}

require __DIR__ . '/includes/header.php';
?>

<nav class="flex items-center gap-2 text-sm text-gray-400 mb-4">
  <a href="<?= BASE_URL ?>/dashboard.php" class="hover:text-selcap-600 transition-colors">Curso</a>
  <span>›</span>
  <span class="text-gray-800 font-medium"><?= htmlspecialchars($evaluation['title']) ?></span>
</nav>

<?php if ($submitted): ?>
  <!-- Resultado -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8 text-center">
    <div class="text-6xl mb-4"><?= $passed ? '🎉' : '📚' ?></div>
    <p class="text-2xl font-extrabold <?= $passed ? 'text-green-600' : 'text-red-500' ?> mb-2">
      <?= round($score) ?>/<?= $total ?> puntos (<?= round($score/$total*100) ?>%)
    </p>
    <p class="text-gray-500 mb-6">
      <?= $passed ? '¡Felicitaciones! Aprobaste la evaluación.' : 'No alcanzaste la nota mínima. Repasa el contenido e inténtalo de nuevo.' ?>
      <br><span class="text-xs">Intento <?= $attemptCount + 1 ?> de <?= $evaluation['max_attempts'] ?></span>
    </p>
    <div class="flex justify-center gap-3">
      <a href="<?= BASE_URL ?>/dashboard.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold px-5 py-2.5 rounded-xl transition-colors text-sm">Volver al curso</a>
      <?php if (!$passed && ($attemptCount + 1) < $evaluation['max_attempts']): ?>
        <a href="<?= BASE_URL ?>/evaluation.php?id=<?= $evalId ?>" class="bg-selcap-600 hover:bg-selcap-700 text-white font-semibold px-5 py-2.5 rounded-xl transition-colors text-sm">Reintentar</a>
      <?php endif; ?>
    </div>
  </div>

<?php else: ?>
  <!-- Formulario de evaluación -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 sm:p-8">
    <h1 class="text-xl font-bold text-gray-900 mb-1"><?= htmlspecialchars($evaluation['title']) ?></h1>
    <p class="text-gray-500 text-sm mb-2"><?= nl2br(htmlspecialchars($evaluation['description'] ?? '')) ?></p>
    <p class="text-xs text-gray-400 mb-6">Intento <?= $attemptCount + 1 ?> de <?= $evaluation['max_attempts'] ?></p>

    <form method="POST" class="space-y-8">
      <?php foreach ($questions as $i => $q): 
        $answers = $answersByQ[$q['id']] ?? [];
      ?>
        <div>
          <p class="font-semibold text-gray-800 mb-3"><?= $i + 1 ?>. <?= htmlspecialchars($q['text']) ?> <span class="text-xs text-gray-400">(<?= $q['points'] ?> pt<?= $q['points'] !== 1 ? 's' : '' ?>)</span></p>
          <div class="space-y-2">
            <?php foreach ($answers as $a): ?>
              <label class="flex items-center gap-3 px-4 py-3 bg-gray-50 hover:bg-selcap-50 rounded-xl cursor-pointer border border-gray-100 hover:border-selcap-200 transition-colors has-[:checked]:border-selcap-500 has-[:checked]:bg-selcap-50">
                <input type="radio" name="answer[<?= $q['id'] ?>]" value="<?= $a['id'] ?>" required class="accent-selcap-600 w-4 h-4">
                <span class="text-sm text-gray-700"><?= htmlspecialchars($a['text']) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>

      <button type="submit" name="submit_eval" value="1"
              class="w-full bg-selcap-600 hover:bg-selcap-700 text-white font-semibold py-3.5 rounded-xl transition-colors text-base">
        Enviar respuestas
      </button>
    </form>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
