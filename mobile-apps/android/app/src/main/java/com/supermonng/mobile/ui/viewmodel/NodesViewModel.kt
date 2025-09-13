package com.supermonng.mobile.ui.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.supermonng.mobile.data.repository.SupermonRepository
import com.supermonng.mobile.model.Node
import com.supermonng.mobile.model.NodeStatus
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

class NodesViewModel : ViewModel() {
    
    private val _uiState = MutableStateFlow(NodesUiState())
    val uiState: StateFlow<NodesUiState> = _uiState.asStateFlow()
    
    private val repository = SupermonRepository()
    
    fun loadNodes() {
        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(isLoading = true, errorMessage = null)
            
            try {
                val result = repository.getNodes()
                result.fold(
                    onSuccess = { nodes ->
                        // Fetch detailed information (including connected nodes) for each node
                        val nodesWithDetails = mutableListOf<Node>()
                        for (node in nodes) {
                            try {
                                val detailResult = repository.getNode(node.id)
                                detailResult.fold(
                                    onSuccess = { detailedNode ->
                                        nodesWithDetails.add(detailedNode)
                                    },
                                    onFailure = { 
                                        // If individual node fetch fails, use the basic node info
                                        nodesWithDetails.add(node)
                                    }
                                )
                            } catch (e: Exception) {
                                // If individual node fetch fails, use the basic node info
                                nodesWithDetails.add(node)
                            }
                        }
                        
                        _uiState.value = _uiState.value.copy(
                            nodes = nodesWithDetails,
                            isLoading = false,
                            errorMessage = null
                        )
                    },
                    onFailure = { exception ->
                        _uiState.value = _uiState.value.copy(
                            isLoading = false,
                            errorMessage = exception.message ?: "Failed to load nodes"
                        )
                    }
                )
            } catch (e: Exception) {
                _uiState.value = _uiState.value.copy(
                    isLoading = false,
                    errorMessage = e.message ?: "Network error"
                )
            }
        }
    }
    
    fun connectNode(nodeId: String) {
        viewModelScope.launch {
            try {
                val result = repository.connectNode(nodeId)
                result.fold(
                    onSuccess = {
                        // Reload nodes to get updated status
                        loadNodes()
                    },
                    onFailure = { exception ->
                        _uiState.value = _uiState.value.copy(
                            errorMessage = exception.message ?: "Failed to connect node"
                        )
                    }
                )
            } catch (e: Exception) {
                _uiState.value = _uiState.value.copy(
                    errorMessage = e.message ?: "Network error"
                )
            }
        }
    }
    
    fun disconnectNode(nodeId: String) {
        viewModelScope.launch {
            try {
                val result = repository.disconnectNode(nodeId)
                result.fold(
                    onSuccess = {
                        // Reload nodes to get updated status
                        loadNodes()
                    },
                    onFailure = { exception ->
                        _uiState.value = _uiState.value.copy(
                            errorMessage = exception.message ?: "Failed to disconnect node"
                        )
                    }
                )
            } catch (e: Exception) {
                _uiState.value = _uiState.value.copy(
                    errorMessage = e.message ?: "Network error"
                )
            }
        }
    }
    
    fun monitorNode(nodeId: String) {
        viewModelScope.launch {
            try {
                val result = repository.monitorNode(nodeId)
                result.fold(
                    onSuccess = {
                        // Reload nodes to get updated status
                        loadNodes()
                    },
                    onFailure = { exception ->
                        _uiState.value = _uiState.value.copy(
                            errorMessage = exception.message ?: "Failed to monitor node"
                        )
                    }
                )
            } catch (e: Exception) {
                _uiState.value = _uiState.value.copy(
                    errorMessage = e.message ?: "Network error"
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