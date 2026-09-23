<?php
// Gestion lien parent <-> élève via matricule + login + mdp

// GET /api/parent/eleves  | POST /api/parent/eleves/link | DELETE /api/parent/eleves/{id}
if ($path === '/api/parent/eleves' && $method === 'GET') {
    $sess = requireAuth($pdo);
    $pid = $sess['parent_id'];
    $stmt = $pdo->prepare("
        SELECT e.id, e.matricule, e.nom, e.prenom, e.date_naissance, e.sexe, e.photo_url,
               c.nom as classe, c.niveau,
               COALESCE(ec.nom, et.nom) as etablissement,
               COALESCE(ec.id, et.id, e.ecole_id, e.etablissement_id) as ecole_id,
               COALESCE(ec.code,'') as ecole_code,
               COALESCE(e.annee_scolaire, c.annee_scolaire, '2025-2026') as annee_scolaire,
               e.cle_unique,
               pe.lien, pe.created_at as lie_le
        FROM parent_eleve pe
        JOIN eleves e ON e.id=pe.eleve_id
        LEFT JOIN classes c ON c.id=e.classe_id
        LEFT JOIN etablissements et ON et.id=e.etablissement_id
        LEFT JOIN ecoles ec ON ec.id=COALESCE(e.ecole_id, e.etablissement_id)
        WHERE pe.parent_id=? ORDER BY e.nom
    ");
    $stmt->execute([$pid]);
    jsonResponse($stmt->fetchAll());
}

if ($path === '/api/parent/eleves/link' && $method === 'POST') {
    $sess = requireAuth($pdo);
    $body = getJsonBody();
    $matricule = trim($body['matricule'] ?? '');
    $login = trim($body['login'] ?? '');
    $password = $body['password'] ?? '';
    $lien = trim($body['lien'] ?? 'parent');
    if (!$matricule || !$login || !$password) jsonResponse(['error' => 'Matricule, login et mot de passe requis'], 400);

    $stmt = $pdo->prepare("SELECT * FROM eleves WHERE matricule=? AND login=? LIMIT 1");
    $stmt->execute([$matricule, $login]);
    $eleve = $stmt->fetch();
    if (!$eleve) jsonResponse(['error' => 'Élève non trouvé (matricule/login incorrect)'], 404);
    if (!password_verify($password, $eleve['password_hash'])) jsonResponse(['error' => 'Mot de passe élève incorrect'], 401);

    $chk = $pdo->prepare("SELECT id FROM parent_eleve WHERE parent_id=? AND eleve_id=?");
    $chk->execute([$sess['parent_id'], $eleve['id']]);
    if ($chk->fetch()) jsonResponse(['error' => 'Élève déjà lié à votre compte'], 409);

    $pdo->prepare("INSERT INTO parent_eleve (parent_id, eleve_id, lien) VALUES (?,?,?)")->execute([$sess['parent_id'], $eleve['id'], $lien]);
    jsonResponse(['ok' => true, 'eleve' => ['id' => (int)$eleve['id'], 'matricule' => $eleve['matricule'], 'nom' => $eleve['nom'], 'prenom' => $eleve['prenom']]], 201);
}

if (preg_match('#^/api/parent/eleves/(\d+)$#', $path, $m) && $method === 'DELETE') {
    $sess = requireAuth($pdo);
    $eid = $m[1];
    $pdo->prepare("DELETE FROM parent_eleve WHERE parent_id=? AND eleve_id=?")->execute([$sess['parent_id'], $eid]);
    jsonResponse(['ok' => true]);
}

// GET /api/eleves/{id}  détail (vérifie lien) + nom établissement + cle_unique
if (preg_match('#^/api/eleves/(\d+)$#', $path, $m) && $method === 'GET') {
    $sess = requireAuth($pdo);
    $eid = $m[1];
    $chk = $pdo->prepare("SELECT 1 FROM parent_eleve WHERE parent_id=? AND eleve_id=?");
    $chk->execute([$sess['parent_id'], $eid]);
    if (!$chk->fetch()) jsonResponse(['error' => 'Accès non autorisé à cet élève'], 403);
    $stmt = $pdo->prepare("
        SELECT e.*, c.nom as classe, c.niveau,
               COALESCE(e.annee_scolaire, c.annee_scolaire, '2025-2026') as annee_scolaire,
               COALESCE(ec.nom, et.nom) as etablissement,
               COALESCE(ec.id, et.id, e.ecole_id, e.etablissement_id) as ecole_id,
               COALESCE(ec.code,'') as ecole_code
        FROM eleves e
        LEFT JOIN classes c ON c.id=e.classe_id
        LEFT JOIN etablissements et ON et.id=e.etablissement_id
        LEFT JOIN ecoles ec ON ec.id=COALESCE(e.ecole_id, e.etablissement_id)
        WHERE e.id=?
    ");
    $stmt->execute([$eid]);
    $eleve = $stmt->fetch();
    if (!$eleve) jsonResponse(['error' => 'Élève introuvable'], 404);
    unset($eleve['password_hash']);
    // Dossiers JSON associés (nouvelles clés : eleve -> "id", annuels -> "id|annee")
    try {
        $annee = $eleve['annee_scolaire'] ?? '2025-2026';
        $eleve['cle_unique_calculee'] = cleUniqueEleve((int)$eid);
        $eleve['cle_unique_annee'] = cleUniqueAnnee((int)$eid, (string)$annee);
        $dossierTables = [
            'eleve_dossiers' => ['out' => 'dossier_eleve', 'cle' => cleUniqueEleve((int)$eid)],
            'notes_moyennes_dossiers' => ['out' => 'dossier_notes', 'cle' => cleUniqueAnnee((int)$eid, (string)$annee)],
            'presence_dossiers' => ['out' => 'dossier_presences', 'cle' => cleUniqueAnnee((int)$eid, (string)$annee)],
            'paiements_dossiers' => ['out' => 'dossier_paiements', 'cle' => cleUniqueAnnee((int)$eid, (string)$annee)],
        ];
        foreach ($dossierTables as $tbl => $cfg) {
            try {
                $s = $pdo->prepare("SELECT * FROM $tbl WHERE cle_unique=? LIMIT 1");
                $s->execute([$cfg['cle']]);
                $row = $s->fetch();
                // Fallback legacy "E_C_A"
                if (!$row) {
                    $ecoleId = $eleve['ecole_id'] ?? $eleve['etablissement_id'] ?? 1;
                    $legacy = $eid . '_' . $ecoleId . '_' . $annee;
                    $s = $pdo->prepare("SELECT * FROM $tbl WHERE cle_unique=? LIMIT 1");
                    $s->execute([$legacy]);
                    $row = $s->fetch();
                }
                if ($row) {
                    $eleve[$cfg['out']] = json_decode($row['donnees_json'] ?? '{}', true);
                    $eleve[$cfg['out'] . '_maj'] = $row['updated_at'] ?? null;
                    if (!empty($row['donnees_csv'])) {
                        $eleve[$cfg['out'] . '_csv'] = $row['donnees_csv'];
                        try { $eleve[$cfg['out'] . '_blocs'] = parseCsvBlocs($row['donnees_csv']); } catch (Throwable $e) {}
                    }
                }
            } catch (Throwable $e) {}
        }
        // Années disponibles (sélecteur affiché seulement si > 1)
        try { $eleve['annees_disponibles'] = anneesDisponibles($pdo, (int)$eid); } catch (Throwable $e) {}
    } catch (Throwable $e) {}
    jsonResponse($eleve);
}

jsonResponse(['error' => 'Route élèves non trouvée'], 404);
