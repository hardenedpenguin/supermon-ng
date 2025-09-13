package com.supermonng.mobile.ui.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.supermonng.mobile.model.Node
import com.supermonng.mobile.model.NodeStatus
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

class NodesViewModel : ViewModel() {
    
    private val _uiState = MutableStateFlow(NodesUiState())
    val uiState: StateFlow<NodesUiState> = _uiState.asStateFlow()
    
    fun loadNodes() {
        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(isLoading = true, errorMessage = null)
            
            try {
                // Simulate network delay
                kotlinx.coroutines.delay(1000)
                
                // Create sample nodes for testing
                val sampleNodes = listOf(
                    Node(
                        id = "12345",
                        callsign = "W5GLE",
                        description = "Main Repeater",
                        location = "Austin, TX",
                        status = NodeStatus.ONLINE
                    ),
                    Node(
                        id = "67890",
                        callsign = "W5ABC",
                        description = "Backup Repeater",
                        location = "Houston, TX",
                        status = NodeStatus.OFFLINE
                    )
                )
                
                _uiState.value = _uiState.value.copy(
                    isLoading = false,
                    nodes = sampleNodes,
                    errorMessage = null
                )
            } catch (e: Exception) {
                _uiState.value = _uiState.value.copy(
                    isLoading = false,
                    errorMessage = e.message ?: "Failed to load nodes"
                )
            }
        }
    }
    
    fun connectNode(nodeId: String) {
        viewModelScope.launch {
            try {
                // Simulate network delay
                kotlinx.coroutines.delay(500)
                
                // For now, just show success message
                _uiState.value = _uiState.value.copy(
                    errorMessage = "Connected to node $nodeId"
                )
                
                // Clear message after 3 seconds
                kotlinx.coroutines.delay(3000)
                _uiState.value = _uiState.value.copy(errorMessage = null)
            } catch (e: Exception) {
                _uiState.value = _uiState.value.copy(
                    errorMessage = "Failed to connect: ${e.message}"
                )
            }
        }
    }
    
    fun disconnectNode(nodeId: String) {
        viewModelScope.launch {
            try {
                // Simulate network delay
                kotlinx.coroutines.delay(500)
                
                // For now, just show success message
                _uiState.value = _uiState.value.copy(
                    errorMessage = "Disconnected from node $nodeId"
                )
                
                // Clear message after 3 seconds
                kotlinx.coroutines.delay(3000)
                _uiState.value = _uiState.value.copy(errorMessage = null)
            } catch (e: Exception) {
                _uiState.value = _uiState.value.copy(
                    errorMessage = "Failed to disconnect: ${e.message}"
                )
            }
        }
    }
    
    fun monitorNode(nodeId: String) {
        viewModelScope.launch {
            try {
                // Simulate network delay
                kotlinx.coroutines.delay(500)
                
                // For now, just show success message
                _uiState.value = _uiState.value.copy(
                    errorMessage = "Monitoring node $nodeId"
                )
                
                // Clear message after 3 seconds
                kotlinx.coroutines.delay(3000)
                _uiState.value = _uiState.value.copy(errorMessage = null)
            } catch (e: Exception) {
                _uiState.value = _uiState.value.copy(
                    errorMessage = "Failed to monitor: ${e.message}"
                )
            }
        }
    }
    
    fun clearError() {
        _uiState.value = _uiState.value.copy(errorMessage = null)
    }
}

data class NodesUiState(
    val nodes: List<Node> = emptyList(),
    val isLoading: Boolean = false,
    val errorMessage: String? = null
)