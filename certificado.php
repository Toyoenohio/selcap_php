<?php
// certificado.php — Diploma imprimible con datos dinámicos
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$attemptId = (int)($_GET['id'] ?? 0);
$userId = $_SESSION['user_id'];
$pdo = db();

$stmt = $pdo->prepare('
    SELECT ea.*, e.title as eval_title, c.title as course_title, c.id as course_id,
           u.first_name, u.last_name
    FROM evaluation_attempts ea
    JOIN evaluations e ON ea.evaluation_id = e.id
    JOIN courses c ON e.course_id = c.id
    JOIN users u ON ea.user_id = u.id
    WHERE ea.id = ? AND ea.user_id = ? AND ea.passed = 1
');
$stmt->execute([$attemptId, $userId]);
$cert = $stmt->fetch();

if (!$cert) {
    http_response_code(404);
    $pageTitle = 'Certificado no encontrado';
    require __DIR__ . '/includes/header.php';
    echo '<div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-12 text-center"><p class="text-gray-500">Certificado no encontrado o no tienes acceso.</p><a href="' . BASE_URL . '/certificados.php" class="text-selcap-600 font-medium text-sm mt-4 inline-block">Volver</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$nombreCompleto = htmlspecialchars($cert['first_name'] . ' ' . $cert['last_name']);
$curso = htmlspecialchars($cert['course_title']);
$evaluacion = htmlspecialchars($cert['eval_title']);
$fecha = date('d \d\e F \d\e Y', strtotime($cert['submitted_at']));
$fechaLarga = date('d/m/Y', strtotime($cert['submitted_at']));
$nota = round($cert['score']) . '%';

// Traducción de meses
$meses = ['January' => 'enero','February' => 'febrero','March' => 'marzo','April' => 'abril',
          'May' => 'mayo','June' => 'junio','July' => 'julio','August' => 'agosto',
          'September' => 'septiembre','October' => 'octubre','November' => 'noviembre','December' => 'diciembre'];
$fecha = str_replace(array_keys($meses), array_values($meses), $fecha);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Certificado — <?= $nombreCompleto ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&family=Inter:wght@400;600&display=swap');
  .font-diploma { font-family: 'Playfair Display', Georgia, serif; }
  .border-ornament {
    border: 8px double #b8860b;
    outline: 2px solid #b8860b;
    outline-offset: -14px;
  }
  .gold-text { color: #b8860b; }
  .gold-bg { background: linear-gradient(135deg, #fef9e7 0%, #fdf2d0 100%); }
  @media print {
    body { background: white !important; }
    .no-print { display: none !important; }
    .print-area { box-shadow: none !important; border: 8px double #b8860b !important; }
    @page { margin: 0; size: A4 landscape; }
  }
</style>
</head>
<body class="gold-bg min-h-screen flex items-center justify-center p-4">

<div class="print-area bg-white w-full max-w-4xl shadow-2xl border-ornament p-8 sm:p-12 md:p-16 relative">
  
  <!-- Esquinas decorativas -->
  <div class="absolute top-3 left-3 w-6 h-6 border-t-2 border-l-2 border-amber-400"></div>
  <div class="absolute top-3 right-3 w-6 h-6 border-t-2 border-r-2 border-amber-400"></div>
  <div class="absolute bottom-3 left-3 w-6 h-6 border-b-2 border-l-2 border-amber-400"></div>
  <div class="absolute bottom-3 right-3 w-6 h-6 border-b-2 border-r-2 border-amber-400"></div>

  <div class="text-center">
    <!-- Logo / Encabezado -->
    <p class="text-xs tracking-[0.3em] uppercase text-gray-400 mb-6 font-semibold">Selcap — Capacitación y Desarrollo</p>
    
    <h1 class="font-diploma text-4xl sm:text-5xl md:text-6xl font-black gold-text mb-4 tracking-tight">
      Certificado
    </h1>
    <p class="text-sm text-gray-400 mb-8">de finalización</p>

    <!-- Cuerpo -->
    <p class="text-lg text-gray-500 mb-2">Se otorga el presente certificado a</p>
    
    <h2 class="font-diploma text-3xl sm:text-4xl font-bold text-gray-900 my-4 border-b-2 border-amber-200 pb-3 inline-block px-8">
      <?= $nombreCompleto ?>
    </h2>

    <p class="text-lg text-gray-500 mt-6 mb-2">por haber aprobado satisfactoriamente la evaluación</p>
    
    <h3 class="font-diploma text-2xl sm:text-3xl font-bold text-gray-800 my-3">
      «<?= $evaluacion ?>»
    </h3>

    <p class="text-gray-500 mb-1">correspondiente al curso</p>
    
    <h3 class="text-xl sm:text-2xl font-bold text-selcap-700 my-2">
      <?= $curso ?>
    </h3>

    <!-- Datos -->
    <div class="grid grid-cols-3 gap-8 mt-10 pt-8 border-t border-amber-200 max-w-lg mx-auto">
      <div>
        <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Nota</p>
        <p class="text-2xl font-bold text-gray-800"><?= $nota ?></p>
      </div>
      <div>
        <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Fecha</p>
        <p class="text-sm font-semibold text-gray-700"><?= $fechaLarga ?></p>
      </div>
      <div>
        <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Estado</p>
        <p class="text-sm font-bold text-green-600">Aprobado ✓</p>
      </div>
    </div>

    <!-- Firmas -->
    <div class="grid grid-cols-2 gap-8 mt-12 pt-8 border-t border-amber-200 max-w-md mx-auto">
      <div>
        <div class="h-px bg-gray-300 mb-2"></div>
        <p class="text-xs text-gray-400">Dirección Académica</p>
      </div>
      <div>
        <div class="h-px bg-gray-300 mb-2"></div>
        <p class="text-xs text-gray-400">Selcap</p>
      </div>
    </div>
  </div>
</div>

<!-- Botones -->
<div class="fixed bottom-6 right-6 flex flex-col gap-2 no-print">
  <button onclick="window.print()" class="bg-selcap-600 hover:bg-selcap-700 text-white font-semibold px-5 py-3 rounded-xl shadow-lg transition-colors text-sm flex items-center gap-2">
    🖨️ Imprimir
  </button>
  <a href="<?= BASE_URL ?>/certificados.php" class="bg-white hover:bg-gray-50 text-gray-700 font-semibold px-5 py-3 rounded-xl shadow-lg border border-gray-200 transition-colors text-sm text-center">
    ← Volver
  </a>
</div>

</body>
</html>
