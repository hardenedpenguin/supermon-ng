<?php

declare(strict_types=1);

namespace SupermonNg\Application\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use SupermonNg\Services\DvswitchService;
use Exception;

class DvswitchController
{
    private LoggerInterface $logger;
    private DvswitchService $dvswitchService;
    private ConfigController $configController;
    
    public function __construct(
        LoggerInterface $logger,
        DvswitchService $dvswitchService,
        ConfigController $configController
    ) {
        $this->logger = $logger;
        $this->dvswitchService = $dvswitchService;
        $this->configController = $configController;
    }
    
    /**
     * Get currently logged in user
     */
    private function getCurrentUser(): ?string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check if user is logged in via session (must be authenticated)
        if (isset($_SESSION['user']) && !empty($_SESSION['user']) && isset($_SESSION['authenticated']) && $_SESSION['authenticated']) {
            // Check if session is not too old (24 hours)
            if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) < 86400) {
                return $_SESSION['user'];
            } else {
                // Session expired
                return null;
            }
        }
        
        if (isset($_SERVER['PHP_AUTH_USER'])) {
            return $_SERVER['PHP_AUTH_USER'];
        }
        
        if (isset($_SERVER['REMOTE_USER'])) {
            return $_SERVER['REMOTE_USER'];
        }
        
        return null;
    }
    
    /**
     * Check if user has DVSwitch permission
     */
    private function hasPermission(?string $user): bool
    {
        $this->logger->warning("Checking DVSwitch permission", [
            'user' => $user ?? 'null',
            'session_user' => $_SESSION['user'] ?? 'not set',
            'php_auth_user' => $_SERVER['PHP_AUTH_USER'] ?? 'not set',
            'remote_user' => $_SERVER['REMOTE_USER'] ?? 'not set'
        ]);
        
        // Use reflection to call ConfigController's hasUserPermission method
        $reflection = new \ReflectionClass($this->configController);
        $method = $reflection->getMethod('hasUserPermission');
        $method->setAccessible(true);
        
        $hasPermission = $method->invoke($this->configController, $user, 'DVSWITCHUSER');
        
        $this->logger->warning("DVSwitch permission check result", [
            'user' => $user ?? 'null',
            'has_permission' => $hasPermission
        ]);
        
        return $hasPermission;
    }
    
    /**
     * Get nodes with DVSwitch configured
     */
    public function getNodes(Request $request, Response $response): Response
    {
        try {
            $currentUser = $this->getCurrentUser();
            
            // Check permission
            if (!$this->hasPermission($currentUser)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'You are not authorized to use DVSwitch mode switching.'
                ]));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }
            
            $nodes = $this->dvswitchService->getNodesWithDvswitch($currentUser);
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $nodes
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (Exception $e) {
            $this->logger->error('Error getting DVSwitch nodes', [
                'error' => $e->getMessage()
            ]);
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error retrieving nodes: ' . $e->getMessage()
            ]));
            
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
    
    /**
     * Get all available modes for a specific node
     */
    public function getModes(Request $request, Response $response, array $args): Response
    {
        try {
            $currentUser = $this->getCurrentUser();
            
            // Check permission
            if (!$this->hasPermission($currentUser)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'You are not authorized to use DVSwitch mode switching.'
                ]));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }
            
            $nodeId = $args['nodeId'] ?? $request->getQueryParams()['node'] ?? '';
            if (empty($nodeId)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Node ID is required.'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            
            // Check if DVSwitch is configured for this node
            if (!$this->dvswitchService->isConfiguredForNode($nodeId, $currentUser)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => "DVSwitch is not configured for node {$nodeId}."
                ]));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }
            
            $modes = $this->dvswitchService->getModes($nodeId, $currentUser);
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $modes
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (Exception $e) {
            $this->logger->error('Error getting DVSwitch modes', [
                'node_id' => $args['nodeId'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error retrieving modes: ' . $e->getMessage()
            ]));
            
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
    
    /**
     * Get talkgroups for a specific mode and node
     */
    public function getTalkgroups(Request $request, Response $response, array $args): Response
    {
        try {
            $currentUser = $this->getCurrentUser();
            
            // Check permission
            if (!$this->hasPermission($currentUser)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'You are not authorized to use DVSwitch mode switching.'
                ]));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }
            
            $nodeId = $args['nodeId'] ?? $request->getQueryParams()['node'] ?? '';
            $modeName = $args['mode'] ?? '';
            
            if (empty($nodeId)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Node ID is required.'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            
            if (empty($modeName)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Mode name is required.'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            
            $talkgroups = $this->dvswitchService->getTalkgroupsForMode($nodeId, $modeName, $currentUser);
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $talkgroups
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (Exception $e) {
            $this->logger->error('Error getting DVSwitch talkgroups', [
                'node_id' => $args['nodeId'] ?? 'unknown',
                'mode' => $args['mode'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error retrieving talkgroups: ' . $e->getMessage()
            ]));
            
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
    
    /**
     * Switch to a specific mode for a node
     */
    public function switchMode(Request $request, Response $response, array $args): Response
    {
        $this->logger->warning("switchMode endpoint called", [
            'session_status' => session_status(),
            'session_id' => session_id() ?: 'no session',
            'session_user' => $_SESSION['user'] ?? 'not set',
            'php_auth_user' => $_SERVER['PHP_AUTH_USER'] ?? 'not set',
            'remote_user' => $_SERVER['REMOTE_USER'] ?? 'not set'
        ]);
        
        try {
            $currentUser = $this->getCurrentUser();
            
            $this->logger->warning("getCurrentUser returned", [
                'current_user' => $currentUser ?? 'null'
            ]);
            
            // Check permission
            if (!$this->hasPermission($currentUser)) {
                $this->logger->warning("Permission denied for DVSwitch", [
                    'user' => $currentUser ?? 'null'
                ]);
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'You are not authorized to use DVSwitch mode switching.'
                ]));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }
            
            $body = $request->getParsedBody();
            $nodeId = $body['node'] ?? $args['nodeId'] ?? '';
            $modeName = $args['mode'] ?? '';
            
            if (empty($nodeId)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Node ID is required.'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            
            if (empty($modeName)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Mode name is required.'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            
            $result = $this->dvswitchService->switchMode($nodeId, $modeName, $currentUser);
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $result
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (Exception $e) {
            $this->logger->error('Error switching DVSwitch mode', [
                'node_id' => $args['nodeId'] ?? 'unknown',
                'mode' => $args['mode'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error switching mode: ' . $e->getMessage()
            ]));
            
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
    
    /**
     * Switch to a specific talkgroup for a node
     */
    public function switchTalkgroup(Request $request, Response $response, array $args): Response
    {
        $this->logger->warning("switchTalkgroup endpoint called", [
            'session_status' => session_status(),
            'session_id' => session_id() ?: 'no session',
            'session_user' => $_SESSION['user'] ?? 'not set',
            'php_auth_user' => $_SERVER['PHP_AUTH_USER'] ?? 'not set',
            'remote_user' => $_SERVER['REMOTE_USER'] ?? 'not set'
        ]);
        
        try {
            $currentUser = $this->getCurrentUser();
            
            $this->logger->warning("getCurrentUser returned", [
                'current_user' => $currentUser ?? 'null'
            ]);
            
            // Check permission
            if (!$this->hasPermission($currentUser)) {
                $this->logger->warning("Permission denied for DVSwitch", [
                    'user' => $currentUser ?? 'null'
                ]);
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'You are not authorized to use DVSwitch mode switching.'
                ]));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }
            
            $body = $request->getParsedBody();
            $nodeId = $body['node'] ?? '';
            $tgid = $args['tgid'] ?? '';
            
            if (empty($nodeId)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Node ID is required.'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            
            if (empty($tgid)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Talkgroup ID is required.'
                ]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            
            // Decode URL-encoded talkgroup ID
            $tgid = urldecode($tgid);
            
            $result = $this->dvswitchService->switchTalkgroup($nodeId, $tgid, $currentUser);
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $result
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (Exception $e) {
            $this->logger->error('Error switching DVSwitch talkgroup', [
                'node_id' => $body['node'] ?? 'unknown',
                'tgid' => $args['tgid'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error switching talkgroup: ' . $e->getMessage()
            ]));
            
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}

