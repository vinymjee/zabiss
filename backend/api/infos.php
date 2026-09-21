<?php
// GET /api/infos?ecole_id=2 -> infos avec nom établissement + filtre par établissement
if ($path === '/api/infos' && $method === 'GET') {
    $sess = requireAuth($pdo);
    $ecoleFilter = $_GET['ecole_id'] ?? $_GET['etablissement_id'] ?? null;

    $sql = "
        SELECT i.*,
               COALESCE(ec.nom, et.nom) as etablissement,
               COALESCE(ec.id, et.id, i.ecole_id, i.etablissement_id) as ecole_id,
               COALESCE(ec.code, '') as ecole_code
        FROM infos_etablissement i
        LEFT JOIN etablissements et ON et.id=i.etablissement_id
        LEFT JOIN ecoles ec ON ec.id = COALESCE(i.ecole_id, i.etablissement_id)
        WHERE (
          i.etablissement_id IN (SELECT etablissement_id FROM eleves WHERE id IN (SELECT eleve_id FROM parent_eleve WHERE parent_id=?))
          OR i.ecole_id IN (SELECT COALESCE(ecole_id, etablissement_id, 1) FROM eleves WHERE id IN (SELECT eleve_id FROM parent_eleve WHERE parent_id=?))
          OR i.etablissement_id IS NULL
        )
    ";
    $params = [$sess['parent_id'], $sess['parent_id']];
    if ($ecoleFilter) {
        // NOTE: intval interpolé (pas de placeholder) car PDO-SQLite compare mal COALESCE(...)=? avec string
        $sql .= " AND COALESCE(i.ecole_id, i.etablissement_id) = " . ((int)$ecoleFilter) . " ";
    }
    $sql .= " ORDER BY i.important DESC, i.date_publication DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    jsonResponse($stmt->fetchAll());
}
if (preg_match('#^/api/eleves/(\d+)/infos$#', $path, $m) && $method === 'GET') {
    $sess = requireAuth($pdo);
    $eid = $m[1];
    $chk = $pdo->prepare("SELECT COALESCE(ecole_id, etablissement_id, 1) FROM eleves WHERE id=?");
    $chk->execute([$eid]);
    $etab = $chk->fetchColumn() ?: 1;
    $stmt = $pdo->prepare("
        SELECT i.*, COALESCE(ec.nom, et.nom) as etablissement,
               COALESCE(ec.id, et.id) as ecole_id, COALESCE(ec.code,'') as ecole_code
        FROM infos_etablissement i
        LEFT JOIN etablissements et ON et.id=i.etablissement_id
        LEFT JOIN ecoles ec ON ec.id=COALESCE(i.ecole_id, i.etablissement_id)
        WHERE COALESCE(i.ecole_id, i.etablissement_id, ?) = ? OR i.etablissement_id IS NULL
        ORDER BY important DESC, date_publication DESC
    ");
    $stmt->execute([$etab, $etab]);
    jsonResponse($stmt->fetchAll());
}
jsonResponse(['error' => 'Route infos non trouvée'], 404);
