<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/n8n.php';
requireLogin();

$evalId = (int) ($_GET['id'] ?? 0);
$userId = $_SESSION['user_id'];
$pdo = db();

$stmt = $pdo->prepare('SELECT e.* FROM evaluations e WHERE e.id = ?');
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

// Evaluación inactiva o fuera de ventana
$now = date('Y-m-d H:i:s');
$beforeWindow = !empty($evaluation['active_from']) && $now < $evaluation['active_from'];
$afterWindow  = !empty($evaluation['active_until']) && $now > $evaluation['active_until'];
$outsideWindow = $beforeWindow || $afterWindow;

if (empty($evaluation['is_active']) || $outsideWindow) {
    $pageTitle = 'Evaluación no disponible';
    require __DIR__ . '/includes/header.php';

    if ($outsideWindow && !empty($evaluation['active_from']) && !empty($evaluation['active_until'])) {
        $fromStr = date('d/m/Y H:i', strtotime($evaluation['active_from']));
        $untilStr = date('d/m/Y H:i', strtotime($evaluation['active_until']));
        if ($beforeWindow) {
            $msg = "Esta evaluación estará disponible a partir del <strong>{$fromStr}</strong>.";
        } else {
            $msg = "Esta evaluación finalizó el <strong>{$untilStr}</strong>.";
        }
    } elseif ($beforeWindow && !empty($evaluation['active_from'])) {
        $fromStr = date('d/m/Y H:i', strtotime($evaluation['active_from']));
        $msg = "Esta evaluación estará disponible a partir del <strong>{$fromStr}</strong>.";
    } elseif ($afterWindow && !empty($evaluation['active_until'])) {
        $untilStr = date('d/m/Y H:i', strtotime($evaluation['active_until']));
        $msg = "Esta evaluación finalizó el <strong>{$untilStr}</strong>.";
    } else {
        $msg = "Esta evaluación no está activa en este momento. El instructor la habilitará cuando corresponda.";
    }
    ?>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-12 text-center">
        <div class="text-6xl mb-4">🔒</div>
        <h2 class="text-xl font-extrabold text-gray-800 mb-2">Evaluación no disponible</h2>
        <p class="text-gray-500 mb-4"><?= $msg ?></p>
        <a href="<?= BASE_URL ?>/dashboard.php" class="bg-selcap-600 hover:bg-selcap-700 text-white font-semibold px-5 py-2.5 rounded-xl transition-colors text-sm inline-block">
            Volver al curso
        </a>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

// ── Un solo intento: ¿ya lo hizo? ──
$lastStmt = $pdo->prepare('SELECT * FROM evaluation_attempts WHERE user_id = ? AND evaluation_id = ? ORDER BY attempt_number DESC LIMIT 1');
$lastStmt->execute([$userId, $evalId]);
$lastAttempt = $lastStmt->fetch();

$alreadyTaken = (bool) $lastAttempt;
$passed = $alreadyTaken && $lastAttempt['passed'];

// ── Encuesta post-examen: ¿ya respondió? (course_id de la evaluación) ──
$surveyCourseId = (int)($evaluation['course_id'] ?? 0);
if ($surveyCourseId > 0) {
    $survCheck = $pdo->prepare('SELECT * FROM course_surveys WHERE user_id = ? AND course_id = ? LIMIT 1');
    $survCheck->execute([$userId, $surveyCourseId]);
    $existingSurvey = $survCheck->fetch();
}

// ── Guardar encuesta post-examen (procesar ANTES del bloque alreadyTaken) ──
$surveySaved = false;
$surveyError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_survey']) && $surveyCourseId > 0) {
    // Escala 1-4; comentarios opcionales
    $evalGeneral   = (int)($_POST['eval_general'] ?? 0);
    $evalContenido = (int)($_POST['eval_contenido'] ?? 0);
    $evalInstructor = (int)($_POST['eval_instructor'] ?? 0);
    $recomendaria  = isset($_POST['recomendaria']) ? 1 : 0;
    $comentarios   = trim($_POST['comentarios'] ?? '');

    $valid = $evalGeneral >= 1 && $evalGeneral <= 4;

    if (!$valid) {
        $surveyError = 'Por favor responde la escala de evaluación general (1-4).';
    } else {
        try {
            $survStmt = $pdo->prepare(
                'INSERT INTO course_surveys (user_id, course_id, eval_general, eval_contenido, eval_instructor, recomendaria, comentarios)
                 VALUES (?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    eval_general = VALUES(eval_general),
                    eval_contenido = VALUES(eval_contenido),
                    eval_instructor = VALUES(eval_instructor),
                    recomendaria = VALUES(recomendaria),
                    comentarios = VALUES(comentarios)'
            );
            $survStmt->execute([
                $userId,
                $surveyCourseId,
                $evalGeneral,
                $evalContenido >= 1 && $evalContenido <= 4 ? $evalContenido : null,
                $evalInstructor >= 1 && $evalInstructor <= 4 ? $evalInstructor : null,
                $recomendaria,
                $comentarios !== '' ? $comentarios : null,
            ]);
            $surveySaved = true;
            // Recargar encuesta recién guardada (el statement anterior ya fue consumido)
            $survReload = $pdo->prepare('SELECT * FROM course_surveys WHERE user_id = ? AND course_id = ? LIMIT 1');
            $survReload->execute([$userId, $surveyCourseId]);
            $existingSurvey = $survReload->fetch();

            // Auditoría
            $auditSurv = $pdo->prepare('INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)');
            $auditSurv->execute([$userId, 'survey_submitted', 'course', $surveyCourseId, json_encode(['eval_general' => $evalGeneral]), $_SERVER['REMOTE_ADDR'] ?? '']);
        } catch (PDOException $e) {
            $surveyError = 'No se pudo guardar la encuesta. Intenta de nuevo.';
        }
    }
}

if ($alreadyTaken) {
    $pageTitle = $passed ? 'Evaluación aprobada' : 'Evaluación completada';
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8 text-center">
        <div class="text-6xl mb-4"><?= $passed ? '🎉' : '📋' ?></div>
        <p class="text-2xl font-extrabold <?= $passed ? 'text-green-600' : 'text-gray-700' ?> mb-2">
            <?= round($lastAttempt['score']) ?>%
        </p>
        <p class="text-lg text-gray-500 mb-1">
            <?= $passed ? '¡Aprobaste esta evaluación!' : 'No alcanzaste la nota mínima.' ?>
        </p>
        <p class="text-sm text-gray-400 mb-2">
            Nota de aprobación: <?= $evaluation['passing_score'] ?>% — Obtuviste <?= round($lastAttempt['score']) ?>%
        </p>
        <?php if (!empty($lastAttempt['feedback'])): ?>
        <div class="mt-4 p-4 bg-amber-50 border border-amber-200 rounded-xl text-left">
            <p class="text-xs font-semibold text-amber-700 mb-1 uppercase tracking-wide">📝 Retroalimentación del instructor</p>
            <p class="text-sm text-gray-700"><?= nl2br(htmlspecialchars($lastAttempt['feedback'])) ?></p>
        </div>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/dashboard.php" class="bg-selcap-600 hover:bg-selcap-700 text-white font-semibold px-5 py-2.5 rounded-xl transition-colors text-sm mt-6 inline-block">
            Volver al curso
        </a>
    </div>

    <?php if ($passed && $surveyCourseId > 0 && !$existingSurvey): ?>
    <!-- Encuesta post-examen (desde certificados.php) -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 sm:p-8 mt-6" id="encuesta">
        <h2 class="text-lg font-bold text-gray-900 mb-1">📋 Encuesta de satisfacción</h2>
        <p class="text-sm text-gray-500 mb-6">Tu opinión nos ayuda a mejorar la experiencia de capacitación. Responde en una escala del 1 (muy en desacuerdo) al 4 (muy de acuerdo).</p>

        <?php if ($surveySaved): ?>
        <div class="p-4 bg-green-50 border border-green-200 rounded-xl text-green-700 text-sm font-medium">
            ✅ ¡Gracias por tu respuesta! Tu encuesta fue registrada.
        </div>
        <?php elseif ($surveyError): ?>
        <div class="p-4 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm font-medium mb-4">
            ⚠️ <?= htmlspecialchars($surveyError) ?>
        </div>
        <?php endif; ?>

        <?php if (!$surveySaved): ?>
        <form method="POST" class="space-y-6">
            <?php
            $surveyQuestions2 = [
                'eval_general'    => '¿Cómo calificas tu experiencia general con el curso?',
                'eval_contenido'  => '¿El contenido fue claro, completo y útil para tu trabajo?',
                'eval_instructor' => '¿El instructor dominó el tema y respondió tus dudas?',
            ];
            $scaleLabels2 = ['', 'Muy malo', 'Regular', 'Bueno', 'Excelente'];
            foreach ($surveyQuestions2 as $field => $label):
            ?>
            <div>
                <p class="font-semibold text-gray-800 mb-3"><?= htmlspecialchars($label) ?></p>
                <div class="grid grid-cols-4 gap-2">
                    <?php for ($i = 1; $i <= 4; $i++): ?>
                    <label class="flex flex-col items-center gap-1 px-3 py-3 bg-gray-50 hover:bg-selcap-50 rounded-xl cursor-pointer border border-gray-100 hover:border-selcap-200 transition-colors has-[:checked]:border-selcap-500 has-[:checked]:bg-selcap-50">
                        <input type="radio" name="<?= $field ?>" value="<?= $i ?>" required class="accent-selcap-600 w-4 h-4">
                        <span class="text-sm font-medium text-gray-700"><?= $i ?></span>
                        <span class="text-[10px] text-gray-400 text-center leading-tight"><?= $scaleLabels2[$i] ?></span>
                    </label>
                    <?php endfor; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <div>
                <p class="font-semibold text-gray-800 mb-2">¿Recomendarías este curso a un colega?</p>
                <div class="flex gap-4">
                    <label class="flex items-center gap-2 px-4 py-3 bg-gray-50 hover:bg-selcap-50 rounded-xl cursor-pointer border border-gray-100 has-[:checked]:border-selcap-500 has-[:checked]:bg-selcap-50 transition-colors">
                        <input type="checkbox" name="recomendaria" value="1" class="accent-selcap-600 w-4 h-4">
                        <span class="text-sm text-gray-700">Sí, lo recomendaría</span>
                    </label>
                </div>
            </div>

            <div>
                <p class="font-semibold text-gray-800 mb-2">Comentarios (opcional)</p>
                <textarea name="comentarios" rows="3" maxlength="1000"
                    class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm"
                    placeholder="¿Qué te gustó? ¿Qué mejorarías?"></textarea>
            </div>

            <button type="submit" name="submit_survey" value="1"
                    class="w-full bg-selcap-600 hover:bg-selcap-700 text-white font-semibold py-3 rounded-xl transition-colors text-base">
                Enviar encuesta
            </button>
        </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

// ── Obtener preguntas ──
$qStmt = $pdo->prepare('SELECT * FROM questions WHERE evaluation_id = ? ORDER BY sort_order');
$qStmt->execute([$evalId]);
$questions = $qStmt->fetchAll();

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
$surveySaved = false;
$surveyError = '';
$existingSurvey = null;

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

    // passing_score del propio examen (editable)
    $passingScore = (int) ($evaluation['passing_score'] ?? 80);
    $passed = $pct >= $passingScore;

    // Guardar único intento
    $insStmt = $pdo->prepare('INSERT INTO evaluation_attempts (user_id, evaluation_id, attempt_number, score, passed, answers_snapshot, submitted_at)
        VALUES (?, ?, 1, ?, ?, ?, NOW())');
    $insStmt->execute([$userId, $evalId, $pct, $passed ? 1 : 0, json_encode($responses)]);

    // Auditoría
    $auditStmt = $pdo->prepare('INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)');
    $auditStmt->execute([$userId, 'evaluation_submitted', 'evaluation', $evalId, json_encode(['score' => $pct, 'passed' => $passed, 'passing_score' => $passingScore]), $_SERVER['REMOTE_ADDR'] ?? '']);

    // ── Generar certificado PDF vía n8n si aprobó ──
    $certResult = null;
    if ($passed) {
        $newAttemptId = (int)$pdo->lastInsertId();
        $certResult = sendCertificateToN8N($newAttemptId);
    }

    $submitted = true;
    $pageTitle = 'Resultado';
} else {
    $pageTitle = htmlspecialchars($evaluation['title']);
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
    <p class="text-gray-500 mb-2">
      <?= $passed ? '¡Felicitaciones! Aprobaste la evaluación.' : 'No alcanzaste la nota mínima requerida.' ?>
    </p>
    <p class="text-xs text-gray-400 mb-4">Nota de aprobación: <?= $evaluation['passing_score'] ?? 80 ?>% — Esta evaluación solo permite 1 intento.</p>
    <?php if ($passed && $certResult): ?>
      <div class="mt-2 mb-4 p-4 <?= $certResult['success'] ? 'bg-blue-50 border-blue-200 text-blue-700' : 'bg-amber-50 border-amber-200 text-amber-700' ?> border rounded-xl text-sm">
        <?php if ($certResult['success']): ?>
          📄 Tu certificado PDF se está generando automáticamente. En unos minutos estará disponible para descarga en la sección <a href="<?= BASE_URL ?>/certificados.php" class="font-semibold underline">Certificados</a>.
        <?php else: ?>
          ⚠️ Tu certificado fue registrado pero hubo un problema generando el PDF. Podrás descargarlo más tarde desde <a href="<?= BASE_URL ?>/certificados.php" class="font-semibold underline">Certificados</a>.
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <div class="flex items-center justify-center gap-3">
      <?php if ($passed): ?>
        <a href="<?= BASE_URL ?>/certificados.php" class="bg-amber-500 hover:bg-amber-600 text-white font-semibold px-5 py-2.5 rounded-xl transition-colors text-sm">
          🎓 Ver certificados
        </a>
      <?php endif; ?>
      <a href="<?= BASE_URL ?>/dashboard.php" class="bg-selcap-600 hover:bg-selcap-700 text-white font-semibold px-5 py-2.5 rounded-xl transition-colors text-sm">
        Volver al curso
      </a>
    </div>
  </div>

  <?php if ($passed && $surveyCourseId > 0 && !$existingSurvey): ?>
  <!-- Encuesta post-examen -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 sm:p-8 mt-6">
    <h2 class="text-lg font-bold text-gray-900 mb-1">📋 Encuesta de satisfacción</h2>
    <p class="text-sm text-gray-500 mb-6">Tu opinión nos ayuda a mejorar la experiencia de capacitación. Responde en una escala del 1 (muy en desacuerdo) al 4 (muy de acuerdo).</p>

    <?php if ($surveySaved): ?>
      <div class="p-4 bg-green-50 border border-green-200 rounded-xl text-green-700 text-sm font-medium">
        ✅ ¡Gracias por tu respuesta! Tu encuesta fue registrada.
      </div>
    <?php elseif ($surveyError): ?>
      <div class="p-4 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm font-medium mb-4">
        ⚠️ <?= htmlspecialchars($surveyError) ?>
      </div>
    <?php endif; ?>

    <?php if (!$surveySaved): ?>
    <form method="POST" class="space-y-6">
      <?php
      $surveyQuestions = [
        'eval_general'    => '¿Cómo calificas tu experiencia general con el curso?',
        'eval_contenido'  => '¿El contenido fue claro, completo y útil para tu trabajo?',
        'eval_instructor' => '¿El instructor dominó el tema y respondió tus dudas?',
      ];
      $scaleLabels = ['', 'Muy malo', 'Regular', 'Bueno', 'Excelente'];
      foreach ($surveyQuestions as $field => $label):
      ?>
        <div>
          <p class="font-semibold text-gray-800 mb-3"><?= htmlspecialchars($label) ?></p>
          <div class="grid grid-cols-4 gap-2">
            <?php for ($i = 1; $i <= 4; $i++): ?>
              <label class="flex flex-col items-center gap-1 px-3 py-3 bg-gray-50 hover:bg-selcap-50 rounded-xl cursor-pointer border border-gray-100 hover:border-selcap-200 transition-colors has-[:checked]:border-selcap-500 has-[:checked]:bg-selcap-50">
                <input type="radio" name="<?= $field ?>" value="<?= $i ?>" required class="accent-selcap-600 w-4 h-4">
                <span class="text-sm font-medium text-gray-700"><?= $i ?></span>
                <span class="text-[10px] text-gray-400 text-center leading-tight"><?= $scaleLabels[$i] ?></span>
              </label>
            <?php endfor; ?>
          </div>
        </div>
      <?php endforeach; ?>

      <div>
        <p class="font-semibold text-gray-800 mb-2">¿Recomendarías este curso a un colega?</p>
        <div class="flex gap-4">
          <label class="flex items-center gap-2 px-4 py-3 bg-gray-50 hover:bg-selcap-50 rounded-xl cursor-pointer border border-gray-100 has-[:checked]:border-selcap-500 has-[:checked]:bg-selcap-50 transition-colors">
            <input type="checkbox" name="recomendaria" value="1" class="accent-selcap-600 w-4 h-4">
            <span class="text-sm text-gray-700">Sí, lo recomendaría</span>
          </label>
        </div>
      </div>

      <div>
        <p class="font-semibold text-gray-800 mb-2">Comentarios (opcional)</p>
        <textarea name="comentarios" rows="3" maxlength="1000"
          class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm"
          placeholder="¿Qué te gustó? ¿Qué mejorarías?"></textarea>
      </div>

      <button type="submit" name="submit_survey" value="1"
              class="w-full bg-selcap-600 hover:bg-selcap-700 text-white font-semibold py-3 rounded-xl transition-colors text-base">
        Enviar encuesta
      </button>
    </form>
    <?php endif; ?>
  </div>
  <?php endif; ?>

<?php else: ?>
  <!-- Formulario de evaluación -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 sm:p-8">
    <h1 class="text-xl font-bold text-gray-900 mb-1"><?= htmlspecialchars($evaluation['title']) ?></h1>
    <div class="text-gray-500 text-sm mb-4 lesson-content"><?= $evaluation['description'] ?? '' ?></div>
    <div class="flex items-center gap-2 mb-6">
      <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-amber-50 border border-amber-200 text-amber-700 text-xs font-semibold rounded-full">
        ⚠️ Un solo intento
      </span>
      <span class="text-xs text-gray-400">Nota de aprobación: <?= $evaluation['passing_score'] ?? 80 ?>%</span>
    </div>

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
