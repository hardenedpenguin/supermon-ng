/**
 * WebSocket service for real-time updates
 * Replaces polling with push-based notifications
 */

export interface WebSocketMessage {
  type: string;
  timestamp?: number;
  [key: string]: any;
}

export interface WebSocketConfig {
  url: string;
  reconnectInterval: number;
  maxReconnectAttempts: number;
  heartbeatInterval: number;
}

export class WebSocketService {
  private ws: WebSocket | null = null;
  private config: WebSocketConfig;
  private reconnectAttempts = 0;
  private reconnectTimer: NodeJS.Timeout | null = null;
  private heartbeatTimer: NodeJS.Timeout | null = null;
  private isConnecting = false;
  private messageHandlers: Map<string, ((data: any) => void)[]> = new Map();
  private connectionHandlers: ((connected: boolean) => void)[] = [];
  private subscribedTopics: Set<string> = new Set();

  constructor(config: Partial<WebSocketConfig> = {}) {
    this.config = {
      url: config.url || this.getWebSocketUrl(),
      reconnectInterval: config.reconnectInterval || 5000,
      maxReconnectAttempts: config.maxReconnectAttempts || 10,
      heartbeatInterval: config.heartbeatInterval || 30000
    };
  }

  /**
   * Connect to WebSocket server
   */
  public connect(): Promise<void> {
    return new Promise((resolve, reject) => {
      if (this.ws?.readyState === WebSocket.OPEN) {
        resolve();
        return;
      }

      if (this.isConnecting) {
        reject(new Error('Connection already in progress'));
        return;
      }

      this.isConnecting = true;

      try {
        this.ws = new WebSocket(this.config.url);
        
        this.ws.onopen = () => {
          console.log('WebSocket connected');
          this.isConnecting = false;
          this.reconnectAttempts = 0;
          this.startHeartbeat();
          this.notifyConnectionHandlers(true);
          this.resubscribeTopics();
          resolve();
        };

        this.ws.onmessage = (event) => {
          this.handleMessage(event);
        };

        this.ws.onclose = (event) => {
          console.log('WebSocket disconnected', event);
          this.isConnecting = false;
          this.stopHeartbeat();
          this.notifyConnectionHandlers(false);
          this.handleReconnect();
        };

        this.ws.onerror = (error) => {
          console.error('WebSocket error:', error);
          this.isConnecting = false;
          this.notifyConnectionHandlers(false);
          reject(error);
        };

      } catch (error) {
        this.isConnecting = false;
        reject(error);
      }
    });
  }

  /**
   * Disconnect from WebSocket server
   */
  public disconnect(): void {
    if (this.reconnectTimer) {
      clearTimeout(this.reconnectTimer);
      this.reconnectTimer = null;
    }
    
    this.stopHeartbeat();
    
    if (this.ws) {
      this.ws.close();
      this.ws = null;
    }
    
    this.subscribedTopics.clear();
    this.notifyConnectionHandlers(false);
  }

  /**
   * Subscribe to a topic
   */
  public subscribe(topic: string): void {
    if (this.subscribedTopics.has(topic)) {
      return;
    }

    this.subscribedTopics.add(topic);
    
    if (this.isConnected()) {
      this.send({
        type: 'subscribe',
        topic: topic
      });
    }
  }

  /**
   * Unsubscribe from a topic
   */
  public unsubscribe(topic: string): void {
    if (!this.subscribedTopics.has(topic)) {
      return;
    }

    this.subscribedTopics.delete(topic);
    
    if (this.isConnected()) {
      this.send({
        type: 'unsubscribe',
        topic: topic
      });
    }
  }

  /**
   * Send authentication token
   */
  public authenticate(token: string): void {
    this.send({
      type: 'auth',
      token: token
    });
  }

  /**
   * Add message handler for specific message type
   */
  public onMessage(type: string, handler: (data: any) => void): void {
    if (!this.messageHandlers.has(type)) {
      this.messageHandlers.set(type, []);
    }
    this.messageHandlers.get(type)!.push(handler);
  }

  /**
   * Remove message handler
   */
  public offMessage(type: string, handler: (data: any) => void): void {
    const handlers = this.messageHandlers.get(type);
    if (handlers) {
      const index = handlers.indexOf(handler);
      if (index > -1) {
        handlers.splice(index, 1);
      }
    }
  }

  /**
   * Add connection state handler
   */
  public onConnectionChange(handler: (connected: boolean) => void): void {
    this.connectionHandlers.push(handler);
  }

  /**
   * Remove connection state handler
   */
  public offConnectionChange(handler: (connected: boolean) => void): void {
    const index = this.connectionHandlers.indexOf(handler);
    if (index > -1) {
      this.connectionHandlers.splice(index, 1);
    }
  }

  /**
   * Check if WebSocket is connected
   */
  public isConnected(): boolean {
    return this.ws?.readyState === WebSocket.OPEN;
  }

  /**
   * Get connection state
   */
  public getConnectionState(): string {
    if (!this.ws) return 'CLOSED';
    
    switch (this.ws.readyState) {
      case WebSocket.CONNECTING: return 'CONNECTING';
      case WebSocket.OPEN: return 'OPEN';
      case WebSocket.CLOSING: return 'CLOSING';
      case WebSocket.CLOSED: return 'CLOSED';
      default: return 'UNKNOWN';
    }
  }

  /**
   * Send message to WebSocket server
   */
  private send(message: WebSocketMessage): void {
    if (this.isConnected()) {
      this.ws!.send(JSON.stringify(message));
    } else {
      console.warn('WebSocket not connected, cannot send message:', message);
    }
  }

  /**
   * Handle incoming WebSocket messages
   */
  private handleMessage(event: MessageEvent): void {
    try {
      const message: WebSocketMessage = JSON.parse(event.data);
      
      // Handle system messages
      switch (message.type) {
        case 'pong':
          // Heartbeat response - no action needed
          break;
          
        case 'error':
          console.error('WebSocket server error:', message.message);
          break;
          
        case 'subscribed':
          console.log('Subscribed to topic:', message.topic);
          break;
          
        case 'unsubscribed':
          console.log('Unsubscribed from topic:', message.topic);
          break;
          
        case 'authenticated':
          console.log('WebSocket authentication successful');
          break;
          
        default:
          // Forward to message handlers
          const handlers = this.messageHandlers.get(message.type);
          if (handlers) {
            handlers.forEach(handler => handler(message));
          }
      }
    } catch (error) {
      console.error('Error parsing WebSocket message:', error);
    }
  }

  /**
   * Handle reconnection logic
   */
  private handleReconnect(): void {
    if (this.reconnectAttempts >= this.config.maxReconnectAttempts) {
      console.error('Max reconnection attempts reached');
      return;
    }

    this.reconnectAttempts++;
    console.log(`Attempting to reconnect (${this.reconnectAttempts}/${this.config.maxReconnectAttempts})...`);
    
    this.reconnectTimer = setTimeout(() => {
      this.connect().catch(error => {
        console.error('Reconnection failed:', error);
      });
    }, this.config.reconnectInterval);
  }

  /**
   * Start heartbeat to keep connection alive
   */
  private startHeartbeat(): void {
    this.stopHeartbeat();
    
    this.heartbeatTimer = setInterval(() => {
      if (this.isConnected()) {
        this.send({ type: 'ping' });
      }
    }, this.config.heartbeatInterval);
  }

  /**
   * Stop heartbeat
   */
  private stopHeartbeat(): void {
    if (this.heartbeatTimer) {
      clearInterval(this.heartbeatTimer);
      this.heartbeatTimer = null;
    }
  }

  /**
   * Resubscribe to topics after reconnection
   */
  private resubscribeTopics(): void {
    this.subscribedTopics.forEach(topic => {
      this.send({
        type: 'subscribe',
        topic: topic
      });
    });
  }

  /**
   * Notify connection state handlers
   */
  private notifyConnectionHandlers(connected: boolean): void {
    this.connectionHandlers.forEach(handler => handler(connected));
  }

  /**
   * Get WebSocket URL from environment or default
   */
  private getWebSocketUrl(): string {
    // Try to get from environment variable or API
    const wsHost = import.meta.env.VITE_WEBSOCKET_HOST || window.location.hostname;
    const wsPort = import.meta.env.VITE_WEBSOCKET_PORT || '9091';
    const wsSecure = import.meta.env.VITE_WEBSOCKET_SECURE === 'true' || window.location.protocol === 'https:';
    
    const protocol = wsSecure ? 'wss' : 'ws';
    return `${protocol}://${wsHost}:${wsPort}`;
  }
}

// Create singleton instance
export const websocketService = new WebSocketService();

// Auto-connect on import
websocketService.connect().catch(error => {
  console.warn('WebSocket auto-connect failed:', error);
});

export default websocketService;
