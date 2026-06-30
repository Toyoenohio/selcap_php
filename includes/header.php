<?php
require_once __DIR__ . '/auth.php';
$user = currentUser();
$isAdmin = $user && $user['role'] === 'admin';
$currentPage = $currentPage ?? '';
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

<div class="flex min-h-screen">
  <!-- Sidebar -->
  <aside class="w-64 bg-white border-r border-gray-200 hidden lg:flex flex-col shrink-0">
    <div class="p-5 border-b border-gray-100">
      <a href="<?= BASE_URL ?>/dashboard.php" class="flex items-center gap-2.5 no-underline">
        <div class="w-9 h-9 bg-selcap-500 rounded-lg flex items-center justify-center">
          <span class="text-white font-extrabold text-sm">AV</span>
        </div>
        <span class="font-bold text-gray-800 text-lg">Aula Virtual</span>
      </a>
    </div>

    <nav class="flex-1 p-4 space-y-1">
      <a href="<?= BASE_URL ?>/dashboard.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors
        <?= $currentPage === 'dashboard' ? 'bg-selcap-50 text-selcap-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?>">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
        Panel
      </a>
      <a href="<?= BASE_URL ?>/catalogo.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors
        <?= $currentPage === 'catalogo' ? 'bg-selcap-50 text-selcap-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?>">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
        Catálogo
      </a>
      <a href="<?= BASE_URL ?>/mis-cursos.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors
        <?= $currentPage === 'mis-cursos' ? 'bg-selcap-50 text-selcap-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?>">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>
        Mis Cursos
      </a>
      <a href="<?= BASE_URL ?>/certificados.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors
        <?= $currentPage === 'certificados' ? 'bg-selcap-50 text-selcap-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?>">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
        Certificados
      </a>
      <a href="<?= BASE_URL ?>/perfil.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors
        <?= $currentPage === 'perfil' ? 'bg-selcap-50 text-selcap-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?>">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
        Perfil
      </a>
      <?php if ($isAdmin): ?>
      <a href="<?= BASE_URL ?>/admin/" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors
        <?= $currentPage === 'admin' ? 'bg-selcap-50 text-selcap-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?>">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        Administración
      </a>
      <a href="<?= BASE_URL ?>/admin/alumnos.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors
        <?= $currentPage === 'alumnos' ? 'bg-selcap-50 text-selcap-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?>">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/></svg>
        Alumnos
      </a>
      <a href="<?= BASE_URL ?>/admin/reportes.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors
        <?= $currentPage === 'reportes' ? 'bg-selcap-50 text-selcap-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?>">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        Reportes
      </a>
      <?php endif; ?>
    </nav>

    <div class="p-4 border-t border-gray-100">
      <div class="flex items-center gap-3 mb-3">
        <div class="w-8 h-8 rounded-full bg-selcap-100 flex items-center justify-center text-selcap-700 font-bold text-xs">
          <?= strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? '', 0, 1)) ?>
        </div>
        <div class="min-w-0">
          <p class="text-sm font-medium text-gray-800 truncate"><?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?></p>
          <p class="text-xs text-gray-400"><?= $isAdmin ? 'Administrador' : 'Estudiante' ?></p>
        </div>
      </div>
      <a href="<?= BASE_URL ?>/logout.php" class="flex items-center gap-2 text-xs text-gray-400 hover:text-red-500 transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4 4m4-4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
        Cerrar sesión
      </a>
    </div>
  </aside>

  <!-- Mobile header -->
  <div class="lg:hidden fixed top-0 left-0 right-0 bg-white border-b border-gray-200 z-50 px-4 py-3 flex items-center justify-between">
    <div class="flex items-center gap-2">
      <button onclick="document.getElementById('mobile-menu').classList.toggle('hidden')" class="p-1">
        <svg class="w-6 h-6 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
      </button>
      <span class="font-bold text-gray-800">Aula Virtual</span>
    </div>
    <a href="<?= BASE_URL ?>/logout.php" class="text-xs text-gray-400">Salir</a>
  </div>

  <!-- Mobile menu -->
  <div id="mobile-menu" class="hidden lg:hidden fixed inset-0 z-40 bg-white pt-16">
    <nav class="p-4 space-y-1">
      <a href="<?= BASE_URL ?>/dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-base font-medium <?= $currentPage === 'dashboard' ? 'bg-selcap-50 text-selcap-700' : 'text-gray-600' ?>">📊 Panel</a>
      <a href="<?= BASE_URL ?>/catalogo.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-base font-medium <?= $currentPage === 'catalogo' ? 'bg-selcap-50 text-selcap-700' : 'text-gray-600' ?>">🏷️ Catálogo</a>
      <a href="<?= BASE_URL ?>/mis-cursos.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-base font-medium <?= $currentPage === 'mis-cursos' ? 'bg-selcap-50 text-selcap-700' : 'text-gray-600' ?>">📚 Mis Cursos</a>
      <a href="<?= BASE_URL ?>/certificados.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-base font-medium <?= $currentPage === 'certificados' ? 'bg-selcap-50 text-selcap-700' : 'text-gray-600' ?>">🏅 Certificados</a>
      <a href="<?= BASE_URL ?>/perfil.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-base font-medium <?= $currentPage === 'perfil' ? 'bg-selcap-50 text-selcap-700' : 'text-gray-600' ?>">👤 Perfil</a>
      <?php if ($isAdmin): ?>
      <div class="border-t border-gray-100 mt-2 pt-2">
        <p class="px-4 text-xs text-gray-400 mb-1 uppercase tracking-wide">Administración</p>
        <a href="<?= BASE_URL ?>/admin/" class="flex items-center gap-3 px-4 py-3 rounded-xl text-base font-medium <?= $currentPage === 'admin' ? 'bg-selcap-50 text-selcap-700' : 'text-gray-600' ?>">⚙️ Secciones</a>
        <a href="<?= BASE_URL ?>/admin/alumnos.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-base font-medium <?= $currentPage === 'alumnos' ? 'bg-selcap-50 text-selcap-700' : 'text-gray-600' ?>">👥 Alumnos</a>
        <a href="<?= BASE_URL ?>/admin/reportes.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-base font-medium <?= $currentPage === 'reportes' ? 'bg-selcap-50 text-selcap-700' : 'text-gray-600' ?>">📊 Reportes</a>
      </div>
      <?php endif; ?>
    </nav>
  </div>

  <!-- Main content -->
  <div class="flex-1 lg:ml-0 mt-14 lg:mt-0">
    <main class="max-w-5xl mx-auto px-4 lg:px-8 py-6">
