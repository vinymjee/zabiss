<?php
// Gestion lien parent <-> élève via matricule + login + mdp

// GET /api/parent/eleves  | POST /api/parent/eleves/link | DELETE /api/parent/eleves/{id}
if ($path === '/api/parent/eleves' && $method === 'GET') {
    $sess = requireAuth($pdo);
    $pid = $sess['parent_id'];
    $stmt = $pdo->prepare("SELECT e.id, e.matricule, e.nom, e.prenom, e.date_naissance, e.sexe, e.photo_url, c.nom as classe, c.niveau, et.nom as etablissement, pe.lien, pe.created_at as lie_le FROM parent_eleve pe JOIN eleves e ON e.id=pe.eleve_id LEFT JOIN classes c ON c.id=e.classe_id LEFT JOIN etablissements et ON et.id=e.etablissement_id WHERE pe.parent_id=? ORDER BY e.nom");
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
    if (!$matricule || !$login || !$password) jsonResponse(['error'=>'Matricule, login et mot de passe requis'],400);

    $stmt = $pdo->prepare("SELECT * FROM eleves WHERE matricule=? AND login=? LIMIT 1");
    $stmt->execute([$matricule,$login]);
    $eleve = $stmt->fetch();
    if (!$eleve) jsonResponse(['error'=>'Élève non trouvé (matricule/login incorrect)'],404);
    if (!password_verify($password, $eleve['password_hash'])) jsonResponse(['error'=>'Mot de passe élève incorrect'],401);

    // Déjà lié ?
    $chk = $pdo->prepare("SELECT id FROM parent_eleve WHERE parent_id=? AND eleve_id=?");
    $chk->execute([$sess['parent_id'], $eleve['id']]);
    if ($chk->fetch()) jsonResponse(['error'=>'Élève déjà lié à votre compte'],409);

    // Vérif espace contrôle : on simule validation auto si elevé existe et mdp ok
    // En prod on pourrait mettre statut en_attente
    $pdo->prepare("INSERT INTO parent_eleve (parent_id, eleve_id, lien) VALUES (?,?,?)")->execute([$sess['parent_id'], $eleve['id'], $lien]);
    jsonResponse(['ok'=>true,'eleve'=>['id'=>(int)$eleve['id'],'matricule'=>$eleve['matricule'],'nom'=>$eleve['nom'],'prenom'=>$eleve['prenom']]],201);
}

if (preg_match('#^/api/parent/eleves/(\d+)$#', $path, $m) && $method === 'DELETE') {
    $sess = requireAuth($pdo);
    $eid = $m[1];
    $pdo->prepare("DELETE FROM parent_eleve WHERE parent_id=? AND eleve_id=?")->execute([$sess['parent_id'],$eid]);
    jsonResponse(['ok'=>true]);
}

// GET /api/eleves/{id}  détail (vérifie lien)
if (preg_match('#^/api/eleves/(\d+)$#', $path, $m) && $method === 'GET') {
    $sess = requireAuth($pdo);
    $eid = $m[1];
    $chk = $pdo->prepare("SELECT 1 FROM parent_eleve WHERE parent_id=? AND eleve_id=?");
    $chk->execute([$sess['parent_id'],$eid]);
    if (!$chk->fetch()) jsonResponse(['error'=>'Accès non autorisé à cet élève'],403);
    $stmt = $pdo->prepare("SELECT e.*, c.nom as classe, c.niveau, c.annee_scolaire, et.nom as etablissement FROM eleves e LEFT JOIN classes c ON c.id=e.classe_id LEFT JOIN etablissements et ON et.id=e.etablissement_id WHERE e.id=?");
    $stmt->execute([$eid]);
    $eleve = $stmt->fetch();
    unset($eleve['password_hash']);
    jsonResponse($eleve);
}

jsonResponse(['error'=>'Route élèves non trouvée'],404);
