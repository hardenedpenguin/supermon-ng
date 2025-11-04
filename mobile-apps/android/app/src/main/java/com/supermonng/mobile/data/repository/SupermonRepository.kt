package com.supermonng.mobile.data.repository

import com.supermonng.mobile.data.api.SupermonApiService
import com.supermonng.mobile.data.network.NetworkModule
import okhttp3.MediaType.Companion.toMediaTypeOrNull
import com.supermonng.mobile.model.Node
import com.supermonng.mobile.model.NodeStatus
import retrofit2.Response

class SupermonRepository {
    
    private val apiService: SupermonApiService
        get() = NetworkModule.getApiService()
    
    suspend fun login(username: String, password: String): Result<Boolean> {
        return try {
            // Clear any existing cookies and session token before login
            NetworkModule.clearCookies()
            NetworkModule.clearSessionToken()
            NetworkModule.clearCsrfToken()
            
            val response = apiService.login(
                com.supermonng.mobile.data.api.LoginRequest(username, password)
            )
            
            if (response.isSuccessful && response.body()?.success == true) {
                val loginData = response.body()?.data
                
                // Extract session cookie from Set-Cookie header
                val setCookieHeaders = response.headers().values("Set-Cookie")
                if (setCookieHeaders.isNotEmpty()) {
                    val fullCookie = setCookieHeaders.first()
                    // Extract session token from cookie (format: supermon61=TOKEN; Path=/supermon-ng; ...)
                    val sessionToken = fullCookie.split(";")[0].substringAfter("supermon61=")
                    if (sessionToken.isNotEmpty()) {
                        NetworkModule.setSessionToken(sessionToken)
                    }
                } else if (loginData?.session_id != null) {
                    // Fallback to session_id from response if no Set-Cookie header
                    NetworkModule.setSessionToken(loginData.session_id)
                }
                
                // Fetch and store CSRF token after successful login
                val csrfResult = getCsrfToken()
                csrfResult.getOrElse {
                    // Log warning but don't fail login - CSRF token can be fetched on first use
                }
                
                return Result.success(true)
            } else {
                val errorMessage = response.body()?.message ?: "Login failed with code ${response.code()}"
                Result.failure(Exception(errorMessage))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun checkAuth(): Result<Boolean> {
        return try {
            val response = apiService.checkAuth()
            if (response.isSuccessful && response.body()?.data?.authenticated == true) {
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
            // Clear session and CSRF token on logout
            NetworkModule.clearSessionToken()
            NetworkModule.clearCsrfToken()
            NetworkModule.clearCookies()
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
            // Ensure we have a CSRF token before making the request
            val csrfResult = getCsrfToken()
            val csrfToken = csrfResult.getOrElse { return Result.failure(it) }
            
            // Build request body - CSRF token will be added to header by NetworkModule
            // But we also include it in body as fallback for backend compatibility
            val jsonString = if (permanent) {
                """{"localnode":"$nodeId","remotenode":"$targetNodeId","perm":"$permanent","csrf_token":"$csrfToken"}"""
            } else {
                """{"localnode":"$nodeId","remotenode":"$targetNodeId","csrf_token":"$csrfToken"}"""
            }
            val requestBody = okhttp3.RequestBody.create(
                "application/json; charset=utf-8".toMediaTypeOrNull(),
                jsonString
            )
            
            val response = apiService.connectNodeRaw(requestBody)
            
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(true)
            } else {
                val errorBody = response.errorBody()?.string() ?: "No error body"
                val responseBody = response.body()?.message ?: "No response message"
                Result.failure(Exception("Connect failed: $responseBody. Code: ${response.code()}"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun disconnectNode(nodeId: String, targetNodeId: String): Result<Boolean> {
        return try {
            // Check authentication
            val authResult = apiService.checkAuth()
            if (authResult.body()?.data?.authenticated != true) {
                return Result.failure(Exception("Session authentication failed. Please login again."))
            }

            // Get CSRF token
            val csrfResult = getCsrfToken()
            val csrfToken = csrfResult.getOrElse {
                return Result.failure(Exception("CSRF token failed: ${it.message}"))
            }

            // Build request body with CSRF token (also in header via NetworkModule)
            val jsonString = """{"localnode":"$nodeId","remotenode":"$targetNodeId","csrf_token":"$csrfToken"}"""
            val requestBody = okhttp3.RequestBody.create(
                "application/json; charset=utf-8".toMediaTypeOrNull(),
                jsonString
            )
            
            val response = apiService.disconnectNodeRaw(requestBody)

            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(true)
            } else {
                val responseBody = response.body()?.message ?: "No response message"
                Result.failure(Exception("Disconnect failed: $responseBody. Code: ${response.code()}"))
            }
        } catch (e: Exception) {
            Result.failure(Exception("Disconnect exception: ${e.message}"))
        }
    }
    
    suspend fun monitorNode(nodeId: String, targetNodeId: String): Result<Boolean> {
        return try {
            val csrfResult = getCsrfToken()
            val csrfToken = csrfResult.getOrElse { return Result.failure(it) }
            
            // Build request body with CSRF token (also in header via NetworkModule)
            val jsonString = """{"localnode":"$nodeId","remotenode":"$targetNodeId","csrf_token":"$csrfToken"}"""
            val requestBody = okhttp3.RequestBody.create(
                "application/json; charset=utf-8".toMediaTypeOrNull(),
                jsonString
            )
            
            val response = apiService.monitorNodeRaw(requestBody)
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(true)
            } else {
                val responseBody = response.body()?.message ?: "No response message"
                Result.failure(Exception("Monitor failed: $responseBody. Code: ${response.code()}"))
            }
        } catch (e: Exception) {
            Result.failure(Exception("Monitor exception: ${e.message}"))
        }
    }
    
    suspend fun localMonitorNode(nodeId: String): Result<Boolean> {
        return try {
            // Ensure CSRF token is available
            val csrfResult = getCsrfToken()
            val csrfToken = csrfResult.getOrElse { return Result.failure(it) }
            
            // Build request body with CSRF token (also in header via NetworkModule)
            val jsonString = """{"localnode":"$nodeId","csrf_token":"$csrfToken"}"""
            val requestBody = okhttp3.RequestBody.create(
                "application/json; charset=utf-8".toMediaTypeOrNull(),
                jsonString
            )
            
            val response = apiService.localMonitorNodeRaw(requestBody)
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(true)
            } else {
                val responseBody = response.body()?.message ?: "No response message"
                Result.failure(Exception("Local monitor failed: $responseBody. Code: ${response.code()}"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun sendDtmf(nodeId: String, dtmf: String): Result<Boolean> {
        return try {
            // Ensure CSRF token is available (NetworkModule will add to header)
            getCsrfToken().getOrElse { return Result.failure(it) }
            
            // Use DtmfRequest data class - NetworkModule handles CSRF token in header
            val response = apiService.sendDtmf(
                com.supermonng.mobile.data.api.DtmfRequest(nodeId, dtmf)
            )
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(true)
            } else {
                val responseBody = response.body()?.message ?: "No response message"
                Result.failure(Exception("DTMF failed: $responseBody. Code: ${response.code()}"))
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
    
    suspend fun getAmiStatus(nodeIds: List<String>): Result<Map<String, com.supermonng.mobile.data.api.AmiNodeData>> {
        return try {
            val nodesParam = nodeIds.joinToString(",")
            val response = apiService.getAmiStatus(nodesParam)
            if (response.isSuccessful) {
                val data = response.body()?.data
                if (data != null) {
                    Result.success(data)
                } else {
                    Result.failure(Exception("AMI status not available"))
                }
            } else {
                Result.failure(Exception("Failed to fetch AMI status: ${response.code()}"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    private suspend fun getCsrfToken(): Result<String> {
        return try {
            // Check if we already have a token
            val existingToken = NetworkModule.getCsrfToken()
            if (!existingToken.isNullOrEmpty()) {
                return Result.success(existingToken)
            }
            
            // Fetch new token
            val response = apiService.getCsrfToken()
            if (response.isSuccessful && response.body()?.success == true) {
                val token = response.body()?.csrf_token
                if (token != null && token.isNotEmpty()) {
                    // Store token in NetworkModule
                    NetworkModule.setCsrfToken(token)
                    Result.success(token)
                } else {
                    Result.failure(Exception("CSRF token not available in response"))
                }
            } else {
                val errorBody = response.errorBody()?.string() ?: "No error body"
                Result.failure(Exception("Failed to fetch CSRF token: ${response.code()} - $errorBody"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
}
