# Selcap AV — Aula Virtual (PHP)

Plataforma de aula virtual para Selcap, migrada de Next.js/Prisma a PHP vanilla + MySQL para deploy en cPanel shared hosting.

## Requisitos

- PHP 8.x con PDO MySQL
- MySQL 5.7+ o MariaDB 10.x
- Apache con mod_rewrite

## Instalación

### 1. Subir archivos
Subí todo el contenido a `public_html/` de cPanel.

### 2. Base de datos
Ejecutá `sql/schema.sql` en phpMyAdmin → SQL. Esto crea:
- 11 tablas (users, courses, sections, lessons, attachments, evaluations, questions, answers, enrollments, progress, attempts)
- 1 curso demo ("Fundamentos de Seguridad Industrial") con 3 secciones, 3 lecciones y 1 evaluación
- Admin user: `admin@selcap.com` / `admin123`

### 3. Configurar conexión
Editá `includes/config.php` con los datos de tu BD:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'selcap_av');
define('DB_USER', 'tu_usuario');
define('DB_PASS', 'tu_contraseña');
```

### 4. Permisos
```bash
chmod 755 uploads/
```

## Estructura

```
selcap_php/
├── index.php              → landing → login o dashboard
├── login.php              → autenticación
├── register.php           → registro + auto-enról
├── logout.php
├── dashboard.php          → panel estudiante (secciones, progreso, evaluaciones)
├── lesson.php?id=X        → visor de lección (HTML, video, archivos)
├── evaluation.php?id=X    → tomar evaluación (multiple choice, score)
├── enroll.php             → handler de inscripción
├── admin/
│   ├── index.php          → CRUD secciones
│   ├── lessons.php        → CRUD lecciones + upload archivos
│   └── evaluations.php    → CRUD evaluaciones + preguntas/respuestas
├── includes/
│   ├── config.php         → DB + constantes
│   ├── auth.php           → sesiones + helpers
│   ├── header.php         → layout HTML
│   └── footer.php
├── uploads/               → archivos subidos (chmod 755)
└── sql/
    └── schema.sql         → esquema MySQL + seed data
```

## Stack

- PHP 8.x vanilla (sin composer, sin framework)
- MySQL
- Tailwind CSS CDN
- Sesiones nativas de PHP
- Subida directa al filesystem

## Usuarios por defecto

| Email | Password | Rol |
|---|---|---|
| admin@selcap.com | admin123 | Admin |

El registro público crea usuarios con rol `student`. Los estudiantes se auto-enrolan al curso activo.
