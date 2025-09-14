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
            
            val response = apiService.login(
                com.supermonng.mobile.data.api.LoginRequest(username, password)
            )
            
            // Debug login response - concise version
            val setCookieHeaders = response.headers().values("Set-Cookie")
            val headerNames = response.headers().names()
            
            println("DEBUG: Login response code: ${response.code()}")
            println("DEBUG: Set-Cookie headers: $setCookieHeaders")
            println("DEBUG: All header names: $headerNames")
            
            // Check for any cookie-related headers
            headerNames.forEach { name ->
                if (name.lowercase().contains("cookie") || name.lowercase().contains("set")) {
                    println("DEBUG: Cookie header '$name': ${response.headers().values(name)}")
                }
            }
            
            if (response.isSuccessful && response.body()?.success == true) {
                // Store the session token from the response
                val setCookieHeaders = response.headers().values("Set-Cookie")
                var debugInfo = "DEBUG: Login successful\n"
                debugInfo += "DEBUG: Set-Cookie headers: $setCookieHeaders\n"
                debugInfo += "DEBUG: All header names: ${response.headers().names()}\n"
                
                if (setCookieHeaders.isNotEmpty()) {
                    val fullCookie = setCookieHeaders.first()
                    debugInfo += "DEBUG: Full cookie string: $fullCookie\n"
                    
                    // Extract and store session token manually
                    val sessionToken = fullCookie.split(";")[0].split("=")[1]
                    debugInfo += "DEBUG: Extracted session token: $sessionToken\n"
                    NetworkModule.setSessionToken(sessionToken)
                    debugInfo += "DEBUG: Stored session token manually\n"
                } else {
                    debugInfo += "DEBUG: No Set-Cookie headers received - this is the problem!\n"
                    return Result.failure(Exception("$debugInfo\nLogin successful but no session token received!"))
                }
                
                // Login successful - session token stored and tested
                return Result.success(true)
            } else {
                val errorInfo = "DEBUG: Login failed\n"
                val errorInfo2 = "DEBUG: Response code: ${response.code()}\n"
                val errorInfo3 = "DEBUG: Response body: ${response.body()}\n"
                Result.failure(Exception("$errorInfo$errorInfo2$errorInfo3\nLogin failed: ${response.body()?.message ?: "Unknown error"}"))
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
            println("DEBUG: Attempting to connect node $nodeId to $targetNodeId")
            
            // Test auth check first
            println("DEBUG: Testing auth check before connect")
            val authResult = apiService.checkAuth()
            println("DEBUG: Auth check response: ${authResult.code()}, authenticated: ${authResult.body()?.data?.authenticated}")
            
            val csrfResult = getCsrfToken()
            val csrfToken = csrfResult.getOrElse { return Result.failure(it) }
            
            // Use raw JSON to avoid data class serialization issues
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
            println("DEBUG: Connect response: ${response.code()}, success: ${response.body()?.success}")
            
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(true)
            } else {
                val errorBody = response.errorBody()?.string() ?: "No error body"
                val responseBody = response.body()?.message ?: "No response message"
                println("DEBUG: Connect failed - Code: ${response.code()}, Message: $responseBody, Error: $errorBody")
                Result.failure(Exception("Connect failed: $responseBody. Error body: $errorBody. Code: ${response.code()}"))
            }
        } catch (e: Exception) {
            println("DEBUG: Connect exception: ${e.message}")
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

            // Use raw JSON to avoid data class serialization issues
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
            
            // Use raw JSON to avoid data class serialization issues
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
            println("DEBUG: Getting CSRF token with session: ${NetworkModule.getSessionToken()}")
            val response = apiService.getCsrfToken()
            if (response.isSuccessful && response.body()?.success == true) {
                val token = response.body()?.csrf_token
                if (token != null) {
                    println("DEBUG: Got CSRF token: $token")
                    Result.success(token)
                } else {
                    Result.failure(Exception("CSRF token not available"))
                }
            } else {
                val errorBody = response.errorBody()?.string() ?: "No error body"
                println("DEBUG: CSRF token failed: ${response.code()} - $errorBody")
                Result.failure(Exception("Failed to fetch CSRF token: ${response.code()} - $errorBody"))
            }
        } catch (e: Exception) {
            println("DEBUG: CSRF token exception: ${e.message}")
            Result.failure(e)
        }
    }
    
}
