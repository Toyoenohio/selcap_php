<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$pdo = db();
$msg = ''; $msgType = '';

// ── Acciones ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_evaluation') {
        $stmt = $pdo->prepare('INSERT INTO evaluations (section_id, title, description, max_attempts, passing_score, sort_order) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([(int)$_POST['section_id'], trim($_POST['title']), $_POST['description'] ?? '', 1, (int)$_POST['passing_score'], $_POST['sort_order'] ?? 0]);
        $msg = 'Evaluación creada.'; $msgType = 'green';
    } elseif ($_POST['action'] === 'update_evaluation') {
        $pdo->prepare('UPDATE evaluations SET section_id = ?, title = ?, description = ?, passing_score = ?, sort_order = ? WHERE id = ?')
            ->execute([(int)$_POST['section_id'], trim($_POST['title']), $_POST['description'] ?? '', (int)$_POST['passing_score'], $_POST['sort_order'] ?? 0, (int)$_POST['id']]);
        $msg = 'Evaluación actualizada.'; $msgType = 'blue';
    } elseif ($_POST['action'] === 'delete_evaluation') {
        $pdo->prepare('DELETE FROM evaluations WHERE id = ?')->execute([(int)$_POST['id']]);
        $msg = 'Evaluación eliminada.'; $msgType = 'red';
    } elseif ($_POST['action'] === 'create_question') {
        $stmt = $pdo->prepare('INSERT INTO questions (evaluation_id, text, type, points, sort_order) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([(int)$_POST['evaluation_id'], trim($_POST['text']), 'multiple_choice', (int)$_POST['points'], $_POST['sort_order'] ?? 0]);
        $qId = (int) $pdo->lastInsertId();

        $answerTexts = $_POST['answer_text'] ?? [];
        $answerCorrect = $_POST['answer_correct'] ?? '';
        $correctIdx = (int)$answerCorrect;
        foreach ($answerTexts as $i => $text) {
            if (!trim($text)) continue;
            $isCorrect = ($i === $correctIdx) ? 1 : 0;
            $pdo->prepare('INSERT INTO answers (question_id, text, is_correct, sort_order) VALUES (?, ?, ?, ?)')
                ->execute([$qId, trim($text), $isCorrect, $i]);
        }
        $msg = 'Pregunta creada.'; $msgType = 'green';
    } elseif ($_POST['action'] === 'update_question') {
        $qId = (int)$_POST['q_id'];
        $pdo->prepare('UPDATE questions SET text = ?, points = ?, sort_order = ? WHERE id = ?')
            ->execute([trim($_POST['text']), (int)$_POST['points'], $_POST['sort_order'] ?? 0, $qId]);

        $pdo->prepare('DELETE FROM answers WHERE question_id = ?')->execute([$qId]);
        $answerTexts = $_POST['answer_text'] ?? [];
        $answerCorrect = $_POST['answer_correct'] ?? '0';
        $correctIdx = (int)$answerCorrect;
        foreach ($answerTexts as $i => $text) {
            if (!trim($text)) continue;
            $isCorrect = ($i === $correctIdx) ? 1 : 0;
            $pdo->prepare('INSERT INTO answers (question_id, text, is_correct, sort_order) VALUES (?, ?, ?, ?)')
                ->execute([$qId, trim($text), $isCorrect, $i]);
        }
        $msg = 'Pregunta actualizada.'; $msgType = 'blue';
    } elseif ($_POST['action'] === 'delete_question') {
        $pdo->prepare('DELETE FROM questions WHERE id = ?')->execute([(int)$_POST['q_id']]);
        $msg = 'Pregunta eliminada.'; $msgType = 'red';
    } elseif ($_POST['action'] === 'save_feedback') {
        $attemptId = (int)$_POST['attempt_id'];
        $feedback = trim($_POST['feedback'] ?? '');
        $pdo->prepare('UPDATE evaluation_attempts SET feedback = ? WHERE id = ?')
            ->execute([$feedback ?: null, $attemptId]);
        $msg = 'Retroalimentación guardada.'; $msgType = 'green';
    }
}

// Secciones
$sectionsStmt = $pdo->prepare('SELECT * FROM sections WHERE course_id = ? ORDER BY sort_order');
$sectionsStmt->execute([ACTIVE_COURSE_ID]);
$sections = $sectionsStmt->fetchAll();

// Filtro
$filterSection = $_GET['section_id'] ?? '';
$where = $filterSection ? 'WHERE s.id = ' . (int)$filterSection : '';

// Evaluaciones
$evalsStmt = $pdo->prepare("SELECT e.*, s.title as section_title FROM evaluations e
    JOIN sections s ON e.section_id = s.id $where ORDER BY s.sort_order, e.sort_order");
$evalsStmt->execute();
$evaluations = $evalsStmt->fetchAll();

// Preguntas y respuestas
$allQ = [];
$allA = [];
if ($evaluations) {
    $evalIds = array_column($evaluations, 'id');
    $in = implode(',', array_map('intval', $evalIds));
    $qStmt = $pdo->prepare("SELECT * FROM questions WHERE evaluation_id IN ($in) ORDER BY sort_order");
    $qStmt->execute();
    foreach ($qStmt->fetchAll() as $q) {
        $allQ[$q['evaluation_id']][] = $q;
    }
    $aStmt = $pdo->prepare("SELECT * FROM answers WHERE question_id IN (SELECT id FROM questions WHERE evaluation_id IN ($in)) ORDER BY sort_order");
    $aStmt->execute();
    foreach ($aStmt->fetchAll() as $a) {
        $allA[$a['question_id']][] = $a;
    }
}

// Intentos de estudiantes (para feedback)
$attemptsByEval = [];
if ($evaluations) {
    $attStmt = $pdo->prepare("SELECT ea.*, u.first_name, u.last_name, u.email FROM evaluation_attempts ea
        JOIN users u ON ea.user_id = u.id
        WHERE ea.evaluation_id IN ($in) ORDER BY ea.submitted_at DESC LIMIT 100");
    $attStmt->execute();
    foreach ($attStmt->fetchAll() as $att) {
        $attemptsByEval[$att['evaluation_id']][] = $att;
    }
}

$pageTitle = 'Admin — Evaluaciones';
require __DIR__ . '/../includes/header.php';
?>

<div class="flex items-center justify-between mb-6">
  <h1 class="text-2xl font-extrabold text-gray-900">Evaluaciones</h1>
  <div class="flex items-center gap-2 text-sm flex-wrap">
    <a href="<?= BASE_URL ?>/admin/courses.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-xl font-semibold transition-colors">Cursos</a>
    <a href="<?= BASE_URL ?>/admin/" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-xl font-semibold transition-colors">Secciones</a>
    <a href="<?= BASE_URL ?>/admin/lessons.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-xl font-semibold transition-colors">Lecciones</a>
    <a href="<?= BASE_URL ?>/admin/evaluations.php" class="bg-selcap-600 text-white px-4 py-2 rounded-xl font-semibold">Evaluaciones</a>
    <a href="<?= BASE_URL ?>/admin/alumnos.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-xl font-semibold transition-colors">Alumnos</a>
    <a href="<?= BASE_URL ?>/admin/reportes.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-xl font-semibold transition-colors">Reportes</a>
  </div>
</div>

<?php if ($msg): ?>
  <div class="bg-<?= $msgType ?>-50 border border-<?= $msgType ?>-200 text-<?= $msgType ?>-700 px-4 py-3 rounded-xl text-sm mb-4"><?= $msg ?></div>
<?php endif; ?>

<?php if ($filterSection): ?>
  <a href="<?= BASE_URL ?>/admin/evaluations.php" class="text-selcap-600 text-sm font-medium hover:underline mb-4 inline-block">← Todas las secciones</a>
<?php endif; ?>

<!-- Crear evaluación -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 mb-6">
  <h2 class="font-bold text-gray-800 mb-3">Nueva evaluación</h2>
  <form method="POST" class="space-y-3">
    <input type="hidden" name="action" value="create_evaluation">
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
      <select name="section_id" required class="px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500">
        <option value="">Seleccionar sección</option>
        <?php foreach ($sections as $s): ?>
          <option value="<?= $s['id'] ?>" <?= $filterSection == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['title']) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="text" name="title" placeholder="Título" required
             class="sm:col-span-2 px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500">
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
      <div>
        <label class="text-xs text-gray-400 block mb-1">% Aprobación</label>
        <input type="number" name="passing_score" placeholder="80" value="80" min="0" max="100"
               class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500">
      </div>
      <input type="number" name="sort_order" placeholder="Orden" value="0"
             class="px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500">
    </div>
    <textarea name="description" placeholder="Descripción" rows="2"
              class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500"></textarea>
    <button type="submit" class="bg-selcap-600 hover:bg-selcap-700 text-white font-semibold px-5 py-2.5 rounded-xl transition-colors text-sm">
      Crear evaluación
    </button>
  </form>
</div>

<!-- Lista -->
<?php foreach ($evaluations as $ev): 
  $questions = $allQ[$ev['id']] ?? [];
  $attempts = $attemptsByEval[$ev['id']] ?? [];
?>
  <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 mb-4">
    <form method="POST" class="space-y-3 mb-4">
      <input type="hidden" name="action" value="update_evaluation">
      <input type="hidden" name="id" value="<?= $ev['id'] ?>">
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <select name="section_id" class="px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm">
          <?php foreach ($sections as $s): ?>
            <option value="<?= $s['id'] ?>" <?= $s['id'] == $ev['section_id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['title']) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="text" name="title" value="<?= htmlspecialchars($ev['title']) ?>"
               class="sm:col-span-2 px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 font-medium">
      </div>
      <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
        <div>
          <label class="text-xs text-gray-400 block mb-1">% Aprobación</label>
          <input type="number" name="passing_score" value="<?= $ev['passing_score'] ?? 80 ?>" min="0" max="100"
                 class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm">
        </div>
        <input type="number" name="sort_order" value="<?= $ev['sort_order'] ?>"
               class="px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm">
      </div>
      <textarea name="description" rows="2"
                class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm"><?= htmlspecialchars($ev['description'] ?? '') ?></textarea>
      <div class="flex items-center gap-2">
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2 rounded-xl transition-colors text-sm">Guardar</button>
        <form method="POST" onsubmit="return confirm('¿Eliminar esta evaluación y todas sus preguntas?')" class="inline">
          <input type="hidden" name="action" value="delete_evaluation">
          <input type="hidden" name="id" value="<?= $ev['id'] ?>">
          <button type="submit" class="bg-red-100 hover:bg-red-200 text-red-700 font-semibold px-4 py-2 rounded-xl transition-colors text-sm">Eliminar</button>
        </form>
      </div>
    </form>

    <!-- Preguntas -->
    <div class="border-t border-gray-100 pt-4">
      <h3 class="font-bold text-gray-700 text-sm mb-3">Preguntas (<?= count($questions) ?>)</h3>
      
      <?php foreach ($questions as $q): 
        $answers = $allA[$q['id']] ?? [];
      ?>
        <div class="bg-gray-50 rounded-xl p-4 mb-3">
          <form method="POST" class="space-y-3">
            <input type="hidden" name="action" value="update_question">
            <input type="hidden" name="q_id" value="<?= $q['id'] ?>">
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
              <input type="text" name="text" value="<?= htmlspecialchars($q['text']) ?>" placeholder="Texto de la pregunta" required
                     class="sm:col-span-3 px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm">
              <input type="number" name="points" value="<?= $q['points'] ?>" placeholder="Puntos"
                     class="px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm">
            </div>
            <div class="space-y-2">
              <?php foreach ($answers as $i => $a): ?>
                <div class="flex items-center gap-2">
                  <input type="radio" name="answer_correct" value="<?= $i ?>" <?= $a['is_correct'] ? 'checked' : '' ?> class="accent-selcap-600 w-4 h-4 shrink-0">
                  <input type="text" name="answer_text[<?= $i ?>]" value="<?= htmlspecialchars($a['text']) ?>"
                         class="flex-1 px-3 py-2 bg-white border border-gray-200 rounded-lg text-gray-900 focus:outline-none focus:ring-1 focus:ring-selcap-500 text-sm">
                  <?php if ($a['is_correct']): ?>
                    <span class="text-xs text-green-600 font-bold shrink-0">✓</span>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
              <?php $newIdx = count($answers); ?>
              <div class="flex items-center gap-2">
                <input type="radio" name="answer_correct" value="<?= $newIdx ?>" class="accent-selcap-600 w-4 h-4 shrink-0">
                <input type="text" name="answer_text[<?= $newIdx ?>]" placeholder="Nueva respuesta..."
                       class="flex-1 px-3 py-2 bg-white border border-dashed border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-1 focus:ring-selcap-500 text-sm">
              </div>
            </div>
            <div class="flex items-center gap-2">
              <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-3 py-2 rounded-lg transition-colors text-xs">Guardar pregunta</button>
              <form method="POST" onsubmit="return confirm('¿Eliminar esta pregunta?')" class="inline">
                <input type="hidden" name="action" value="delete_question">
                <input type="hidden" name="q_id" value="<?= $q['id'] ?>">
                <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-semibold">Eliminar</button>
              </form>
            </div>
          </form>
        </div>
      <?php endforeach; ?>

      <details class="mt-3">
        <summary class="text-selcap-600 font-semibold text-sm cursor-pointer hover:underline">+ Agregar pregunta</summary>
        <form method="POST" class="mt-3 space-y-3">
          <input type="hidden" name="action" value="create_question">
          <input type="hidden" name="evaluation_id" value="<?= $ev['id'] ?>">
          <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <input type="text" name="text" placeholder="Texto de la pregunta" required
                   class="sm:col-span-3 px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm">
            <input type="number" name="points" value="1" placeholder="Puntos"
                   class="px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm">
          </div>
          <div class="space-y-2">
            <?php for ($i = 0; $i < 4; $i++): ?>
              <div class="flex items-center gap-2">
                <input type="radio" name="answer_correct" value="<?= $i ?>" <?= $i === 0 ? 'checked' : '' ?> class="accent-selcap-600 w-4 h-4 shrink-0">
                <input type="text" name="answer_text[<?= $i ?>]" placeholder="Respuesta <?= $i + 1 ?>"
                       class="flex-1 px-3 py-2 bg-white border border-gray-200 rounded-lg text-gray-900 focus:outline-none focus:ring-1 focus:ring-selcap-500 text-sm">
              </div>
            <?php endfor; ?>
          </div>
          <button type="submit" class="bg-selcap-600 hover:bg-selcap-700 text-white font-semibold px-4 py-2.5 rounded-xl transition-colors text-sm">
            Crear pregunta
          </button>
        </form>
      </details>
    </div>

    <!-- Intentos de estudiantes + Feedback -->
    <?php if ($attempts): ?>
    <div class="border-t border-gray-100 pt-4 mt-4">
      <h3 class="font-bold text-gray-700 text-sm mb-3">Intentos de estudiantes (<?= count($attempts) ?>)</h3>
      <div class="space-y-3">
        <?php foreach ($attempts as $att): 
          $hasFeedback = !empty($att['feedback']);
        ?>
          <div class="bg-gray-50 rounded-xl p-4">
            <div class="flex items-start justify-between mb-2">
              <div>
                <p class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($att['first_name'] . ' ' . $att['last_name']) ?></p>
                <p class="text-xs text-gray-400"><?= htmlspecialchars($att['email']) ?> — <?= $att['submitted_at'] ?></p>
              </div>
              <div class="text-right">
                <p class="text-lg font-extrabold <?= $att['passed'] ? 'text-green-600' : 'text-red-500' ?>"><?= round($att['score']) ?>%</p>
                <p class="text-xs text-gray-400"><?= $att['passed'] ? '✅ Aprobado' : '❌ Reprobado' ?></p>
              </div>
            </div>
            <form method="POST" class="mt-2">
              <input type="hidden" name="action" value="save_feedback">
              <input type="hidden" name="attempt_id" value="<?= $att['id'] ?>">
              <textarea name="feedback" placeholder="Retroalimentación para el estudiante (solo visible si tiene contenido)..." rows="2"
                        class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-gray-900 focus:outline-none focus:ring-1 focus:ring-selcap-500 text-sm"><?= htmlspecialchars($att['feedback'] ?? '') ?></textarea>
              <button type="submit" class="mt-2 bg-selcap-600 hover:bg-selcap-700 text-white font-semibold px-3 py-1.5 rounded-lg transition-colors text-xs">
                <?= $hasFeedback ? 'Actualizar feedback' : 'Guardar feedback' ?>
              </button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
<?php endforeach; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
