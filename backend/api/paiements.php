<?php
// GET /api/eleves/{id}/paiements?annee_scolaire=2025-2026 (défaut : année la plus récente avec données)
if (preg_match('#^/api/eleves/(\d+)/paiements$#', $path, $m) && $method === 'GET') {
    $sess = requireAuth($pdo);
    $eid = (int)$m[1];
    $chk = $pdo->prepare("SELECT 1 FROM parent_eleve WHERE parent_id=? AND eleve_id=?");
    $chk->execute([$sess['parent_id'],$eid]);
    if (!$chk->fetch()) jsonResponse(['error'=>'Non autorisé'],403);
    $a = trim($_GET['annee_scolaire'] ?? $_GET['annee'] ?? '');
    $anneeActive = (preg_match('/^\d{4}-\d{4}$/', $a)) ? $a : anneeLaPlusRecente($pdo, $eid);
    if ($anneeActive) {
        $stmt=$pdo->prepare("SELECT * FROM paiements WHERE eleve_id=? AND annee_scolaire=? ORDER BY date_echeance DESC");
        $stmt->execute([$eid, $anneeActive]);
    } else {
        $stmt=$pdo->prepare("SELECT * FROM paiements WHERE eleve_id=? ORDER BY date_echeance DESC");
        $stmt->execute([$eid]);
    }
    $rows=$stmt->fetchAll();
    $totalDu = array_sum(array_column($rows,'montant'));
    $totalPaye = array_sum(array_column($rows,'montant_paye'));
    $reste = $totalDu - $totalPaye;
    jsonResponse([
        'paiements'=>$rows,
        'stats'=>['totalDu'=>$totalDu,'totalPaye'=>$totalPaye,'reste'=>$reste],
        'annee_active'=>$anneeActive,
        'annees'=>anneesDisponibles($pdo, $eid),
    ]);
}
if ($path === '/api/parent/paiements' && $method === 'GET') {
    $sess = requireAuth($pdo);
    // Tous paiements de tous enfants (toutes années ; filtre ?annee_scolaire= optionnel)
    $a = trim($_GET['annee_scolaire'] ?? $_GET['annee'] ?? '');
    if (preg_match('/^\d{4}-\d{4}$/', $a)) {
        $stmt=$pdo->prepare("SELECT pa.*, e.nom, e.prenom FROM paiements pa JOIN eleves e ON e.id=pa.eleve_id JOIN parent_eleve pe ON pe.eleve_id=e.id WHERE pe.parent_id=? AND pa.annee_scolaire=? ORDER BY pa.date_echeance DESC");
        $stmt->execute([$sess['parent_id'], $a]);
    } else {
        $stmt=$pdo->prepare("SELECT pa.*, e.nom, e.prenom FROM paiements pa JOIN eleves e ON e.id=pa.eleve_id JOIN parent_eleve pe ON pe.eleve_id=e.id WHERE pe.parent_id=? ORDER BY pa.date_echeance DESC");
        $stmt->execute([$sess['parent_id']]);
    }
    jsonResponse($stmt->fetchAll());
}
jsonResponse(['error'=>'Route paiements non trouvée'],404);
