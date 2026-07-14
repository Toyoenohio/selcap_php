<?php
// insert_questions.php — Insertar preguntas 2-10 del examen PEP-3
require_once __DIR__ . '/includes/config.php';
$pdo = db();

$courseId = 3;
$evalName = 'PEP-3';

// Buscar evaluation_id
$stmt = $pdo->prepare('SELECT id FROM evaluations WHERE course_id = ? AND title LIKE ?');
$stmt->execute([$courseId, "%$evalName%"]);
$eval = $stmt->fetch();

if (!$eval) {
    die("Evaluación '$evalName' no encontrada en course_id=$courseId.\n");
}
$evalId = (int)$eval['id'];
echo "Evaluación ID: $evalId\n";

$questions = [
    [
        'text' => 'La calificación del instrumento ha sido cuantificada:',
        'answers' => ['1, 2, 3', '3, 4, 5', '0, 1, 2', '0, 1, 2, 3'],
        'correct' => 2
    ],
    [
        'text' => 'El instrumento consta de:',
        'answers' => ['Pruebas directas.', 'Pruebas directas y un informe del cuidador.', 'Informe al cuidador.', 'Pruebas indirectas y un informe de comportamiento adaptativo.'],
        'correct' => 1
    ],
    [
        'text' => 'El compuesto de Conductas inadaptada está formada por:',
        'answers' => [
            'Verbal cognitivo/pre verbal, lenguaje expresivo, lenguaje receptivo.',
            'Motricidad fina, motricidad gruesa, imitación visual-motriz.',
            'Expresión afectiva, reciprocidad social, conductas motoras características.',
            'Conductas verbales características, conductas motoras características, reciprocidad social, expresión afectiva.'
        ],
        'correct' => 3
    ],
    [
        'text' => 'El informe al cuidador basado en las observaciones cotidianas del niño contiene tres subpruebas:',
        'answers' => [
            'Autovalimiento personal, conducta adaptativa y lenguaje receptivo.',
            'Conductas problemáticas, autovalimiento personal, conducta adaptativa.',
            'Expresión afectiva, conductas problemáticas, autovalimiento personal.',
            'Conductas verbales características, lenguaje expresivo, conductas problemáticas.'
        ],
        'correct' => 1
    ],
    [
        'text' => 'Según la tabla 3.1 sobre los niveles adaptativos relativos al desarrollo para gradaciones percentiles del PEP-3, el resultado obtenido en el % de rango en subtest de lenguaje expresivo es de 26, su nivel adaptativo es de:',
        'answers' => ['Adecuado.', 'Severo.', 'Moderado.', 'Leve (suave).'],
        'correct' => 2
    ],
    [
        'text' => 'Para calcular la edad de desarrollo de cada subtest:',
        'answers' => [
            'Se ubica el puntaje bruto directamente en la sección 6.',
            'Se ubica el % de rango en la sección 6.',
            'Se ubica el puntaje bruto en la tabla A.9.',
            'Se ubica el % de rango en la tabla A.9.'
        ],
        'correct' => 0
    ],
    [
        'text' => 'Para obtener las puntuaciones típicas:',
        'answers' => [
            'Se suman las puntuaciones típicas de los subtest del compuesto comunicación.',
            'Se suman las puntuaciones típicas de los subtest del compuesto motricidad.',
            'Se suman las puntuaciones típicas de los subtest de cada compuesto.',
            'Se suman las puntuaciones típicas de los subtest del compuesto conducta inadaptada.'
        ],
        'correct' => 2
    ],
    [
        'text' => 'Para obtener los percentiles de rango de los compuestos se realiza con la tabla:',
        'answers' => ['Tabla A.9.', 'Tabla A.8.', 'Tabla 3.1.', 'Tabla B.1.'],
        'correct' => 3
    ],
    [
        'text' => 'Para una niña de 6 años y 4 meses de edad, con puntaje bruto de 19 en subtest lenguaje receptivo, ¿Cuál es el registro de puntajes de (edad de desarrollo, % de rango y desarrollo/nivel adaptativo)?',
        'answers' => [
            'Edad de desarrollo 18 meses, % de rango 4, nivel adaptativo severo.',
            'Edad de desarrollo 18 meses, % de rango 5, nivel adaptativo leve.',
            'Edad de desarrollo 22 meses, % de rango 4, nivel adaptativo severo.',
            'Edad de desarrollo 22 meses, % de rango 5, nivel adaptativo moderado.'
        ],
        'correct' => 2
    ],
];

$inserted = 0;
foreach ($questions as $i => $q) {
    $sortOrder = $i + 2; // pregunta 2-10
    $ins = $pdo->prepare('INSERT INTO questions (evaluation_id, text, type, points, sort_order) VALUES (?, ?, "multiple_choice", 1, ?)');
    $ins->execute([$evalId, $q['text'], $sortOrder]);
    $qId = (int)$pdo->lastInsertId();

    foreach ($q['answers'] as $j => $ansText) {
        $isCorrect = ($j === $q['correct']) ? 1 : 0;
        $pdo->prepare('INSERT INTO answers (question_id, text, is_correct, sort_order) VALUES (?, ?, ?, ?)')
            ->execute([$qId, $ansText, $isCorrect, $j]);
    }
    $inserted++;
    echo "✓ Pregunta $sortOrder: " . mb_substr($q['text'], 0, 60) . "...\n";
}

echo "\n$inserted preguntas insertadas en evaluación '$evalName' (ID $evalId).\n";
