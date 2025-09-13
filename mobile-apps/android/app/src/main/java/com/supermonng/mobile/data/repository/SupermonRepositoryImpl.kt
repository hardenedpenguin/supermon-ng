package com.supermonng.mobile.data.repository

import com.supermonng.mobile.data.api.SupermonApiService
import com.supermonng.mobile.data.local.SupermonLocalDataSource
import com.supermonng.mobile.domain.model.*
import com.supermonng.mobile.domain.repository.SupermonRepository
import kotlinx.coroutines.flow.Flow
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class SupermonRepositoryImpl @Inject constructor(
    private val apiService: SupermonApiService,
    private val localDataSource: SupermonLocalDataSource
) : SupermonRepository {
    
    override suspend fun login(credentials: LoginCredentials): Result<LoginData> {
        return try {
            val response = apiService.login(credentials)
            if (response.isSuccessful && response.body()?.success == true) {
                val loginData = response.body()?.data
                if (loginData != null) {
                    localDataSource.storeCredentials(credentials)
                    Result.success(loginData)
                } else {
                    Result.failure(Exception("Login data is null"))
                }
            } else {
                val errorMessage = response.body()?.message ?: "Login failed"
                Result.failure(Exception(errorMessage))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    override suspend fun logout(): Result<Unit> {
        return try {
            val response = apiService.logout()
            if (response.isSuccessful) {
                localDataSource.clearCredentials()
                Result.success(Unit)
            } else {
                Result.failure(Exception("Logout failed"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    override suspend fun getCurrentUser(): Result<LoginData> {
        return try {
            val response = apiService.getCurrentUser()
            if (response.isSuccessful && response.body()?.success == true) {
                val loginData = response.body()?.data
                if (loginData != null) {
                    Result.success(loginData)
                } else {
                    Result.failure(Exception("User data is null"))
                }
            } else {
                Result.failure(Exception("Failed to get current user"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    override suspend fun checkAuth(): Result<LoginData> {
        return try {
            val response = apiService.checkAuth()
            if (response.isSuccessful && response.body()?.success == true) {
                val loginData = response.body()?.data
                if (loginData != null) {
                    Result.success(loginData)
                } else {
                    Result.failure(Exception("Auth check failed"))
                }
            } else {
                Result.failure(Exception("Auth check failed"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    override suspend fun getNodes(): Result<List<Node>> {
        return try {
            val response = apiService.getNodes()
            if (response.isSuccessful && response.body()?.success == true) {
                val nodes = response.body()?.data ?: emptyList()
                Result.success(nodes)
            } else {
                Result.failure(Exception("Failed to load nodes"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    override suspend fun getAvailableNodes(): Result<List<Node>> {
        return try {
            val response = apiService.getAvailableNodes()
            if (response.isSuccessful && response.body()?.success == true) {
                val nodes = response.body()?.data ?: emptyList()
                Result.success(nodes)
            } else {
                Result.failure(Exception("Failed to load available nodes"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    override suspend fun getNode(nodeId: String): Result<Node> {
        return try {
            val response = apiService.getNode(nodeId)
            if (response.isSuccessful && response.body()?.success == true) {
                val node = response.body()?.data
                if (node != null) {
                    Result.success(node)
                } else {
                    Result.failure(Exception("Node data is null"))
                }
            } else {
                Result.failure(Exception("Failed to load node"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    override suspend fun getNodeStatus(nodeId: String): Result<Node> {
        return try {
            val response = apiService.getNodeStatus(nodeId)
            if (response.isSuccessful && response.body()?.success == true) {
                val node = response.body()?.data
                if (node != null) {
                    Result.success(node)
                } else {
                    Result.failure(Exception("Node status data is null"))
                }
            } else {
                Result.failure(Exception("Failed to load node status"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    override suspend fun connectNode(localNode: String, nodeId: String, permanent: Boolean): Result<Unit> {
        return try {
            val request = com.supermonng.mobile.data.api.NodeActionRequest(
                localNode = localNode,
                node = nodeId,
                permanent = permanent
            )
            val response = apiService.connectNode(request)
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(Unit)
            } else {
                val errorMessage = response.body()?.message ?: "Failed to connect node"
                Result.failure(Exception(errorMessage))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    override suspend fun disconnectNode(localNode: String, nodeId: String): Result<Unit> {
        return try {
            val request = com.supermonng.mobile.data.api.NodeActionRequest(
                localNode = localNode,
                node = nodeId,
                permanent = false
            )
            val response = apiService.disconnectNode(request)
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(Unit)
            } else {
                val errorMessage = response.body()?.message ?: "Failed to disconnect node"
                Result.failure(Exception(errorMessage))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    override suspend fun monitorNode(localNode: String, nodeId: String): Result<Unit> {
        return try {
            val request = com.supermonng.mobile.data.api.NodeActionRequest(
                localNode = localNode,
                node = nodeId,
                permanent = false
            )
            val response = apiService.monitorNode(request)
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(Unit)
            } else {
                val errorMessage = response.body()?.message ?: "Failed to monitor node"
                Result.failure(Exception(errorMessage))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    override suspend fun localMonitorNode(localNode: String, nodeId: String): Result<Unit> {
        return try {
            val request = com.supermonng.mobile.data.api.NodeActionRequest(
                localNode = localNode,
                node = nodeId,
                permanent = false
            )
            val response = apiService.localMonitorNode(request)
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(Unit)
            } else {
                val errorMessage = response.body()?.message ?: "Failed to local monitor node"
                Result.failure(Exception(errorMessage))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    override suspend fun sendDtmf(localNode: String, nodeId: String, dtmf: String): Result<Unit> {
        return try {
            val request = com.supermonng.mobile.data.api.DtmfRequest(
                localNode = localNode,
                node = nodeId,
                dtmf = dtmf
            )
            val response = apiService.sendDtmf(request)
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(Unit)
            } else {
                val errorMessage = response.body()?.message ?: "Failed to send DTMF"
                Result.failure(Exception(errorMessage))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    override suspend fun getLsnodes(nodeId: String): Result<LsnodData> {
        return try {
            val response = apiService.getLsnodes(nodeId)
            if (response.isSuccessful && response.body()?.success == true) {
                val lsnodData = response.body()?.data
                if (lsnodData != null) {
                    Result.success(lsnodData)
                } else {
                    Result.failure(Exception("Lsnod data is null"))
                }
            } else {
                Result.failure(Exception("Failed to load lsnodes"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    override suspend fun getSystemInfo(): Result<SystemInfo> {
        return try {
            val response = apiService.getSystemInfo()
            if (response.isSuccessful && response.body()?.success == true) {
                val systemInfo = response.body()?.data
                if (systemInfo != null) {
                    Result.success(systemInfo)
                } else {
                    Result.failure(Exception("System info is null"))
                }
            } else {
                Result.failure(Exception("Failed to load system info"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    override suspend fun getSystemStats(): Result<SystemStats> {
        return try {
            val response = apiService.getSystemStats()
            if (response.isSuccessful && response.body()?.success == true) {
                val systemStats = response.body()?.data
                if (systemStats != null) {
                    Result.success(systemStats)
                } else {
                    Result.failure(Exception("System stats is null"))
                }
            } else {
                Result.failure(Exception("Failed to load system stats"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    override suspend fun getSystemLogs(): Result<List<String>> {
        return try {
            val response = apiService.getSystemLogs()
            if (response.isSuccessful && response.body()?.success == true) {
                val logs = response.body()?.data ?: emptyList()
                Result.success(logs)
            } else {
                Result.failure(Exception("Failed to load system logs"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    override suspend fun getNodeConfig(): Result<Map<String, Any>> {
        return try {
            val response = apiService.getNodeConfig()
            if (response.isSuccessful && response.body()?.success == true) {
                val config = response.body()?.data ?: emptyMap()
                Result.success(config)
            } else {
                Result.failure(Exception("Failed to load node config"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    override suspend fun getUserPreferences(): Result<UserPreferences> {
        return try {
            val response = apiService.getUserPreferences()
            if (response.isSuccessful && response.body()?.success == true) {
                val preferences = response.body()?.data
                if (preferences != null) {
                    Result.success(preferences)
                } else {
                    Result.failure(Exception("User preferences is null"))
                }
            } else {
                Result.failure(Exception("Failed to load user preferences"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    override suspend fun updateUserPreferences(preferences: UserPreferences): Result<Unit> {
        return try {
            val response = apiService.updateUserPreferences(preferences)
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(Unit)
            } else {
                val errorMessage = response.body()?.message ?: "Failed to update preferences"
                Result.failure(Exception(errorMessage))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    override fun getStoredCredentials(): Flow<LoginCredentials?> {
        return localDataSource.getStoredCredentials()
    }
    
    override suspend fun storeCredentials(credentials: LoginCredentials) {
        localDataSource.storeCredentials(credentials)
    }
    
    override suspend fun clearCredentials() {
        localDataSource.clearCredentials()
    }
    
    override fun getStoredServerUrl(): Flow<String?> {
        return localDataSource.getStoredServerUrl()
    }
    
    override suspend fun storeServerUrl(url: String) {
        localDataSource.storeServerUrl(url)
    }
}
