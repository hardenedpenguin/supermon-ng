/**
 * WebSocket Service
 * 
 * Manages per-node WebSocket connections for real-time data updates.
 * Matches Allmon3's per-node WebSocket architecture.
 */

export interface WebSocketMessage {
  node: string
  timestamp: number
  xstat?: string
  sawstat?: string
  [key: string]: any
}

export interface WebSocketConnectionState {
  connected: boolean
  connecting: boolean
  error: string | null
  reconnectAttempts: number
  lastMessageTime: number
}

type MessageHandler = (data: WebSocketMessage) => void
type ConnectionStateHandler = (state: WebSocketConnectionState) => void

class WebSocketService {
  private connections: Map<string, WebSocket> = new Map()
  private connectionStates: Map<string, WebSocketConnectionState> = new Map()
  private messageHandlers: Map<string, Set<MessageHandler>> = new Map()
  private stateHandlers: Map<string, Set<ConnectionStateHandler>> = new Map()
  private reconnectTimers: Map<string, NodeJS.Timeout> = new Map()
  private pingTimers: Map<string, NodeJS.Timeout> = new Map()
  
  private readonly MAX_RECONNECT_ATTEMPTS = 10
  private readonly INITIAL_RECONNECT_DELAY = 1000 // 1 second
  private readonly MAX_RECONNECT_DELAY = 30000 // 30 seconds
  private readonly PING_INTERVAL = 30000 // 30 seconds
  private readonly CONNECTION_TIMEOUT = 10000 // 10 seconds

  /**
   * Connect to a node's WebSocket server
   */
  async connectToNode(nodeId: string, wsUrl: string): Promise<void> {
    // Disconnect if already connected
    if (this.connections.has(nodeId)) {
      this.disconnectFromNode(nodeId)
    }

    // Initialize connection state
    this.connectionStates.set(nodeId, {
      connected: false,
      connecting: true,
      error: null,
      reconnectAttempts: 0,
      lastMessageTime: 0
    })
    this.notifyStateChange(nodeId)

    return new Promise((resolve, reject) => {
      try {
        const ws = new WebSocket(wsUrl)
        let connectionTimeout: NodeJS.Timeout

        // Set connection timeout
        connectionTimeout = setTimeout(() => {
          if (ws.readyState === WebSocket.CONNECTING) {
            ws.close()
            const error = 'Connection timeout'
            this.updateConnectionState(nodeId, {
              connected: false,
              connecting: false,
              error,
              reconnectAttempts: this.connectionStates.get(nodeId)?.reconnectAttempts || 0,
              lastMessageTime: 0
            })
            reject(new Error(error))
          }
        }, this.CONNECTION_TIMEOUT)

        ws.onopen = () => {
          clearTimeout(connectionTimeout)
          this.connections.set(nodeId, ws)
          this.updateConnectionState(nodeId, {
            connected: true,
            connecting: false,
            error: null,
            reconnectAttempts: 0,
            lastMessageTime: Date.now()
          })

          // Start ping interval
          this.startPing(nodeId)

          // Send subscribe message
          ws.send(JSON.stringify({ action: 'subscribe', node: nodeId }))

          resolve()
        }

        ws.onmessage = (event) => {
          try {
            const data = JSON.parse(event.data) as WebSocketMessage
            this.updateConnectionState(nodeId, {
              ...this.connectionStates.get(nodeId)!,
              lastMessageTime: Date.now()
            })
            this.notifyMessage(nodeId, data)
          } catch (error) {
            console.error(`Error parsing WebSocket message for node ${nodeId}:`, error)
          }
        }

        ws.onerror = (error) => {
          clearTimeout(connectionTimeout)
          const errorMessage = `WebSocket error for node ${nodeId}`
          console.error(errorMessage, error)
          this.updateConnectionState(nodeId, {
            ...this.connectionStates.get(nodeId)!,
            connecting: false,
            error: errorMessage
          })
          reject(new Error(errorMessage))
        }

        ws.onclose = (event) => {
          clearTimeout(connectionTimeout)
          this.stopPing(nodeId)
          this.connections.delete(nodeId)

          const state = this.connectionStates.get(nodeId)
          if (state) {
            this.updateConnectionState(nodeId, {
              ...state,
              connected: false,
              connecting: false
            })

            // Attempt reconnection if not a clean close
            if (event.code !== 1000 && state.reconnectAttempts < this.MAX_RECONNECT_ATTEMPTS) {
              this.scheduleReconnect(nodeId, wsUrl)
            } else if (state.reconnectAttempts >= this.MAX_RECONNECT_ATTEMPTS) {
              this.updateConnectionState(nodeId, {
                ...state,
                error: 'Max reconnection attempts reached'
              })
            }
          }
        }

      } catch (error) {
        const errorMessage = `Failed to create WebSocket connection for node ${nodeId}`
        console.error(errorMessage, error)
        this.updateConnectionState(nodeId, {
          connected: false,
          connecting: false,
          error: errorMessage,
          reconnectAttempts: this.connectionStates.get(nodeId)?.reconnectAttempts || 0,
          lastMessageTime: 0
        })
        reject(error)
      }
    })
  }

  /**
   * Disconnect from a node's WebSocket server
   */
  disconnectFromNode(nodeId: string): void {
    // Clear reconnect timer
    const reconnectTimer = this.reconnectTimers.get(nodeId)
    if (reconnectTimer) {
      clearTimeout(reconnectTimer)
      this.reconnectTimers.delete(nodeId)
    }

    // Stop ping
    this.stopPing(nodeId)

    // Close WebSocket connection
    const ws = this.connections.get(nodeId)
    if (ws) {
      ws.close(1000, 'Client disconnect')
      this.connections.delete(nodeId)
    }

    // Update state
    this.connectionStates.delete(nodeId)
    this.notifyStateChange(nodeId)
  }

  /**
   * Subscribe to messages from a node
   */
  onNodeMessage(nodeId: string, handler: MessageHandler): () => void {
    if (!this.messageHandlers.has(nodeId)) {
      this.messageHandlers.set(nodeId, new Set())
    }
    this.messageHandlers.get(nodeId)!.add(handler)

    // Return unsubscribe function
    return () => {
      const handlers = this.messageHandlers.get(nodeId)
      if (handlers) {
        handlers.delete(handler)
        if (handlers.size === 0) {
          this.messageHandlers.delete(nodeId)
        }
      }
    }
  }

  /**
   * Subscribe to connection state changes for a node
   */
  onNodeStateChange(nodeId: string, handler: ConnectionStateHandler): () => void {
    if (!this.stateHandlers.has(nodeId)) {
      this.stateHandlers.set(nodeId, new Set())
    }
    this.stateHandlers.get(nodeId)!.add(handler)

    // Return unsubscribe function
    return () => {
      const handlers = this.stateHandlers.get(nodeId)
      if (handlers) {
        handlers.delete(handler)
        if (handlers.size === 0) {
          this.stateHandlers.delete(nodeId)
        }
      }
    }
  }

  /**
   * Check if a node is connected
   */
  isNodeConnected(nodeId: string): boolean {
    const state = this.connectionStates.get(nodeId)
    return state?.connected === true
  }

  /**
   * Get connection state for a node
   */
  getNodeState(nodeId: string): WebSocketConnectionState | null {
    return this.connectionStates.get(nodeId) || null
  }

  /**
   * Get all connected node IDs
   */
  getAllConnectedNodes(): string[] {
    return Array.from(this.connections.keys())
  }

  /**
   * Disconnect from all nodes
   */
  disconnectAll(): void {
    const nodeIds = Array.from(this.connections.keys())
    nodeIds.forEach(nodeId => this.disconnectFromNode(nodeId))
  }

  /**
   * Schedule reconnection attempt
   */
  private scheduleReconnect(nodeId: string, wsUrl: string): void {
    const state = this.connectionStates.get(nodeId)
    if (!state) return

    const attempts = state.reconnectAttempts + 1
    const delay = Math.min(
      this.INITIAL_RECONNECT_DELAY * Math.pow(2, attempts - 1),
      this.MAX_RECONNECT_DELAY
    )

    this.updateConnectionState(nodeId, {
      ...state,
      reconnectAttempts: attempts
    })

    const timer = setTimeout(() => {
      this.reconnectTimers.delete(nodeId)
      this.connectToNode(nodeId, wsUrl).catch(error => {
        console.error(`Reconnection failed for node ${nodeId}:`, error)
      })
    }, delay)

    this.reconnectTimers.set(nodeId, timer)
  }

  /**
   * Start ping interval for a node
   */
  private startPing(nodeId: string): void {
    this.stopPing(nodeId) // Clear any existing ping timer

    const timer = setInterval(() => {
      const ws = this.connections.get(nodeId)
      if (ws && ws.readyState === WebSocket.OPEN) {
        ws.send(JSON.stringify({ action: 'ping' }))
      } else {
        this.stopPing(nodeId)
      }
    }, this.PING_INTERVAL)

    this.pingTimers.set(nodeId, timer)
  }

  /**
   * Stop ping interval for a node
   */
  private stopPing(nodeId: string): void {
    const timer = this.pingTimers.get(nodeId)
    if (timer) {
      clearInterval(timer)
      this.pingTimers.delete(nodeId)
    }
  }

  /**
   * Update connection state and notify handlers
   */
  private updateConnectionState(nodeId: string, state: WebSocketConnectionState): void {
    this.connectionStates.set(nodeId, state)
    this.notifyStateChange(nodeId)
  }

  /**
   * Notify message handlers
   */
  private notifyMessage(nodeId: string, data: WebSocketMessage): void {
    const handlers = this.messageHandlers.get(nodeId)
    if (handlers) {
      handlers.forEach(handler => {
        try {
          handler(data)
        } catch (error) {
          console.error(`Error in message handler for node ${nodeId}:`, error)
        }
      })
    }
  }

  /**
   * Notify state change handlers
   */
  private notifyStateChange(nodeId: string): void {
    const handlers = this.stateHandlers.get(nodeId)
    const state = this.connectionStates.get(nodeId)
    if (handlers && state) {
      handlers.forEach(handler => {
        try {
          handler(state)
        } catch (error) {
          console.error(`Error in state handler for node ${nodeId}:`, error)
        }
      })
    }
  }
}

// Export singleton instance
export const webSocketService = new WebSocketService()

