<?php
// GET /api/ecoles -> liste des écoles (filtrée pour un parent : ses écoles + toutes si ?all=1 avec auth parent)
// Réponse inclut toujours : id, code, nom (nom établissement)

// Liste publique filtrée parent (auth parent requise)
if ($path === '/api/ecoles' && $method === 'GET') {
    $sess = requireAuth($pdo);
    // Écoles des enfants du parent
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT ec.id, ec.code, ec.nom
            FROM ecoles ec
            WHERE ec.id IN (
              SELECT COALESCE(e.ecole_id, e.etablissement_id, 1) FROM eleves e
              WHERE e.id IN (SELECT eleve_id FROM parent_eleve WHERE parent_id=?)
            )
            ORDER BY ec.nom
        ");
        $stmt->execute([$sess['parent_id']]);
        $mine = $stmt->fetchAll();
    } catch (Throwable $e) { $mine = []; }

    // Si ?all=1 -> toutes les écoles actives (pour le filtre global)
    if (($_GET['all'] ?? '') === '1') {
        $all = $pdo->query("SELECT id, code, nom FROM ecoles WHERE actif=1 ORDER BY nom")->fetchAll();
        jsonResponse(['mes_ecoles' => $mine, 'toutes' => $all]);
    }
    jsonResponse($mine);
}

jsonResponse(['error' => 'Route écoles non trouvée'], 404);
