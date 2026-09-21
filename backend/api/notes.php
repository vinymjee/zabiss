<?php
// GET /api/eleves/{id}/notes?periode=T1
// GET /api/eleves/{id}/moyennes

if (preg_match('#^/api/eleves/(\d+)/notes$#', $path, $m) && $method === 'GET') {
    $sess = requireAuth($pdo);
    $eid = $m[1];
    $chk = $pdo->prepare("SELECT 1 FROM parent_eleve WHERE parent_id=? AND eleve_id=?");
    $chk->execute([$sess['parent_id'],$eid]);
    if (!$chk->fetch()) jsonResponse(['error'=>'Non autorisé'],403);
    $periode = $_GET['periode'] ?? null;
    $sql = "SELECT n.*, m.nom as matiere, m.code as matiere_code, m.coefficient as matiere_coef FROM notes n JOIN matieres m ON m.id=n.matiere_id WHERE n.eleve_id=? ";
    $params = [$eid];
    if ($periode) { $sql.=" AND n.periode=? "; $params[]=$periode; }
    $sql.=" ORDER BY n.periode, m.nom, n.date_eval";
    $stmt=$pdo->prepare($sql); $stmt->execute($params);
    jsonResponse($stmt->fetchAll());
}

if (preg_match('#^/api/eleves/(\d+)/moyennes$#', $path, $m) && $method === 'GET') {
    $sess = requireAuth($pdo);
    $eid = $m[1];
    $chk = $pdo->prepare("SELECT 1 FROM parent_eleve WHERE parent_id=? AND eleve_id=?");
    $chk->execute([$sess['parent_id'],$eid]);
    if (!$chk->fetch()) jsonResponse(['error'=>'Non autorisé'],403);

    // Filtre période optionnel (?periode=T1) : mêmes agrégats restreints à la période
    $periodeFilter = $_GET['periode'] ?? null;
    $periodeSql = $periodeFilter ? " AND n.periode=" . $pdo->quote($periodeFilter) . " " : "";

    // Moyenne par matière puis générale pondérée
    $stmt=$pdo->prepare("
        SELECT m.id, m.nom as matiere, m.code, m.coefficient as coef,
               AVG(n.note * 20 / n.note_sur) as moyenne_mat,
               COUNT(*) as nb_notes
        FROM notes n JOIN matieres m ON m.id=n.matiere_id
        WHERE n.eleve_id=? $periodeSql
        GROUP BY m.id
    ");
    $stmt->execute([$eid]);
    $parMatiere = $stmt->fetchAll();

    $totalPoints = 0; $totalCoef=0;
    foreach($parMatiere as &$row){
        $row['moyenne_mat'] = round((float)$row['moyenne_mat'],2);
        $totalPoints += $row['moyenne_mat'] * $row['coef'];
        $totalCoef += $row['coef'];
    }
    $moyenneGenerale = $totalCoef ? round($totalPoints/$totalCoef,2) : null;

    // Par période (liste complète des périodes stockées, même si un filtre est actif)
    $stmt2=$pdo->prepare("
        SELECT n.periode, m.nom as matiere, m.coefficient as coef,
               AVG(n.note * 20 / n.note_sur) as moy
        FROM notes n JOIN matieres m ON m.id=n.matiere_id
        WHERE n.eleve_id=?
        GROUP BY n.periode, m.id
        ORDER BY n.periode
    ");
    $stmt2->execute([$eid]);
    $parPeriodeRaw = $stmt2->fetchAll();
    $parPeriode=[];
    foreach($parPeriodeRaw as $r){
        $parPeriode[$r['periode']][]=$r;
    }
    // Moy générale par période
    $moyParPeriode=[];
    foreach($parPeriode as $periode=>$rows){
        $tp=0;$tc=0; foreach($rows as $rr){ $tp+= $rr['moy']*$rr['coef']; $tc+=$rr['coef']; }
        $moyParPeriode[$periode]= $tc? round($tp/$tc,2): null;
    }

    // Rang (simulé si pas assez d'élèves) : on calcule dans la classe
    $eleveClasse = $pdo->prepare("SELECT classe_id FROM eleves WHERE id=?"); $eleveClasse->execute([$eid]); $classeId = $eleveClasse->fetchColumn();
    $rang=null; $effectif=null;
    if ($classeId){
        $q=$pdo->prepare("SELECT id FROM eleves WHERE classe_id=?"); $q->execute([$classeId]); $ids=array_column($q->fetchAll(),'id');
        // calcul moyenne générale pour chaque élève de la classe
        $moyennesClasse=[];
        foreach($ids as $idc){
            $s=$pdo->prepare("SELECT AVG(n.note*20/n.note_sur * m.coefficient) as w FROM notes n JOIN matieres m ON m.id=n.matiere_id WHERE n.eleve_id=?");
            // approx
            $s2=$pdo->prepare("SELECT m.coefficient, AVG(n.note*20/n.note_sur) as moy FROM notes n JOIN matieres m ON m.id=n.matiere_id WHERE n.eleve_id=? GROUP BY m.id");
            $s2->execute([$idc]); $rows=$s2->fetchAll(); $tp2=0;$tc2=0; foreach($rows as $rw){ $tp2+=$rw['moy']*$rw['coefficient']; $tc2+=$rw['coefficient']; }
            $moyc = $tc2? $tp2/$tc2:0;
            $moyennesClasse[$idc]=$moyc;
        }
        arsort($moyennesClasse);
        $pos=1; foreach($moyennesClasse as $k=>$v){ if((int)$k===(int)$eid){ $rang=$pos; break;} $pos++; }
        $effectif=count($moyennesClasse);
    }

    // Liste distincte des périodes stockées (pour le combo frontend)
    try {
        $sp = $pdo->prepare("SELECT DISTINCT periode FROM notes WHERE eleve_id=? ORDER BY periode");
        $sp->execute([$eid]);
        $periodesStockees = array_values(array_filter(array_column($sp->fetchAll(), 'periode')));
    } catch (Throwable $e) { $periodesStockees = array_keys($parPeriode); }

    jsonResponse([
        'parMatiere'=>$parMatiere,
        'moyenneGenerale'=>$moyenneGenerale,
        'parPeriode'=>$parPeriode,
        'moyParPeriode'=>$moyParPeriode,
        'periodes'=>$periodesStockees,
        'periodeFiltre'=>$periodeFilter,
        'rang'=>$rang,
        'effectif'=>$effectif,
        'mention'=> $moyenneGenerale!==null ? (
            $moyenneGenerale>=16?'Très bien':($moyenneGenerale>=14?'Bien':($moyenneGenerale>=12?'Assez bien':($moyenneGenerale>=10?'Passable':'Insuffisant')))
        ): null
    ]);
}

jsonResponse(['error'=>'Route notes non trouvée'],404);
