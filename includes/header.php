<?php
// ═══════════════════════════════════════════════
// Selcap AV — Header HTML
// ═══════════════════════════════════════════════
require_once __DIR__ . '/auth.php';
$user = currentUser();
$isAdmin = $user && $user['role'] === 'admin';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?? 'Selcap AV' ?> — Aula Virtual</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            selcap: { 50:'#f0fdfa', 100:'#ccfbf1', 500:'#14b8a6', 600:'#0d9488', 700:'#0f766e' }
          }
        }
      }
    }
  </script>
  <style>
    body { font-family: system-ui, -apple-system, sans-serif; }
    .lesson-content h2 { font-size:1.5rem; font-weight:700; margin:1.5rem 0 0.75rem; color:#1e293b; }
    .lesson-content h3 { font-size:1.2rem; font-weight:600; margin:1rem 0 0.5rem; color:#334155; }
    .lesson-content p { margin:0.5rem 0; line-height:1.7; color:#475569; }
    .lesson-content ul, .lesson-content ol { margin:0.5rem 0 0.5rem 1.5rem; }
    .lesson-content li { margin:0.25rem 0; line-height:1.6; }
    .lesson-content table { width:100%; margin:1rem 0; font-size:0.875rem; }
    .lesson-content th { background:#f1f5f9; padding:0.5rem 0.75rem; text-align:left; font-weight:600; }
    .lesson-content td { padding:0.5rem 0.75rem; border-top:1px solid #e2e8f0; }
  </style>
</head>
<body class="bg-gray-50 min-h-screen">

<header class="bg-white border-b border-gray-200 sticky top-0 z-50 shadow-sm">
  <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between">
    <a href="<?= BASE_URL ?>/dashboard.php" class="flex items-center gap-2.5 no-underline">
      <div class="w-9 h-9 bg-selcap-500 rounded-lg flex items-center justify-center">
        <span class="text-white font-extrabold text-sm">AV</span>
      </div>
      <span class="font-bold text-gray-800 text-lg">Selcap AV — Aquamarina 🌿</span>
    </a>
    <div class="flex items-center gap-3">
      <?php if ($isAdmin): ?>
        <a href="<?= BASE_URL ?>/admin/" class="text-xs font-medium text-white bg-selcap-600 hover:bg-selcap-700 px-3 py-1.5 rounded-lg transition-colors">Admin</a>
      <?php endif; ?>
      <div class="hidden sm:flex items-center gap-1.5 text-sm">
        <span class="w-7 h-7 rounded-full bg-selcap-100 flex items-center justify-center text-selcap-700 font-bold text-xs">
          <?= strtoupper(substr($user['first_name'] ?? 'U', 0, 1)) ?>
        </span>
        <span class="text-gray-600"><?= htmlspecialchars($user['first_name'] ?? '') ?></span>
      </div>
      <a href="<?= BASE_URL ?>/logout.php" class="text-xs text-gray-400 hover:text-red-500 transition-colors">Salir</a>
    </div>
  </div>
</header>

<main class="max-w-5xl mx-auto px-4 py-6">
