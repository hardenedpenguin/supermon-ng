<?php
/**
 * Supermon-NG Configuration Migration System
 * 
 * This script handles configuration migrations between versions,
 * ensuring user customizations are preserved while applying new
 * configuration options and structure changes.
 * 
 * @author Supermon-NG Team
 * @version 4.0.3
 */

class ConfigMigrator
{
    private $appDir;
    private $userFilesDir;
    private $backupDir;
    private $logFile;
    
    public function __construct($appDir = '/var/www/html/supermon-ng')
    {
        $this->appDir = $appDir;
        $this->userFilesDir = $appDir . '/user_files';
        $this->backupDir = '/tmp/supermon-ng-migration-' . date('Ymd_His');
        $this->logFile = $appDir . '/logs/migration.log';
        
        // Create backup directory
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
        
        // Create logs directory if it doesn't exist
        if (!is_dir(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0755, true);
        }
    }
    
    /**
     * Log migration actions
     */
    private function log($message, $level = 'INFO')
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
        
        // Also output to console if running from command line
        if (php_sapi_name() === 'cli') {
            echo $logMessage;
        }
    }
    
    /**
     * Get current version from common.inc
     */
    public function getCurrentVersion()
    {
        $commonInc = $this->appDir . '/includes/common.inc';
        if (!file_exists($commonInc)) {
            return 'unknown';
        }
        
        $content = file_get_contents($commonInc);
        if (preg_match('/V(\d+\.\d+\.\d+)/', $content, $matches)) {
            return $matches[1];
        }
        
        return 'unknown';
    }
    
    /**
     * Get target version from new common.inc
     */
    public function getTargetVersion()
    {
        $commonInc = $this->appDir . '/includes/common.inc';
        if (!file_exists($commonInc)) {
            return 'unknown';
        }
        
        $content = file_get_contents($commonInc);
        if (preg_match('/V(\d+\.\d+\.\d+)/', $content, $matches)) {
            return $matches[1];
        }
        
        return 'unknown';
    }
    
    /**
     * Check if migration is needed
     */
    public function needsMigration($fromVersion, $toVersion)
    {
        if ($fromVersion === 'unknown' || $toVersion === 'unknown') {
            return true; // Assume migration needed if versions unknown
        }
        
        return version_compare($fromVersion, $toVersion, '<');
    }
    
    /**
     * Migrate global.inc configuration
     */
    public function migrateGlobalInc($backupFile, $newFile)
    {
        $this->log("Migrating global.inc configuration");
        
        if (!file_exists($backupFile)) {
            $this->log("No existing global.inc found, using new template", 'WARNING');
            return true;
        }
        
        if (!file_exists($newFile)) {
            $this->log("New global.inc template not found", 'ERROR');
            return false;
        }
        
        // Parse existing configuration
        $existingConfig = $this->parseConfigFile($backupFile);
        $newConfig = $this->parseConfigFile($newFile);
        
        // Merge configurations, preserving user values
        $mergedConfig = $this->mergeConfigurations($existingConfig, $newConfig);
        
        // Write merged configuration
        $this->writeConfigFile($this->userFilesDir . '/global.inc', $mergedConfig);
        
        $this->log("global.inc migration completed successfully");
        return true;
    }
    
    /**
     * Migrate authusers.inc configuration
     */
    public function migrateAuthUsers($backupFile, $newFile)
    {
        $this->log("Migrating authusers.inc configuration");
        
        if (!file_exists($backupFile)) {
            $this->log("No existing authusers.inc found, using new template", 'WARNING');
            return true;
        }
        
        // For authusers.inc, we typically want to preserve the entire file
        // as it contains user-specific authentication data
        if (file_exists($backupFile)) {
            copy($backupFile, $this->userFilesDir . '/authusers.inc');
            $this->log("authusers.inc preserved from backup");
        }
        
        return true;
    }
    
    /**
     * Migrate favorites.ini configuration
     */
    public function migrateFavorites($backupFile, $newFile)
    {
        $this->log("Migrating favorites.ini configuration");
        
        if (!file_exists($backupFile)) {
            $this->log("No existing favorites.ini found, using new template", 'WARNING');
            return true;
        }
        
        // Parse INI files
        $existingFavorites = parse_ini_file($backupFile, true);
        $newFavorites = file_exists($newFile) ? parse_ini_file($newFile, true) : [];
        
        // Merge favorites, preserving user favorites
        $mergedFavorites = array_merge($newFavorites, $existingFavorites);
        
        // Write merged favorites
        $this->writeIniFile($this->userFilesDir . '/favorites.ini', $mergedFavorites);
        
        $this->log("favorites.ini migration completed successfully");
        return true;
    }
    
    /**
     * Migrate privatenodes.txt
     */
    public function migratePrivateNodes($backupFile, $newFile)
    {
        $this->log("Migrating privatenodes.txt");
        
        if (!file_exists($backupFile)) {
            $this->log("No existing privatenodes.txt found, using new template", 'WARNING');
            return true;
        }
        
        // For privatenodes.txt, we typically want to preserve the entire file
        if (file_exists($backupFile)) {
            copy($backupFile, $this->userFilesDir . '/privatenodes.txt');
            $this->log("privatenodes.txt preserved from backup");
        }
        
        return true;
    }
    
    /**
     * Parse configuration file and extract variables
     */
    private function parseConfigFile($filePath)
    {
        $config = [];
        $content = file_get_contents($filePath);
        
        // Extract PHP variable assignments
        preg_match_all('/\$([A-Z_]+)\s*=\s*["\']([^"\']*)["\'];/', $content, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $config[$match[1]] = $match[2];
        }
        
        return $config;
    }
    
    /**
     * Merge two configuration arrays, preserving user values
     */
    private function mergeConfigurations($existing, $new)
    {
        $merged = $new; // Start with new defaults
        
        // Override with existing user values (but only if they're not empty)
        foreach ($existing as $key => $value) {
            if (!empty($value) && $value !== 'YOURCALL' && $value !== 'Your Name' && $value !== 'Your Location') {
                $merged[$key] = $value;
            }
        }
        
        return $merged;
    }
    
    /**
     * Write configuration file from array
     */
    private function writeConfigFile($filePath, $config)
    {
        $content = "<?php\n";
        $content .= "/**\n";
        $content .= " * Supermon-ng Global Configuration\n";
        $content .= " * Migrated configuration - user values preserved\n";
        $content .= " * Generated: " . date('Y-m-d H:i:s') . "\n";
        $content .= " */\n\n";
        
        foreach ($config as $key => $value) {
            $content .= "\$$key = \"$value\";\n";
        }
        
        file_put_contents($filePath, $content);
    }
    
    /**
     * Write INI file from array
     */
    private function writeIniFile($filePath, $data)
    {
        $content = "";
        
        foreach ($data as $section => $values) {
            $content .= "[$section]\n";
            foreach ($values as $key => $value) {
                $content .= "$key = \"$value\"\n";
            }
            $content .= "\n";
        }
        
        file_put_contents($filePath, $content);
    }
    
    /**
     * Run complete migration
     */
    public function runMigration($backupDir)
    {
        $this->log("Starting configuration migration");
        
        $migrations = [
            'global.inc' => [$this, 'migrateGlobalInc'],
            'authusers.inc' => [$this, 'migrateAuthUsers'],
            'favorites.ini' => [$this, 'migrateFavorites'],
            'privatenodes.txt' => [$this, 'migratePrivateNodes']
        ];
        
        $success = true;
        
        foreach ($migrations as $file => $migrationFunction) {
            $backupFile = $backupDir . '/' . $file;
            $newFile = $this->userFilesDir . '/' . $file;
            
            try {
                if (!$migrationFunction($backupFile, $newFile)) {
                    $this->log("Migration failed for $file", 'ERROR');
                    $success = false;
                }
            } catch (Exception $e) {
                $this->log("Exception during migration of $file: " . $e->getMessage(), 'ERROR');
                $success = false;
            }
        }
        
        // Preserve sbin directory if it exists
        $sbinBackup = $backupDir . '/sbin';
        $sbinTarget = $this->userFilesDir . '/sbin';
        
        if (is_dir($sbinBackup)) {
            if (is_dir($sbinTarget)) {
                $this->log("Preserving sbin directory from backup");
                $this->copyDirectory($sbinBackup, $sbinTarget);
            }
        }
        
        // Preserve preferences directory if it exists
        $prefsBackup = $backupDir . '/preferences';
        $prefsTarget = $this->userFilesDir . '/preferences';
        
        if (is_dir($prefsBackup)) {
            if (is_dir($prefsTarget)) {
                $this->log("Preserving preferences directory from backup");
                $this->copyDirectory($prefsBackup, $prefsTarget);
            }
        }
        
        if ($success) {
            $this->log("Configuration migration completed successfully");
        } else {
            $this->log("Configuration migration completed with errors", 'WARNING');
        }
        
        return $success;
    }
    
    /**
     * Copy directory recursively
     */
    private function copyDirectory($src, $dst)
    {
        if (!is_dir($dst)) {
            mkdir($dst, 0755, true);
        }
        
        $dir = opendir($src);
        while (($file = readdir($dir)) !== false) {
            if ($file != '.' && $file != '..') {
                $srcFile = $src . '/' . $file;
                $dstFile = $dst . '/' . $file;
                
                if (is_dir($srcFile)) {
                    $this->copyDirectory($srcFile, $dstFile);
                } else {
                    copy($srcFile, $dstFile);
                }
            }
        }
        closedir($dir);
    }
}

// Command line interface
if (php_sapi_name() === 'cli') {
    $migrator = new ConfigMigrator();
    
    if ($argc < 2) {
        echo "Usage: php migrate-config.php <backup_directory>\n";
        echo "Example: php migrate-config.php /tmp/supermon-ng-backup-20250101_120000/user_files\n";
        exit(1);
    }
    
    $backupDir = $argv[1];
    
    if (!is_dir($backupDir)) {
        echo "Error: Backup directory '$backupDir' does not exist\n";
        exit(1);
    }
    
    $success = $migrator->runMigration($backupDir);
    exit($success ? 0 : 1);
}
