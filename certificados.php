<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/n8n.php';
requireLogin();
$userId = $_SESSION['user_id'];
$pdo = db();

// ── Reintentar generación de certificado ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['retry_attempt_id'])) {
    $retryId = (int)$_POST['retry_attempt_id'];
    // Verificar que el intento pertenece al usuario
    $checkStmt = $pdo->prepare('SELECT id FROM evaluation_attempts WHERE id = ? AND user_id = ? AND passed = 1');
    $checkStmt->execute([$retryId, $userId]);
    if ($checkStmt->fetch()) {
        sendCertificateToN8N($retryId);
    }
    header('Location: ' . BASE_URL . '/certificados.php');
    exit;
}

$certsStmt = $pdo->prepare('SELECT ea.*, e.title as eval_title, c.title as course_title, c.id as course_id,
       cert.status as cert_status, cert.certificate_url, cert.folio, cert.error_message
    FROM evaluation_attempts ea
    JOIN evaluations e ON ea.evaluation_id = e.id
    JOIN courses c ON e.course_id = c.id
    LEFT JOIN certificates cert ON cert.attempt_id = ea.id
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
          <?php if (!empty($cert['folio'])): ?>
            <p class="text-xs text-gray-400">Folio: N° <?= htmlspecialchars($cert['folio']) ?></p>
          <?php endif; ?>
        </div>
        <div class="flex items-center gap-2 shrink-0 flex-wrap justify-end">
          <?php
            $status = $cert['cert_status'] ?? null;
            if ($status === 'ready' && !empty($cert['certificate_url'])):
          ?>
            <a href="<?= BASE_URL ?>/<?= htmlspecialchars($cert['certificate_url']) ?>" target="_blank"
               class="bg-green-100 hover:bg-green-200 text-green-800 font-semibold px-3 py-1.5 rounded-lg transition-colors text-sm">
              ⬇ Descargar PDF
            </a>
          <?php elseif ($status === 'processing' || $status === 'pending'): ?>
            <span class="bg-blue-50 text-blue-600 font-semibold px-3 py-1.5 rounded-lg text-sm inline-flex items-center gap-1">
              <svg class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
              Generando...
            </span>
          <?php elseif ($status === 'error'): ?>
            <form method="POST" class="inline">
              <input type="hidden" name="retry_attempt_id" value="<?= $cert['id'] ?>">
              <button type="submit" class="bg-red-100 hover:bg-red-200 text-red-700 font-semibold px-3 py-1.5 rounded-lg transition-colors text-sm"
                      title="<?= htmlspecialchars($cert['error_message'] ?? 'Error desconocido') ?>">
                🔄 Reintentar
              </button>
            </form>
          <?php else: ?>
            <!-- Sin registro de certificado PDF — generar -->
            <form method="POST" class="inline">
              <input type="hidden" name="retry_attempt_id" value="<?= $cert['id'] ?>">
              <button type="submit" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold px-3 py-1.5 rounded-lg transition-colors text-sm">
                📄 Generar PDF
              </button>
            </form>
          <?php endif; ?>
          <a href="<?= BASE_URL ?>/certificado.php?id=<?= $cert['id'] ?>" class="bg-amber-100 hover:bg-amber-200 text-amber-800 font-semibold px-3 py-1.5 rounded-lg transition-colors text-sm">🎓 Ver certificado</a>
          <a href="<?= BASE_URL ?>/curso.php?id=<?= $cert['course_id'] ?>" class="text-selcap-600 text-sm font-semibold hover:underline">Ver curso →</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>

