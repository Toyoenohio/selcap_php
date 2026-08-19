<?php
// ═══════════════════════════════════════════════
// Partial — Formulario de encuesta post-examen (11 preguntas oficiales Selcap)
// Incluido desde evaluation.php en ambos flujos (aprobó ahora / ya aprobada).
// Requiere: $surveySaved, $surveyError (variables ya definidas en el padre).
// ═══════════════════════════════════════════════
?>
<div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 sm:p-8 mt-6" id="encuesta">
    <h2 class="text-lg font-bold text-gray-900 mb-1">📋 Encuesta de satisfacción</h2>
    <p class="text-sm text-gray-500 mb-6">La misma para todos los cursos de Selcap. Tu opinión nos ayuda a mejorar. Escala: <strong>1 = Muy adecuado, 2 = Adecuado, 3 = Algo adecuado, 4 = Inadecuado</strong>.</p>

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
        // Escalas obligatorias (1-4)
        $scaleQuestions = [
            'eval_general'        => '1. Evalúa de forma general el curso <span class="text-red-500">*</span>',
            'eval_tecnologia'     => '3. Evalúe la tecnología usada <span class="text-red-500">*</span>',
            'horario_adecuado'    => '5. ¿Le acomoda este horario para realizar talleres de capacitación online? <span class="text-red-500">*</span>',
            'proceso_inscripcion' => '6. Evalúe el proceso de inscripción al curso <span class="text-red-500">*</span>',
            'efectividad_relator' => '7. Evalúe de forma global la efectividad del relator <span class="text-red-500">*</span>',
        ];
        $scaleLabels = ['', 'Muy adecuado', 'Adecuado', 'Algo adecuado', 'Inadecuado'];
        foreach ($scaleQuestions as $field => $label):
        ?>
        <div>
            <p class="font-semibold text-gray-800 mb-3"><?= $label ?></p>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
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

        <!-- Q2: mejoras (optativo) -->
        <div>
            <p class="font-semibold text-gray-800 mb-2">2. Indica aspectos para posibles mejoras <span class="text-xs text-gray-400">(optativo)</span></p>
            <textarea name="mejoras" rows="2" maxlength="1000" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm" placeholder="¿Qué mejorarías?"></textarea>
        </div>

        <!-- Q4: dificultades tecnología (optativo) -->
        <div>
            <p class="font-semibold text-gray-800 mb-2">4. Comente dificultades en el uso de la tecnología <span class="text-xs text-gray-400">(optativo)</span></p>
            <textarea name="dificultades_tecnologia" rows="2" maxlength="1000" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm" placeholder="¿Tuviste algún problema técnico?"></textarea>
        </div>

        <!-- Q8: comentario final (optativo) -->
        <div>
            <p class="font-semibold text-gray-800 mb-2">8. Si quiere agregar algún comentario final hágalo aquí <span class="text-xs text-gray-400">(optativo)</span></p>
            <textarea name="comentario_final" rows="2" maxlength="1000" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm" placeholder="Comentario final..."></textarea>
        </div>

        <!-- Q9: experiencia (optativo) -->
        <div>
            <p class="font-semibold text-gray-800 mb-2">9. Compártenos tu experiencia del curso <span class="text-xs text-gray-400">(optativo)</span></p>
            <textarea name="experiencia" rows="3" maxlength="2000" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm" placeholder="Cuéntanos tu experiencia..."></textarea>
        </div>

        <!-- Q10: autorización publicación (optativo) -->
        <div>
            <p class="font-semibold text-gray-800 mb-2">10. ¿Nos autorizas a compartir tu opinión de manera pública? <span class="text-xs text-gray-400">(optativo)</span></p>
            <div class="flex gap-4">
                <label class="flex items-center gap-2 px-4 py-3 bg-gray-50 hover:bg-selcap-50 rounded-xl cursor-pointer border border-gray-100 has-[:checked]:border-selcap-500 has-[:checked]:bg-selcap-50 transition-colors">
                    <input type="radio" name="autoriza_publicar" value="1" class="accent-selcap-600 w-4 h-4" onchange="document.getElementById('nombre_publico_wrap').style.display='block'">
                    <span class="text-sm text-gray-700">Sí</span>
                </label>
                <label class="flex items-center gap-2 px-4 py-3 bg-gray-50 hover:bg-selcap-50 rounded-xl cursor-pointer border border-gray-100 has-[:checked]:border-selcap-500 has-[:checked]:bg-selcap-50 transition-colors">
                    <input type="radio" name="autoriza_publicar" value="0" class="accent-selcap-600 w-4 h-4" onchange="document.getElementById('nombre_publico_wrap').style.display='none'">
                    <span class="text-sm text-gray-700">No</span>
                </label>
            </div>
        </div>

        <!-- Q11: nombre (solo si autorizó) -->
        <div id="nombre_publico_wrap" style="display:none">
            <p class="font-semibold text-gray-800 mb-2">11. Si autorizaste a publicar tu opinión déjanos tu nombre y apellido <span class="text-xs text-gray-400">(optativo)</span></p>
            <input type="text" name="nombre_publico" maxlength="150" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:ring-2 focus:ring-selcap-500 text-sm" placeholder="Nombre y apellido">
        </div>

        <button type="submit" name="submit_survey" value="1"
                class="w-full bg-selcap-600 hover:bg-selcap-700 text-white font-semibold py-3 rounded-xl transition-colors text-base">
            Enviar encuesta
        </button>
    </form>
    <?php endif; ?>
</div>
