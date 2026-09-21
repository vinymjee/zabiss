<?php
// API externe pour applications écoles : auth via X-API-KEY (clé de l'école)
// Chaque écriture crée/maj le dossier JSON indexé par cle_unique = "{id_eleve}_{id_ecole}_{annee}"
// + synchronise les tables normalisées (notes, presences, paiements) pour affichage parent.

function resolveEleveExterne(PDO $pdo, array $ecole, array $body): ?array {
    $ecoleId = (int)$ecole['id'];
    // 1) cle_unique directe
    if (!empty($body['cle_unique'])) {
        $p = parseCleUnique($body['cle_unique']);
        if ($p) {
            $s = $pdo->prepare("SELECT * FROM eleves WHERE id=? LIMIT 1");
            $s->execute([$p['eleve_id']]);
            if ($e = $s->fetch()) return $e;
        }
    }
    // 2) eleve_id
    if (!empty($body['eleve_id'])) {
        $s = $pdo->prepare("SELECT * FROM eleves WHERE id=? LIMIT 1");
        $s->execute([(int)$body['eleve_id']]);
        if ($e = $s->fetch()) return $e;
    }
    // 3) matricule (+ ecole si possible)
    if (!empty($body['matricule'])) {
        $s = $pdo->prepare("SELECT * FROM eleves WHERE matricule=? LIMIT 1");
        $s->execute([$body['matricule']]);
        if ($e = $s->fetch()) return $e;
    }
    // 4) login
    if (!empty($body['login'])) {
        $s = $pdo->prepare("SELECT * FROM eleves WHERE login=? LIMIT 1");
        $s->execute([$body['login']]);
        if ($e = $s->fetch()) return $e;
    }
    return null;
}

function upsertDossier(PDO $pdo, string $table, string $cle, int $eleveId, int $ecoleId, string $annee, array $donnees): void {
    $json = json_encode($donnees, JSON_UNESCAPED_UNICODE);
    $driver = getenv('DB_DRIVER') ?: 'mysql';
    if ($driver === 'sqlite') {
        $pdo->prepare("INSERT INTO $table (cle_unique, eleve_id, ecole_id, annee_scolaire, donnees_json, updated_at) VALUES (?,?,?,?,?,datetime('now')) ON CONFLICT(cle_unique) DO UPDATE SET donnees_json=excluded.donnees_json, updated_at=datetime('now')")
            ->execute([$cle, $eleveId, $ecoleId, $annee, $json]);
    } else {
        $pdo->prepare("INSERT INTO $table (cle_unique, eleve_id, ecole_id, annee_scolaire, donnees_json) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE donnees_json=VALUES(donnees_json)")
            ->execute([$cle, $eleveId, $ecoleId, $annee, $json]);
    }
}

// ---- Health ----
if (($path === '/api/externes/health' || $path === '/api/external/health') && $method === 'GET') {
    $ecole = requireApiKey($pdo);
    jsonResponse(['ok' => true, 'ecole' => ['id' => (int)$ecole['id'], 'code' => $ecole['code'], 'nom' => $ecole['nom']]]);
}

// ---- Upsert ELEVE ----
if (($path === '/api/externes/eleves' || $path === '/api/external/eleves') && $method === 'POST') {
    $ecole = requireApiKey($pdo);
    $b = getJsonBody();
    $annee = $b['annee_scolaire'] ?? '2025-2026';
    $existing = resolveEleveExterne($pdo, $ecole, $b);

    if ($existing) {
        // update champs si fournis
        $fields = [];
        $params = [];
        foreach (['nom' => 'nom', 'prenom' => 'prenom', 'date_naissance' => 'date_naissance', 'sexe' => 'sexe', 'classe_id' => 'classe_id', 'photo_url' => 'photo_url'] as $k => $col) {
            if (array_key_exists($k, $b)) { $fields[] = "$col=?"; $params[] = $b[$k]; }
        }
        // mot de passe élève : si fourni, re-hash
        if (!empty($b['password'])) { $fields[] = "password_hash=?"; $params[] = hashPassword($b['password']); }
        $fields[] = "ecole_id=?"; $params[] = $ecole['id'];
        $fields[] = "etablissement_id=?"; $params[] = $ecole['id'];
        $fields[] = "annee_scolaire=?"; $params[] = $annee;
        if ($fields) {
            $params[] = $existing['id'];
            $pdo->prepare("UPDATE eleves SET " . implode(',', $fields) . " WHERE id=?")->execute($params);
        }
        $eleveId = (int)$existing['id'];
    } else {
        foreach (['matricule', 'login', 'nom', 'prenom'] as $f) {
            if (empty($b[$f])) jsonResponse(['error' => "Champ $f requis pour création"], 400);
        }
        $pdo->prepare("INSERT INTO eleves (matricule, login, password_hash, nom, prenom, date_naissance, sexe, classe_id, etablissement_id, ecole_id, annee_scolaire) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$b['matricule'], $b['login'], hashPassword($b['password'] ?? 'eleve123'), $b['nom'], $b['prenom'], $b['date_naissance'] ?? null, $b['sexe'] ?? 'M', $b['classe_id'] ?? null, $ecole['id'], $ecole['id'], $annee]);
        $eleveId = (int)$pdo->lastInsertId();
    }
    $cle = cleUnique($eleveId, (int)$ecole['id'], $annee);
    try { $pdo->prepare("UPDATE eleves SET cle_unique=? WHERE id=?")->execute([$cle, $eleveId]); } catch (Throwable $e) {}
    $s = $pdo->prepare("SELECT * FROM eleves WHERE id=?");
    $s->execute([$eleveId]);
    $eleve = $s->fetch();
    unset($eleve['password_hash']);
    // Dossier JSON eleve
    $dossier = ['eleve' => $eleve, 'ecole' => ['id' => (int)$ecole['id'], 'code' => $ecole['code'], 'nom' => $ecole['nom']], 'annee_scolaire' => $annee];
    if (isset($b['donnees']) && is_array($b['donnees'])) $dossier = array_merge($dossier, $b['donnees']);
    upsertDossier($pdo, 'eleve_dossiers', $cle, $eleveId, (int)$ecole['id'], $annee, $dossier);
    jsonResponse(['ok' => true, 'cle_unique' => $cle, 'eleve_id' => $eleveId, 'eleve' => $eleve], $existing ? 200 : 201);
}

// ---- Ingest NOTES + MOYENNES ----
if (($path === '/api/externes/notes' || $path === '/api/external/notes') && $method === 'POST') {
    $ecole = requireApiKey($pdo);
    $b = getJsonBody();
    $eleve = resolveEleveExterne($pdo, $ecole, $b);
    if (!$eleve) jsonResponse(['error' => 'Élève introuvable (fournis eleve_id, matricule ou cle_unique)'], 404);
    $annee = $b['annee_scolaire'] ?? $eleve['annee_scolaire'] ?? '2025-2026';
    $eleveId = (int)$eleve['id'];
    $cle = cleUnique($eleveId, (int)$ecole['id'], $annee);
    $notesIn = $b['notes'] ?? [];
    if (!is_array($notesIn) || !count($notesIn)) jsonResponse(['error' => 'Tableau notes requis'], 400);

    // Sync normalisé : résoudre/créer matières puis insérer notes
    $synced = 0;
    foreach ($notesIn as $n) {
        $matNom = $n['matiere'] ?? $n['matiere_nom'] ?? null;
        $matCode = $n['code'] ?? $n['matiere_code'] ?? null;
        if (!$matNom && !$matCode) continue;
        // find or create matiere
        $mid = null;
        if ($matCode) {
            $s = $pdo->prepare("SELECT id FROM matieres WHERE code=? LIMIT 1");
            $s->execute([$matCode]);
            $mid = $s->fetchColumn();
        }
        if (!$mid && $matNom) {
            $s = $pdo->prepare("SELECT id FROM matieres WHERE nom=? LIMIT 1");
            $s->execute([$matNom]);
            $mid = $s->fetchColumn();
        }
        if (!$mid) {
            $pdo->prepare("INSERT INTO matieres (nom, code, coefficient) VALUES (?,?,?)")
                ->execute([$matNom ?: $matCode, $matCode ?: ('MAT' . time() . rand(10, 99)), (int)($n['matiere_coef'] ?? $n['coefficient_matiere'] ?? 1)]);
            $mid = $pdo->lastInsertId();
        }
        $pdo->prepare("INSERT INTO notes (eleve_id, matiere_id, periode, type_eval, note, note_sur, coefficient, date_eval, annee_scolaire, commentaire) VALUES (?,?,?,?,?,?,?,?,?,?)")
            ->execute([$eleveId, $mid, $n['periode'] ?? 'T1', $n['type_eval'] ?? 'devoir', $n['note'], $n['note_sur'] ?? 20, $n['coefficient'] ?? 1, $n['date_eval'] ?? date('Y-m-d'), $annee, $n['commentaire'] ?? null]);
        $synced++;
    }
    // Dossier JSON (notes brutes + moyennes fournies ou calculées à la lecture)
    $dossier = ['cle_unique' => $cle, 'eleve_id' => $eleveId, 'ecole' => $ecole['nom'], 'annee_scolaire' => $annee, 'notes' => $notesIn, 'moyennes' => ($b['moyennes'] ?? null), 'nb_synced' => $synced, 'source' => 'externe'];
    upsertDossier($pdo, 'notes_moyennes_dossiers', $cle, $eleveId, (int)$ecole['id'], $annee, $dossier);
    jsonResponse(['ok' => true, 'cle_unique' => $cle, 'notes_synced' => $synced]);
}

// ---- Ingest PRESENCES ----
if (($path === '/api/externes/presences' || $path === '/api/external/presences') && $method === 'POST') {
    $ecole = requireApiKey($pdo);
    $b = getJsonBody();
    $eleve = resolveEleveExterne($pdo, $ecole, $b);
    if (!$eleve) jsonResponse(['error' => 'Élève introuvable'], 404);
    $annee = $b['annee_scolaire'] ?? $eleve['annee_scolaire'] ?? '2025-2026';
    $eleveId = (int)$eleve['id'];
    $cle = cleUnique($eleveId, (int)$ecole['id'], $annee);
    $lignes = $b['lignes'] ?? $b['presences'] ?? [];
    if (!is_array($lignes) || !count($lignes)) jsonResponse(['error' => 'Tableau lignes/presences requis'], 400);
    $synced = 0;
    foreach ($lignes as $l) {
        $date = $l['date_jour'] ?? $l['date'] ?? null;
        if (!$date) continue;
        $statut = $l['statut'] ?? 'present';
        // upsert par (eleve_id, date_jour)
        $s = $pdo->prepare("SELECT id FROM presences WHERE eleve_id=? AND date_jour=? LIMIT 1");
        $s->execute([$eleveId, $date]);
        $pid = $s->fetchColumn();
        if ($pid) {
            $pdo->prepare("UPDATE presences SET statut=?, motif=?, justifie=?, heure_arrivee=? WHERE id=?")
                ->execute([$statut, $l['motif'] ?? null, (int)($l['justifie'] ?? 0), $l['heure_arrivee'] ?? null, $pid]);
        } else {
            $pdo->prepare("INSERT INTO presences (eleve_id, date_jour, statut, motif, justifie, heure_arrivee) VALUES (?,?,?,?,?,?)")
                ->execute([$eleveId, $date, $statut, $l['motif'] ?? null, (int)($l['justifie'] ?? 0), $l['heure_arrivee'] ?? null]);
        }
        $synced++;
    }
    upsertDossier($pdo, 'presence_dossiers', $cle, $eleveId, (int)$ecole['id'], $annee, ['cle_unique' => $cle, 'lignes' => $lignes, 'nb_synced' => $synced, 'source' => 'externe']);
    jsonResponse(['ok' => true, 'cle_unique' => $cle, 'presences_synced' => $synced]);
}

// ---- Ingest PAIEMENTS ----
if (($path === '/api/externes/paiements' || $path === '/api/external/paiements') && $method === 'POST') {
    $ecole = requireApiKey($pdo);
    $b = getJsonBody();
    $eleve = resolveEleveExterne($pdo, $ecole, $b);
    if (!$eleve) jsonResponse(['error' => 'Élève introuvable'], 404);
    $annee = $b['annee_scolaire'] ?? $eleve['annee_scolaire'] ?? '2025-2026';
    $eleveId = (int)$eleve['id'];
    $cle = cleUnique($eleveId, (int)$ecole['id'], $annee);
    $pays = $b['paiements'] ?? [];
    if (!is_array($pays) || !count($pays)) jsonResponse(['error' => 'Tableau paiements requis'], 400);
    $synced = 0;
    foreach ($pays as $p) {
        if (!isset($p['montant']) || !isset($p['type_paiement'])) continue;
        $ref = $p['reference'] ?? ('EXT-' . time() . '-' . rand(100, 999));
        $s = $pdo->prepare("SELECT id FROM paiements WHERE eleve_id=? AND reference=? LIMIT 1");
        $s->execute([$eleveId, $ref]);
        $pid = $s->fetchColumn();
        if ($pid) {
            $pdo->prepare("UPDATE paiements SET montant=?, montant_paye=?, type_paiement=?, statut=?, date_echeance=?, date_paiement=?, mode_paiement=?, annee_scolaire=? WHERE id=?")
                ->execute([$p['montant'], $p['montant_paye'] ?? 0, $p['type_paiement'], $p['statut'] ?? 'en_attente', $p['date_echeance'] ?? null, $p['date_paiement'] ?? null, $p['mode_paiement'] ?? null, $annee, $pid]);
        } else {
            $pdo->prepare("INSERT INTO paiements (eleve_id, parent_id, montant, montant_paye, type_paiement, statut, date_echeance, date_paiement, reference, mode_paiement, annee_scolaire) VALUES (?,?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$eleveId, null, $p['montant'], $p['montant_paye'] ?? 0, $p['type_paiement'], $p['statut'] ?? 'en_attente', $p['date_echeance'] ?? null, $p['date_paiement'] ?? null, $ref, $p['mode_paiement'] ?? null, $annee]);
        }
        $synced++;
    }
    upsertDossier($pdo, 'paiements_dossiers', $cle, $eleveId, (int)$ecole['id'], $annee, ['cle_unique' => $cle, 'paiements' => $pays, 'nb_synced' => $synced, 'source' => 'externe']);
    jsonResponse(['ok' => true, 'cle_unique' => $cle, 'paiements_synced' => $synced]);
}

// ---- Publish INFOS ----
if (($path === '/api/externes/infos' || $path === '/api/external/infos') && $method === 'POST') {
    $ecole = requireApiKey($pdo);
    $b = getJsonBody();
    if (empty($b['titre']) || empty($b['contenu'])) jsonResponse(['error' => 'Titre + contenu requis'], 400);
    $pdo->prepare("INSERT INTO infos_etablissement (etablissement_id, ecole_id, titre, contenu, type_info, date_evenement, important) VALUES (?,?,?,?,?,?,?)")
        ->execute([(int)$ecole['id'], (int)$ecole['id'], $b['titre'], $b['contenu'], $b['type_info'] ?? 'general', $b['date_evenement'] ?? null, (int)($b['important'] ?? 0)]);
    jsonResponse(['ok' => true, 'id' => (int)$pdo->lastInsertId()], 201);
}

jsonResponse(['error' => 'Route externe non trouvée'], 404);
