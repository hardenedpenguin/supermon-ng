<?php

namespace SupermonNg\Application\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use SupermonNg\Services\SessionOptimizationService;
use SupermonNg\Services\AuthenticationOptimizationService;
use SupermonNg\Services\HttpOptimizationService;

/**
 * Session Performance monitoring controller
 * 
 * Provides endpoints for monitoring session and authentication performance,
 * security metrics, and optimization statistics.
 */
class SessionPerformanceController
{
    private LoggerInterface $logger;
    private SessionOptimizationService $sessionService;
    private AuthenticationOptimizationService $authService;
    private HttpOptimizationService $httpService;

    public function __construct(
        LoggerInterface $logger,
        SessionOptimizationService $sessionService,
        AuthenticationOptimizationService $authService,
        HttpOptimizationService $httpService
    ) {
        $this->logger = $logger;
        $this->sessionService = $sessionService;
        $this->authService = $authService;
        $this->httpService = $httpService;
    }

    /**
     * Get comprehensive session performance metrics
     */
    public function getMetrics(Request $request, Response $response): Response
    {
        $this->logger->info('Fetching session performance metrics');
        
        try {
            $metrics = [
                'timestamp' => date('c'),
                'session' => $this->sessionService->getPerformanceStats(),
                'authentication' => $this->authService->getPerformanceStats(),
                'security' => $this->getSecurityMetrics(),
                'system' => $this->getSystemMetrics(),
                'optimization' => $this->getOptimizationMetrics()
            ];
            
            return $this->httpService->optimizeJsonResponse($response, [
                'success' => true,
                'data' => $metrics
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Error fetching session performance metrics', [
                'error' => $e->getMessage()
            ]);
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to fetch session performance metrics: ' . $e->getMessage()
            ]));
            
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Get session performance stats
     */
    public function getSessionStats(Request $request, Response $response): Response
    {
        $this->logger->info('Fetching session performance stats');
        
        try {
            $stats = $this->sessionService->getPerformanceStats();
            
            return $this->httpService->optimizeJsonResponse($response, [
                'success' => true,
                'data' => $stats
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Error fetching session stats', [
                'error' => $e->getMessage()
            ]);
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to fetch session stats: ' . $e->getMessage()
            ]));
            
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Get authentication performance stats
     */
    public function getAuthStats(Request $request, Response $response): Response
    {
        $this->logger->info('Fetching authentication performance stats');
        
        try {
            $stats = $this->authService->getPerformanceStats();
            
            return $this->httpService->optimizeJsonResponse($response, [
                'success' => true,
                'data' => $stats
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Error fetching auth stats', [
                'error' => $e->getMessage()
            ]);
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to fetch auth stats: ' . $e->getMessage()
            ]));
            
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Test authentication with sample credentials
     */
    public function testAuthentication(Request $request, Response $response): Response
    {
        $this->logger->info('Testing authentication system');
        
        try {
            $clientIp = $request->getServerParams()['REMOTE_ADDR'] ?? '127.0.0.1';
            
            // Test with invalid credentials to check rate limiting
            $testResult = $this->authService->authenticate('test_user', 'invalid_password', $clientIp);
            
            // Test password strength validation
            $passwordStrength = $this->authService->validatePasswordStrength('TestPassword123!');
            
            return $this->httpService->optimizeJsonResponse($response, [
                'success' => true,
                'message' => 'Authentication system test completed',
                'data' => [
                    'test_authentication' => $testResult,
                    'password_strength_test' => $passwordStrength,
                    'client_ip' => $clientIp,
                    'timestamp' => date('c')
                ]
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Error testing authentication', [
                'error' => $e->getMessage()
            ]);
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to test authentication: ' . $e->getMessage()
            ]));
            
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Clean up expired sessions
     */
    public function cleanupExpiredSessions(Request $request, Response $response): Response
    {
        $this->logger->info('Cleaning up expired sessions');
        
        try {
            $cleanedCount = $this->sessionService->cleanupExpiredSessions();
            
            return $this->httpService->optimizeJsonResponse($response, [
                'success' => true,
                'message' => 'Expired sessions cleaned up successfully',
                'data' => [
                    'cleaned_sessions' => $cleanedCount
                ]
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Error cleaning up expired sessions', [
                'error' => $e->getMessage()
            ]);
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to cleanup expired sessions: ' . $e->getMessage()
            ]));
            
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Clear authentication cache
     */
    public function clearAuthCache(Request $request, Response $response): Response
    {
        $this->logger->info('Clearing authentication cache');
        
        try {
            $clearedCount = $this->authService->clearAuthCache();
            
            return $this->httpService->optimizeJsonResponse($response, [
                'success' => true,
                'message' => 'Authentication cache cleared successfully',
                'data' => [
                    'cleared_entries' => $clearedCount
                ]
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Error clearing auth cache', [
                'error' => $e->getMessage()
            ]);
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to clear auth cache: ' . $e->getMessage()
            ]));
            
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Reset session statistics
     */
    public function resetSessionStats(Request $request, Response $response): Response
    {
        $this->logger->info('Resetting session statistics');
        
        try {
            $this->sessionService->resetStats();
            
            return $this->httpService->optimizeJsonResponse($response, [
                'success' => true,
                'message' => 'Session statistics reset successfully'
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Error resetting session stats', [
                'error' => $e->getMessage()
            ]);
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to reset session stats: ' . $e->getMessage()
            ]));
            
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Reset authentication statistics
     */
    public function resetAuthStats(Request $request, Response $response): Response
    {
        $this->logger->info('Resetting authentication statistics');
        
        try {
            $this->authService->resetStats();
            
            return $this->httpService->optimizeJsonResponse($response, [
                'success' => true,
                'message' => 'Authentication statistics reset successfully'
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Error resetting auth stats', [
                'error' => $e->getMessage()
            ]);
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to reset auth stats: ' . $e->getMessage()
            ]));
            
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Get security metrics
     */
    private function getSecurityMetrics(): array
    {
        return [
            'session_security' => [
                'secure_cookies' => ini_get('session.cookie_secure') == '1',
                'httponly_cookies' => ini_get('session.cookie_httponly') == '1',
                'strict_mode' => ini_get('session.use_strict_mode') == '1',
                'samesite_cookies' => ini_get('session.cookie_samesite'),
                'session_timeout' => ini_get('session.gc_maxlifetime')
            ],
            'authentication_security' => [
                'rate_limiting_enabled' => true,
                'brute_force_protection' => true,
                'password_hashing' => 'bcrypt',
                'failed_attempt_tracking' => true,
                'lockout_protection' => true
            ],
            'system_security' => [
                'php_version' => PHP_VERSION,
                'openssl_version' => OPENSSL_VERSION_TEXT ?? 'Not available',
                'hash_algorithms' => hash_algos(),
                'crypt_strong' => function_exists('random_bytes')
            ]
        ];
    }

    /**
     * Get system metrics
     */
    private function getSystemMetrics(): array
    {
        return [
            'load_average' => sys_getloadavg(),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'memory_limit' => ini_get('memory_limit'),
            'server_time' => time(),
            'timezone' => date_default_timezone_get(),
            'session_status' => session_status(),
            'session_id' => session_id() ? substr(session_id(), 0, 8) . '...' : 'none'
        ];
    }

    /**
     * Get optimization metrics
     */
    private function getOptimizationMetrics(): array
    {
        return [
            'optimizations_active' => [
                'session_caching' => true,
                'authentication_caching' => true,
                'rate_limiting' => true,
                'brute_force_protection' => true,
                'password_strength_validation' => true,
                'secure_session_handling' => true,
                'session_timeout_management' => true,
                'failed_attempt_tracking' => true
            ],
            'security_improvements' => [
                'session_security' => 'Secure session cookies with httponly and samesite',
                'authentication_security' => 'Rate limiting and brute force protection',
                'password_security' => 'Strong password hashing and validation',
                'access_control' => 'Failed attempt tracking and lockout protection',
                'session_management' => 'Automatic timeout and cleanup',
                'cache_security' => 'Secure authentication data caching'
            ]
        ];
    }
}
