<?php
/**
 * Supermon-ng Root Router
 * 
 * This file serves as the main entry point for the application.
 * It routes API requests to the backend and serves the Vue frontend for all other requests.
 */

// Check if this is an API request
if (strpos($_SERVER['REQUEST_URI'], '/api/') === 0) {
    // Route API requests to the backend
    require_once __DIR__ . '/public/index.php';
    exit;
}

// For all other requests, serve the Vue frontend
$vueIndexPath = __DIR__ . '/public/index.html';

if (file_exists($vueIndexPath)) {
    // Read and output the Vue app HTML
    $content = file_get_contents($vueIndexPath);
    
    // Update asset paths to be relative to the root
    $content = str_replace('src="/assets/', 'src="/public/assets/', $content);
    $content = str_replace('href="/assets/', 'href="/public/assets/', $content);
    $content = str_replace('href="/icons/', 'href="/public/icons/', $content);
    
    echo $content;
} else {
    // Fallback if Vue app is not built
    http_response_code(404);
    echo '<h1>Vue App Not Found</h1>';
    echo '<p>Please build the frontend application first.</p>';
}
