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
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Container\ContainerInterface;
use SupermonNg\Services\DatabaseGenerationService;
use SupermonNg\Services\AllStarConfigService;
use SupermonNg\Services\CacheService;
use SupermonNg\Services\AmiBatchService;
use SupermonNg\Services\FileCacheService;
use SupermonNg\Services\DatabaseOptimizationService;
use SupermonNg\Services\AstdbCacheService;

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
    
    // JWT Service
    'jwt.service' => function () {
        return new class($_ENV['JWT_SECRET'] ?? 'your-secret-key') {
            private string $secret;
            
            public function __construct(string $secret) {
                $this->secret = $secret;
            }
            
            public function encode(array $payload): string {
                return JWT::encode($payload, $this->secret, 'HS256');
            }
            
            public function decode(string $token): array {
                return (array) JWT::decode($token, new Key($this->secret, 'HS256'));
            }
        };
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
    
    // AMI Batch service
    AmiBatchService::class => function (ContainerInterface $c) {
        return new AmiBatchService(
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
    
    
    // Database optimization service
    DatabaseOptimizationService::class => function (ContainerInterface $c) {
        return new DatabaseOptimizationService(
            $c->get(LoggerInterface::class),
            $c->get(CacheInterface::class),
            $c->get(Connection::class)
        );
    },
    
        // Configuration Cache service
        \SupermonNg\Services\ConfigurationCacheService::class => function (ContainerInterface $c) {
            return new \SupermonNg\Services\ConfigurationCacheService(
                $c->get(LoggerInterface::class)
            );
        },
        
        // Lazy File Loader service
        \SupermonNg\Services\LazyFileLoaderService::class => function (ContainerInterface $c) {
            return new \SupermonNg\Services\LazyFileLoaderService(
                $c->get(LoggerInterface::class),
                $c->get(\SupermonNg\Services\ConfigurationCacheService::class)
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
    
    
    // Performance monitoring controller
    \SupermonNg\Application\Controllers\PerformanceController::class => function (ContainerInterface $c) {
        return new \SupermonNg\Application\Controllers\PerformanceController(
            $c->get(LoggerInterface::class),
            $c->get(\SupermonNg\Services\ConfigurationCacheService::class),
            $c->get(\SupermonNg\Services\LazyFileLoaderService::class),
            $c->get(\SupermonNg\Services\AstdbCacheService::class),
            $c->get(\SupermonNg\Services\IncludeManagerService::class)
        );
    },
    
    // Database performance monitoring controller
    \SupermonNg\Application\Controllers\DatabasePerformanceController::class => function (ContainerInterface $c) {
        return new \SupermonNg\Application\Controllers\DatabasePerformanceController(
            $c->get(LoggerInterface::class),
            $c->get(\SupermonNg\Services\DatabaseOptimizationService::class),
            $c->get(\SupermonNg\Services\CacheOptimizationService::class)
        );
    },
    
    // HTTP performance monitoring controller
    \SupermonNg\Application\Controllers\HttpPerformanceController::class => function (ContainerInterface $c) {
        return new \SupermonNg\Application\Controllers\HttpPerformanceController(
            $c->get(LoggerInterface::class),
            $c->get(\SupermonNg\Services\HttpOptimizationService::class),
            $c->get(\SupermonNg\Services\MiddlewareOptimizationService::class)
        );
    },
    
    // Session performance monitoring controller
    \SupermonNg\Application\Controllers\SessionPerformanceController::class => function (ContainerInterface $c) {
        return new \SupermonNg\Application\Controllers\SessionPerformanceController(
            $c->get(LoggerInterface::class),
            $c->get(\SupermonNg\Services\SessionOptimizationService::class),
            $c->get(\SupermonNg\Services\AuthenticationOptimizationService::class),
            $c->get(\SupermonNg\Services\HttpOptimizationService::class)
        );
    },
    
    // File I/O performance monitoring controller
    \SupermonNg\Application\Controllers\FileIOPerformanceController::class => function (ContainerInterface $c) {
        return new \SupermonNg\Application\Controllers\FileIOPerformanceController(
            $c->get(LoggerInterface::class),
            $c->get(\SupermonNg\Services\ExternalProcessOptimizationService::class),
            $c->get(\SupermonNg\Services\FileIOCachingService::class),
            $c->get(\SupermonNg\Services\HttpOptimizationService::class)
        );
    },
    
    // Middleware with dependency injection
    \SupermonNg\Application\Middleware\ApiAuthMiddleware::class => function (ContainerInterface $c) {
        return new \SupermonNg\Application\Middleware\ApiAuthMiddleware(
            $c->get(LoggerInterface::class),
            $c->get(\SupermonNg\Services\IncludeManagerService::class)
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
    
    // Database optimization service
    \SupermonNg\Services\DatabaseOptimizationService::class => function (ContainerInterface $c) {
        try {
            $connection = $c->get(Connection::class);
        } catch (\Exception $e) {
            // Fallback to null if database connection is not available
            $connection = null;
        }
        return new \SupermonNg\Services\DatabaseOptimizationService(
            $c->get(LoggerInterface::class),
            $c->get(CacheInterface::class),
            $connection
        );
    },
    
    // Cache optimization service
    \SupermonNg\Services\CacheOptimizationService::class => function (ContainerInterface $c) {
        return new \SupermonNg\Services\CacheOptimizationService(
            $c->get(LoggerInterface::class),
            $c->get(CacheInterface::class)
        );
    },
    
    // HTTP optimization service
    \SupermonNg\Services\HttpOptimizationService::class => function (ContainerInterface $c) {
        return new \SupermonNg\Services\HttpOptimizationService(
            $c->get(LoggerInterface::class)
        );
    },
    
    // Middleware optimization service
    \SupermonNg\Services\MiddlewareOptimizationService::class => function (ContainerInterface $c) {
        return new \SupermonNg\Services\MiddlewareOptimizationService(
            $c->get(LoggerInterface::class)
        );
    },
    
    // Session optimization service
    \SupermonNg\Services\SessionOptimizationService::class => function (ContainerInterface $c) {
        return new \SupermonNg\Services\SessionOptimizationService(
            $c->get(LoggerInterface::class),
            $c->get(CacheInterface::class),
            3600, // Session timeout
            1800  // Max inactive time
        );
    },
    
    // Authentication optimization service
    \SupermonNg\Services\AuthenticationOptimizationService::class => function (ContainerInterface $c) {
        return new \SupermonNg\Services\AuthenticationOptimizationService(
            $c->get(LoggerInterface::class),
            $c->get(CacheInterface::class),
            5,    // Max login attempts
            900   // Lockout duration
        );
    },
    
    // External process optimization service
    \SupermonNg\Services\ExternalProcessOptimizationService::class => function (ContainerInterface $c) {
        return new \SupermonNg\Services\ExternalProcessOptimizationService(
            $c->get(LoggerInterface::class),
            $c->get(CacheInterface::class)
        );
    },
    
    // File I/O caching service
    \SupermonNg\Services\FileIOCachingService::class => function (ContainerInterface $c) {
        return new \SupermonNg\Services\FileIOCachingService(
            $c->get(LoggerInterface::class),
            $c->get(CacheInterface::class),
            10485760, // 10MB max memory cache
            1048576,  // 1MB max file size
            300       // 5 minutes default TTL
        );
    },
    
    // Controllers with dependency injection
    \SupermonNg\Application\Controllers\ConfigController::class => function (ContainerInterface $c) {
        try {
            $cacheService = $c->get(CacheService::class);
        } catch (\Exception $e) {
            $cacheService = null;
        }
        
        return new \SupermonNg\Application\Controllers\ConfigController(
            $c->get(LoggerInterface::class),
            $cacheService,
            $c->get(\SupermonNg\Services\IncludeManagerService::class)
        );
    },
    
    \SupermonNg\Application\Controllers\NodeController::class => function (ContainerInterface $c) {
        return new \SupermonNg\Application\Controllers\NodeController(
            $c->get(LoggerInterface::class),
            $c->get(\SupermonNg\Services\AllStarConfigService::class),
            $c->get(\SupermonNg\Services\AstdbCacheService::class),
            $c->get(\SupermonNg\Services\IncludeManagerService::class)
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
            $c->get(\SupermonNg\Services\AstdbCacheService::class)
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
            $c->get(\SupermonNg\Application\Controllers\ConfigController::class)
        );
    },
    
];
