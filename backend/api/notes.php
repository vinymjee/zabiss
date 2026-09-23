<?php
// GET /api/eleves/{id}/notes?periode=T1&annee_scolaire=2025-2026
// GET /api/eleves/{id}/moyennes?periode=T1&annee_scolaire=2025-2026
// GET /api/eleves/{id}/annees -> années scolaires avec données (plus récente en premier)

if (preg_match('#^/api/eleves/(\d+)/annees$#', $path, $m) && $method === 'GET') {
    $sess = requireAuth($pdo);
    $eid = (int)$m[1];
    $chk = $pdo->prepare("SELECT 1 FROM parent_eleve WHERE parent_id=? AND eleve_id=?");
    $chk->execute([$sess['parent_id'], $eid]);
    if (!$chk->fetch()) jsonResponse(['error' => 'Non autorisé'], 403);
    $annees = anneesDisponibles($pdo, $eid);
    jsonResponse(['annees' => $annees, 'annee_active' => $annees[0] ?? null]);
}

// Résout l'année active : ?annee_scolaire= si fournie et valide, sinon la plus récente avec données, sinon null (toutes)
function resolveAnneeActive(PDO $pdo, int $eid): ?string {
    $a = trim($_GET['annee_scolaire'] ?? $_GET['annee'] ?? '');
    if ($a !== '' && preg_match('/^\d{4}-\d{4}$/', $a)) return $a;
    return anneeLaPlusRecente($pdo, $eid);
}

if (preg_match('#^/api/eleves/(\d+)/notes$#', $path, $m) && $method === 'GET') {
    $sess = requireAuth($pdo);
    $eid = $m[1];
    $chk = $pdo->prepare("SELECT 1 FROM parent_eleve WHERE parent_id=? AND eleve_id=?");
    $chk->execute([$sess['parent_id'],$eid]);
    if (!$chk->fetch()) jsonResponse(['error'=>'Non autorisé'],403);
    $periode = $_GET['periode'] ?? null;
    $anneeActive = resolveAnneeActive($pdo, (int)$eid);
    $sql = "SELECT n.*, m.nom as matiere, m.code as matiere_code, m.coefficient as matiere_coef FROM notes n JOIN matieres m ON m.id=n.matiere_id WHERE n.eleve_id=? ";
    $params = [$eid];
    if ($periode) { $sql.=" AND n.periode=? "; $params[]=$periode; }
    if ($anneeActive) { $sql.=" AND n.annee_scolaire=? "; $params[]=$anneeActive; }
    $sql.=" ORDER BY n.periode, m.nom, n.date_eval";
    $stmt=$pdo->prepare($sql); $stmt->execute($params);
    $rows = $stmt->fetchAll();
    // Compat : tableau direct par défaut (frontend historique) ; enveloppe si ?enveloppe=1
    if (isset($_GET['enveloppe'])) {
        jsonResponse(['notes' => $rows, 'annee_active' => $anneeActive, 'annees' => anneesDisponibles($pdo, (int)$eid)]);
    }
    jsonResponse($rows);
}

if (preg_match('#^/api/eleves/(\d+)/moyennes$#', $path, $m) && $method === 'GET') {
    // NOTE : le frontend historique attend l'objet moyennes directement.
    // Si ?enveloppe=1 on renvoie {moyennes, annee_active, annees}, sinon l'objet seul + champs annee.
    $sess = requireAuth($pdo);
    $eid = $m[1];
    $chk = $pdo->prepare("SELECT 1 FROM parent_eleve WHERE parent_id=? AND eleve_id=?");
    $chk->execute([$sess['parent_id'],$eid]);
    if (!$chk->fetch()) jsonResponse(['error'=>'Non autorisé'],403);

    // Filtre période optionnel (?periode=T1) : mêmes agrégats restreints à la période
    $periodeFilter = $_GET['periode'] ?? null;
    $periodeSql = ($periodeFilter && preg_match('/^[\w\- ]{1,30}$/', $periodeFilter)) ? " AND n.periode=" . $pdo->quote($periodeFilter) . " " : "";

    // Filtre année : ?annee_scolaire= ou plus récente avec données
    $anneeActive = resolveAnneeActive($pdo, (int)$eid);
    $anneeSql = ($anneeActive && preg_match('/^\d{4}-\d{4}$/', $anneeActive)) ? " AND n.annee_scolaire=" . $pdo->quote($anneeActive) . " " : "";

    // Moyenne par matière puis générale pondérée
    $stmt=$pdo->prepare("
        SELECT m.id, m.nom as matiere, m.code, m.coefficient as coef,
               AVG(n.note * 20 / n.note_sur) as moyenne_mat,
               COUNT(*) as nb_notes
        FROM notes n JOIN matieres m ON m.id=n.matiere_id
        WHERE n.eleve_id=? $periodeSql $anneeSql
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

    // Par période (scopé à l'année active pour le combo)
    $stmt2=$pdo->prepare("
        SELECT n.periode, m.nom as matiere, m.coefficient as coef,
               AVG(n.note * 20 / n.note_sur) as moy
        FROM notes n JOIN matieres m ON m.id=n.matiere_id
        WHERE n.eleve_id=? $anneeSql
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

    // Liste distincte des périodes stockées pour l'année active (combo frontend)
    try {
        if ($anneeActive) {
            $sp = $pdo->prepare("SELECT DISTINCT periode FROM notes WHERE eleve_id=? AND annee_scolaire=? ORDER BY periode");
            $sp->execute([$eid, $anneeActive]);
        } else {
            $sp = $pdo->prepare("SELECT DISTINCT periode FROM notes WHERE eleve_id=? ORDER BY periode");
            $sp->execute([$eid]);
        }
        $periodesStockees = array_values(array_filter(array_column($sp->fetchAll(), 'periode')));
    } catch (Throwable $e) { $periodesStockees = array_keys($parPeriode); }

    // Rang : calculé sur l'année active dans la classe
    $eleveClasse = $pdo->prepare("SELECT classe_id FROM eleves WHERE id=?"); $eleveClasse->execute([$eid]); $classeId = $eleveClasse->fetchColumn();
    $rang=null; $effectif=null;
    if ($classeId){
        $q=$pdo->prepare("SELECT id FROM eleves WHERE classe_id=?"); $q->execute([$classeId]); $ids=array_column($q->fetchAll(),'id');
        $moyennesClasse=[];
        foreach($ids as $idc){
            $s2=$pdo->prepare("SELECT m.coefficient, AVG(n.note*20/n.note_sur) as moy FROM notes n JOIN matieres m ON m.id=n.matiere_id WHERE n.eleve_id=? $periodeSql $anneeSql GROUP BY m.id");
            $s2->execute([$idc]); $rows=$s2->fetchAll(); $tp2=0;$tc2=0; foreach($rows as $rw){ $tp2+=$rw['moy']*$rw['coefficient']; $tc2+=$rw['coefficient']; }
            $moyc = $tc2? $tp2/$tc2:0;
            $moyennesClasse[$idc]=$moyc;
        }
        arsort($moyennesClasse);
        $pos=1; foreach($moyennesClasse as $k=>$v){ if((int)$k===(int)$eid){ $rang=$pos; break;} $pos++; }
        $effectif=count($moyennesClasse);
    }

    // CSV à blocs pour l'affichage (stocké si poussé par l'école, sinon généré depuis les tables)
    $csvNotes = null;
    try {
        $ck = cleUniqueAnnee((int)$eid, (string)($anneeActive ?? ''));
        if ($anneeActive) {
            $sc = $pdo->prepare("SELECT donnees_csv FROM notes_moyennes_dossiers WHERE cle_unique=? LIMIT 1");
            $sc->execute([$ck]);
            $csvNotes = $sc->fetchColumn() ?: null;
        }
    } catch (Throwable $e) { $csvNotes = null; }
    if (!$csvNotes) {
        try { $csvNotes = buildNotesCsvFromDb($pdo, (int)$eid, $anneeActive); } catch (Throwable $e) {}
        if ($csvNotes === '') $csvNotes = null;
    }
    $blocsNotes = $csvNotes ? parseCsvBlocs($csvNotes) : [];

    jsonResponse([
        'parMatiere'=>$parMatiere,
        'moyenneGenerale'=>$moyenneGenerale,
        'parPeriode'=>$parPeriode,
        'moyParPeriode'=>$moyParPeriode,
        'periodes'=>$periodesStockees,
        'periodeFiltre'=>$periodeFilter,
        'annee_active'=>$anneeActive,
        'csv'=>$csvNotes,
        'blocs'=>$blocsNotes,
        'annees'=>anneesDisponibles($pdo, (int)$eid),
        'rang'=>$rang,
        'effectif'=>$effectif,
        'mention'=> $moyenneGenerale!==null ? (
            $moyenneGenerale>=16?'Très bien':($moyenneGenerale>=14?'Bien':($moyenneGenerale>=12?'Assez bien':($moyenneGenerale>=10?'Passable':'Insuffisant')))
        ): null
    ]);
}

jsonResponse(['error'=>'Route notes non trouvée'],404);
