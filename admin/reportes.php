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
    <a href="<?= BASE_URL ?>/admin/" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-xl font-semibold transition-colors">Secciones</a>
    <a href="<?= BASE_URL ?>/admin/lessons.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-xl font-semibold transition-colors">Lecciones</a>
    <a href="<?= BASE_URL ?>/admin/evaluations.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-xl font-semibold transition-colors">Evaluaciones</a>
    <a href="<?= BASE_URL ?>/admin/alumnos.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-xl font-semibold transition-colors">Alumnos</a>
    <a href="<?= BASE_URL ?>/admin/reportes.php" class="bg-selcap-600 text-white px-4 py-2 rounded-xl font-semibold">Reportes</a>
  </div>
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
