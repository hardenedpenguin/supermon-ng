package com.supermonng.mobile.ui.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.supermonng.mobile.domain.model.Node
import com.supermonng.mobile.domain.usecase.GetNodesUseCase
import com.supermonng.mobile.domain.usecase.NodeControlUseCase
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

@HiltViewModel
class NodesViewModel @Inject constructor(
    private val getNodesUseCase: GetNodesUseCase,
    private val nodeControlUseCase: NodeControlUseCase
) : ViewModel() {
    
    private val _uiState = MutableStateFlow(NodesUiState())
    val uiState: StateFlow<NodesUiState> = _uiState.asStateFlow()
    
    fun loadNodes() {
        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(isLoading = true, errorMessage = null)
            
            getNodesUseCase()
                .onSuccess { nodes ->
                    _uiState.value = _uiState.value.copy(
                        isLoading = false,
                        nodes = nodes,
                        errorMessage = null
                    )
                }
                .onFailure { exception ->
                    _uiState.value = _uiState.value.copy(
                        isLoading = false,
                        errorMessage = exception.message ?: "Failed to load nodes"
                    )
                }
        }
    }
    
    fun connectNode(nodeId: String) {
        // For now, we'll use a default local node ID
        // In a real implementation, this would come from user preferences or node config
        val localNode = "546051" // This should be configurable
        
        viewModelScope.launch {
            nodeControlUseCase.connectNode(localNode, nodeId)
                .onSuccess {
                    // Refresh nodes to update status
                    loadNodes()
                }
                .onFailure { exception ->
                    _uiState.value = _uiState.value.copy(
                        errorMessage = "Failed to connect: ${exception.message}"
                    )
                }
        }
    }
    
    fun disconnectNode(nodeId: String) {
        val localNode = "546051" // This should be configurable
        
        viewModelScope.launch {
            nodeControlUseCase.disconnectNode(localNode, nodeId)
                .onSuccess {
                    // Refresh nodes to update status
                    loadNodes()
                }
                .onFailure { exception ->
                    _uiState.value = _uiState.value.copy(
                        errorMessage = "Failed to disconnect: ${exception.message}"
                    )
                }
        }
    }
    
    fun monitorNode(nodeId: String) {
        val localNode = "546051" // This should be configurable
        
        viewModelScope.launch {
            nodeControlUseCase.monitorNode(localNode, nodeId)
                .onSuccess {
                    // Could show a toast or navigate to monitor screen
                }
                .onFailure { exception ->
                    _uiState.value = _uiState.value.copy(
                        errorMessage = "Failed to monitor: ${exception.message}"
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
