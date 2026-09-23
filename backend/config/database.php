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

// Clés uniques :
// - table eleve (dossier identité) : "{id_eleve}" ex: "12"
// - notes / presences / paiements : une ligne par élève et par année,
//   clé "{id_eleve}|{annee_scolaire}" ex: "12|2025-2026", données en JSON.
function cleUniqueEleve(int|string $eleveId): string {
    return (string)$eleveId;
}

function cleUniqueAnnee(int|string $eleveId, string $annee): string {
    $annee = trim($annee) ?: '2025-2026';
    return $eleveId . '|' . $annee;
}

// Ancien format (déprécié, encore accepté en lecture) : "{id_eleve}_{id_ecole}_{annee}"
function cleUnique(int|string $eleveId, int|string $ecoleId, string $annee): string {
    return cleUniqueAnnee($eleveId, $annee);
}

function parseCleUnique(string $cle): ?array {
    // Nouveau : "12" (eleve) ou "12|2025-2026" (eleve + année)
    if (preg_match('/^(\d+)$/', $cle, $m)) {
        return ['eleve_id' => (int)$m[1], 'ecole_id' => null, 'annee_scolaire' => null];
    }
    if (preg_match('/^(\d+)\|(.+)$/', $cle, $m)) {
        return ['eleve_id' => (int)$m[1], 'ecole_id' => null, 'annee_scolaire' => $m[2]];
    }
    // Legacy : "12_3_2025-2026"
    if (preg_match('/^(\d+)_(\d+)_(.+)$/', $cle, $m)) {
        return ['eleve_id' => (int)$m[1], 'ecole_id' => (int)$m[2], 'annee_scolaire' => $m[3]];
    }
    return null;
}

// Année scolaire la plus récente ayant des données pour un élève (défaut d'affichage parent)
function anneesDisponibles(PDO $pdo, int $eleveId): array {
    $annees = [];
    try {
        $s = $pdo->prepare("SELECT DISTINCT annee_scolaire FROM notes WHERE eleve_id=? AND annee_scolaire IS NOT NULL AND annee_scolaire<>''");
        $s->execute([$eleveId]);
        foreach ($s->fetchAll() as $r) { if (!empty($r['annee_scolaire'])) $annees[] = $r['annee_scolaire']; }
    } catch (Throwable $e) {}
    try {
        $s = $pdo->prepare("SELECT DISTINCT annee_scolaire FROM paiements WHERE eleve_id=? AND annee_scolaire IS NOT NULL AND annee_scolaire<>''");
        $s->execute([$eleveId]);
        foreach ($s->fetchAll() as $r) { if (!empty($r['annee_scolaire'])) $annees[] = $r['annee_scolaire']; }
    } catch (Throwable $e) {}
    foreach (['notes_moyennes_dossiers', 'presence_dossiers', 'paiements_dossiers'] as $tbl) {
        try {
            $s = $pdo->prepare("SELECT DISTINCT annee_scolaire FROM $tbl WHERE eleve_id=?");
            $s->execute([$eleveId]);
            foreach ($s->fetchAll() as $r) { if (!empty($r['annee_scolaire'])) $annees[] = $r['annee_scolaire']; }
        } catch (Throwable $e) {}
    }
    // Présences : année scolaire déduite de la date (rentrée en septembre)
    try {
        $s = $pdo->prepare("SELECT DISTINCT date_jour FROM presences WHERE eleve_id=?");
        $s->execute([$eleveId]);
        foreach ($s->fetchAll() as $r) {
            $d = $r['date_jour'] ?? null;
            if ($d && preg_match('/^(\d{4})-(\d{2})-\d{2}/', $d, $m)) {
                $y = (int)$m[1]; $mo = (int)$m[2];
                $annees[] = $mo >= 9 ? "$y-" . ($y + 1) : ($y - 1) . "-$y";
            }
        }
    } catch (Throwable $e) {}
    $annees = array_values(array_unique($annees));
    rsort($annees);
    return $annees;
}

function anneeLaPlusRecente(PDO $pdo, int $eleveId): ?string {
    $liste = anneesDisponibles($pdo, $eleveId);
    return $liste[0] ?? null;
}

// Plage de dates d'une année scolaire "2025-2026" (septembre -> août)
function anneeToRange(string $annee): ?array {
    if (!preg_match('/^(\d{4})\s*-\s*(\d{4})$/', trim($annee), $m)) return null;
    return [$m[1] . '-09-01', $m[2] . '-08-31'];
}

// ---------- Stockage CSV (affichage) ----------
// Format notes :
//   ::NOM_PERIODE;RANG;MOYENNE;APPRECIATION;MOY_PREMIER   (début de bloc)
//   :Discipline;Devoir 1;...;Moyenne                      (entêtes, ":" unique)
//   Français;12;11.25;...                                (lignes)
// Format présences :
//   ::NOM_PERIODE;TOTAL;PRESENTS;ABSENTS;RETARDS;TAUX
//   :Date;Statut;Motif;Justifié
//   2025-11-01;present;;Non

function csvEscape($v): string {
    $s = (string)($v ?? '');
    if (str_contains($s, '"') || str_contains($s, ';') || str_contains($s, "\n") || str_contains($s, "\r")) {
        return '"' . str_replace('"', '""', $s) . '"';
    }
    return $s;
}

// Découpe une ligne CSV ";" en respectant les guillemets
function csvSplitLine(string $line): array {
    $out = [];
    $cur = '';
    $inQ = false;
    $len = strlen($line);
    for ($i = 0; $i < $len; $i++) {
        $ch = $line[$i];
        if ($inQ) {
            if ($ch === '"') {
                if ($i + 1 < $len && $line[$i + 1] === '"') { $cur .= '"'; $i++; }
                else $inQ = false;
            } else $cur .= $ch;
        } else {
            if ($ch === '"') $inQ = true;
            elseif ($ch === ';') { $out[] = $cur; $cur = ''; }
            else $cur .= $ch;
        }
    }
    $out[] = $cur;
    return array_map('trim', $out);
}

// Construit un CSV à blocs : [['meta'=>[...], 'headers'=>[...]|null, 'rows'=>[[...]]]]
function buildCsvBlocs(array $blocs): string {
    $lines = [];
    foreach ($blocs as $b) {
        $lines[] = '::' . implode(';', array_map('csvEscape', $b['meta'] ?? []));
        if (!empty($b['headers'])) $lines[] = ':' . implode(';', array_map('csvEscape', $b['headers']));
        foreach ($b['rows'] ?? [] as $row) {
            $lines[] = implode(';', array_map('csvEscape', $row));
        }
        $lines[] = '';
    }
    return rtrim(implode("\n", $lines), "\n") . "\n";
}

// Parse un CSV à blocs -> mêmes structures (robuste : ignore lignes vides)
function parseCsvBlocs(string $csv): array {
    $blocs = [];
    $current = null;
    foreach (preg_split('/\r\n|\r|\n/', $csv) as $raw) {
        $line = trim($raw);
        if ($line === '') continue;
        if (str_starts_with($line, '::')) {
            if ($current !== null) $blocs[] = $current;
            $current = ['meta' => csvSplitLine(substr($line, 2)), 'headers' => null, 'rows' => []];
        } elseif (str_starts_with($line, ':')) {
            if ($current === null) $current = ['meta' => [], 'headers' => null, 'rows' => []];
            $current['headers'] = csvSplitLine(substr($line, 1));
        } else {
            if ($current === null) $current = ['meta' => [], 'headers' => null, 'rows' => []];
            $current['rows'][] = csvSplitLine($line);
        }
    }
    if ($current !== null) $blocs[] = $current;
    return $blocs;
}

function appreciationMoyenne($moy): string {
    if ($moy === null) return '';
    if ($moy >= 16) return 'Très bien';
    if ($moy >= 14) return 'Bien';
    if ($moy >= 12) return 'Assez bien';
    if ($moy >= 10) return 'Passable';
    return 'Insuffisant';
}

// Construit le CSV notes depuis les tables normalisées (1 bloc / période de l'année)
function buildNotesCsvFromDb(PDO $pdo, int $eleveId, ?string $annee): string {
    $anneeSql = ($annee && preg_match('/^\d{4}-\d{4}$/', $annee)) ? " AND n.annee_scolaire=" . $pdo->quote($annee) . " " : "";
    try {
        $s = $pdo->prepare("SELECT DISTINCT n.periode FROM notes n WHERE n.eleve_id=? $anneeSql ORDER BY n.periode");
        $s->execute([$eleveId]);
        $periodes = array_values(array_filter(array_column($s->fetchAll(), 'periode')));
    } catch (Throwable $e) { return ''; }
    if (!$periodes) return '';
    // Classe de l'élève pour rang / moy premier par période
    try {
        $c = $pdo->prepare("SELECT classe_id FROM eleves WHERE id=?");
        $c->execute([$eleveId]);
        $classeId = $c->fetchColumn();
        $idsClasse = [];
        if ($classeId) {
            $q = $pdo->prepare("SELECT id FROM eleves WHERE classe_id=?");
            $q->execute([$classeId]);
            $idsClasse = array_column($q->fetchAll(), 'id');
        }
    } catch (Throwable $e) { $classeId = null; $idsClasse = []; }

    $blocs = [];
    foreach ($periodes as $per) {
        // Moyennes par matière sur la période (+ année)
        $stmt = $pdo->prepare("
            SELECT m.nom as matiere, m.coefficient as coef, AVG(n.note*20/n.note_sur) as moy, COUNT(*) as nb
            FROM notes n JOIN matieres m ON m.id=n.matiere_id
            WHERE n.eleve_id=? AND n.periode=" . $pdo->quote($per) . " $anneeSql
            GROUP BY m.id ORDER BY m.nom");
        $stmt->execute([$eleveId]);
        $rowsMat = $stmt->fetchAll();
        $tp = 0; $tc = 0;
        $rows = [];
        foreach ($rowsMat as $rm) {
            $moy = round((float)$rm['moy'], 2);
            $tp += $moy * (int)$rm['coef']; $tc += (int)$rm['coef'];
            $rows[] = [$rm['matiere'], number_format($moy, 2, '.', ''), (string)$rm['nb']];
        }
        $moyGen = $tc ? round($tp / $tc, 2) : null;
        // Rang + moy premier dans la classe sur la période
        $rangTxt = '-'; $moyPremier = '';
        if ($idsClasse) {
            $moyClasse = [];
            foreach ($idsClasse as $idc) {
                $s2 = $pdo->prepare("SELECT m.coefficient, AVG(n.note*20/n.note_sur) as moy FROM notes n JOIN matieres m ON m.id=n.matiere_id WHERE n.eleve_id=? AND n.periode=" . $pdo->quote($per) . " $anneeSql GROUP BY m.id");
                $s2->execute([$idc]);
                $rr = $s2->fetchAll();
                $a = 0; $b = 0;
                foreach ($rr as $rw) { $a += $rw['moy'] * $rw['coefficient']; $b += $rw['coefficient']; }
                $moyClasse[$idc] = $b ? $a / $b : 0;
            }
            arsort($moyClasse);
            $pos = 1;
            foreach ($moyClasse as $k => $v) {
                if ((int)$k === $eleveId) { $rangTxt = $pos . '/' . count($moyClasse); break; }
                $pos++;
            }
            $moyPremier = $moyClasse ? number_format(round(max($moyClasse), 2), 2, '.', '') : '';
        }
        $blocs[] = [
            'meta' => [$per, $rangTxt, $moyGen !== null ? number_format($moyGen, 2, '.', '') : '', appreciationMoyenne($moyGen), $moyPremier],
            'headers' => ['Discipline', 'Moyenne', 'Nb notes'],
            'rows' => $rows,
        ];
    }
    return buildCsvBlocs($blocs);
}

// Construit le CSV présences depuis la table normalisée (1 bloc / mois)
function buildPresenceCsvFromDb(PDO $pdo, int $eleveId, ?string $annee): string {
    $range = $annee ? anneeToRange($annee) : null;
    try {
        if ($range) {
            $s = $pdo->prepare("SELECT * FROM presences WHERE eleve_id=? AND date_jour BETWEEN ? AND ? ORDER BY date_jour ASC");
            $s->execute([$eleveId, $range[0], $range[1]]);
        } else {
            $s = $pdo->prepare("SELECT * FROM presences WHERE eleve_id=? ORDER BY date_jour ASC");
            $s->execute([$eleveId]);
        }
        $rows = $s->fetchAll();
    } catch (Throwable $e) { return ''; }
    if (!$rows) return '';
    // Grouper par mois YYYY-MM
    $parMois = [];
    foreach ($rows as $r) {
        $mois = substr($r['date_jour'] ?? '', 0, 7);
        if (!$mois) $mois = 'Divers';
        $parMois[$mois][] = $r;
    }
    ksort($parMois);
    $blocs = [];
    foreach ($parMois as $mois => $lignes) {
        $tot = count($lignes);
        $pres = count(array_filter($lignes, fn($x) => ($x['statut'] ?? '') === 'present'));
        $abs = count(array_filter($lignes, fn($x) => ($x['statut'] ?? '') === 'absent'));
        $ret = count(array_filter($lignes, fn($x) => ($x['statut'] ?? '') === 'retard'));
        $taux = $tot ? round($pres / $tot * 100, 1) : 100;
        $csvRows = [];
        foreach ($lignes as $l) {
            $csvRows[] = [$l['date_jour'] ?? '', $l['statut'] ?? '', $l['motif'] ?? '', !empty($l['justifie']) ? 'Oui' : 'Non'];
        }
        $blocs[] = [
            'meta' => [$mois, (string)$tot, (string)$pres, (string)$abs, (string)$ret, (string)$taux],
            'headers' => ['Date', 'Statut', 'Motif', 'Justifié'],
            'rows' => $csvRows,
        ];
    }
    return buildCsvBlocs($blocs);
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
        // Stockage CSV (rendu affichage) pour notes et présences : colonne donnees_csv
        foreach (['notes_moyennes_dossiers', 'presence_dossiers'] as $csvTbl) {
            if (tableExists($pdo, $csvTbl) && !columnExists($pdo, $csvTbl, 'donnees_csv')) {
                try { $pdo->exec($driver === 'sqlite' ? "ALTER TABLE $csvTbl ADD COLUMN donnees_csv TEXT" : "ALTER TABLE $csvTbl ADD COLUMN donnees_csv MEDIUMTEXT NULL"); } catch (Throwable $e) {}
            }
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

    // Backfill cle_unique eleves : clé = id eleve seul
    try {
        if (tableExists($pdo, 'eleves') && columnExists($pdo, 'eleves', 'cle_unique')) {
            // Normaliser les anciennes clés 3-parties vers l'id seul
            try {
                $rows = $pdo->query("SELECT id, cle_unique FROM eleves WHERE cle_unique IS NOT NULL")->fetchAll();
                foreach ($rows as $r) {
                    if ((string)$r['cle_unique'] !== (string)$r['id']) {
                        try { $pdo->prepare("UPDATE eleves SET cle_unique=? WHERE id=?")->execute([(string)$r['id'], $r['id']]); } catch (Throwable $e) {}
                    }
                }
            } catch (Throwable $e) {}
            $rows = $pdo->query("SELECT id FROM eleves WHERE cle_unique IS NULL")->fetchAll();
            foreach ($rows as $r) {
                try { $pdo->prepare("UPDATE eleves SET cle_unique=? WHERE id=?")->execute([(string)$r['id'], $r['id']]); } catch (Throwable $e) {}
            }
        }
    } catch (Throwable $e) {}

    // Migration dossiers JSON : anciennes clés "E_C_A" -> "E" (eleve) / "E|A" (annuels)
    try {
        $migrate = [
            'eleve_dossiers' => 'eleve',
            'notes_moyennes_dossiers' => 'annee',
            'presence_dossiers' => 'annee',
            'paiements_dossiers' => 'annee',
        ];
        foreach ($migrate as $tbl => $mode) {
            if (!tableExists($pdo, $tbl)) continue;
            try { $rows = $pdo->query("SELECT * FROM $tbl")->fetchAll(); }
            catch (Throwable $e) { continue; }
            foreach ($rows as $r) {
                $old = $r['cle_unique'] ?? '';
                $p = parseCleUnique((string)$old);
                if (!$p) continue;
                $new = $mode === 'eleve'
                    ? cleUniqueEleve($p['eleve_id'] ?? $r['eleve_id'])
                    : cleUniqueAnnee($r['eleve_id'], $p['annee_scolaire'] ?? $r['annee_scolaire']);
                if ($new === $old) continue;
                try {
                    // Garder la ligne la plus récente en cas de collision
                    $chk = $pdo->prepare("SELECT updated_at FROM $tbl WHERE cle_unique=? LIMIT 1");
                    $chk->execute([$new]);
                    $existing = $chk->fetch();
                    if ($existing) {
                        $oldTs = strtotime($r['updated_at'] ?? '2000-01-01') ?: 0;
                        $newTs = strtotime($existing['updated_at'] ?? '2000-01-01') ?: 0;
                        if ($oldTs >= $newTs) {
                            $pdo->prepare("UPDATE $tbl SET donnees_json=?, updated_at=CURRENT_TIMESTAMP WHERE cle_unique=?")
                                ->execute([$r['donnees_json'], $new]);
                        }
                        $pdo->prepare("DELETE FROM $tbl WHERE cle_unique=?")->execute([$old]);
                    } else {
                        $pdo->prepare("UPDATE $tbl SET cle_unique=? WHERE cle_unique=?")->execute([$new, $old]);
                    }
                } catch (Throwable $e) {}
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
