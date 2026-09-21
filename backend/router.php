<?php
// Router pour php -S
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($uri !== '/' && file_exists(__DIR__ . $uri) && is_file(__DIR__ . $uri)) {
    return false; // serve static
}
require __DIR__ . '/api/index.php';
