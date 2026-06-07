<?php

use Psr\Log\LoggerInterface;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Processor\UidProcessor;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\CacheInterface;
use Psr\Container\ContainerInterface;
use SupermonNg\Services\DatabaseGenerationService;
use SupermonNg\Services\AllStarConfigService;
use SupermonNg\Services\CacheService;
use SupermonNg\Services\FileCacheService;
use SupermonNg\Services\AstdbCacheService;
use SupermonNg\Services\AMIHelperService;
use SupermonNg\Services\LocalAllmonGeneratorService;

return [
    // Logger
    LoggerInterface::class => function () {
        $logger = new Logger('supermon-ng');
        
        // Set log level based on environment
        $logLevel = ($_ENV['APP_ENV'] ?? 'production') === 'production' 
            ? ($_ENV['LOG_LEVEL'] ?? Logger::WARNING)  // Only warnings and errors in production
            : ($_ENV['LOG_LEVEL'] ?? Logger::INFO);    // Full logging in development
        
        // Add rotating file handler with optimized settings
        $logger->pushHandler(new RotatingFileHandler(
            $_ENV['LOG_PATH'] ?? 'logs/app.log',
            $_ENV['LOG_MAX_FILES'] ?? (($_ENV['APP_ENV'] ?? 'production') === 'production' ? 7 : 30), // Fewer files in production
            $logLevel
        ));
        
        // Only add UID processor in development for performance
        if (($_ENV['APP_ENV'] ?? 'production') !== 'production') {
            $logger->pushProcessor(new UidProcessor());
        }
        
        return $logger;
    },
    
    // Database connection with pooling optimization
    Connection::class => function () {
        $dbType = $_ENV['DB_TYPE'] ?? 'sqlite';
        $dbPath = $_ENV['DB_PATH'] ?? 'database/supermon-ng.db';
        
        if ($dbType === 'sqlite') {
            $connectionParams = [
                'driver' => 'pdo_sqlite',
                'path' => $dbPath,
                // SQLite optimizations
                'options' => [
                    \PDO::ATTR_PERSISTENT => false, // Disable persistent connections for better performance
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                ],
            ];
        } else {
            $connectionParams = [
                'driver' => 'pdo_mysql',
                'host' => $_ENV['DB_HOST'] ?? 'localhost',
                'port' => $_ENV['DB_PORT'] ?? 3306,
                'dbname' => $_ENV['DB_NAME'] ?? 'supermon_ng',
                'user' => $_ENV['DB_USER'] ?? 'root',
                'password' => $_ENV['DB_PASSWORD'] ?? '',
                'charset' => 'utf8mb4',
                // MySQL optimizations
                'options' => [
                    \PDO::ATTR_PERSISTENT => true, // Enable persistent connections for MySQL
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES => false, // Use native prepared statements
                    \PDO::MYSQL_ATTR_INIT_COMMAND => "SET sql_mode='STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'",
                ],
                // Connection pooling settings
                'pool_size' => $_ENV['DB_POOL_SIZE'] ?? 5,
                'pool_timeout' => $_ENV['DB_POOL_TIMEOUT'] ?? 30,
            ];
        }
        
        return DriverManager::getConnection($connectionParams);
    },
    
    // Cache
    CacheInterface::class => function () {
        return new FilesystemAdapter(
            'supermon-ng',
            $_ENV['CACHE_TTL'] ?? 3600,
            'cache/'
        );
    },
    
    // AllStar Configuration Service
    AllStarConfigService::class => function ($container) {
        return new AllStarConfigService(
            $container->get(LoggerInterface::class),
            $_ENV['USER_FILES_PATH'] ?? '/var/www/html/supermon-ng/user_files/'
        );
    },
    
    // AMI Client - now uses AllStarConfigService
    'ami.client' => function ($container) {
        return new class($container->get(AllStarConfigService::class)) {
            private AllStarConfigService $configService;
            private array $connections = [];
            
            public function __construct(AllStarConfigService $configService) {
                $this->configService = $configService;
            }
            
            public function connect(string $nodeId, ?string $username = null): bool {
                try {
                    $config = $this->configService->getAmiConfig($nodeId, $username);
                    
                    // Include AMI functions
                    require_once __DIR__ . '/../includes/amifunctions.inc';
                    
                    // Use static methods - SimpleAmiClient methods are all static
                    $host = $config['host'] . ':' . ($config['port'] ?? 5038);
                    $fp = \SimpleAmiClient::getConnection($host, $config['username'], $config['password']);
                    
                    if ($fp !== false) {
                        $this->connections[$nodeId] = [
                            'fp' => $fp,
                            'host' => $host,
                            'user' => $config['username']
                        ];
                        return true;
                    }
                    
                    return false;
                } catch (Exception $e) {
                    return false;
                }
            }
            
            public function sendCommand(string $nodeId, string $command, ?string $username = null): string {
                try {
                    if (!isset($this->connections[$nodeId])) {
                        if (!$this->connect($nodeId, $username)) {
                            return "Error: Failed to connect to AMI";
                        }
                    }
                    
                    $conn = $this->connections[$nodeId];
                    $fp = $conn['fp'];
                    $response = \SimpleAmiClient::command($fp, $command);
                    
                    return $response ?: "No response";
                } catch (Exception $e) {
                    return "Error: " . $e->getMessage();
                }
            }
            
            public function disconnect(string $nodeId): void {
                if (isset($this->connections[$nodeId])) {
                    $conn = $this->connections[$nodeId];
                    // Return connection to pool instead of closing
                    \SimpleAmiClient::returnConnection($conn['fp'], $conn['host'], $conn['user']);
                    unset($this->connections[$nodeId]);
                }
            }
        };
    },
    
    // Database Generation Service
    DatabaseGenerationService::class => function ($container) {
        return new DatabaseGenerationService(
            $container->get(LoggerInterface::class),
            $container->get(CacheInterface::class)
        );
    },
    
    // Cache service
    CacheService::class => function (ContainerInterface $c) {
        return new CacheService(
            $c->get(CacheInterface::class),
            $c->get(LoggerInterface::class)
        );
    },
    
    // File Cache service
    FileCacheService::class => function (ContainerInterface $c) {
        return new FileCacheService(
            $c->get(CacheInterface::class),
            $c->get(LoggerInterface::class)
        );
    },
    
        // Configuration Cache service
        \SupermonNg\Services\ConfigurationCacheService::class => function (ContainerInterface $c) {
            return new \SupermonNg\Services\ConfigurationCacheService(
                $c->get(LoggerInterface::class)
            );
        },
        
        // Include Manager service
        \SupermonNg\Services\IncludeManagerService::class => function (ContainerInterface $c) {
            return new \SupermonNg\Services\IncludeManagerService(
                $c->get(LoggerInterface::class),
                $c->get(\SupermonNg\Services\ConfigurationCacheService::class)
            );
        },
        
        // ASTDB Cache service
        AstdbCacheService::class => function (ContainerInterface $c) {
            return new AstdbCacheService(
                $c->get(LoggerInterface::class)
            );
        },
    
    \SupermonNg\Application\Middleware\AdminAuthMiddleware::class => function (ContainerInterface $c) {
        return new \SupermonNg\Application\Middleware\AdminAuthMiddleware(
            $c->get(LoggerInterface::class),
            $c->get(\SupermonNg\Services\IncludeManagerService::class)
        );
    },
    
    // Services with dependency injection
    AMIHelperService::class => function (ContainerInterface $c) {
        return new AMIHelperService(
            $c->get(LoggerInterface::class),
            $c->get(\SupermonNg\Services\IncludeManagerService::class)
        );
    },
    
    // Controllers with dependency injection
    \SupermonNg\Services\SessionService::class => function () {
        return new \SupermonNg\Services\SessionService();
    },

    \SupermonNg\Application\Middleware\RequireAuthMiddleware::class => function (ContainerInterface $c) {
        return new \SupermonNg\Application\Middleware\RequireAuthMiddleware(
            $c->get(\SupermonNg\Services\SessionService::class)
        );
    },

    \SupermonNg\Services\UserPermissionService::class => function () {
        $path = $_ENV['USER_FILES_PATH'] ?? (dirname(__DIR__, 2) . '/user_files/');
        return new \SupermonNg\Services\UserPermissionService($path);
    },

    LocalAllmonGeneratorService::class => function (ContainerInterface $c) {
        $userFiles = $_ENV['USER_FILES_PATH'] ?? (dirname(__DIR__, 2) . '/user_files/');
        return new LocalAllmonGeneratorService(
            $c->get(LoggerInterface::class),
            $userFiles,
            $_ENV['ASTERISK_RPT_CONF'] ?? '/etc/asterisk/rpt.conf',
            $_ENV['ASTERISK_MANAGER_CONF'] ?? '/etc/asterisk/manager.conf'
        );
    },

    \SupermonNg\Application\Controllers\AdminController::class => function (ContainerInterface $c) {
        return new \SupermonNg\Application\Controllers\AdminController(
            $c->get(LoggerInterface::class),
            $c->get(LocalAllmonGeneratorService::class)
        );
    },

    \SupermonNg\Application\Controllers\ConfigController::class => function (ContainerInterface $c) {
        try {
            $cacheService = $c->get(CacheService::class);
        } catch (\Exception $e) {
            $cacheService = null;
        }
        
        return new \SupermonNg\Application\Controllers\ConfigController(
            $c->get(LoggerInterface::class),
            $cacheService,
            $c->get(\SupermonNg\Services\IncludeManagerService::class),
            $c->get(\SupermonNg\Services\UserPermissionService::class),
            $c->get(\SupermonNg\Services\SessionService::class)
        );
    },
    
    \SupermonNg\Application\Controllers\NodeController::class => function (ContainerInterface $c) {
        return new \SupermonNg\Application\Controllers\NodeController(
            $c->get(LoggerInterface::class),
            $c->get(\SupermonNg\Services\AllStarConfigService::class),
            $c->get(\SupermonNg\Services\AstdbCacheService::class),
            $c->get(\SupermonNg\Services\IncludeManagerService::class),
            $c->get(\SupermonNg\Services\UserPermissionService::class),
            $c->get(\SupermonNg\Services\SessionService::class)
        );
    },

    \SupermonNg\Application\Controllers\NodeStatusController::class => function (ContainerInterface $c) {
        return new \SupermonNg\Application\Controllers\NodeStatusController(
            $c->get(LoggerInterface::class),
            $c->get(\SupermonNg\Services\SessionService::class),
            $c->get(\SupermonNg\Services\UserPermissionService::class)
        );
    },

    \SupermonNg\Application\Controllers\AuthController::class => function (ContainerInterface $c) {
        return new \SupermonNg\Application\Controllers\AuthController(
            $c->get(LoggerInterface::class)
        );
    },
    
    \SupermonNg\Application\Controllers\DatabaseController::class => function (ContainerInterface $c) {
        return new \SupermonNg\Application\Controllers\DatabaseController(
            $c->get(LoggerInterface::class),
            $c->get(\SupermonNg\Services\DatabaseGenerationService::class),
            $c->get(\SupermonNg\Services\AstdbCacheService::class),
            $c->get(\SupermonNg\Services\SessionService::class),
            $c->get(\SupermonNg\Services\UserPermissionService::class)
        );
    },
    
    \SupermonNg\Application\Controllers\AstdbController::class => function (ContainerInterface $c) {
        return new \SupermonNg\Application\Controllers\AstdbController(
            $c->get(LoggerInterface::class),
            $c->get(\SupermonNg\Services\AstdbCacheService::class)
        );
    },
    
    // DVSwitch Service
    \SupermonNg\Services\DvswitchService::class => function (ContainerInterface $c) {
        return new \SupermonNg\Services\DvswitchService(
            $c->get(LoggerInterface::class),
            $c->get(\SupermonNg\Services\AllStarConfigService::class),
            $_ENV['DVSWITCH_PATH'] ?? '/opt/MMDVM_Bridge/dvswitch.sh',
            $_ENV['DVSWITCH_CONFIG_PATH'] ?? __DIR__ . '/../../user_files/dvswitch_config.yml'
        );
    },
    
    // DVSwitch Controller
    \SupermonNg\Application\Controllers\DvswitchController::class => function (ContainerInterface $c) {
        return new \SupermonNg\Application\Controllers\DvswitchController(
            $c->get(LoggerInterface::class),
            $c->get(\SupermonNg\Services\DvswitchService::class),
            $c->get(\SupermonNg\Services\UserPermissionService::class),
            $c->get(\SupermonNg\Services\SessionService::class)
        );
    },

    \SupermonNg\Services\VersionCheckService::class => function (ContainerInterface $c) {
        return new \SupermonNg\Services\VersionCheckService(
            $c->get(CacheInterface::class),
            $c->get(LoggerInterface::class),
            $c->get(\SupermonNg\Services\IncludeManagerService::class)
        );
    },

    // Bootstrap Controller (single-call auth + systemInfo + database + nodes)
    \SupermonNg\Application\Controllers\BootstrapController::class => function (ContainerInterface $c) {
        return new \SupermonNg\Application\Controllers\BootstrapController(
            $c->get(\SupermonNg\Application\Controllers\AuthController::class),
            $c->get(\SupermonNg\Application\Controllers\ConfigController::class),
            $c->get(\SupermonNg\Application\Controllers\DatabaseController::class),
            $c->get(\SupermonNg\Services\VersionCheckService::class)
        );
    },
    
];
