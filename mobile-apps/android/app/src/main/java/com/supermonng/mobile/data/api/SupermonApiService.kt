package com.supermonng.mobile.data.api

import com.supermonng.mobile.domain.model.*
import retrofit2.Response
import retrofit2.http.*

interface SupermonApiService {
    
    // Authentication endpoints
    @POST("auth/login")
    suspend fun login(@Body credentials: LoginCredentials): Response<ApiResponse<LoginData>>
    
    @POST("auth/logout")
    suspend fun logout(): Response<ApiResponse<Unit>>
    
    @GET("auth/me")
    suspend fun getCurrentUser(): Response<ApiResponse<LoginData>>
    
    @GET("auth/check")
    suspend fun checkAuth(): Response<ApiResponse<LoginData>>
    
    // Node endpoints
    @GET("nodes")
    suspend fun getNodes(): Response<ApiResponse<List<Node>>>
    
    @GET("nodes/available")
    suspend fun getAvailableNodes(): Response<ApiResponse<List<Node>>>
    
    @GET("nodes/{id}")
    suspend fun getNode(@Path("id") nodeId: String): Response<ApiResponse<Node>>
    
    @GET("nodes/{id}/status")
    suspend fun getNodeStatus(@Path("id") nodeId: String): Response<ApiResponse<Node>>
    
    @POST("nodes/connect")
    suspend fun connectNode(@Body request: NodeActionRequest): Response<ApiResponse<Unit>>
    
    @POST("nodes/disconnect")
    suspend fun disconnectNode(@Body request: NodeActionRequest): Response<ApiResponse<Unit>>
    
    @POST("nodes/monitor")
    suspend fun monitorNode(@Body request: NodeActionRequest): Response<ApiResponse<Unit>>
    
    @POST("nodes/local-monitor")
    suspend fun localMonitorNode(@Body request: NodeActionRequest): Response<ApiResponse<Unit>>
    
    @POST("nodes/dtmf")
    suspend fun sendDtmf(@Body request: DtmfRequest): Response<ApiResponse<Unit>>
    
    @GET("nodes/{id}/lsnodes")
    suspend fun getLsnodes(@Path("id") nodeId: String): Response<ApiResponse<LsnodData>>
    
    @GET("nodes/{id}/lsnodes/web")
    suspend fun getLsnodesWeb(@Path("id") nodeId: String): Response<ApiResponse<LsnodData>>
    
    // System endpoints
    @GET("system/info")
    suspend fun getSystemInfo(): Response<ApiResponse<SystemInfo>>
    
    @GET("system/stats")
    suspend fun getSystemStats(): Response<ApiResponse<SystemStats>>
    
    @GET("system/logs")
    suspend fun getSystemLogs(): Response<ApiResponse<List<String>>>
    
    @GET("system/client-ip")
    suspend fun getClientIp(): Response<ApiResponse<String>>
    
    // Config endpoints
    @GET("config/nodes")
    suspend fun getNodeConfig(): Response<ApiResponse<Map<String, Any>>>
    
    @GET("config/user/preferences")
    suspend fun getUserPreferences(): Response<ApiResponse<UserPreferences>>
    
    @PUT("config/user/preferences")
    suspend fun updateUserPreferences(@Body preferences: UserPreferences): Response<ApiResponse<Unit>>
    
    @GET("config/system-info")
    suspend fun getConfigSystemInfo(): Response<ApiResponse<SystemInfo>>
}

data class NodeActionRequest(
    val localNode: String,
    val node: String,
    val permanent: Boolean = false
)

data class DtmfRequest(
    val localNode: String,
    val node: String,
    val dtmf: String
)

data class LsnodData(
    val systemState: Map<String, String> = emptyMap(),
    val mainNode: Map<String, String> = emptyMap(),
    val nodes: List<Map<String, String>> = emptyList(),
    val nodeStatus: List<String> = emptyList(),
    val nodeLstatus: List<String> = emptyList(),
    val iaxRegistry: List<String> = emptyList()
)

data class SystemInfo(
    val version: String? = null,
    val uptime: String? = null,
    val hostname: String? = null,
    val os: String? = null,
    val architecture: String? = null
)

data class SystemStats(
    val cpuUsage: Double? = null,
    val memoryUsage: Double? = null,
    val diskUsage: Double? = null,
    val networkStats: NetworkStats? = null
)

data class NetworkStats(
    val bytesReceived: Long? = null,
    val bytesSent: Long? = null,
    val packetsReceived: Long? = null,
    val packetsSent: Long? = null
)
