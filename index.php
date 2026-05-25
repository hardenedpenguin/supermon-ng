<?php
/**
 * Supermon-ng Root Router
 * 
 * This file serves as the main entry point for the application.
 * It routes API requests to the backend and serves the Vue frontend for all other requests.
 */

// Check if this is an API request
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
if (str_contains($requestUri, '/api/')) {
    // Route API requests to the backend
    require_once __DIR__ . '/public/index.php';
    exit;
}

// For all other requests, serve the Vue frontend
$vueIndexPath = __DIR__ . '/public/index.html';

if (file_exists($vueIndexPath)) {
    // Serve built SPA as-is (paths come from Vite base / APP_BASE_PATH at build time)
    readfile($vueIndexPath);
} else {
    // Fallback if Vue app is not built
    http_response_code(404);
    echo '<h1>Vue App Not Found</h1>';
    echo '<p>Please build the frontend application first.</p>';
}
