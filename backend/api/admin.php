<?php
// Console admin : POST /api/admin/login, puis Bearer admin

if ($path === '/api/admin/login' && $method === 'POST') {
    $body = getJsonBody();
    $email = trim($body['email'] ?? '');
    $password = $body['password'] ?? '';
    if (!$email || !$password) jsonResponse(['error' => 'Email et mot de passe requis'], 400);
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE email=? LIMIT 1");
    $stmt->execute([$email]);
    $admin = $stmt->fetch();
    if (!$admin || !password_verify($password, $admin['password_hash'])) jsonResponse(['error' => 'Identifiants admin invalides'], 401);
    $token = generateToken();
    $expires = date('Y-m-d H:i:s', time() + 60 * 60 * 12);
    $pdo->prepare("INSERT INTO admin_sessions (admin_id, token, expires_at) VALUES (?,?,?)")->execute([$admin['id'], $token, $expires]);
    jsonResponse(['token' => $token, 'admin' => ['id' => (int)$admin['id'], 'nom' => $admin['nom'], 'email' => $admin['email'], 'role' => $admin['role']]]);
}

if ($path === '/api/admin/me' && $method === 'GET') {
    $s = requireAdmin($pdo);
    jsonResponse(['admin' => ['id' => (int)$s['admin_id'], 'nom' => $s['nom'], 'email' => $s['email'], 'role' => $s['role']]]);
}

if ($path === '/api/admin/logout' && $method === 'POST') {
    $s = requireAdmin($pdo);
    $h = getallheaders()['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $pdo->prepare("DELETE FROM admin_sessions WHERE token=?")->execute([str_replace('Bearer ', '', $h)]);
    jsonResponse(['ok' => true]);
}

// ---- Stats ----
if ($path === '/api/admin/stats' && $method === 'GET') {
    requireAdmin($pdo);
    $out = [];
    foreach (['parents' => 'parents', 'eleves' => 'eleves', 'ecoles' => 'ecoles', 'infos_etablissement' => 'infos', 'notes' => 'notes', 'presences' => 'presences', 'paiements' => 'paiements'] as $tbl => $k) {
        try { $out[$k] = (int)$pdo->query("SELECT COUNT(*) FROM $tbl")->fetchColumn(); }
        catch (Throwable $e) { $out[$k] = 0; }
    }
    foreach (['eleve_dossiers' => 'dossiers_eleve', 'notes_moyennes_dossiers' => 'dossiers_notes', 'presence_dossiers' => 'dossiers_presences', 'paiements_dossiers' => 'dossiers_paiements'] as $tbl => $k) {
        try { $out[$k] = (int)$pdo->query("SELECT COUNT(*) FROM $tbl")->fetchColumn(); }
        catch (Throwable $e) { $out[$k] = 0; }
    }
    jsonResponse($out);
}

// ---- Ecoles ----
if ($path === '/api/admin/ecoles' && $method === 'GET') {
    requireAdmin($pdo);
    $rows = $pdo->query("SELECT *, (SELECT COUNT(*) FROM eleves WHERE COALESCE(ecole_id, etablissement_id)=ecoles.id) as nb_eleves FROM ecoles ORDER BY nom")->fetchAll();
    jsonResponse($rows);
}
if ($path === '/api/admin/ecoles' && $method === 'POST') {
    requireAdmin($pdo);
    $b = getJsonBody();
    $nom = trim($b['nom'] ?? '');
    if (!$nom) jsonResponse(['error' => 'Nom requis'], 400);
    $code = trim($b['code'] ?? ('ECOLE-' . time()));
    $apiKey = generateApiKey();
    try {
        $pdo->prepare("INSERT INTO ecoles (code, nom, adresse, telephone, email, api_key, actif) VALUES (?,?,?,?,?,?,1)")
            ->execute([$code, $nom, $b['adresse'] ?? null, $b['telephone'] ?? null, $b['email'] ?? null, $apiKey]);
        $id = $pdo->lastInsertId();
        // miroir etablissements pour compat FK
        try { $pdo->prepare("INSERT INTO etablissements (id, nom, adresse, telephone, email) VALUES (?,?,?,?,?)")->execute([$id, $nom, $b['adresse'] ?? null, $b['telephone'] ?? null, $b['email'] ?? null]); } catch (Throwable $e) {}
        jsonResponse(['ok' => true, 'id' => (int)$id, 'api_key' => $apiKey], 201);
    } catch (Throwable $e) { jsonResponse(['error' => 'Création impossible (code déjà utilisé ?)'], 409); }
}
if (preg_match('#^/api/admin/ecoles/(\d+)$#', $path, $m) && $method === 'PUT') {
    requireAdmin($pdo);
    $b = getJsonBody();
    $pdo->prepare("UPDATE ecoles SET nom=COALESCE(?,nom), adresse=?, telephone=?, email=?, actif=COALESCE(?,actif) WHERE id=?")
        ->execute([$b['nom'] ?? null, $b['adresse'] ?? null, $b['telephone'] ?? null, $b['email'] ?? null, $b['actif'] ?? null, $m[1]]);
    jsonResponse(['ok' => true]);
}
if (preg_match('#^/api/admin/ecoles/(\d+)/regen-key$#', $path, $m) && $method === 'POST') {
    requireAdmin($pdo);
    $new = generateApiKey();
    $pdo->prepare("UPDATE ecoles SET api_key=? WHERE id=?")->execute([$new, $m[1]]);
    jsonResponse(['ok' => true, 'api_key' => $new]);
}

// ---- Eleves (admin) ----
if ($path === '/api/admin/eleves' && $method === 'GET') {
    requireAdmin($pdo);
    $search = trim($_GET['search'] ?? '');
    $ecoleId = $_GET['ecole_id'] ?? null;
    $sql = "SELECT e.*, COALESCE(ec.nom, et.nom) as ecole_nom, COALESCE(e.ecole_id, e.etablissement_id) as ecole_id FROM eleves e LEFT JOIN ecoles ec ON ec.id=COALESCE(e.ecole_id, e.etablissement_id) LEFT JOIN etablissements et ON et.id=e.etablissement_id WHERE 1=1 ";
    $params = [];
    if ($ecoleId) { $sql .= " AND COALESCE(e.ecole_id, e.etablissement_id)=" . ((int)$ecoleId) . " "; }
    if ($search) { $sql .= " AND (e.nom LIKE ? OR e.prenom LIKE ? OR e.matricule LIKE ? OR e.login LIKE ?) "; $s = "%$search%"; array_push($params, $s, $s, $s, $s); }
    $sql .= " ORDER BY e.nom LIMIT 200";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) unset($r['password_hash']);
    jsonResponse($rows);
}
if (preg_match('#^/api/admin/eleves/(\d+)$#', $path, $m) && $method === 'GET') {
    requireAdmin($pdo);
    $eid = $m[1];
    $stmt = $pdo->prepare("SELECT e.*, COALESCE(ec.nom, et.nom) as ecole_nom FROM eleves e LEFT JOIN ecoles ec ON ec.id=COALESCE(e.ecole_id,e.etablissement_id) LEFT JOIN etablissements et ON et.id=e.etablissement_id WHERE e.id=?");
    $stmt->execute([$eid]);
    $eleve = $stmt->fetch();
    if (!$eleve) jsonResponse(['error' => 'Introuvable'], 404);
    unset($eleve['password_hash']);
    $ecoleId = $eleve['ecole_id'] ?? $eleve['etablissement_id'] ?? 1;
    $annee = $eleve['annee_scolaire'] ?? '2025-2026';
    $cle = cleUnique((int)$eid, (int)$ecoleId, (string)$annee);
    $eleve['cle_unique_calculee'] = $cle;
    foreach (['eleve_dossiers', 'notes_moyennes_dossiers', 'presence_dossiers', 'paiements_dossiers'] as $tbl) {
        try {
            $s = $pdo->prepare("SELECT * FROM $tbl WHERE cle_unique=? LIMIT 1");
            $s->execute([$cle]);
            $eleve[$tbl] = $s->fetch() ?: null;
            if ($eleve[$tbl] && isset($eleve[$tbl]['donnees_json'])) {
                $eleve[$tbl]['donnees'] = json_decode($eleve[$tbl]['donnees_json'], true);
            }
        } catch (Throwable $e) { $eleve[$tbl] = null; }
    }
    jsonResponse($eleve);
}
if ($path === '/api/admin/eleves' && $method === 'POST') {
    requireAdmin($pdo);
    $b = getJsonBody();
    foreach (['matricule', 'login', 'nom', 'prenom'] as $f) {
        if (empty($b[$f])) jsonResponse(['error' => "Champ $f requis"], 400);
    }
    $ecoleId = (int)($b['ecole_id'] ?? 1);
    $annee = $b['annee_scolaire'] ?? '2025-2026';
    $pwd = $b['password'] ?? 'eleve123';
    try {
        $pdo->prepare("INSERT INTO eleves (matricule, login, password_hash, nom, prenom, date_naissance, sexe, classe_id, etablissement_id, ecole_id, annee_scolaire) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$b['matricule'], $b['login'], hashPassword($pwd), $b['nom'], $b['prenom'], $b['date_naissance'] ?? null, $b['sexe'] ?? 'M', $b['classe_id'] ?? null, $ecoleId, $ecoleId, $annee]);
        $id = (int)$pdo->lastInsertId();
        $cle = cleUnique($id, $ecoleId, $annee);
        try { $pdo->prepare("UPDATE eleves SET cle_unique=? WHERE id=?")->execute([$cle, $id]); } catch (Throwable $e) {}
        jsonResponse(['ok' => true, 'id' => $id, 'cle_unique' => $cle], 201);
    } catch (Throwable $e) { jsonResponse(['error' => 'Matricule/login déjà utilisé'], 409); }
}

// ---- Dossiers JSON (lecture admin) ----
if ($path === '/api/admin/dossiers' && $method === 'GET') {
    requireAdmin($pdo);
    $cle = $_GET['cle_unique'] ?? null;
    $eleveId = $_GET['eleve_id'] ?? null;
    $out = [];
    foreach (['eleve_dossiers', 'notes_moyennes_dossiers', 'presence_dossiers', 'paiements_dossiers'] as $tbl) {
        try {
            if ($cle) {
                $s = $pdo->prepare("SELECT * FROM $tbl WHERE cle_unique=?");
                $s->execute([$cle]);
            } elseif ($eleveId) {
                $s = $pdo->prepare("SELECT * FROM $tbl WHERE eleve_id=? ORDER BY annee_scolaire DESC");
                $s->execute([$eleveId]);
            } else {
                $s = $pdo->query("SELECT * FROM $tbl ORDER BY updated_at DESC LIMIT 100");
            }
            $rows = $s->fetchAll();
            foreach ($rows as &$r) { $r['donnees'] = json_decode($r['donnees_json'] ?? '{}', true); }
            $out[$tbl] = $rows;
        } catch (Throwable $e) { $out[$tbl] = []; }
    }
    jsonResponse($out);
}

// ---- Parents ----
if ($path === '/api/admin/parents' && $method === 'GET') {
    requireAdmin($pdo);
    $rows = $pdo->query("SELECT p.*, (SELECT COUNT(*) FROM parent_eleve pe WHERE pe.parent_id=p.id) as nb_enfants FROM parents p ORDER BY p.created_at DESC LIMIT 200")->fetchAll();
    foreach ($rows as &$r) unset($r['password_hash']);
    jsonResponse($rows);
}

// ---- Infos (CRUD admin) ----
if ($path === '/api/admin/infos' && $method === 'GET') {
    requireAdmin($pdo);
    $rows = $pdo->query("SELECT i.*, COALESCE(ec.nom, et.nom) as ecole_nom FROM infos_etablissement i LEFT JOIN ecoles ec ON ec.id=COALESCE(i.ecole_id,i.etablissement_id) LEFT JOIN etablissements et ON et.id=i.etablissement_id ORDER BY i.date_publication DESC LIMIT 200")->fetchAll();
    jsonResponse($rows);
}
if ($path === '/api/admin/infos' && $method === 'POST') {
    requireAdmin($pdo);
    $b = getJsonBody();
    if (empty($b['titre']) || empty($b['contenu'])) jsonResponse(['error' => 'Titre + contenu requis'], 400);
    $ecoleId = $b['ecole_id'] ?? $b['etablissement_id'] ?? null;
    $pdo->prepare("INSERT INTO infos_etablissement (etablissement_id, ecole_id, titre, contenu, type_info, date_evenement, important) VALUES (?,?,?,?,?,?,?)")
        ->execute([$ecoleId, $ecoleId, $b['titre'], $b['contenu'], $b['type_info'] ?? 'general', $b['date_evenement'] ?? null, (int)($b['important'] ?? 0)]);
    jsonResponse(['ok' => true, 'id' => (int)$pdo->lastInsertId()], 201);
}
if (preg_match('#^/api/admin/infos/(\d+)$#', $path, $m) && $method === 'DELETE') {
    requireAdmin($pdo);
    $pdo->prepare("DELETE FROM infos_etablissement WHERE id=?")->execute([$m[1]]);
    jsonResponse(['ok' => true]);
}

jsonResponse(['error' => 'Route admin non trouvée'], 404);
