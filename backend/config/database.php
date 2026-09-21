<?php
// Zabiss - Configuration BDD
// Supporte MySQL en prod et SQLite pour tests locaux sans MySQL

function getPDO(): PDO {
    $driver = getenv('DB_DRIVER') ?: 'mysql';

    if ($driver === 'sqlite') {
        $path = getenv('DB_SQLITE_PATH') ?: __DIR__ . '/../zabiss.sqlite';
        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $db   = getenv('DB_NAME') ?: 'zabiss';
    $user = getenv('DB_USER') ?: 'zabiss';
    $pass = getenv('DB_PASS') ?: 'zabiss_secret';

    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
}

function jsonResponse(mixed $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function getJsonBody(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function requireAuth(PDO $pdo): array {
    $headers = getallheaders() ?: [];
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (!$auth && isset($_SERVER['HTTP_AUTHORIZATION'])) $auth = $_SERVER['HTTP_AUTHORIZATION'];
    // Fallback : Authorization peut être dans $_SERVER sans HTTP_ prefix sous php -S
    if (!$auth && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) $auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    if (!str_starts_with($auth, 'Bearer ')) {
        jsonResponse(['error' => 'Non authentifié'], 401);
    }
    $token = substr($auth, 7);
    $driver = getenv('DB_DRIVER') ?: 'mysql';
    if ($driver === 'sqlite') {
        $stmt = $pdo->prepare("SELECT s.*, p.id as parent_id, p.nom, p.prenom, p.email FROM sessions s JOIN parents p ON p.id = s.parent_id WHERE s.token = ? LIMIT 1");
        $stmt->execute([$token]);
        $session = $stmt->fetch();
        if ($session && strtotime($session['expires_at']) < time()) $session = false;
    } else {
        $stmt = $pdo->prepare("SELECT s.*, p.id as parent_id, p.nom, p.prenom, p.email FROM sessions s JOIN parents p ON p.id = s.parent_id WHERE s.token = ? AND s.expires_at > NOW() LIMIT 1");
        $stmt->execute([$token]);
        $session = $stmt->fetch();
    }
    if (!$session) jsonResponse(['error' => 'Session expirée'], 401);
    return $session;
}

function generateToken(): string {
    return bin2hex(random_bytes(32));
}

function generateApiKey(): string {
    return 'zbk_' . bin2hex(random_bytes(24));
}

function hashPassword(string $pwd): string {
    return password_hash($pwd, PASSWORD_DEFAULT);
}

// Clé unique demandée : "{id_eleve}_{id_ecole}_{annee_scolaire}" ex: "12_3_2025-2026"
function cleUnique(int|string $eleveId, int|string $ecoleId, string $annee): string {
    $annee = trim($annee) ?: '2025-2026';
    return $eleveId . '_' . $ecoleId . '_' . $annee;
}

function parseCleUnique(string $cle): ?array {
    // format id_eleve_id_ecole_annee (l'année peut contenir des tirets)
    if (!preg_match('/^(\d+)_(\d+)_(.+)$/', $cle, $m)) return null;
    return ['eleve_id' => (int)$m[1], 'ecole_id' => (int)$m[2], 'annee_scolaire' => $m[3]];
}

function tableExists(PDO $pdo, string $table): bool {
    try {
        $driver = getenv('DB_DRIVER') ?: 'mysql';
        if ($driver === 'sqlite') {
            $s = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");
            $s->execute([$table]);
            return (bool)$s->fetchColumn();
        }
        $s = $pdo->prepare("SHOW TABLES LIKE ?");
        $s->execute([$table]);
        return (bool)$s->fetchColumn();
    } catch (Throwable $e) { return false; }
}

function columnExists(PDO $pdo, string $table, string $column): bool {
    try {
        $driver = getenv('DB_DRIVER') ?: 'mysql';
        if ($driver === 'sqlite') {
            $s = $pdo->query("PRAGMA table_info($table)");
            foreach ($s->fetchAll() as $row) {
                if (($row['name'] ?? '') === $column) return true;
            }
            return false;
        }
        $s = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $s->execute([$column]);
        return (bool)$s->fetch();
    } catch (Throwable $e) { return false; }
}

// Migration v2 idempotente : ecoles + dossiers JSON + admin + colonnes
function ensureV2Schema(PDO $pdo): void {
    $driver = getenv('DB_DRIVER') ?: 'mysql';
    try {
        $schemaFile = $driver === 'sqlite'
            ? __DIR__ . '/../sql/schema.sqlite.v2.sql'
            : __DIR__ . '/../sql/schema.v2.sql';
        if (is_file($schemaFile)) {
            $sql = file_get_contents($schemaFile);
            // SQLite : exec multi-statements OK ; MySQL : découper grossièrement
            if ($driver === 'sqlite') {
                $pdo->exec($sql);
            } else {
                // PDO MySQL n'exécute qu'une instruction par exec sans emulation -> découper sur ;
                $stmts = array_filter(array_map('trim', explode(';', $sql)));
                foreach ($stmts as $st) {
                    if ($st === '' || str_starts_with($st, '--')) continue;
                    try { $pdo->exec($st); } catch (Throwable $e) {}
                }
            }
        }
    } catch (Throwable $e) {}

    // Colonnes additionnelles (idempotent)
    try {
        if (tableExists($pdo, 'eleves')) {
            if (!columnExists($pdo, 'eleves', 'ecole_id')) {
                $pdo->exec($driver === 'sqlite' ? "ALTER TABLE eleves ADD COLUMN ecole_id INTEGER" : "ALTER TABLE eleves ADD COLUMN ecole_id INT NULL");
            }
            if (!columnExists($pdo, 'eleves', 'annee_scolaire')) {
                $pdo->exec($driver === 'sqlite' ? "ALTER TABLE eleves ADD COLUMN annee_scolaire TEXT DEFAULT '2025-2026'" : "ALTER TABLE eleves ADD COLUMN annee_scolaire VARCHAR(20) DEFAULT '2025-2026'");
            }
            if (!columnExists($pdo, 'eleves', 'cle_unique')) {
                try { $pdo->exec($driver === 'sqlite' ? "ALTER TABLE eleves ADD COLUMN cle_unique TEXT" : "ALTER TABLE eleves ADD COLUMN cle_unique VARCHAR(120) NULL UNIQUE"); } catch (Throwable $e) {}
            }
            if (!columnExists($pdo, 'eleves', 'donnees_json')) {
                $pdo->exec($driver === 'sqlite' ? "ALTER TABLE eleves ADD COLUMN donnees_json TEXT" : "ALTER TABLE eleves ADD COLUMN donnees_json MEDIUMTEXT NULL");
            }
            // Backfill ecole_id depuis etablissement_id
            if (columnExists($pdo, 'eleves', 'etablissement_id') && columnExists($pdo, 'eleves', 'ecole_id')) {
                try { $pdo->exec("UPDATE eleves SET ecole_id = etablissement_id WHERE ecole_id IS NULL"); } catch (Throwable $e) {}
            }
            if (columnExists($pdo, 'eleves', 'annee_scolaire')) {
                try { $pdo->exec("UPDATE eleves SET annee_scolaire='2025-2026' WHERE annee_scolaire IS NULL OR annee_scolaire=''"); } catch (Throwable $e) {}
            }
        }
        if (tableExists($pdo, 'infos_etablissement') && !columnExists($pdo, 'infos_etablissement', 'ecole_id')) {
            $pdo->exec($driver === 'sqlite' ? "ALTER TABLE infos_etablissement ADD COLUMN ecole_id INTEGER" : "ALTER TABLE infos_etablissement ADD COLUMN ecole_id INT NULL");
            try { $pdo->exec("UPDATE infos_etablissement SET ecole_id = etablissement_id WHERE ecole_id IS NULL"); } catch (Throwable $e) {}
        }
    } catch (Throwable $e) {}

    // Migrer etablissements -> ecoles (si ecoles vide)
    try {
        if (tableExists($pdo, 'ecoles') && tableExists($pdo, 'etablissements')) {
            $cnt = (int)$pdo->query("SELECT COUNT(*) FROM ecoles")->fetchColumn();
            if ($cnt === 0) {
                $rows = $pdo->query("SELECT * FROM etablissements")->fetchAll();
                foreach ($rows as $r) {
                    $code = 'ECOLE-' . $r['id'];
                    $apiKey = generateApiKey();
                    try {
                        $pdo->prepare("INSERT INTO ecoles (id, code, nom, adresse, telephone, email, api_key, actif) VALUES (?,?,?,?,?,?,?,1)")
                            ->execute([$r['id'], $code, $r['nom'], $r['adresse'] ?? null, $r['telephone'] ?? null, $r['email'] ?? null, $apiKey]);
                    } catch (Throwable $e) {}
                }
                // S'il n'y avait aucun établissement, créer l'école démo
                $cnt2 = (int)$pdo->query("SELECT COUNT(*) FROM ecoles")->fetchColumn();
                if ($cnt2 === 0) {
                    $pdo->prepare("INSERT INTO ecoles (code, nom, adresse, telephone, email, api_key, actif) VALUES (?,?,?,?,?,?,1)")
                        ->execute(['ZAB-ABJ', 'Groupe Scolaire Zabiss Excellence', 'Abidjan, Cocody', '+225 07 00 00 00 00', 'contact@zabiss.ci', generateApiKey()]);
                }
            }
        }
    } catch (Throwable $e) {}

    // Admin par défaut
    try {
        if (tableExists($pdo, 'admins')) {
            $cnt = (int)$pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
            if ($cnt === 0) {
                $pdo->prepare("INSERT INTO admins (nom, email, password_hash, role) VALUES (?,?,?,?)")
                    ->execute(['Administrateur', 'admin@zabiss.ci', password_hash('admin123', PASSWORD_DEFAULT), 'admin']);
            }
        }
    } catch (Throwable $e) {}

    // Backfill cle_unique eleves
    try {
        if (tableExists($pdo, 'eleves') && columnExists($pdo, 'eleves', 'cle_unique')) {
            $rows = $pdo->query("SELECT id, COALESCE(ecole_id, etablissement_id, 1) as ec, COALESCE(annee_scolaire,'2025-2026') as an FROM eleves WHERE cle_unique IS NULL")->fetchAll();
            foreach ($rows as $r) {
                $cle = cleUnique($r['id'], $r['ec'] ?? 1, $r['an'] ?? '2025-2026');
                try { $pdo->prepare("UPDATE eleves SET cle_unique=? WHERE id=?")->execute([$cle, $r['id']]); } catch (Throwable $e) {}
            }
        }
    } catch (Throwable $e) {}
}

// Auth application externe via X-API-KEY (clé de l'école)
function requireApiKey(PDO $pdo): array {
    $headers = getallheaders() ?: [];
    $key = $headers['X-API-KEY'] ?? $headers['x-api-key'] ?? $headers['X-Api-Key'] ?? '';
    if (!$key && isset($_SERVER['HTTP_X_API_KEY'])) $key = $_SERVER['HTTP_X_API_KEY'];
    if (!$key) {
        // fallback : ?api_key=
        $key = $_GET['api_key'] ?? '';
    }
    if (!$key) jsonResponse(['error' => 'Clé API manquante (header X-API-KEY)'], 401);
    $stmt = $pdo->prepare("SELECT * FROM ecoles WHERE api_key=? LIMIT 1");
    $stmt->execute([$key]);
    $ecole = $stmt->fetch();
    if (!$ecole) jsonResponse(['error' => 'Clé API invalide'], 401);
    if (isset($ecole['actif']) && (int)$ecole['actif'] === 0) jsonResponse(['error' => 'École désactivée'], 403);
    return $ecole;
}

// Auth admin via Bearer (table admin_sessions)
function requireAdmin(PDO $pdo): array {
    $headers = getallheaders() ?: [];
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (!$auth && isset($_SERVER['HTTP_AUTHORIZATION'])) $auth = $_SERVER['HTTP_AUTHORIZATION'];
    if (!str_starts_with($auth, 'Bearer ')) jsonResponse(['error' => 'Non authentifié (admin)'], 401);
    $token = substr($auth, 7);
    $driver = getenv('DB_DRIVER') ?: 'mysql';
    if ($driver === 'sqlite') {
        $stmt = $pdo->prepare("SELECT s.*, a.id as admin_id, a.nom, a.email, a.role FROM admin_sessions s JOIN admins a ON a.id=s.admin_id WHERE s.token=? LIMIT 1");
        $stmt->execute([$token]);
        $sess = $stmt->fetch();
        if ($sess && strtotime($sess['expires_at']) < time()) $sess = false;
    } else {
        $stmt = $pdo->prepare("SELECT s.*, a.id as admin_id, a.nom, a.email, a.role FROM admin_sessions s JOIN admins a ON a.id=s.admin_id WHERE s.token=? AND s.expires_at > NOW() LIMIT 1");
        $stmt->execute([$token]);
        $sess = $stmt->fetch();
    }
    if (!$sess) jsonResponse(['error' => 'Session admin expirée'], 401);
    return $sess;
}
