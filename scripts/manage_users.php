#!/usr/bin/env php
<?php

/**
 * User Management Utility for Supermon-ng
 * 
 * This script helps manage users in the .htpasswd file.
 * 
 * Usage:
 *   php scripts/manage_users.php list                    # List all users
 *   php scripts/manage_users.php add <username> <password>  # Add a new user
 *   php scripts/manage_users.php remove <username>       # Remove a user
 *   php scripts/manage_users.php change <username> <password>  # Change user password
 */

if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line.');
}

$htpasswdFile = 'user_files/.htpasswd';

// Ensure the user_files directory exists
if (!is_dir('user_files')) {
    mkdir('user_files', 0755, true);
}

// Ensure .htpasswd file exists
if (!file_exists($htpasswdFile)) {
    file_put_contents($htpasswdFile, '');
    echo "Created new .htpasswd file.\n";
}

if ($argc < 2) {
    showUsage();
    exit(1);
}

$command = $argv[1];

switch ($command) {
    case 'list':
        listUsers();
        break;
    case 'add':
        if ($argc < 4) {
            echo "Error: Username and password required for add command.\n";
            showUsage();
            exit(1);
        }
        addUser($argv[2], $argv[3]);
        break;
    case 'remove':
        if ($argc < 3) {
            echo "Error: Username required for remove command.\n";
            showUsage();
            exit(1);
        }
        removeUser($argv[2]);
        break;
    case 'change':
        if ($argc < 4) {
            echo "Error: Username and password required for change command.\n";
            showUsage();
            exit(1);
        }
        changePassword($argv[2], $argv[3]);
        break;
    default:
        echo "Error: Unknown command '$command'.\n";
        showUsage();
        exit(1);
}

function showUsage() {
    echo "User Management Utility for Supermon-ng\n\n";
    echo "Usage:\n";
    echo "  php scripts/manage_users.php list                    # List all users\n";
    echo "  php scripts/manage_users.php add <username> <password>  # Add a new user\n";
    echo "  php scripts/manage_users.php remove <username>       # Remove a user\n";
    echo "  php scripts/manage_users.php change <username> <password>  # Change user password\n\n";
    echo "Examples:\n";
    echo "  php scripts/manage_users.php list\n";
    echo "  php scripts/manage_users.php add admin mypassword\n";
    echo "  php scripts/manage_users.php remove olduser\n";
    echo "  php scripts/manage_users.php change admin newpassword\n";
}

function listUsers() {
    global $htpasswdFile;
    
    $lines = file($htpasswdFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    if (empty($lines)) {
        echo "No users found in .htpasswd file.\n";
        return;
    }
    
    echo "Users in .htpasswd file:\n";
    echo str_repeat('-', 50) . "\n";
    
    foreach ($lines as $line) {
        $parts = explode(':', $line, 2);
        if (count($parts) === 2) {
            $username = trim($parts[0]);
            $hash = trim($parts[1]);
            $hashType = getHashType($hash);
            echo sprintf("%-20s %s\n", $username, $hashType);
        }
    }
}

function addUser($username, $password) {
    global $htpasswdFile;
    
    // Validate username
    if (empty($username) || !preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
        echo "Error: Username must be alphanumeric and can contain underscores and hyphens.\n";
        exit(1);
    }
    
    // Check if user already exists
    if (userExists($username)) {
        echo "Error: User '$username' already exists.\n";
        exit(1);
    }
    
    // Generate bcrypt hash
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    
    // Add user to file
    $line = "$username:$hash\n";
    file_put_contents($htpasswdFile, $line, FILE_APPEND | LOCK_EX);
    
    echo "User '$username' added successfully.\n";
}

function removeUser($username) {
    global $htpasswdFile;
    
    if (!userExists($username)) {
        echo "Error: User '$username' does not exist.\n";
        exit(1);
    }
    
    $lines = file($htpasswdFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $newLines = [];
    
    foreach ($lines as $line) {
        $parts = explode(':', $line, 2);
        if (count($parts) === 2 && trim($parts[0]) !== $username) {
            $newLines[] = $line;
        }
    }
    
    file_put_contents($htpasswdFile, implode("\n", $newLines) . "\n");
    
    echo "User '$username' removed successfully.\n";
}

function changePassword($username, $password) {
    global $htpasswdFile;
    
    if (!userExists($username)) {
        echo "Error: User '$username' does not exist.\n";
        exit(1);
    }
    
    $lines = file($htpasswdFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $newLines = [];
    $updated = false;
    
    foreach ($lines as $line) {
        $parts = explode(':', $line, 2);
        if (count($parts) === 2) {
            if (trim($parts[0]) === $username) {
                // Update password for this user
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $newLines[] = "$username:$hash";
                $updated = true;
            } else {
                $newLines[] = $line;
            }
        }
    }
    
    if ($updated) {
        file_put_contents($htpasswdFile, implode("\n", $newLines) . "\n");
        echo "Password for user '$username' changed successfully.\n";
    } else {
        echo "Error: Failed to update password for user '$username'.\n";
        exit(1);
    }
}

function userExists($username) {
    global $htpasswdFile;
    
    $lines = file($htpasswdFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        $parts = explode(':', $line, 2);
        if (count($parts) === 2 && trim($parts[0]) === $username) {
            return true;
        }
    }
    
    return false;
}

function getHashType($hash) {
    if (strpos($hash, '$2y$') === 0) {
        return 'bcrypt';
    } elseif (strpos($hash, '$apr1$') === 0) {
        return 'Apache MD5';
    } elseif (strpos($hash, '{SHA}') === 0) {
        return 'SHA1';
    } elseif (strlen($hash) === 32 && ctype_xdigit($hash)) {
        return 'MD5';
    } else {
        return 'plain text (insecure)';
    }
}


