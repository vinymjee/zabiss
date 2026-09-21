<?php
if (preg_match('#^/api/eleves/(\d+)/presences$#', $path, $m) && $method === 'GET') {
    $sess = requireAuth($pdo);
    $eid = $m[1];
    $chk = $pdo->prepare("SELECT 1 FROM parent_eleve WHERE parent_id=? AND eleve_id=?");
    $chk->execute([$sess['parent_id'],$eid]);
    if (!$chk->fetch()) jsonResponse(['error'=>'Non autorisé'],403);

    $stmt=$pdo->prepare("SELECT * FROM presences WHERE eleve_id=? ORDER BY date_jour DESC");
    $stmt->execute([$eid]);
    $rows=$stmt->fetchAll();

    $total = count($rows);
    $presents = count(array_filter($rows, fn($r)=>$r['statut']==='present'));
    $absents = count(array_filter($rows, fn($r)=>$r['statut']==='absent'));
    $retards = count(array_filter($rows, fn($r)=>$r['statut']==='retard'));
    $taux = $total? round($presents/$total*100,1): 100;

    jsonResponse(['presences'=>$rows,'stats'=>['total'=>$total,'presents'=>$presents,'absents'=>$absents,'retards'=>$retards,'tauxPresence'=>$taux]]);
}
jsonResponse(['error'=>'Route presences non trouvée'],404);
