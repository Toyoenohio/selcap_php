<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$pdo = db();

// ── Filtros ──
$filterUser = $_GET['user_id'] ?? '';
$filterAction = $_GET['action'] ?? '';
$filterDays = $_GET['days'] ?? '30';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

// Construir WHERE
$where = ['1=1'];
$params = [];

if ($filterUser) {
    $where[] = 'a.user_id = ?';
    $params[] = (int)$filterUser;
}
if ($filterAction) {
    $where[] = 'a.action = ?';
    $params[] = $filterAction;
}
if ($filterDays) {
    $where[] = 'a.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)';
    $params[] = (int)$filterDays;
}

$whereStr = implode(' AND ', $where);

// Total
$countStmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM audit_log a WHERE $whereStr");
$countStmt->execute($params);
$total = (int)$countStmt->fetch()['cnt'];
$totalPages = max(1, ceil($total / $perPage));

// Datos
$stmt = $pdo->prepare("SELECT a.*, 
    COALESCE(u.first_name, 'Sistema') as user_first_name,
    COALESCE(u.last_name, '') as user_last_name,
    u.email as user_email
    FROM audit_log a 
    LEFT JOIN users u ON a.user_id = u.id
    WHERE $whereStr 
    ORDER BY a.created_at DESC 
    LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Usuarios para filtro
$usersStmt = $pdo->prepare("SELECT id, first_name, last_name, email FROM users WHERE role = 'student' ORDER BY first_name");
$usersStmt->execute();
$users = $usersStmt->fetchAll();

// Acciones únicas para filtro
$actionsStmt = $pdo->prepare("SELECT DISTINCT action FROM audit_log ORDER BY action");
$actionsStmt->execute();
$actions = $actionsStmt->fetchAll();

$pageTitle = 'Admin — Reportes / Auditoría';
$currentPage = 'reportes';
require __DIR__ . '/../includes/header.php';
?>

<div class="flex items-center justify-between mb-6">
  <h1 class="text-2xl font-extrabold text-gray-900">Reportes / Auditoría</h1>
  <div class="flex items-center gap-2 text-sm flex-wrap">
    <a href="<?= BASE_URL ?>/admin/courses.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-xl font-semibold transition-colors">Cursos</a>
    <a href="<?= BASE_URL ?>/admin/" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-xl font-semibold transition-colors">Secciones</a>
    <a href="<?= BASE_URL ?>/admin/lessons.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-xl font-semibold transition-colors">Lecciones</a>
    <a href="<?= BASE_URL ?>/admin/evaluations.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-xl font-semibold transition-colors">Evaluaciones</a>
    <a href="<?= BASE_URL ?>/admin/alumnos.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-xl font-semibold transition-colors">Alumnos</a>
    <a href="<?= BASE_URL ?>/admin/reportes.php" class="bg-selcap-600 text-white px-4 py-2 rounded-xl font-semibold">Reportes</a>
  </div>
</div>

<!-- Encuestas de satisfacción -->
<?php
$survSummary = $pdo->query('
    SELECT c.id, c.title, COUNT(s.id) as total,
           ROUND(AVG(s.eval_general), 2) as avg_general,
           ROUND(AVG(s.eval_tecnologia), 2) as avg_tecnologia,
           ROUND(AVG(s.horario_adecuado), 2) as avg_horario,
           ROUND(AVG(s.proceso_inscripcion), 2) as avg_inscripcion,
           ROUND(AVG(s.efectividad_relator), 2) as avg_relator,
           SUM(s.autoriza_publicar = 1) as autorizan,
           COUNT(NULLIF(s.experiencia, "")) as con_experiencia
    FROM courses c
    LEFT JOIN course_surveys s ON s.course_id = c.id
    GROUP BY c.id, c.title
    HAVING total > 0
    ORDER BY total DESC
')->fetchAll();

$survRecent = $pdo->query('
    SELECT s.*, u.first_name, u.last_name, u.email, c.title as course_title
    FROM course_surveys s
    JOIN users u ON s.user_id = u.id
    JOIN courses c ON s.course_id = c.id
    ORDER BY s.created_at DESC
    LIMIT 10
')->fetchAll();
?>
<div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 mb-6">
  <div class="flex items-center justify-between mb-4">
    <h2 class="text-lg font-bold text-gray-900">📋 Encuestas de satisfacción</h2>
    <span class="text-xs text-gray-400">Escala 1-4 · 11 preguntas oficiales</span>
  </div>

  <?php if (empty($survSummary)): ?>
    <p class="text-gray-400 text-sm py-4">Aún no hay encuestas respondidas.</p>
  <?php else: ?>
    <div class="overflow-x-auto mb-4">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-100">
          <tr>
            <th class="text-left px-3 py-2.5 font-semibold text-gray-600">Curso</th>
            <th class="text-center px-3 py-2.5 font-semibold text-gray-600">Respuestas</th>
            <th class="text-center px-3 py-2.5 font-semibold text-gray-600">Gral.</th>
            <th class="text-center px-3 py-2.5 font-semibold text-gray-600">Tecnología</th>
            <th class="text-center px-3 py-2.5 font-semibold text-gray-600">Horario</th>
            <th class="text-center px-3 py-2.5 font-semibold text-gray-600">Inscripción</th>
            <th class="text-center px-3 py-2.5 font-semibold text-gray-600">Relator</th>
            <th class="text-center px-3 py-2.5 font-semibold text-gray-600">Autorizan</th>
            <th class="text-center px-3 py-2.5 font-semibold text-gray-600">Testimonios</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <?php foreach ($survSummary as $s): ?>
            <tr class="hover:bg-gray-50 transition-colors">
              <td class="px-3 py-3 font-medium text-gray-800"><?= htmlspecialchars($s['title']) ?></td>
              <td class="px-3 py-3 text-center text-gray-700 font-semibold"><?= (int)$s['total'] ?></td>
              <td class="px-3 py-3 text-center text-gray-700"><?= $s['avg_general'] !== null ? htmlspecialchars($s['avg_general']) : '—' ?></td>
              <td class="px-3 py-3 text-center text-gray-700"><?= $s['avg_tecnologia'] !== null ? htmlspecialchars($s['avg_tecnologia']) : '—' ?></td>
              <td class="px-3 py-3 text-center text-gray-700"><?= $s['avg_horario'] !== null ? htmlspecialchars($s['avg_horario']) : '—' ?></td>
              <td class="px-3 py-3 text-center text-gray-700"><?= $s['avg_inscripcion'] !== null ? htmlspecialchars($s['avg_inscripcion']) : '—' ?></td>
              <td class="px-3 py-3 text-center text-gray-700"><?= $s['avg_relator'] !== null ? htmlspecialchars($s['avg_relator']) : '—' ?></td>
              <td class="px-3 py-3 text-center text-gray-700"><?= (int)$s['autorizan'] ?>/<?= (int)$s['total'] ?></td>
              <td class="px-3 py-3 text-center text-gray-700"><?= (int)$s['con_experiencia'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <h3 class="text-sm font-bold text-gray-700 mb-2">Últimas respuestas</h3>
    <div class="space-y-2">
      <?php foreach ($survRecent as $r): ?>
        <div class="bg-gray-50 rounded-xl p-3 text-sm">
          <div class="flex items-center justify-between gap-2">
            <span class="font-medium text-gray-800"><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></span>
            <span class="text-xs text-gray-400"><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></span>
          </div>
          <p class="text-xs text-gray-500">
            <?= htmlspecialchars($r['course_title']) ?> ·
            Gral <?= (int)$r['eval_general'] ?>/4 ·
            Tec <?= $r['eval_tecnologia'] !== null ? (int)$r['eval_tecnologia'] . '/4' : '—' ?> ·
            Horario <?= $r['horario_adecuado'] !== null ? (int)$r['horario_adecuado'] . '/4' : '—' ?> ·
            Insc <?= $r['proceso_inscripcion'] !== null ? (int)$r['proceso_inscripcion'] . '/4' : '—' ?> ·
            Relator <?= $r['efectividad_relator'] !== null ? (int)$r['efectividad_relator'] . '/4' : '—' ?>
            <?= $r['autoriza_publicar'] === null ? '' : ($r['autoriza_publicar'] ? '· ✅ Autoriza publicar' : '· 🔒 No autoriza publicar') ?>
          </p>
          <?php if (!empty($r['mejoras'])): ?>
            <p class="text-xs text-gray-600 mt-1"><strong>Mejoras:</strong> "<?= htmlspecialchars($r['mejoras']) ?>"</p>
          <?php endif; ?>
          <?php if (!empty($r['dificultades_tecnologia'])): ?>
            <p class="text-xs text-gray-600 mt-1"><strong>Dif. tecnología:</strong> "<?= htmlspecialchars($r['dificultades_tecnologia']) ?>"</p>
          <?php endif; ?>
          <?php if (!empty($r['experiencia'])): ?>
            <p class="text-xs text-gray-600 mt-1 italic">"<?= htmlspecialchars($r['experiencia']) ?>"</p>
          <?php endif; ?>
          <?php if (!empty($r['comentario_final'])): ?>
            <p class="text-xs text-gray-600 mt-1"><strong>Comentario final:</strong> "<?= htmlspecialchars($r['comentario_final']) ?>"</p>
          <?php endif; ?>
          <?php if (!empty($r['nombre_publico'])): ?>
            <p class="text-xs text-gray-500 mt-1">✍️ <?= htmlspecialchars($r['nombre_publico']) ?></p>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Filtros -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 mb-6">
  <form method="GET" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
    <div>
      <label class="text-xs text-gray-400 block mb-1">Usuario</label>
      <select name="user_id" class="w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm">
        <option value="">Todos</option>
        <?php foreach ($users as $u): ?>
          <option value="<?= $u['id'] ?>" <?= $filterUser == $u['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="text-xs text-gray-400 block mb-1">Acción</label>
      <select name="action" class="w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm">
        <option value="">Todas</option>
        <?php foreach ($actions as $a): ?>
          <option value="<?= htmlspecialchars($a['action']) ?>" <?= $filterAction === $a['action'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($a['action']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="text-xs text-gray-400 block mb-1">Últimos días</label>
      <select name="days" class="w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm">
        <?php foreach ([1, 7, 30, 90, 365] as $d): ?>
          <option value="<?= $d ?>" <?= $filterDays == $d ? 'selected' : '' ?>><?= $d ?> días</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="flex items-end">
      <button type="submit" class="w-full bg-selcap-600 hover:bg-selcap-700 text-white font-semibold px-4 py-2.5 rounded-xl transition-colors text-sm">
        Filtrar
      </button>
    </div>
  </form>
</div>

<!-- Contador -->
<p class="text-sm text-gray-500 mb-4">
  <span class="font-semibold text-gray-700"><?= $total ?></span> registros encontrados
  <?php if ($filterUser || $filterAction): ?>
    · <a href="<?= BASE_URL ?>/admin/reportes.php" class="text-selcap-600 hover:underline">Limpiar filtros</a>
  <?php endif; ?>
</p>

<!-- Tabla -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden mb-6">
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 border-b border-gray-100">
        <tr>
          <th class="text-left px-4 py-3 font-semibold text-gray-600">Fecha</th>
          <th class="text-left px-4 py-3 font-semibold text-gray-600">Usuario</th>
          <th class="text-left px-4 py-3 font-semibold text-gray-600">Acción</th>
          <th class="text-left px-4 py-3 font-semibold text-gray-600 hidden md:table-cell">Detalles</th>
          <th class="text-left px-4 py-3 font-semibold text-gray-600 hidden lg:table-cell">IP</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-50">
        <?php foreach ($logs as $log): 
          $details = json_decode($log['details'] ?? '{}', true);
        ?>
          <tr class="hover:bg-gray-50 transition-colors">
            <td class="px-4 py-3 text-gray-500 whitespace-nowrap">
              <?= date('d/m/Y H:i', strtotime($log['created_at'])) ?>
            </td>
            <td class="px-4 py-3">
              <?php if ($log['user_id']): ?>
                <span class="font-medium text-gray-800"><?= htmlspecialchars($log['user_first_name'] . ' ' . $log['user_last_name']) ?></span>
                <span class="text-xs text-gray-400 block"><?= htmlspecialchars($log['user_email'] ?? '') ?></span>
              <?php else: ?>
                <span class="text-gray-400 italic">Sistema</span>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3">
              <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold
                <?php
                $actionStyles = [
                    'login' => 'bg-blue-100 text-blue-700',
                    'student_created' => 'bg-green-100 text-green-700',
                    'student_created_webhook' => 'bg-purple-100 text-purple-700',
                    'student_reactivated_webhook' => 'bg-purple-100 text-purple-700',
                    'student_enrolled_webhook' => 'bg-indigo-100 text-indigo-700',
                    'student_updated' => 'bg-yellow-100 text-yellow-700',
                    'student_deactivated' => 'bg-red-100 text-red-700',
                    'evaluation_submitted' => 'bg-indigo-100 text-indigo-700',
                ];
                $style = $actionStyles[$log['action']] ?? 'bg-gray-100 text-gray-700';
                echo $style;
                ?>
              "><?= htmlspecialchars($log['action']) ?></span>
            </td>
            <td class="px-4 py-3 text-gray-500 text-xs hidden md:table-cell max-w-xs truncate">
              <?php
              $detailStr = '';
              foreach ($details as $k => $v) {
                  if (is_scalar($v)) $detailStr .= "$k: $v; ";
              }
              echo htmlspecialchars(rtrim($detailStr, '; '));
              ?>
            </td>
            <td class="px-4 py-3 text-gray-400 text-xs hidden lg:table-cell font-mono">
              <?= htmlspecialchars($log['ip_address'] ?? '') ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if (empty($logs)): ?>
    <p class="text-center text-gray-400 py-12">No se encontraron registros de auditoría.</p>
  <?php endif; ?>
</div>

<!-- Paginación -->
<?php if ($totalPages > 1): ?>
<div class="flex items-center justify-center gap-2">
  <?php 
  $qs = $_GET;
  unset($qs['page']);
  $baseQs = http_build_query($qs);
  $baseUrl = BASE_URL . '/admin/reportes.php' . ($baseQs ? '?' . $baseQs . '&' : '?');
  ?>
  <?php if ($page > 1): ?>
    <a href="<?= $baseUrl ?>page=<?= $page - 1 ?>" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm font-medium text-gray-700 transition-colors">← Anterior</a>
  <?php endif; ?>
  <span class="text-sm text-gray-500">Página <?= $page ?> de <?= $totalPages ?></span>
  <?php if ($page < $totalPages): ?>
    <a href="<?= $baseUrl ?>page=<?= $page + 1 ?>" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm font-medium text-gray-700 transition-colors">Siguiente →</a>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
