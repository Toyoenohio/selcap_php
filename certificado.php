<?php
// certificado.php — Diploma oficial Selcap
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$attemptId = (int)($_GET['id'] ?? 0);
$userId = $_SESSION['user_id'];
$pdo = db();

$stmt = $pdo->prepare('
    SELECT ea.*, e.title as eval_title,
           c.title as course_title, c.hours, c.date_range, c.address,
           u.first_name, u.last_name, u.rut
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
$fechaRealizacion = htmlspecialchars($cert['date_range'] ?? '');
$horas = $cert['hours'] ? (int)$cert['hours'] . ' horas.' : '';
$direccion = htmlspecialchars($cert['address'] ?? 'Av. Tobalaba 1621, Providencia, Santiago.');
$rut = htmlspecialchars($cert['rut'] ?? 'XX.XXX.XXX-X');
$verifyUrl = 'https://aula.selcap.cl/verificar.php?id=' . $attemptId;
$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=110x110&data=' . urlencode($verifyUrl);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificado de Aprobación — <?= $nombreCompleto ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Georgia', 'Times New Roman', serif;
            background: #f5f5f5;
            display: flex; justify-content: center; align-items: center;
            min-height: 100vh; padding: 20px;
        }
        .certificado {
            width: 297mm; height: 210mm; background: #fff;
            border: 4px solid #7a7a7a; padding: 30mm 22mm 25mm 22mm;
            position: relative; box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            overflow: hidden;
        }
        .header-logos { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6mm; }
        .logo-nch {
            width: 26mm; height: 26mm; border: 2px solid #1e5aa0; border-radius: 50%;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            text-align: center; color: #1e5aa0; font-size: 7pt; line-height: 1.2; padding: 1mm;
        }
        .logo-nch .certificado-nch { font-size: 5.5pt; text-transform: uppercase; letter-spacing: 0.5px; }
        .logo-nch .numero-nch { font-size: 10pt; font-weight: bold; margin: 0.5mm 0; }
        .logo-selcap-central { text-align: center; flex: 1; display: flex; flex-direction: column; align-items: center; }
        .selcap-icono {
            width: 16mm; height: 16mm; background: #000; border-radius: 50%;
            position: relative; margin-bottom: 2mm;
        }
        .selcap-icono::before {
            content: ''; position: absolute; width: 5mm; height: 5mm; background: #fff;
            border-radius: 50%; top: 50%; left: 50%; transform: translate(-50%, -50%);
        }
        .selcap-icono::after {
            content: ''; position: absolute; width: 2.5mm; height: 2.5mm; background: #000;
            border-radius: 50%; top: 15%; right: 18%;
        }
        .logo-selcap-central h1 {
            font-family: 'Arial', sans-serif; font-size: 22pt; font-weight: normal;
            letter-spacing: 1px; color: #000;
        }
        .titulo-certificado {
            text-align: center; font-size: 15pt; font-weight: bold; text-transform: uppercase;
            letter-spacing: 2px; color: #333; margin-bottom: 6mm; margin-top: 2mm;
        }
        .texto-legal {
            text-align: center; font-size: 10pt; line-height: 1.6; color: #222;
            margin-bottom: 8mm; padding: 0 8mm;
        }
        .texto-legal strong { font-weight: bold; }
        .nombre-curso {
            text-align: center; font-size: 13pt; font-weight: bold; font-style: italic;
            color: #000; margin-bottom: 10mm; padding: 0 12mm; line-height: 1.4;
        }
        .info-grid {
            display: flex; justify-content: space-between; margin-bottom: 8mm;
            font-size: 10pt; line-height: 1.8;
        }
        .info-izquierda { text-align: left; flex: 1; }
        .info-derecha { text-align: right; min-width: 50mm; }
        .info-grid strong { font-weight: bold; }
        .seccion-alumno { margin-bottom: 8mm; display: flex; align-items: flex-start; gap: 8mm; }
        .seccion-alumno-izq { flex: 1; }
        .label-alumno { font-size: 11pt; margin-bottom: 2mm; }
        .nombre-alumno {
            font-family: 'Dancing Script', 'Brush Script MT', cursive;
            font-size: 26pt; color: #000; margin-left: 12mm; line-height: 1.2;
            min-height: 12mm; border-bottom: 1px solid #ccc; display: inline-block; padding-bottom: 1mm;
        }
        .datos-alumno { margin-top: 3mm; margin-left: 12mm; font-size: 10pt; line-height: 1.6; }
        .qr-container { text-align: center; flex-shrink: 0; }
        .qr-container img { border: 1px solid #ddd; padding: 2px; display: block; }
        .qr-label { font-size: 7pt; color: #999; margin-top: 2mm; }
        .footer-certificado {
            display: flex; justify-content: space-between; align-items: flex-end;
            margin-top: auto; padding-top: 10mm;
        }
        .firma-container { text-align: center; margin-left: 18mm; }
        .linea-firma {
            width: 65mm; border-bottom: 1px solid #333; margin-bottom: 1mm;
            min-height: 18mm; display: flex; align-items: flex-end; justify-content: center;
        }
        .firma-texto {
            font-family: 'Dancing Script', 'Brush Script MT', cursive;
            font-size: 16pt; color: #2c5aa0; margin-bottom: 1mm;
        }
        .nombre-firma { font-size: 10pt; font-weight: bold; color: #000; }
        .cargo-firma { font-size: 9pt; color: #333; }
        .sello-container {
            width: 32mm; height: 32mm; border: 2px solid #333; border-radius: 50%;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            text-align: center; font-size: 6.5pt; line-height: 1.3; color: #000;
            position: relative; margin-right: 8mm;
        }
        .sello-logo {
            width: 7mm; height: 7mm; background: #000; border-radius: 50%;
            position: relative; margin-bottom: 1mm;
        }
        .sello-logo::before {
            content: ''; position: absolute; width: 2.5mm; height: 2.5mm; background: #fff;
            border-radius: 50%; top: 50%; left: 50%; transform: translate(-50%, -50%);
        }
        .sello-logo::after {
            content: ''; position: absolute; width: 1.2mm; height: 1.2mm; background: #000;
            border-radius: 50%; top: 8%; right: 14%;
        }
        .sello-nombre { font-size: 6pt; font-weight: bold; }
        .sello-rut { font-size: 6.5pt; margin-top: 0.5mm; }
        @media print {
            body { background: #fff; padding: 0; }
            .certificado { box-shadow: none; border: 3px solid #7a7a7a; page-break-inside: avoid; }
            .no-print { display: none !important; }
            @page { margin: 0; size: A4 landscape; }
        }
        .btn-print { position: fixed; bottom: 20px; right: 20px; display: flex; gap: 8px; flex-direction: column; z-index: 100; }
        .btn { padding: 10px 20px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; text-decoration: none; font-family: 'Arial', sans-serif; }
        .btn-primary { background: #1a4d8c; color: #fff; }
        .btn-primary:hover { background: #153d6f; }
        .btn-secondary { background: #fff; color: #333; border: 1px solid #ccc; text-align: center; }
        .btn-secondary:hover { background: #f5f5f5; }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@500;700&display=swap" rel="stylesheet">
</head>
<body>

<div class="certificado">
    <!-- Logos superiores -->
    <div class="header-logos">
        <div class="logo-nch">
            <div class="certificado-nch">Certificado<br>NCh 2728</div>
            <div class="numero-nch">2728</div>
        </div>
        <div class="logo-selcap-central">
            <div class="selcap-icono"></div>
            <h1>Selcap</h1>
        </div>
        <div style="width:26mm;"></div>
    </div>

    <!-- Título -->
    <div class="titulo-certificado">Certificado de Aprobación</div>

    <!-- Texto legal -->
    <div class="texto-legal">
        De conformidad con los reglamentos académicos vigentes del <strong>"Programa para el Desarrollo de la Capacitación"</strong>,
        se otorga el presente certificado de aprobación, por cuanto se ha cumplido con las exigencias de rendimiento y
        asistencia del siguiente curso:
    </div>

    <!-- Nombre del curso -->
    <div class="nombre-curso">«<?= $curso ?>»</div>

    <!-- Información del curso -->
    <div class="info-grid">
        <div class="info-izquierda">
            <?php if ($fechaRealizacion): ?>
            <div>• <strong>Fecha de Realización:</strong> <?= $fechaRealizacion ?></div>
            <?php endif; ?>
            <div>• <strong>Realizado en:</strong> Selcap Capacitación Limitada, ubicado en <?= $direccion ?></div>
        </div>
        <div class="info-derecha">
            <?php if ($horas): ?>
            <div><strong>N° Total de Horas:</strong> <?= $horas ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Datos del alumno + QR -->
    <div class="seccion-alumno">
        <div class="seccion-alumno-izq">
            <div class="label-alumno">A don(ña): <span class="nombre-alumno"><?= $nombreCompleto ?>.</span></div>
            <div class="datos-alumno">
                <div><strong>R.U.T.:</strong> <?= $rut ?></div>
            </div>
        </div>
        <div class="qr-container">
            <img src="<?= $qrUrl ?>" alt="QR Verificación" width="95" height="95">
            <div class="qr-label">Escanear para verificar</div>
        </div>
    </div>

    <!-- Footer: Firma y Sello -->
    <div class="footer-certificado">
        <div class="firma-container">
            <div class="linea-firma">
                <span class="firma-texto">G. Woywood</span>
            </div>
            <div class="nombre-firma">Gerardo Woywood</div>
            <div class="cargo-firma">Director - Psicólogo</div>
        </div>

        <div class="sello-container">
            <div class="sello-nombre">Selcap Capacitación Ltda.</div>
            <div class="sello-logo"></div>
            <div style="font-weight:bold;font-size:7pt;">Selcap</div>
            <div class="sello-rut">RUT: 77.578.690-6</div>
        </div>
    </div>
</div>

<div class="btn-print no-print">
    <button onclick="window.print()" class="btn btn-primary">🖨️ Imprimir certificado</button>
    <a href="<?= BASE_URL ?>/certificados.php" class="btn btn-secondary">← Volver</a>
</div>

</body>
</html>
