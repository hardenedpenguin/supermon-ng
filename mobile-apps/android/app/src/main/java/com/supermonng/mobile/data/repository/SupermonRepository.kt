package com.supermonng.mobile.data.repository

import com.supermonng.mobile.data.api.SupermonApiService
import com.supermonng.mobile.data.network.NetworkModule
import com.supermonng.mobile.model.Node
import com.supermonng.mobile.model.NodeStatus
import retrofit2.Response

class SupermonRepository {
    
    private val apiService: SupermonApiService
        get() = NetworkModule.getApiService()
    
    suspend fun login(username: String, password: String): Result<Boolean> {
        return try {
            val response = apiService.login(
                com.supermonng.mobile.data.api.LoginRequest(username, password)
            )
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(true)
            } else {
                Result.failure(Exception(response.body()?.message ?: "Login failed"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun checkAuth(): Result<Boolean> {
        return try {
            val response = apiService.checkAuth()
            if (response.isSuccessful && response.body()?.authenticated == true) {
                Result.success(true)
            } else {
                Result.failure(Exception("Not authenticated"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun logout(): Result<Boolean> {
        return try {
            val response = apiService.logout()
            Result.success(response.isSuccessful)
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun getNodes(): Result<List<Node>> {
        return try {
            val response = apiService.getNodes()
            if (response.isSuccessful) {
                val nodesResponse = response.body()
                if (nodesResponse?.success == true) {
                    val nodes = nodesResponse.data ?: emptyList()
                    Result.success(nodes)
                } else {
                    Result.failure(Exception("API returned success=false"))
                }
            } else {
                Result.failure(Exception("Failed to fetch nodes: ${response.code()}"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun getNode(nodeId: String): Result<Node> {
        return try {
            val response = apiService.getNode(nodeId)
            if (response.isSuccessful) {
                val nodeResponse = response.body()
                if (nodeResponse?.success == true) {
                    val node = nodeResponse.data
                    Result.success(node)
                } else {
                    Result.failure(Exception("API returned success=false"))
                }
            } else {
                Result.failure(Exception("Failed to fetch node: ${response.code()}"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun connectNode(nodeId: String, targetNodeId: String? = null, permanent: Boolean = false): Result<Boolean> {
        return try {
            val response = apiService.connectNode(
                com.supermonng.mobile.data.api.NodeActionRequest(nodeId, targetNodeId, permanent)
            )
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(true)
            } else {
                Result.failure(Exception(response.body()?.message ?: "Connect failed"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun disconnectNode(nodeId: String): Result<Boolean> {
        return try {
            val response = apiService.disconnectNode(
                com.supermonng.mobile.data.api.NodeActionRequest(nodeId)
            )
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(true)
            } else {
                Result.failure(Exception(response.body()?.message ?: "Disconnect failed"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun monitorNode(nodeId: String): Result<Boolean> {
        return try {
            val response = apiService.monitorNode(
                com.supermonng.mobile.data.api.NodeActionRequest(nodeId)
            )
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(true)
            } else {
                Result.failure(Exception(response.body()?.message ?: "Monitor failed"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun localMonitorNode(nodeId: String): Result<Boolean> {
        return try {
            val response = apiService.localMonitorNode(
                com.supermonng.mobile.data.api.NodeActionRequest(nodeId)
            )
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(true)
            } else {
                Result.failure(Exception(response.body()?.message ?: "Local monitor failed"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun sendDtmf(nodeId: String, dtmf: String): Result<Boolean> {
        return try {
            val response = apiService.sendDtmf(
                com.supermonng.mobile.data.api.DtmfRequest(nodeId, dtmf)
            )
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(true)
            } else {
                Result.failure(Exception(response.body()?.message ?: "DTMF failed"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun getSystemInfo(): Result<com.supermonng.mobile.data.api.SystemInfoData> {
        return try {
            val response = apiService.getSystemInfo()
            if (response.isSuccessful) {
                val data = response.body()?.data
                if (data != null) {
                    Result.success(data)
                } else {
                    Result.failure(Exception("System info not available"))
                }
            } else {
                Result.failure(Exception("Failed to fetch system info: ${response.code()}"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
}
