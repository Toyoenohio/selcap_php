# Selcap AV — Aula Virtual (PHP)

Plataforma de aula virtual para Selcap, migrada de Next.js/Prisma a PHP vanilla + MySQL para deploy en cPanel shared hosting.

## Requisitos

- PHP 7.4+ con PDO MySQL
- MySQL 5.7+ o MariaDB 10.x
- Apache con mod_rewrite

## Instalación en cPanel

### 1. Clonar el repo directamente en el servidor

**Opción A — cPanel con Git Version Control:**
1. cPanel → Git Version Control
2. Repository URL: `https://github.com/Toyoenohio/selcap_php.git`
3. Directory: `public_html`
4. Crear → Manage → Pull

**Opción B — Por SSH:**
```bash
ssh tuuser@tuservidor
cd public_html
git clone https://github.com/Toyoenohio/selcap_php.git .
```

### 2. Base de datos
Ejecutá `sql/schema.sql` en phpMyAdmin → SQL. Esto crea:
- 11 tablas (users, courses, sections, lessons, attachments, evaluations, questions, answers, enrollments, progress, attempts)
- 1 curso demo ("Fundamentos de Seguridad Industrial") con 3 secciones, 3 lecciones y 1 evaluación
- Admin user: `admin@selcap.com` / `admin123`

> ⚠️ El password del admin en el seed es incorrecto. Después de instalar, subí `reset_admin.php` y abrilo **una sola vez** para corregirlo. Borralo inmediatamente después.

### 3. Configurar conexión
Editá `includes/config.php` con los datos de tu BD:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'dbekvjvb52iehb');
define('DB_USER', 'uty2jzjeamkly');
define('DB_PASS', 'tu_contraseña');
```

### 4. Permisos
```bash
chmod 755 uploads/
```

### 5. Auto-deploy con Git

**Si clonaste con Git Version Control de cPanel:**
1. cPanel → Git Version Control → Manage → "Deploy HEAD commit" o configurá auto-deploy

**Si querés webhook automático (cada push → deploy instantáneo):**
1. Editá `deploy.php` y cambiá `$SECRET` por una clave segura
2. GitHub → Settings → Webhooks → Add webhook
   - Payload URL: `https://aula.selcap.cl/deploy.php`
   - Content type: `application/json`
   - Secret: la misma clave que pusiste en `$SECRET`
   - Events: Just the push event

Cada vez que hagas push a `main`, GitHub notifica al servidor y hace `git pull` solo.

## Estructura

```
selcap_php/
├── index.php              → landing → login o dashboard
├── login.php              → autenticación
├── register.php           → registro + auto-enról
├── logout.php
├── dashboard.php          → panel estudiante + progreso
├── lesson.php?id=X        → visor de lección (HTML, video, archivos)
├── evaluation.php?id=X    → tomar evaluación (multiple choice, score)
├── enroll.php             → handler de inscripción
├── deploy.php             → webhook para auto-deploy
├── reset_admin.php        → script de reseteo de password (borrar después de usar)
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

- PHP vanilla (sin composer, sin framework)
- MySQL / MariaDB
- Tailwind CSS CDN
- Sesiones nativas de PHP
- Subida directa al filesystem

## Usuarios por defecto

| Email | Password | Rol |
|---|---|---|
| admin@selcap.com | admin123 | Admin |

El registro público crea usuarios con rol `student`. Los estudiantes se auto-enrolan al curso activo.
