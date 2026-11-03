<?php
// Route the legacy /fiber URLs to the standalone customer application.
$uriPath = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$routes = [
    '/fiber' => __DIR__ . '/SKYTRUFIBER/skytrufiber.php',
    '/fiber/' => __DIR__ . '/SKYTRUFIBER/skytrufiber.php',
    '/fiber/consent' => __DIR__ . '/SKYTRUFIBER/consent.php',
    '/fiber/register' => __DIR__ . '/SKYTRUFIBER/register.php',
    '/fiber/register/success' => __DIR__ . '/SKYTRUFIBER/register_success.php',
    '/fiber/chat' => __DIR__ . '/SKYTRUFIBER/chat/chat_support.php',
    '/fiber/forgot_password.php' => __DIR__ . '/SKYTRUFIBER/forgot_password.php',
    '/fiber/save_survey.php' => __DIR__ . '/SKYTRUFIBER/save_survey.php',
    '/fiber/load_survey.php' => __DIR__ . '/SKYTRUFIBER/load_survey.php',
    '/fiber/send_reminders.php' => __DIR__ . '/SKYTRUFIBER/send_reminders.php',
    '/fiber/dashboard' => __DIR__ . '/SKYTRUFIBER/dashboard.php',
    '/fiber/logout' => __DIR__ . '/SKYTRUFIBER/logout.php'
];
if (isset($routes[$uriPath])) {
    require $routes[$uriPath];
    return true;
}
// Let PHP's built-in server serve real assets and the existing /SKYTRUFIBER/*.php endpoints.
$file = __DIR__ . $uriPath;
if ($uriPath !== '/' && is_file($file)) {
    return false;
}
if ($uriPath === '/') {
    header('Location: /fiber', true, 302);
    exit;
}
http_response_code(404);
echo 'Not Found';
