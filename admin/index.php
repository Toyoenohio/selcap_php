<?php
// Redirigir a la nueva página de administración de cursos
header('Location: ' . (defined('BASE_URL') ? BASE_URL : '') . '/admin/courses.php');
exit;
