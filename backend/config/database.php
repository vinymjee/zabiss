<?php
// Zabiss - Configuration BDD
// Supporte MySQL en prod et SQLite pour tests locaux sans MySQL

function getPDO(): PDO {
    $driver = getenv('DB_DRIVER') ?: 'mysql';

    if ($driver === 'sqlite') {
        $path = getenv('DB_SQLITE_PATH') ?: __DIR__ . '/../zabiss.sqlite';
        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $db   = getenv('DB_NAME') ?: 'zabiss';
    $user = getenv('DB_USER') ?: 'zabiss';
    $pass = getenv('DB_PASS') ?: 'zabiss_secret';

    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
}

function jsonResponse(mixed $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function getJsonBody(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function requireAuth(PDO $pdo): array {
    $headers = getallheaders() ?: [];
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (!$auth && isset($_SERVER['HTTP_AUTHORIZATION'])) $auth = $_SERVER['HTTP_AUTHORIZATION'];
    // Fallback : Authorization peut être dans $_SERVER sans HTTP_ prefix sous php -S
    if (!$auth && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) $auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    if (!str_starts_with($auth, 'Bearer ')) {
        jsonResponse(['error' => 'Non authentifié'], 401);
    }
    $token = substr($auth, 7);
    $driver = getenv('DB_DRIVER') ?: 'mysql';
    if ($driver === 'sqlite') {
        $stmt = $pdo->prepare("SELECT s.*, p.id as parent_id, p.nom, p.prenom, p.email FROM sessions s JOIN parents p ON p.id = s.parent_id WHERE s.token = ? LIMIT 1");
        $stmt->execute([$token]);
        $session = $stmt->fetch();
        if ($session && strtotime($session['expires_at']) < time()) $session = false;
    } else {
        $stmt = $pdo->prepare("SELECT s.*, p.id as parent_id, p.nom, p.prenom, p.email FROM sessions s JOIN parents p ON p.id = s.parent_id WHERE s.token = ? AND s.expires_at > NOW() LIMIT 1");
        $stmt->execute([$token]);
        $session = $stmt->fetch();
    }
    if (!$session) jsonResponse(['error' => 'Session expirée'], 401);
    return $session;
}

function generateToken(): string {
    return bin2hex(random_bytes(32));
}

function hashPassword(string $pwd): string {
    return password_hash($pwd, PASSWORD_DEFAULT);
}
