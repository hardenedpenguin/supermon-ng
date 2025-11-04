package com.supermonng.mobile.data.api

import com.supermonng.mobile.model.Node
import retrofit2.Response
import retrofit2.http.*

interface SupermonApiService {
    
    // Authentication endpoints
    @POST("auth/login")
    suspend fun login(@Body credentials: LoginRequest): Response<LoginResponse>
    
    @GET("auth/check")
    suspend fun checkAuth(): Response<AuthCheckResponse>
    
    @POST("auth/logout")
    suspend fun logout(): Response<LogoutResponse>
    
    // Node endpoints
    @GET("nodes")
    suspend fun getNodes(): Response<NodesResponse>
    
    @GET("nodes/{id}")
    suspend fun getNode(@Path("id") nodeId: String): Response<NodeResponse>
    
    @GET("nodes/{id}/status")
    suspend fun getNodeStatus(@Path("id") nodeId: String): Response<NodeStatusResponse>
    
    @GET("nodes/ami/status")
    suspend fun getAmiStatus(@Query("nodes") nodes: String): Response<AmiStatusResponse>
    
    @POST("nodes/connect")
    @Headers("Content-Type: application/json")
    suspend fun connectNodeRaw(@Body jsonBody: okhttp3.RequestBody): Response<NodeActionResponse>
    
    @POST("nodes/disconnect")
    @Headers("Content-Type: application/json")
    suspend fun disconnectNodeRaw(@Body jsonBody: okhttp3.RequestBody): Response<NodeActionResponse>
    
    @POST("nodes/monitor")
    @Headers("Content-Type: application/json")
    suspend fun monitorNodeRaw(@Body jsonBody: okhttp3.RequestBody): Response<NodeActionResponse>
    
    @POST("nodes/local-monitor")
    @Headers("Content-Type: application/json")
    suspend fun localMonitorNodeRaw(@Body jsonBody: okhttp3.RequestBody): Response<NodeActionResponse>
    
    @POST("nodes/dtmf")
    suspend fun sendDtmf(@Body request: DtmfRequest): Response<NodeActionResponse>
    
    // System endpoints
    @GET("system/info")
    suspend fun getSystemInfo(): Response<SystemInfoResponse>
    
    @GET("system/stats")
    suspend fun getSystemStats(): Response<SystemStatsResponse>
    
    // Config endpoints
    @GET("config/nodes")
    suspend fun getNodeConfig(): Response<NodeConfigResponse>
    
    @GET("config/system-info")
    suspend fun getConfigSystemInfo(): Response<SystemInfoResponse>
    
    // CSRF token endpoint
    @GET("csrf-token")
    suspend fun getCsrfToken(): Response<CsrfTokenResponse>
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
    val user: String?,  // Backend returns username as string, not User object
    val authenticated: Boolean?,
    val session_id: String?  // Backend returns session_id, not token
)

data class User(
    val id: String,
    val username: String,
    val email: String?
)

data class AuthCheckResponse(
    val success: Boolean,
    val data: AuthCheckData?
)

data class AuthCheckData(
    val authenticated: Boolean,
    val user: Map<String, String>?,  // Backend returns {"name": "username"}
    val permissions: Map<String, Boolean>?,
    val config_source: String?
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
    val node_id: String?,
    val status: String?,
    val last_heard: String?,
    val connected_nodes: String?,  // Backend returns as string, not list
    val cos_keyed: String?,
    val tx_keyed: String?,
    val cpu_temp: String?,
    val alert: String?,
    val wx: String?,
    val disk: String?,
    val timestamp: String?
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
    val localnode: String,
    val remotenode: String? = null,
    val perm: Boolean = false,
    val csrf_token: String? = null
)

data class NodeActionResponse(
    val success: Boolean,
    val message: String?,
    val data: Any?
)

data class DtmfRequest(
    val localnode: String,  // Backend expects 'localnode', not 'node'
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

data class AmiStatusResponse(
    val success: Boolean,
    val data: Map<String, AmiNodeData>?
)

data class AmiNodeData(
    val node: String,
    val info: String,
    val status: String,
    val cos_keyed: Int,
    val tx_keyed: Int,
    val cpu_temp: String,
    val cpu_up: String,
    val cpu_load: String,
    val ALERT: String?,
    val WX: String?,
    val DISK: String?,
    val remote_nodes: List<ConnectedNode>?
)

data class CsrfTokenResponse(
    val success: Boolean,
    val csrf_token: String
)
