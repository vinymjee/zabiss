<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../config/database.php';

$pdo = getPDO();

// Auto-migrate SQLite si besoin (dev local)
if ((getenv('DB_DRIVER') ?: 'mysql') === 'sqlite') {
    try { $pdo->query("SELECT 1 FROM parents LIMIT 1"); $hasData = $pdo->query("SELECT count(*) FROM eleves")->fetchColumn(); if ($hasData==0) throw new Exception("seed needed"); }
    catch (Throwable $e) {
        $schema = file_get_contents(__DIR__ . '/../sql/schema.sqlite.sql');
        if (!$schema) $schema = file_get_contents(__DIR__ . '/../sql/schema.sql');
        if (str_contains($schema, 'AUTO_INCREMENT')) {
            $sqliteSchema = @file_get_contents(__DIR__ . '/../sql/schema.sqlite.sql');
            if ($sqliteSchema) $schema = $sqliteSchema;
        }
        try { $pdo->exec($schema); } catch(Throwable $e2){}
        $seed = @file_get_contents(__DIR__ . '/../sql/seed.sqlite.sql') ?: @file_get_contents(__DIR__ . '/../sql/seed.sql');
        if ($seed) try { $pdo->exec($seed); } catch(Throwable $e2){}
        // Fix hash pour eleve123 si ancien hash password
        try {
            $hash = password_hash('eleve123', PASSWORD_DEFAULT);
            $pdo->exec("UPDATE eleves SET password_hash='".$hash."' WHERE login IN ('koffi.aya','koffi.moussa','traore.fatou')");
        } catch(Throwable $e3){}
    }
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = preg_replace('#^.*\/api#','/api', $uri);
$path = $path ?: $uri;
$method = $_SERVER['REQUEST_METHOD'];

// Health
if ($path === '/api' || $path === '/api/' || $path === '/api/health') {
    jsonResponse(['status'=>'ok','service'=>'zabiss','version'=>'1.0']);
}
if (str_starts_with($path, '/api/auth')) {
    require __DIR__ . '/auth.php';
    exit;
}
// Eleves : détail et sous-ressources
if (preg_match('#^/api/eleves/\d+/(notes|moyennes|presences|paiements|infos)$#', $path)) {
    // dispatch selon suffix
    if (str_ends_with($path, '/notes') || str_ends_with($path, '/moyennes')) { require __DIR__ . '/notes.php'; exit; }
    if (str_ends_with($path, '/presences')) { require __DIR__ . '/presences.php'; exit; }
    if (str_ends_with($path, '/paiements')) { require __DIR__ . '/paiements.php'; exit; }
    if (str_ends_with($path, '/infos')) { require __DIR__ . '/infos.php'; exit; }
}
if (str_starts_with($path, '/api/eleves') || str_starts_with($path, '/api/parent/eleves')) {
    require __DIR__ . '/eleves.php';
    exit;
}
if (str_starts_with($path, '/api/notes') || str_starts_with($path, '/api/moyennes')) {
    require __DIR__ . '/notes.php';
    exit;
}
if (str_starts_with($path, '/api/presences')) {
    require __DIR__ . '/presences.php';
    exit;
}
if (str_starts_with($path, '/api/paiements') || $path === '/api/parent/paiements') {
    require __DIR__ . '/paiements.php';
    exit;
}
if (str_starts_with($path, '/api/infos')) {
    require __DIR__ . '/infos.php';
    exit;
}
jsonResponse(['error'=>'Route non trouvée','path'=>$path], 404);
