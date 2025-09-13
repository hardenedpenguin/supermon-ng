package com.supermonng.mobile.ui.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.supermonng.mobile.data.network.NetworkModule
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import java.net.URL
import java.net.HttpURLConnection

class SettingsViewModel : ViewModel() {
    
    private val _uiState = MutableStateFlow(SettingsUiState())
    val uiState: StateFlow<SettingsUiState> = _uiState.asStateFlow()
    
    fun loadSettings() {
        viewModelScope.launch {
            // Load saved settings from SharedPreferences or DataStore
            // For now, we'll use default values
            _uiState.value = _uiState.value.copy(
                serverUrl = "https://sm.w5gle.us", // Default to your server as example
                serverPort = "443",
                username = "",
                password = "",
                rememberCredentials = true
            )
        }
    }
    
    fun updateServerUrl(url: String) {
        _uiState.value = _uiState.value.copy(
            serverUrl = url,
            serverUrlError = null,
            connectionStatus = null,
            saveStatus = null
        )
    }
    
    fun updateServerPort(port: String) {
        _uiState.value = _uiState.value.copy(
            serverPort = port,
            serverPortError = null,
            connectionStatus = null,
            saveStatus = null
        )
    }
    
    fun updateUsername(username: String) {
        _uiState.value = _uiState.value.copy(
            username = username,
            saveStatus = null
        )
    }
    
    fun updatePassword(password: String) {
        _uiState.value = _uiState.value.copy(
            password = password,
            saveStatus = null
        )
    }
    
    fun updateRememberCredentials(remember: Boolean) {
        _uiState.value = _uiState.value.copy(
            rememberCredentials = remember
        )
    }
    
    fun togglePasswordVisibility() {
        _uiState.value = _uiState.value.copy(
            passwordVisible = !_uiState.value.passwordVisible
        )
    }
    
    fun testConnection() {
        viewModelScope.launch {
            val currentState = _uiState.value
            
            // Validate URL
            if (currentState.serverUrl.isBlank()) {
                _uiState.value = currentState.copy(
                    serverUrlError = "Server URL is required"
                )
                return@launch
            }
            
            // Validate URL format
            try {
                val url = if (currentState.serverPort.isNotBlank() && currentState.serverPort != "443" && currentState.serverPort != "80") {
                    "${currentState.serverUrl}:${currentState.serverPort}"
                } else {
                    currentState.serverUrl
                }
                
                URL(url) // This will throw if URL is invalid
            } catch (e: Exception) {
                _uiState.value = currentState.copy(
                    serverUrlError = "Invalid URL format"
                )
                return@launch
            }
            
            _uiState.value = currentState.copy(
                isTestingConnection = true,
                connectionStatus = null
            )
            
            try {
                // Simulate connection test
                kotlinx.coroutines.delay(2000)
                
                // For now, we'll simulate a successful connection
                // In a real implementation, this would make an actual HTTP request
                val testUrl = if (currentState.serverPort.isNotBlank() && currentState.serverPort != "443" && currentState.serverPort != "80") {
                    "${currentState.serverUrl}:${currentState.serverPort}/api/v1/system/info"
                } else {
                    "${currentState.serverUrl}/api/v1/system/info"
                }
                
                // TODO: Replace with actual HTTP request
                // val response = makeHttpRequest(testUrl)
                
                _uiState.value = _uiState.value.copy(
                    isTestingConnection = false,
                    connectionStatus = "Success - Server is reachable"
                )
                
            } catch (e: Exception) {
                _uiState.value = _uiState.value.copy(
                    isTestingConnection = false,
                    connectionStatus = "Failed - ${e.message}"
                )
            }
        }
    }
    
    fun saveSettings() {
        viewModelScope.launch {
            val currentState = _uiState.value
            
            // Validate required fields
            if (currentState.serverUrl.isBlank()) {
                _uiState.value = currentState.copy(
                    serverUrlError = "Server URL is required"
                )
                return@launch
            }
            
            if (currentState.username.isBlank()) {
                _uiState.value = currentState.copy(
                    saveStatus = "Username is required"
                )
                return@launch
            }
            
            _uiState.value = currentState.copy(
                isSaving = true,
                saveStatus = null
            )
            
            try {
                // Update the network module with the new base URL
                val baseUrl = if (currentState.serverPort.isNotBlank() && currentState.serverPort != "443" && currentState.serverPort != "80") {
                    "${currentState.serverUrl}:${currentState.serverPort}"
                } else {
                    currentState.serverUrl
                }
                NetworkModule.setBaseUrl(baseUrl)
                
                // Simulate saving settings
                kotlinx.coroutines.delay(500)
                
                // TODO: Save to SharedPreferences or DataStore
                // saveToPreferences(currentState)
                
                _uiState.value = _uiState.value.copy(
                    isSaving = false,
                    saveStatus = "Settings saved successfully!"
                )
                
            } catch (e: Exception) {
                _uiState.value = _uiState.value.copy(
                    isSaving = false,
                    saveStatus = "Failed to save settings: ${e.message}"
                )
            }
        }
    }
    
    // Helper function to get the full server URL
    fun getFullServerUrl(): String {
        val state = _uiState.value
        return if (state.serverPort.isNotBlank() && state.serverPort != "443" && state.serverPort != "80") {
            "${state.serverUrl}:${state.serverPort}"
        } else {
            state.serverUrl
        }
    }
}

data class SettingsUiState(
    val serverUrl: String = "",
    val serverPort: String = "",
    val username: String = "",
    val password: String = "",
    val rememberCredentials: Boolean = true,
    val passwordVisible: Boolean = false,
    val isTestingConnection: Boolean = false,
    val isSaving: Boolean = false,
    val connectionStatus: String? = null,
    val saveStatus: String? = null,
    val serverUrlError: String? = null,
    val serverPortError: String? = null
)
