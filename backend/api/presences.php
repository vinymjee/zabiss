<?php
// GET /api/eleves/{id}/presences?annee_scolaire=2025-2026 (défaut : année la plus récente avec données)
if (preg_match('#^/api/eleves/(\d+)/presences$#', $path, $m) && $method === 'GET') {
    $sess = requireAuth($pdo);
    $eid = (int)$m[1];
    $chk = $pdo->prepare("SELECT 1 FROM parent_eleve WHERE parent_id=? AND eleve_id=?");
    $chk->execute([$sess['parent_id'],$eid]);
    if (!$chk->fetch()) jsonResponse(['error'=>'Non autorisé'],403);

    $a = trim($_GET['annee_scolaire'] ?? $_GET['annee'] ?? '');
    $anneeActive = (preg_match('/^\d{4}-\d{4}$/', $a)) ? $a : anneeLaPlusRecente($pdo, $eid);
    $range = $anneeActive ? anneeToRange($anneeActive) : null;

    if ($range) {
        $stmt=$pdo->prepare("SELECT * FROM presences WHERE eleve_id=? AND date_jour BETWEEN ? AND ? ORDER BY date_jour DESC");
        $stmt->execute([$eid, $range[0], $range[1]]);
    } else {
        $stmt=$pdo->prepare("SELECT * FROM presences WHERE eleve_id=? ORDER BY date_jour DESC");
        $stmt->execute([$eid]);
    }
    $rows=$stmt->fetchAll();

    $total = count($rows);
    $presents = count(array_filter($rows, fn($r)=>$r['statut']==='present'));
    $absents = count(array_filter($rows, fn($r)=>$r['statut']==='absent'));
    $retards = count(array_filter($rows, fn($r)=>$r['statut']==='retard'));
    $taux = $total? round($presents/$total*100,1): 100;

    // CSV à blocs pour l'affichage (stocké si poussé, sinon généré)
    $csvPres = null;
    try {
        if ($anneeActive) {
            $sc = $pdo->prepare("SELECT donnees_csv FROM presence_dossiers WHERE cle_unique=? LIMIT 1");
            $sc->execute([cleUniqueAnnee($eid, $anneeActive)]);
            $csvPres = $sc->fetchColumn() ?: null;
        }
    } catch (Throwable $e) { $csvPres = null; }
    if (!$csvPres) {
        try { $csvPres = buildPresenceCsvFromDb($pdo, $eid, $anneeActive); } catch (Throwable $e) {}
        if ($csvPres === '') $csvPres = null;
    }

    jsonResponse([
        'presences'=>$rows,
        'stats'=>['total'=>$total,'presents'=>$presents,'absents'=>$absents,'retards'=>$retards,'tauxPresence'=>$taux],
        'annee_active'=>$anneeActive,
        'annees'=>anneesDisponibles($pdo, $eid),
        'csv'=>$csvPres,
        'blocs'=>($csvPres ? parseCsvBlocs($csvPres) : []),
    ]);
}
jsonResponse(['error'=>'Route presences non trouvée'],404);
