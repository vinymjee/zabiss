<?php
if ($path === '/api/infos' && $method === 'GET') {
    $sess = requireAuth($pdo);
    // Infos de l'établissement des enfants du parent
    $stmt=$pdo->prepare("
        SELECT i.*, et.nom as etablissement
        FROM infos_etablissement i
        LEFT JOIN etablissements et ON et.id=i.etablissement_id
        WHERE i.etablissement_id IN (SELECT etablissement_id FROM eleves WHERE id IN (SELECT eleve_id FROM parent_eleve WHERE parent_id=?))
           OR i.etablissement_id IS NULL
        ORDER BY i.important DESC, i.date_publication DESC
    ");
    $stmt->execute([$sess['parent_id']]);
    jsonResponse($stmt->fetchAll());
}
if (preg_match('#^/api/eleves/(\d+)/infos$#', $path, $m) && $method === 'GET') {
    $sess = requireAuth($pdo);
    $eid=$m[1];
    $chk=$pdo->prepare("SELECT etablissement_id FROM eleves WHERE id=?"); $chk->execute([$eid]); $etab=$chk->fetchColumn();
    $stmt=$pdo->prepare("SELECT * FROM infos_etablissement WHERE etablissement_id=? OR etablissement_id IS NULL ORDER BY important DESC, date_publication DESC");
    $stmt->execute([$etab]);
    jsonResponse($stmt->fetchAll());
}
jsonResponse(['error'=>'Route infos non trouvée'],404);
