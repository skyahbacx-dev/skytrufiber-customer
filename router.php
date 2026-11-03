<?php
// Clean customer-facing routes. Internal PHP filenames remain private.
$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$uriPath = rtrim($uriPath, '/') ?: '/';
$routes = [
    '/fiber' => __DIR__ . '/SKYTRUFIBER/skytrufiber.php',
    '/fiber/consent' => __DIR__ . '/SKYTRUFIBER/consent.php',
    '/fiber/register' => __DIR__ . '/SKYTRUFIBER/register.php',
    '/fiber/register/success' => __DIR__ . '/SKYTRUFIBER/register_success.php',
    '/fiber/no-consent' => __DIR__ . '/SKYTRUFIBER/no_consent.php',
    '/fiber/chat' => __DIR__ . '/SKYTRUFIBER/chat/chat_support.php',
    '/fiber/forgot-password' => __DIR__ . '/SKYTRUFIBER/forgot_password.php',
    '/fiber/save-survey' => __DIR__ . '/SKYTRUFIBER/save_survey.php',
    '/fiber/load-survey' => __DIR__ . '/SKYTRUFIBER/load_survey.php',
    '/fiber/send-reminders' => __DIR__ . '/SKYTRUFIBER/send_reminders.php',
    '/fiber/dashboard' => __DIR__ . '/SKYTRUFIBER/dashboard.php',
    '/fiber/logout' => __DIR__ . '/SKYTRUFIBER/logout.php'
];
$legacy = [
    '/SKYTRUFIBER/no_consent.php' => '/fiber/no-consent',
    '/fiber/forgot_password.php' => '/fiber/forgot-password',
    '/fiber/save_survey.php' => '/fiber/save-survey',
    '/fiber/load_survey.php' => '/fiber/load-survey',
    '/fiber/send_reminders.php' => '/fiber/send-reminders'
];
if (isset($legacy[$uriPath])) { header('Location: '.$legacy[$uriPath], true, 301); exit; }
if (isset($routes[$uriPath])) { require $routes[$uriPath]; return true; }
$file = __DIR__ . $uriPath;
if ($uriPath !== '/' && is_file($file)) return false;
if ($uriPath === '/') { header('Location: /fiber', true, 302); exit; }
http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo 'Page not found.';
