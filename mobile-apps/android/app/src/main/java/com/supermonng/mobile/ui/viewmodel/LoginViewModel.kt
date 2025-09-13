package com.supermonng.mobile.ui.viewmodel

import android.content.Context
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.supermonng.mobile.data.local.PreferencesManager
import com.supermonng.mobile.data.network.NetworkModule
import com.supermonng.mobile.data.repository.SupermonRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

class LoginViewModel(private val context: Context) : ViewModel() {
    
    private val _uiState = MutableStateFlow(LoginUiState())
    val uiState: StateFlow<LoginUiState> = _uiState.asStateFlow()
    
    private val repository = SupermonRepository()
    private val preferencesManager = PreferencesManager(context)
    
    init {
        loadSavedCredentials()
    }
    
    private fun loadSavedCredentials() {
        val savedUsername = preferencesManager.getUsername()
        val savedPassword = preferencesManager.getPassword()
        val rememberMe = preferencesManager.getRememberMe()
        
        if (savedUsername != null) {
            _uiState.value = _uiState.value.copy(
                username = savedUsername,
                password = savedPassword ?: "",
                rememberMe = rememberMe
            )
        }
    }
    
    fun updateUsername(username: String) {
        _uiState.value = _uiState.value.copy(
            username = username,
            errorMessage = null
        )
    }
    
    fun updatePassword(password: String) {
        _uiState.value = _uiState.value.copy(
            password = password,
            errorMessage = null
        )
    }
    
    fun togglePasswordVisibility() {
        _uiState.value = _uiState.value.copy(
            passwordVisible = !_uiState.value.passwordVisible
        )
    }
    
    fun toggleRememberMe() {
        _uiState.value = _uiState.value.copy(
            rememberMe = !_uiState.value.rememberMe
        )
    }
    
    fun login() {
        val currentState = _uiState.value
        if (currentState.username.isBlank() || currentState.password.isBlank()) {
            _uiState.value = currentState.copy(
                errorMessage = "Please enter both username and password"
            )
            return
        }
        
        viewModelScope.launch {
            _uiState.value = currentState.copy(isLoading = true, errorMessage = null)
            
            try {
                val result = repository.login(currentState.username, currentState.password)
                result.fold(
                    onSuccess = {
                        // Save credentials if remember me is checked
                        preferencesManager.saveCredentials(
                            currentState.username,
                            currentState.password,
                            currentState.rememberMe
                        )
                        
                        _uiState.value = currentState.copy(
                            isLoading = false,
                            isLoginSuccessful = true,
                            errorMessage = null
                        )
                    },
                    onFailure = { exception ->
                        _uiState.value = currentState.copy(
                            isLoading = false,
                            errorMessage = exception.message ?: "Login failed"
                        )
                    }
                )
            } catch (e: Exception) {
                _uiState.value = currentState.copy(
                    isLoading = false,
                    errorMessage = e.message ?: "Network error"
                )
            }
        }
    }
}

data class LoginUiState(
    val username: String = "",
    val password: String = "",
    val passwordVisible: Boolean = false,
    val rememberMe: Boolean = false,
    val isLoading: Boolean = false,
    val isLoginSuccessful: Boolean = false,
    val errorMessage: String? = null
)
