<?php
// $pdo disponible, $path, $method

if ($path === '/api/auth/register' && $method === 'POST') {
    $body = getJsonBody();
    $nom = trim($body['nom'] ?? '');
    $prenom = trim($body['prenom'] ?? '');
    $email = trim($body['email'] ?? '');
    $telephone = trim($body['telephone'] ?? '');
    $password = $body['password'] ?? '';

    if (!$nom || !$prenom || !$email || !$password) jsonResponse(['error'=>'Champs requis manquants'], 400);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonResponse(['error'=>'Email invalide'], 400);
    if (strlen($password) < 6) jsonResponse(['error'=>'Mot de passe trop court (6 min)'], 400);

    $chk = $pdo->prepare("SELECT id FROM parents WHERE email=?");
    $chk->execute([$email]);
    if ($chk->fetch()) jsonResponse(['error'=>'Email déjà utilisé'], 409);

    $hash = hashPassword($password);
    $stmt = $pdo->prepare("INSERT INTO parents (nom, prenom, email, telephone, password_hash, statut) VALUES (?,?,?,?,?, 'valide')");
    $stmt->execute([$nom, $prenom, $email, $telephone, $hash]);
    $parentId = $pdo->lastInsertId();

    $token = generateToken();
    $expires = date('Y-m-d H:i:s', time()+ 60*60*24*7);
    $pdo->prepare("INSERT INTO sessions (parent_id, token, expires_at) VALUES (?,?,?)")->execute([$parentId,$token,$expires]);

    $parent = ['id'=>(int)$parentId,'nom'=>$nom,'prenom'=>$prenom,'email'=>$email,'telephone'=>$telephone];
    jsonResponse(['token'=>$token,'parent'=>$parent], 201);
}

if ($path === '/api/auth/login' && $method === 'POST') {
    $body = getJsonBody();
    $email = trim($body['email'] ?? '');
    $password = $body['password'] ?? '';
    if (!$email || !$password) jsonResponse(['error'=>'Email et mot de passe requis'],400);

    $stmt = $pdo->prepare("SELECT * FROM parents WHERE email=? LIMIT 1");
    $stmt->execute([$email]);
    $parent = $stmt->fetch();
    if (!$parent || !password_verify($password, $parent['password_hash'])) {
        jsonResponse(['error'=>'Identifiants invalides'], 401);
    }
    if ($parent['statut'] === 'rejete') jsonResponse(['error'=>'Compte rejeté'],403);

    $token = generateToken();
    $expires = date('Y-m-d H:i:s', time()+ 60*60*24*7);
    $pdo->prepare("INSERT INTO sessions (parent_id, token, expires_at) VALUES (?,?,?)")->execute([$parent['id'],$token,$expires]);

    unset($parent['password_hash']);
    jsonResponse(['token'=>$token,'parent'=>[
        'id'=>(int)$parent['id'],'nom'=>$parent['nom'],'prenom'=>$parent['prenom'],'email'=>$parent['email'],'telephone'=>$parent['telephone'],'statut'=>$parent['statut']
    ]]);
}

if ($path === '/api/auth/me' && $method === 'GET') {
    $sess = requireAuth($pdo);
    jsonResponse(['parent'=>['id'=>(int)$sess['parent_id'],'nom'=>$sess['nom'],'prenom'=>$sess['prenom'],'email'=>$sess['email']]]);
}

if ($path === '/api/auth/logout' && $method === 'POST') {
    $sess = requireAuth($pdo);
    $h = getallheaders()['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $tok = str_replace('Bearer ','',$h);
    $pdo->prepare("DELETE FROM sessions WHERE token=?")->execute([$tok]);
    jsonResponse(['ok'=>true]);
}

jsonResponse(['error'=>'Auth route non trouvée'],404);
