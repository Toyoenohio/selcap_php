<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$pdo = db();
$msg = ''; $msgType = '';

$courseId = (int)($_GET['course_id'] ?? 0);
$course = null;
$sections = [];

if ($courseId) {
    $cStmt = $pdo->prepare('SELECT * FROM courses WHERE id=?');
    $cStmt->execute([$courseId]);
    $course = $cStmt->fetch();
    if (!$course) { header('Location: ' . BASE_URL . '/admin/courses.php'); exit; }
    $sStmt = $pdo->prepare('SELECT * FROM sections WHERE course_id=? ORDER BY sort_order');
    $sStmt->execute([$courseId]);
    $sections = $sStmt->fetchAll();
}

// Acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_evaluation') {
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $activeFrom = $_POST['active_from'] ?: null;
        $activeUntil = $_POST['active_until'] ?: null;
        $stmt = $pdo->prepare('INSERT INTO evaluations (course_id, section_id, title, description, max_attempts, passing_score, sort_order, is_active, active_from, active_until) VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?, ?)');
        $stmt->execute([$courseId, (int)$_POST['section_id'] ?: null, trim($_POST['title']), $_POST['description'] ?? '', (int)$_POST['passing_score'], $_POST['sort_order'] ?? 0, $isActive, $activeFrom, $activeUntil]);
        $msg = 'Evaluación creada.'; $msgType = 'green';
    } elseif ($_POST['action'] === 'update_evaluation') {
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $activeFrom = $_POST['active_from'] ?: null;
        $activeUntil = $_POST['active_until'] ?: null;
        $pdo->prepare('UPDATE evaluations SET section_id=?, title=?, description=?, passing_score=?, sort_order=?, is_active=?, active_from=?, active_until=? WHERE id=?')
            ->execute([(int)$_POST['section_id'] ?: null, trim($_POST['title']), $_POST['description'] ?? '', (int)$_POST['passing_score'], $_POST['sort_order'] ?? 0, $isActive, $activeFrom, $activeUntil, (int)$_POST['id']]);
        $msg = 'Evaluación actualizada.'; $msgType = 'blue';
    } elseif ($_POST['action'] === 'delete_evaluation') {
        $pdo->prepare('DELETE FROM evaluations WHERE id=?')->execute([(int)$_POST['id']]);
        $msg = 'Evaluación eliminada.'; $msgType = 'red';
    } elseif ($_POST['action'] === 'create_question') {
        $stmt = $pdo->prepare('INSERT INTO questions (evaluation_id, text, type, points, sort_order) VALUES (?, ?, "multiple_choice", ?, ?)');
        $stmt->execute([(int)$_POST['evaluation_id'], trim($_POST['text']), (int)$_POST['points'], $_POST['sort_order'] ?? 0]);
        $qId = (int) $pdo->lastInsertId();
        $answerTexts = $_POST['answer_text'] ?? [];
        $correctIdx = (int)($_POST['answer_correct'] ?? 0);
        foreach ($answerTexts as $i => $text) {
            if (!trim($text)) continue;
            $pdo->prepare('INSERT INTO answers (question_id, text, is_correct, sort_order) VALUES (?, ?, ?, ?)')
                ->execute([$qId, trim($text), $i === $correctIdx ? 1 : 0, $i]);
        }
        $msg = 'Pregunta creada.'; $msgType = 'green';
    } elseif ($_POST['action'] === 'update_question') {
        $qId = (int)$_POST['q_id'];
        $pdo->prepare('UPDATE questions SET text=?, points=?, sort_order=? WHERE id=?')->execute([trim($_POST['text']), (int)$_POST['points'], $_POST['sort_order'] ?? 0, $qId]);
        $pdo->prepare('DELETE FROM answers WHERE question_id=?')->execute([$qId]);
        $answerTexts = $_POST['answer_text'] ?? [];
        $correctIdx = (int)($_POST['answer_correct'] ?? 0);
        foreach ($answerTexts as $i => $text) {
            if (!trim($text)) continue;
            $pdo->prepare('INSERT INTO answers (question_id, text, is_correct, sort_order) VALUES (?, ?, ?, ?)')
                ->execute([$qId, trim($text), $i === $correctIdx ? 1 : 0, $i]);
        }
        $msg = 'Pregunta actualizada.'; $msgType = 'blue';
    } elseif ($_POST['action'] === 'delete_question') {
        $pdo->prepare('DELETE FROM questions WHERE id=?')->execute([(int)$_POST['q_id']]);
        $msg = 'Pregunta eliminada.'; $msgType = 'red';
    } elseif ($_POST['action'] === 'save_feedback') {
        $attemptId = (int)$_POST['attempt_id'];
        $feedback = trim($_POST['feedback'] ?? '');
        $pdo->prepare('UPDATE evaluation_attempts SET feedback=? WHERE id=?')->execute([$feedback ?: null, $attemptId]);
        $msg = 'Retroalimentación guardada.'; $msgType = 'green';
    }
}

// Evaluaciones por curso
$evaluations = [];
$allQ = []; $allA = []; $attemptsByEval = [];
if ($courseId) {
    $evalsStmt = $pdo->prepare("SELECT e.*, s.title as section_title FROM evaluations e LEFT JOIN sections s ON e.section_id=s.id WHERE e.course_id=? ORDER BY e.sort_order");
    $evalsStmt->execute([$courseId]);
    $evaluations = $evalsStmt->fetchAll();

    if ($evaluations) {
        $evalIds = implode(',', array_map(fn($e) => (int)$e['id'], $evaluations));
        $qStmt = $pdo->prepare("SELECT * FROM questions WHERE evaluation_id IN ($evalIds) ORDER BY sort_order");
        $qStmt->execute();
        foreach ($qStmt->fetchAll() as $q) $allQ[$q['evaluation_id']][] = $q;
        $aStmt = $pdo->prepare("SELECT * FROM answers WHERE question_id IN (SELECT id FROM questions WHERE evaluation_id IN ($evalIds)) ORDER BY sort_order");
        $aStmt->execute();
        foreach ($aStmt->fetchAll() as $a) $allA[$a['question_id']][] = $a;
        $attStmt = $pdo->prepare("SELECT ea.*, u.first_name, u.last_name, u.email FROM evaluation_attempts ea JOIN users u ON ea.user_id=u.id WHERE ea.evaluation_id IN ($evalIds) ORDER BY ea.submitted_at DESC LIMIT 100");
        $attStmt->execute();
        foreach ($attStmt->fetchAll() as $att) $attemptsByEval[$att['evaluation_id']][] = $att;
    }
}

$pageTitle = 'Admin — Evaluaciones' . ($course ? ' · ' . htmlspecialchars($course['title']) : '');
$currentPage = 'evaluaciones';
require __DIR__ . '/../includes/header.php';
?>

<div class="flex items-center justify-between mb-6">
  <div>
    <?php if ($course): ?>
      <a href="<?= BASE_URL ?>/admin/courses.php" class="text-sm text-selcap-600 font-medium hover:underline">← Cursos</a>
      <h1 class="text-2xl font-extrabold text-gray-900 mt-1">Evaluaciones · <?= htmlspecialchars($course['title']) ?></h1>
    <?php else: ?>
      <h1 class="text-2xl font-extrabold text-gray-900">Evaluaciones</h1>
      <p class="text-sm text-gray-400">Seleccioná un curso desde <a href="<?= BASE_URL ?>/admin/courses.php" class="text-selcap-600 hover:underline">Cursos</a></p>
    <?php endif; ?>
  </div>
  <div class="flex items-center gap-2 text-sm flex-wrap">
    <a href="<?= BASE_URL ?>/admin/courses.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-xl font-semibold transition-colors">Cursos</a>
    <a href="<?= BASE_URL ?>/admin/alumnos.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-xl font-semibold transition-colors">Alumnos</a>
    <a href="<?= BASE_URL ?>/admin/asignar.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-xl font-semibold transition-colors">Asignar</a>
    <a href="<?= BASE_URL ?>/admin/reportes.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-xl font-semibold transition-colors">Reportes</a>
  </div>
</div>

<?php if ($msg): ?>
  <div class="bg-<?= $msgType ?>-50 border border-<?= $msgType ?>-200 text-<?= $msgType ?>-700 px-4 py-3 rounded-xl text-sm mb-4"><?= $msg ?></div>
<?php endif; ?>

<?php if ($course): ?>
<!-- Crear -->
<details class="bg-white rounded-2xl shadow-sm border border-gray-200 mb-6 group">
  <summary class="p-5 cursor-pointer list-none flex items-center justify-between select-none">
    <h2 class="font-bold text-gray-800">+ Nueva evaluación</h2>
    <svg class="w-5 h-5 text-gray-400 group-open:rotate-45 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
  </summary>
  <div class="px-5 pb-5">
  <form method="POST" class="space-y-3">
    <input type="hidden" name="action" value="create_evaluation">
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
      <select name="section_id" class="px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500">
        <option value="">Sin sección (global del curso)</option>
        <?php foreach ($sections as $s): ?>
          <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['title']) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="text" name="title" placeholder="Título" required class="sm:col-span-2 px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500">
    </div>
    <div class="grid grid-cols-2 gap-3">
      <div><label class="text-xs text-gray-400 block mb-1">% Aprobación</label><input type="number" name="passing_score" value="80" min="0" max="100" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500"></div>
      <input type="number" name="sort_order" placeholder="Orden" value="0" class="px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500">
    </div>
    <textarea name="description" placeholder="Descripción" rows="2" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 wysiwyg-sm"></textarea>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <div>
        <label class="text-xs text-gray-400 block mb-1">📅 Inicio (opcional)</label>
        <input type="datetime-local" name="active_from" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm">
      </div>
      <div>
        <label class="text-xs text-gray-400 block mb-1">📅 Fin (opcional)</label>
        <input type="datetime-local" name="active_until" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm">
      </div>
    </div>
    <div class="flex items-center justify-between">
      <label class="flex items-center gap-3 cursor-pointer select-none">
        <span class="text-sm text-gray-700 font-medium">🔒 ¿Activa?</span>
        <div class="relative">
          <input type="checkbox" name="is_active" value="1" class="sr-only peer">
          <div class="w-10 h-5 bg-gray-300 rounded-full peer-checked:bg-green-500 transition-colors"></div>
          <div class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow peer-checked:translate-x-5 transition-transform"></div>
        </div>
        <span class="text-xs text-gray-400 peer-checked:hidden">Apagada</span>
        <span class="text-xs text-green-600 hidden peer-checked:inline font-semibold">Encendida</span>
      </label>
    </div>
    <button type="submit" class="bg-selcap-600 hover:bg-selcap-700 text-white font-semibold px-5 py-2.5 rounded-xl transition-colors text-sm">Crear evaluación</button>
  </form>
  </div>
</details>

<!-- Lista -->
<?php foreach ($evaluations as $ev): $questions = $allQ[$ev['id']] ?? []; $attempts = $attemptsByEval[$ev['id']] ?? []; ?>
<div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 mb-4">
  <form method="POST" class="space-y-3 mb-4">
    <input type="hidden" name="action" value="update_evaluation"><input type="hidden" name="id" value="<?= $ev['id'] ?>">
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
      <select name="section_id" class="px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm">
        <option value="">Sin sección</option>
        <?php foreach ($sections as $s): ?><option value="<?= $s['id'] ?>" <?= $s['id']==$ev['section_id']?'selected':'' ?>><?= htmlspecialchars($s['title']) ?></option><?php endforeach; ?>
      </select>
      <input type="text" name="title" value="<?= htmlspecialchars($ev['title']) ?>" class="sm:col-span-2 px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 font-medium">
    </div>
    <div class="grid grid-cols-2 gap-3">
      <div><label class="text-xs text-gray-400 block mb-1">% Aprobación</label><input type="number" name="passing_score" value="<?= $ev['passing_score']??80 ?>" min="0" max="100" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm"></div>
      <input type="number" name="sort_order" value="<?= $ev['sort_order'] ?>" class="px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm">
    </div>
    <textarea name="description" rows="2" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm wysiwyg-sm"><?= htmlspecialchars($ev['description']??'') ?></textarea>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <div>
        <label class="text-xs text-gray-400 block mb-1">📅 Inicio (opcional)</label>
        <input type="datetime-local" name="active_from" value="<?= $ev['active_from'] ? date('Y-m-d\TH:i', strtotime($ev['active_from'])) : '' ?>" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm">
      </div>
      <div>
        <label class="text-xs text-gray-400 block mb-1">📅 Fin (opcional)</label>
        <input type="datetime-local" name="active_until" value="<?= $ev['active_until'] ? date('Y-m-d\TH:i', strtotime($ev['active_until'])) : '' ?>" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm">
      </div>
    </div>
    <div class="flex items-center gap-4 flex-wrap">
      <label class="flex items-center gap-3 cursor-pointer select-none">
        <span class="text-sm text-gray-700 font-medium">🔒 ¿Activa?</span>
        <div class="relative">
          <input type="checkbox" name="is_active" value="1" <?= ($ev['is_active'] ?? 0) ? 'checked' : '' ?> class="sr-only peer">
          <div class="w-10 h-5 bg-gray-300 rounded-full peer-checked:bg-green-500 transition-colors"></div>
          <div class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow peer-checked:translate-x-5 transition-transform"></div>
        </div>
        <span class="text-xs text-gray-400 peer-checked:hidden">Apagada</span>
        <span class="text-xs text-green-600 hidden peer-checked:inline font-semibold">Encendida</span>
      </label>
      <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2 rounded-xl transition-colors text-sm">Guardar</button>
    </div>
  </form>
  <form method="POST" onsubmit="return confirm('¿Eliminar?')" class="inline mt-2"><input type="hidden" name="action" value="delete_evaluation"><input type="hidden" name="id" value="<?= $ev['id'] ?>"><button type="submit" class="bg-red-100 hover:bg-red-200 text-red-700 font-semibold px-4 py-2 rounded-xl transition-colors text-sm">Eliminar</button></form>

  <!-- Preguntas -->
  <div class="border-t border-gray-100 pt-4 mt-4">
    <h3 class="font-bold text-gray-700 text-sm mb-3">Preguntas (<?= count($questions) ?>)</h3>
    <?php foreach ($questions as $q): $answers = $allA[$q['id']] ?? []; ?>
      <div class="bg-gray-50 rounded-xl p-4 mb-3">
        <form method="POST" class="space-y-3">
          <input type="hidden" name="action" value="update_question"><input type="hidden" name="q_id" value="<?= $q['id'] ?>">
          <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <input type="text" name="text" value="<?= htmlspecialchars($q['text']) ?>" required class="sm:col-span-3 px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm">
            <input type="number" name="points" value="<?= $q['points'] ?>" class="px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm">
          </div>
          <div class="space-y-2">
            <?php foreach ($answers as $i => $a): ?>
              <div class="flex items-center gap-3 py-1.5 px-2 rounded-lg hover:bg-white transition-colors cursor-pointer" onclick="this.querySelector('input[type=radio]').click()">
                <input type="radio" name="answer_correct" value="<?= $i ?>" <?= $a['is_correct']?'checked':'' ?> class="w-5 h-5 accent-blue-600 cursor-pointer shrink-0">
                <input type="text" name="answer_text[<?= $i ?>]" value="<?= htmlspecialchars($a['text']) ?>" class="flex-1 px-3 py-2 bg-white border border-gray-200 rounded-lg text-gray-900 focus:outline-none focus:ring-1 focus:ring-selcap-500 text-sm">
                <?php if ($a['is_correct']): ?><span class="text-xs text-green-600 font-bold shrink-0 ml-1">✓ Correcta</span><?php endif; ?>
              </div>
            <?php endforeach; ?>
            <?php $newIdx=count($answers); ?>
            <div class="flex items-center gap-3 py-1.5 px-2 rounded-lg hover:bg-white transition-colors cursor-pointer" onclick="this.querySelector('input[type=radio]').click()">
              <input type="radio" name="answer_correct" value="<?= $newIdx ?>" class="w-5 h-5 accent-blue-600 cursor-pointer shrink-0">
              <input type="text" name="answer_text[<?= $newIdx ?>]" placeholder="Nueva respuesta..." class="flex-1 px-3 py-2 bg-white border border-dashed border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-1 focus:ring-selcap-500 text-sm">
            </div>
          </div>
          <div class="flex items-center gap-2">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-3 py-2 rounded-lg transition-colors text-xs">Guardar</button>
          </div>
        </form>
        <form method="POST" onsubmit="return confirm('¿Eliminar?')" class="inline mt-1"><input type="hidden" name="action" value="delete_question"><input type="hidden" name="q_id" value="<?= $q['id'] ?>"><button type="submit" class="text-red-500 hover:text-red-700 text-xs font-semibold">Eliminar</button></form>
      </div>
    <?php endforeach; ?>
    <details class="mt-3"><summary class="text-selcap-600 font-semibold text-sm cursor-pointer hover:underline">+ Agregar pregunta</summary>
      <form method="POST" class="mt-3 space-y-3">
        <input type="hidden" name="action" value="create_question"><input type="hidden" name="evaluation_id" value="<?= $ev['id'] ?>">
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
          <input type="text" name="text" placeholder="Pregunta" required class="sm:col-span-3 px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm">
          <input type="number" name="points" value="1" class="px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm">
        </div>
        <div class="space-y-2">
          <?php for($i=0;$i<4;$i++): ?>
            <div class="flex items-center gap-3 py-1.5 px-2 rounded-lg hover:bg-gray-50 transition-colors cursor-pointer" onclick="this.querySelector('input[type=radio]').click()">
              <input type="radio" name="answer_correct" value="<?= $i ?>" <?= $i===0?'checked':'' ?> class="w-5 h-5 accent-blue-600 cursor-pointer shrink-0">
              <input type="text" name="answer_text[<?= $i ?>]" placeholder="Respuesta <?= $i+1 ?><?= $i===0?' (correcta por defecto)':'' ?>" class="flex-1 px-3 py-2 bg-white border border-gray-200 rounded-lg text-gray-900 focus:outline-none focus:ring-1 focus:ring-selcap-500 text-sm">
            </div>
          <?php endfor; ?>
        </div>
        <button type="submit" class="bg-selcap-600 hover:bg-selcap-700 text-white font-semibold px-4 py-2.5 rounded-xl transition-colors text-sm">Crear pregunta</button>
      </form>
    </details>
  </div>

  <!-- Feedback -->
  <?php if ($attempts): ?>
  <div class="border-t border-gray-100 pt-4 mt-4">
    <h3 class="font-bold text-gray-700 text-sm mb-3">Intentos (<?= count($attempts) ?>)</h3>
    <?php foreach ($attempts as $att): ?>
      <div class="bg-gray-50 rounded-xl p-4 mb-2">
        <div class="flex items-start justify-between mb-2">
          <div><p class="text-sm font-semibold"><?= htmlspecialchars($att['first_name'].' '.$att['last_name']) ?></p><p class="text-xs text-gray-400"><?= $att['submitted_at'] ?></p></div>
          <div class="text-right"><p class="text-lg font-extrabold <?= $att['passed']?'text-green-600':'text-red-500' ?>"><?= round($att['score']) ?>%</p></div>
        </div>
        <form method="POST"><input type="hidden" name="action" value="save_feedback"><input type="hidden" name="attempt_id" value="<?= $att['id'] ?>">
          <textarea name="feedback" placeholder="Retroalimentación..." rows="2" class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-gray-900 focus:outline-none focus:ring-1 focus:ring-selcap-500 text-sm"><?= htmlspecialchars($att['feedback']??'') ?></textarea>
          <button type="submit" class="mt-2 bg-selcap-600 hover:bg-selcap-700 text-white font-semibold px-3 py-1.5 rounded-lg transition-colors text-xs">Guardar feedback</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php endforeach; ?>

<?php if (empty($evaluations)): ?>
  <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-12 text-center text-gray-400">No hay evaluaciones en este curso.</div>
<?php endif; ?>

<?php else: ?>
  <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-12 text-center text-gray-400">
    Seleccioná un curso desde <a href="<?= BASE_URL ?>/admin/courses.php" class="text-selcap-600 font-semibold hover:underline">Cursos</a> para gestionar sus evaluaciones.
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
