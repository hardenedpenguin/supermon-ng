package com.supermonng.mobile.domain.repository

import com.supermonng.mobile.domain.model.*
import kotlinx.coroutines.flow.Flow

interface SupermonRepository {
    
    // Authentication
    suspend fun login(credentials: LoginCredentials): Result<LoginData>
    suspend fun logout(): Result<Unit>
    suspend fun getCurrentUser(): Result<LoginData>
    suspend fun checkAuth(): Result<LoginData>
    
    // Nodes
    suspend fun getNodes(): Result<List<Node>>
    suspend fun getAvailableNodes(): Result<List<Node>>
    suspend fun getNode(nodeId: String): Result<Node>
    suspend fun getNodeStatus(nodeId: String): Result<Node>
    suspend fun connectNode(localNode: String, nodeId: String, permanent: Boolean = false): Result<Unit>
    suspend fun disconnectNode(localNode: String, nodeId: String): Result<Unit>
    suspend fun monitorNode(localNode: String, nodeId: String): Result<Unit>
    suspend fun localMonitorNode(localNode: String, nodeId: String): Result<Unit>
    suspend fun sendDtmf(localNode: String, nodeId: String, dtmf: String): Result<Unit>
    suspend fun getLsnodes(nodeId: String): Result<LsnodData>
    
    // System
    suspend fun getSystemInfo(): Result<SystemInfo>
    suspend fun getSystemStats(): Result<SystemStats>
    suspend fun getSystemLogs(): Result<List<String>>
    
    // Configuration
    suspend fun getNodeConfig(): Result<Map<String, Any>>
    suspend fun getUserPreferences(): Result<UserPreferences>
    suspend fun updateUserPreferences(preferences: UserPreferences): Result<Unit>
    
    // Local storage
    fun getStoredCredentials(): Flow<LoginCredentials?>
    suspend fun storeCredentials(credentials: LoginCredentials)
    suspend fun clearCredentials()
    
    fun getStoredServerUrl(): Flow<String?>
    suspend fun storeServerUrl(url: String)
}
