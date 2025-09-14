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
import kotlinx.coroutines.delay

class NodesViewModel : ViewModel() {
    
    private val _uiState = MutableStateFlow(NodesUiState())
    val uiState: StateFlow<NodesUiState> = _uiState.asStateFlow()
    
    private val repository = SupermonRepository()
    private var isPolling = false
    
    fun loadNodes() {
        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(isLoading = true, errorMessage = null)
            
            try {
                val result = repository.getNodes()
                result.fold(
                    onSuccess = { nodes ->
                        println("DEBUG: Basic nodes fetched successfully, got ${nodes.size} nodes")
                        _uiState.value = _uiState.value.copy(
                            nodes = nodes,
                            isLoading = false,
                            errorMessage = null
                        )
                        
                        // Fetch AMI status to get connected nodes information
                        try {
                            val nodeIds = nodes.map { it.id.toString() }
                            val amiResult = repository.getAmiStatus(nodeIds)
                            amiResult.fold(
                                onSuccess = { amiData ->
                                    println("DEBUG: AMI data fetched successfully, got ${amiData.size} nodes")
                                    // Merge AMI data with nodes
                                    val nodesWithAmiData = nodes.map { node ->
                                        val amiNode = amiData[node.id.toString()]
                                        println("DEBUG: Looking for node ${node.id}, found: ${amiNode != null}")
                                        if (amiNode != null) {
                                            // Convert API ConnectedNode to model ConnectedNode
                                            val remoteNodes = amiNode.remote_nodes?.map { apiNode ->
                                                com.supermonng.mobile.model.ConnectedNode(
                                                    node = apiNode.node,
                                                    info = apiNode.info,
                                                    ip = apiNode.ip,
                                                    last_keyed = apiNode.last_keyed,
                                                    link = apiNode.link,
                                                    direction = apiNode.direction,
                                                    elapsed = apiNode.elapsed,
                                                    mode = apiNode.mode,
                                                    keyed = apiNode.keyed
                                                )
                                            }
                                            
                                            // Parse callsign and description from info field
                                            val infoText = amiNode.info ?: ""
                                            
                                            // Try different parsing strategies
                                            val callsign: String
                                            val description: String
                                            
                                            when {
                                                // Format: "W5GLE  Portable Node" (two spaces)
                                                infoText.contains("  ") -> {
                                                    val parts = infoText.split("  ", limit = 2)
                                                    callsign = parts[0].trim()
                                                    description = parts.getOrNull(1)?.trim() ?: "Node ${node.id}"
                                                }
                                                // Format: "W5GLE Portable Node" (single space)
                                                infoText.contains(" ") -> {
                                                    val parts = infoText.split(" ", limit = 2)
                                                    callsign = parts[0].trim()
                                                    description = parts.getOrNull(1)?.trim() ?: "Node ${node.id}"
                                                }
                                                // No spaces - use whole text as callsign
                                                else -> {
                                                    callsign = infoText.ifEmpty { "Unknown" }
                                                    description = "Node ${node.id}"
                                                }
                                            }
                                            
                                            node.copy(
                                                callsign = callsign,
                                                description = description,
                                                // Don't set location to avoid duplicate display
                                                info = amiNode.info,
                                                remote_nodes = remoteNodes,
                                                status = amiNode.status,
                                                is_online = amiNode.status == "online",
                                                is_keyed = amiNode.cos_keyed > 0 || amiNode.tx_keyed > 0,
                                                cos_keyed = amiNode.cos_keyed,
                                                tx_keyed = amiNode.tx_keyed,
                                                cpu_temp = amiNode.cpu_temp,
                                                cpu_up = amiNode.cpu_up,
                                                cpu_load = amiNode.cpu_load,
                                                ALERT = amiNode.ALERT,
                                                WX = amiNode.WX,
                                                DISK = amiNode.DISK
                                            )
                                        } else {
                                            node
                                        }
                                    }
                                    
                                    _uiState.value = _uiState.value.copy(
                                        nodes = nodesWithAmiData,
                                        isLoading = false,
                                        errorMessage = null
                                    )
                            },
                            onFailure = { exception ->
                                println("DEBUG: AMI fetch failed: ${exception.message}")
                                // If AMI fetch fails, use basic node data
                                _uiState.value = _uiState.value.copy(
                                    nodes = nodes,
                                    isLoading = false,
                                    errorMessage = null
                                )
                            }
                            )
                        } catch (e: Exception) {
                            println("DEBUG: AMI fetch exception: ${e.message}")
                            // If AMI fetch fails, use basic node data
                            _uiState.value = _uiState.value.copy(
                                nodes = nodes,
                                isLoading = false,
                                errorMessage = null
                            )
                        }
                        
                        // Start polling for real-time updates if not already polling
                        if (!isPolling) {
                            startPolling()
                        }
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
    
    fun connectNode(nodeId: String, targetNodeId: String) {
        viewModelScope.launch {
            try {
                val result = repository.connectNode(nodeId, targetNodeId)
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
    
    fun disconnectNode(nodeId: String, targetNodeId: String) {
        viewModelScope.launch {
            try {
                val result = repository.disconnectNode(nodeId, targetNodeId)
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
    
    fun monitorNode(nodeId: String, targetNodeId: String) {
        viewModelScope.launch {
            try {
                val result = repository.monitorNode(nodeId, targetNodeId)
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
    
    private fun startPolling() {
        isPolling = true
        viewModelScope.launch {
            while (isPolling) {
                delay(5000) // Poll every 5 seconds
                try {
                    val result = repository.getNodes()
                    result.fold(
                        onSuccess = { nodes ->
                            // Fetch AMI status to get connected nodes information
                            try {
                                val nodeIds = nodes.map { it.id.toString() }
                                val amiResult = repository.getAmiStatus(nodeIds)
                                amiResult.fold(
                                    onSuccess = { amiData ->
                                        // Merge AMI data with nodes
                                        val nodesWithAmiData = nodes.map { node ->
                                            val amiNode = amiData[node.id.toString()]
                                            if (amiNode != null) {
                                                // Convert API ConnectedNode to model ConnectedNode
                                                val remoteNodes = amiNode.remote_nodes?.map { apiNode ->
                                                    com.supermonng.mobile.model.ConnectedNode(
                                                        node = apiNode.node,
                                                        info = apiNode.info,
                                                        ip = apiNode.ip,
                                                        last_keyed = apiNode.last_keyed,
                                                        link = apiNode.link,
                                                        direction = apiNode.direction,
                                                        elapsed = apiNode.elapsed,
                                                        mode = apiNode.mode,
                                                        keyed = apiNode.keyed
                                                    )
                                                }
                                                
                                                // Parse callsign and description from info field
                                                val infoText = amiNode.info ?: ""
                                                
                                                // Try different parsing strategies
                                                val callsign: String
                                                val description: String
                                                
                                                when {
                                                    // Format: "W5GLE  Portable Node" (two spaces)
                                                    infoText.contains("  ") -> {
                                                        val parts = infoText.split("  ", limit = 2)
                                                        callsign = parts[0].trim()
                                                        description = parts.getOrNull(1)?.trim() ?: "Node ${node.id}"
                                                    }
                                                    // Format: "W5GLE Portable Node" (single space)
                                                    infoText.contains(" ") -> {
                                                        val parts = infoText.split(" ", limit = 2)
                                                        callsign = parts[0].trim()
                                                        description = parts.getOrNull(1)?.trim() ?: "Node ${node.id}"
                                                    }
                                                    // No spaces - use whole text as callsign
                                                    else -> {
                                                        callsign = infoText.ifEmpty { "Unknown" }
                                                        description = "Node ${node.id}"
                                                    }
                                                }
                                                
                                                node.copy(
                                                    callsign = callsign,
                                                    description = description,
                                                    // Don't set location to avoid duplicate display
                                                    info = amiNode.info,
                                                    remote_nodes = remoteNodes,
                                                    status = amiNode.status,
                                                    is_online = amiNode.status == "online",
                                                    is_keyed = amiNode.cos_keyed > 0 || amiNode.tx_keyed > 0,
                                                    cos_keyed = amiNode.cos_keyed,
                                                    tx_keyed = amiNode.tx_keyed,
                                                    cpu_temp = amiNode.cpu_temp,
                                                    cpu_up = amiNode.cpu_up,
                                                    cpu_load = amiNode.cpu_load,
                                                    ALERT = amiNode.ALERT,
                                                    WX = amiNode.WX,
                                                    DISK = amiNode.DISK
                                                )
                                            } else {
                                                node
                                            }
                                        }
                                        
                                        _uiState.value = _uiState.value.copy(nodes = nodesWithAmiData)
                                    },
                                    onFailure = {
                                        // If AMI fetch fails, use basic node data
                                        _uiState.value = _uiState.value.copy(nodes = nodes)
                                    }
                                )
                            } catch (e: Exception) {
                                // If AMI fetch fails, use basic node data
                                _uiState.value = _uiState.value.copy(nodes = nodes)
                            }
                        },
                        onFailure = { 
                            // Silently handle polling errors to avoid disrupting the UI
                        }
                    )
                } catch (e: Exception) {
                    // Silently handle polling errors to avoid disrupting the UI
                }
            }
        }
    }
    
    fun stopPolling() {
        isPolling = false
    }
    
    fun clearErrorMessage() {
        _uiState.value = _uiState.value.copy(errorMessage = null)
    }
}

data class NodesUiState(
    val nodes: List<Node> = emptyList(),
    val isLoading: Boolean = false,
    val errorMessage: String? = null
)