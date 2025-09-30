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
                    
                    // Create AMI connection
                    $ami = new \SimpleAmiClient();
                    $connected = $ami->connect($config['host'], $config['port']);
                    
                    if ($connected) {
                        $loggedIn = $ami->login($config['username'], $config['password']);
                        if ($loggedIn) {
                            $this->connections[$nodeId] = $ami;
                            return true;
                        }
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
                    
                    $ami = $this->connections[$nodeId];
                    $response = \SimpleAmiClient::command($ami, "Command", ["Command" => $command]);
                    
                    return $response ?: "No response";
                } catch (Exception $e) {
                    return "Error: " . $e->getMessage();
                }
            }
            
            public function disconnect(string $nodeId): void {
                if (isset($this->connections[$nodeId])) {
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
    
    
    // Controllers with dependency injection
    \SupermonNg\Application\Controllers\ConfigController::class => function (ContainerInterface $c) {
        try {
            $cacheService = $c->get(CacheService::class);
        } catch (\Exception $e) {
            $cacheService = null;
        }
        
        return new \SupermonNg\Application\Controllers\ConfigController(
            $c->get(LoggerInterface::class),
            $cacheService
        );
    },
    
    \SupermonNg\Application\Controllers\NodeController::class => function (ContainerInterface $c) {
        return new \SupermonNg\Application\Controllers\NodeController(
            $c->get(LoggerInterface::class),
            $c->get(\SupermonNg\Services\AllStarConfigService::class)
        );
    },
    
    \SupermonNg\Application\Controllers\AuthController::class => function (ContainerInterface $c) {
        return new \SupermonNg\Application\Controllers\AuthController(
            $c->get(LoggerInterface::class)
        );
    },
    
];
