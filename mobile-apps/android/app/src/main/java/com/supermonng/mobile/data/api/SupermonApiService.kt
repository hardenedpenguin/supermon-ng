package com.supermonng.mobile.data.api

import com.supermonng.mobile.model.Node
import retrofit2.Response
import retrofit2.http.*

interface SupermonApiService {
    
    // Authentication endpoints
    @POST("api/auth/login")
    suspend fun login(@Body credentials: LoginRequest): Response<LoginResponse>
    
    @GET("api/auth/check")
    suspend fun checkAuth(): Response<AuthCheckResponse>
    
    @POST("api/auth/logout")
    suspend fun logout(): Response<LogoutResponse>
    
    // Node endpoints
    @GET("api/nodes")
    suspend fun getNodes(): Response<NodesResponse>
    
    @GET("api/nodes/{id}")
    suspend fun getNode(@Path("id") nodeId: String): Response<NodeResponse>
    
    @GET("api/nodes/{id}/status")
    suspend fun getNodeStatus(@Path("id") nodeId: String): Response<NodeStatusResponse>
    
    @POST("api/nodes/connect")
    suspend fun connectNode(@Body request: NodeActionRequest): Response<NodeActionResponse>
    
    @POST("api/nodes/disconnect")
    suspend fun disconnectNode(@Body request: NodeActionRequest): Response<NodeActionResponse>
    
    @POST("api/nodes/monitor")
    suspend fun monitorNode(@Body request: NodeActionRequest): Response<NodeActionResponse>
    
    @POST("api/nodes/local-monitor")
    suspend fun localMonitorNode(@Body request: NodeActionRequest): Response<NodeActionResponse>
    
    @POST("api/nodes/dtmf")
    suspend fun sendDtmf(@Body request: DtmfRequest): Response<NodeActionResponse>
    
    // System endpoints
    @GET("api/system/info")
    suspend fun getSystemInfo(): Response<SystemInfoResponse>
    
    @GET("api/system/stats")
    suspend fun getSystemStats(): Response<SystemStatsResponse>
    
    // Config endpoints
    @GET("api/config/nodes")
    suspend fun getNodeConfig(): Response<NodeConfigResponse>
    
    @GET("api/config/system-info")
    suspend fun getConfigSystemInfo(): Response<SystemInfoResponse>
}

// Request/Response data classes
data class LoginRequest(
    val username: String,
    val password: String
)

data class LoginResponse(
    val success: Boolean,
    val message: String?,
    val data: LoginData?
)

data class LoginData(
    val user: User?,
    val token: String?
)

data class User(
    val id: String,
    val username: String,
    val email: String?
)

data class AuthCheckResponse(
    val success: Boolean,
    val authenticated: Boolean,
    val user: User?
)

data class LogoutResponse(
    val success: Boolean,
    val message: String?
)

data class NodeStatusResponse(
    val success: Boolean,
    val data: NodeStatusData?
)

data class NodeStatusData(
    val node: Node,
    val connected_nodes: List<ConnectedNode>?,
    val system_state: Map<String, Any>?
)

data class ConnectedNode(
    val node: String,
    val info: String,
    val ip: String?,
    val last_keyed: String,
    val link: String,
    val direction: String,
    val elapsed: String,
    val mode: String,
    val keyed: String
)

data class NodeActionRequest(
    val node: String,
    val target_node: String? = null,
    val permanent: Boolean = false
)

data class NodeActionResponse(
    val success: Boolean,
    val message: String?,
    val data: Any?
)

data class DtmfRequest(
    val node: String,
    val dtmf: String
)

data class SystemInfoResponse(
    val success: Boolean,
    val data: SystemInfoData?
)

data class SystemInfoData(
    val version: String,
    val build_date: String,
    val server_time: String,
    val uptime: String,
    val local_node: String?
)

data class SystemStatsResponse(
    val success: Boolean,
    val data: SystemStatsData?
)

data class SystemStatsData(
    val cpu_usage: String,
    val memory_usage: String,
    val disk_usage: String,
    val network_stats: Map<String, Any>?
)

data class NodeConfigResponse(
    val success: Boolean,
    val data: Map<String, NodeConfigData>?
)

data class NodeConfigData(
    val host: String?,
    val port: Int?,
    val hideNodeURL: Int?,
    val lsnodes: String?,
    val listenlive: String?,
    val archive: String?,
    val menu: String?,
    val system: String?
)

data class NodesResponse(
    val success: Boolean,
    val data: List<Node>?,
    val count: Int?,
    val timestamp: String?,
    val config_source: String?
)

data class NodeResponse(
    val success: Boolean,
    val data: Node,
    val timestamp: String?
)
