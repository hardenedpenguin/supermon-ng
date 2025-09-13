package com.supermonng.mobile.ui.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
// import com.supermonng.mobile.domain.model.LoginCredentials
// import com.supermonng.mobile.domain.usecase.LoginUseCase
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

class LoginViewModel : ViewModel() {
    
    private val _uiState = MutableStateFlow(LoginUiState())
    val uiState: StateFlow<LoginUiState> = _uiState.asStateFlow()
    
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
            
            // Simple credentials validation
            
            // Simulate login for now - replace with actual API call later
            try {
                // Simulate network delay
                kotlinx.coroutines.delay(1000)
                
                // Simple validation - accept any non-empty credentials for now
                if (currentState.username.isNotBlank() && currentState.password.isNotBlank()) {
                    _uiState.value = currentState.copy(
                        isLoading = false,
                        isLoginSuccessful = true,
                        errorMessage = null
                    )
                } else {
                    _uiState.value = currentState.copy(
                        isLoading = false,
                        errorMessage = "Username and password are required"
                    )
                }
            } catch (e: Exception) {
                _uiState.value = currentState.copy(
                    isLoading = false,
                    errorMessage = e.message ?: "Login failed"
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
