<?php
// verificar.php — Página pública de verificación de certificado (sin login)
require_once __DIR__ . '/includes/config.php';
$pdo = db();

$attemptId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare('
    SELECT ea.*, e.title as eval_title,
           c.title as course_title, c.hours, c.date_range, c.address,
           u.first_name, u.last_name, u.rut
    FROM evaluation_attempts ea
    JOIN evaluations e ON ea.evaluation_id = e.id
    JOIN courses c ON e.course_id = c.id
    JOIN users u ON ea.user_id = u.id
    WHERE ea.id = ? AND ea.passed = 1
');
$stmt->execute([$attemptId]);
$cert = $stmt->fetch();

if (!$cert) {
    http_response_code(404);
    die('<html><body style="font-family:sans-serif;text-align:center;padding:80px 20px;"><h1 style="color:#999;">Certificado no encontrado</h1><p>El certificado solicitado no existe o no es válido.</p></body></html>');
}

$nombreCompleto = htmlspecialchars($cert['first_name'] . ' ' . $cert['last_name']);
$curso = htmlspecialchars($cert['course_title']);
$fechaRealizacion = htmlspecialchars($cert['date_range'] ?? '');
$horas = $cert['hours'] ? (int)$cert['hours'] . ' horas' : '';
$direccion = htmlspecialchars($cert['address'] ?? 'Av. Tobalaba 1621, Providencia, Santiago');
$rut = htmlspecialchars($cert['rut'] ?? 'XX.XXX.XXX-X');
$folio = 'N° ' . str_pad($attemptId, 5, '0', STR_PAD_LEFT);
$fechaEmision = date('d/m/Y');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verificación — <?= $nombreCompleto ?></title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Merriweather:wght@400;700;900&family=Inter:wght@400;600&display=swap');
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body {
    font-family: 'Inter', sans-serif;
    background: #e8e8e8;
    display: flex; align-items: center; justify-content: center;
    min-height: 100vh; padding: 20px;
  }
  .certificado {
    width: 297mm; max-width: 100%; min-height: 210mm;
    background: #fff; border: 2px solid #bbb;
    padding: 40px 50px; position: relative;
    box-shadow: 0 4px 24px rgba(0,0,0,0.12);
  }
  .header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 30px; }
  .sello-ministerio { width: 90px; height: 90px; }
  .logo-selcap { display: flex; align-items: center; gap: 8px; }
  .logo-selcap .circulo { width: 40px; height: 40px; position: relative; }
  .logo-selcap .circulo::before { content: ''; position: absolute; width: 100%; height: 100%; border-radius: 50%; background: #000; }
  .logo-selcap .circulo::after { content: ''; position: absolute; width: 14px; height: 14px; border-radius: 50%; background: #000; top: -8px; left: 50%; transform: translateX(-50%); }
  .logo-selcap .selcap-texto { font-weight: 700; font-size: 22px; color: #000; letter-spacing: -0.5px; }
  .titulo { text-align: center; font-family: 'Merriweather', serif; font-size: 22px; font-weight: 900; color: #1a1a1a; letter-spacing: 1px; margin-bottom: 22px; text-transform: uppercase; }
  .parrafo { text-align: justify; font-size: 12px; line-height: 1.7; color: #333; margin-bottom: 18px; font-family: 'Merriweather', serif; }
  .curso-nombre { text-align: center; font-family: 'Merriweather', serif; font-size: 17px; font-weight: 700; font-style: italic; color: #1a1a1a; margin-bottom: 18px; }
  .datos-curso { font-size: 12px; color: #333; margin-bottom: 22px; line-height: 2; font-family: 'Merriweather', serif; }
  .datos-curso strong { font-weight: 700; color: #1a1a1a; }
  .datos-alumno { font-size: 12px; color: #333; margin-bottom: 35px; line-height: 2.4; font-family: 'Merriweather', serif; }
  .datos-alumno .nombre { font-weight: 700; color: #1a1a1a; font-size: 14px; }
  .footer { display: flex; align-items: flex-end; justify-content: space-between; margin-top: 30px; }
  .firma { text-align: center; flex: 1; }
  .firma .linea { width: 220px; margin: 0 auto 6px; border-bottom: 1px solid #999; }
  .firma .nombre-firma { font-family: 'Merriweather', serif; font-size: 25px; color: #1a4d8c; font-style: italic; margin-bottom: 4px; }
  .firma .cargo { font-size: 11px; color: #555; line-height: 1.4; }
  .sello-selcap { width: 80px; height: 80px; }
  .verificado { position: absolute; top: 12px; right: 16px; background: #22c55e; color: #fff; font-size: 10px; font-weight: 700; padding: 4px 10px; border-radius: 4px; letter-spacing: 0.5px; }
  @media print {
    body { background: #fff; }
    .certificado { box-shadow: none; border: 2px solid #bbb; min-height: auto; }
    .no-print { display: none !important; }
    @page { margin: 0; size: A4 landscape; }
  }
  .btn-print { position: fixed; bottom: 20px; right: 20px; z-index: 100; }
  .btn { padding: 10px 20px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; text-decoration: none; background: #1a4d8c; color: #fff; display: block; }
  .btn:hover { background: #153d6f; }
</style>
</head>
<body>
<div class="certificado">
  <span class="verificado">✓ CERTIFICADO VÁLIDO</span>
  <div class="header">
    <svg class="sello-ministerio" viewBox="0 0 200 200">
      <circle cx="100" cy="100" r="95" fill="none" stroke="#1a4d8c" stroke-width="3"/>
      <circle cx="100" cy="100" r="88" fill="none" stroke="#1a4d8c" stroke-width="1.5"/>
      <path d="M100,5 A95,95 0 0,1 195,100" fill="none" id="arcTopV"/>
      <text font-size="10" fill="#1a4d8c" font-family="Inter,sans-serif" font-weight="600">
        <textPath href="#arcTopV" startOffset="50%" text-anchor="middle">MINISTERIO DEL TRABAJO Y PREVISIÓN SOCIAL</textPath>
      </text>
      <path d="M100,195 A95,95 0 0,1 5,100" fill="none" id="arcBotV"/>
      <text font-size="14" fill="#1a4d8c" font-family="Inter,sans-serif" font-weight="700" letter-spacing="4">
        <textPath href="#arcBotV" startOffset="50%" text-anchor="middle">CERTIFICADO</textPath>
      </text>
      <text x="100" y="108" text-anchor="middle" font-size="16" fill="#1a4d8c" font-family="Merriweather,serif" font-weight="900">NCh 2728</text>
      <text x="100" y="126" text-anchor="middle" font-size="8" fill="#1a4d8c" font-family="Inter,sans-serif">CURSO ESPECIAL DE</text>
      <text x="100" y="137" text-anchor="middle" font-size="8" fill="#1a4d8c" font-family="Inter,sans-serif">CAPACITACIÓN</text>
      <circle cx="100" cy="100" r="16" fill="none" stroke="#1a4d8c" stroke-width="1.5"/>
      <text x="100" y="104" text-anchor="middle" font-size="9" fill="#1a4d8c" font-family="Merriweather,serif" font-weight="700">NCh</text>
      <text x="100" y="113" text-anchor="middle" font-size="8" fill="#1a4d8c" font-family="Inter,sans-serif">2728</text>
    </svg>
    <div class="logo-selcap"><div class="circulo"></div><span class="selcap-texto">Selcap</span></div>
  </div>
  <div class="titulo">CERTIFICADO DE APROBACION</div>
  <p class="parrafo">De conformidad con los reglamentos académicos vigentes del "Programa para el Desarrollo de la Capacitación", se otorga el presente certificado de aprobación, por cuanto se ha cumplido con las exigencias de rendimiento y asistencia del siguiente curso:</p>
  <div class="curso-nombre"><?= $curso ?></div>
  <div class="datos-curso">
    <?php if ($fechaRealizacion): ?><strong>Fecha de Realización:</strong> <?= $fechaRealizacion ?><br><?php endif; ?>
    <?php if ($horas): ?><strong>N° Total de Horas:</strong> <?= $horas ?><br><?php endif; ?>
    <strong>Realizado en:</strong> Selcap Capacitación Limitada, ubicado en <?= $direccion ?>.
  </div>
  <div class="datos-alumno">
    <strong>A don(a):</strong> <span class="nombre"><?= $nombreCompleto ?></span><br>
    <strong>R.U.T.:</strong> <?= $rut ?><br>
    <strong>N° Folio:</strong> <?= $folio ?>
  </div>
  <div class="footer">
    <div class="firma">
      <div class="linea"></div>
      <div class="nombre-firma">Gerardo Woywood</div>
      <div class="cargo">Director - Psicólogo</div>
    </div>
    <svg class="sello-selcap" viewBox="0 0 160 160">
      <circle cx="80" cy="80" r="75" fill="none" stroke="#b91c1c" stroke-width="3"/>
      <circle cx="80" cy="80" r="69" fill="none" stroke="#b91c1c" stroke-width="1"/>
      <path d="M80,5 A75,75 0 0,1 155,80" fill="none" id="selloTopV"/>
      <text font-size="10" fill="#b91c1c" font-family="Inter,sans-serif" font-weight="600">
        <textPath href="#selloTopV" startOffset="50%" text-anchor="middle">SELCAP CAPACITACIÓN LTDA.</textPath>
      </text>
      <path d="M80,155 A75,75 0 0,1 5,80" fill="none" id="selloBotV"/>
      <text font-size="9" fill="#b91c1c" font-family="Inter,sans-serif" font-weight="600">
        <textPath href="#selloBotV" startOffset="50%" text-anchor="middle">RUT: 77.578.690-6</textPath>
      </text>
      <circle cx="80" cy="78" r="16" fill="#000"/>
      <circle cx="80" cy="66" r="6" fill="#000"/>
    </svg>
  </div>
</div>
<div class="btn-print no-print">
  <button onclick="window.print()" class="btn">🖨️ Imprimir</button>
</div>
</body>
</html>
