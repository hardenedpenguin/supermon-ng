package com.supermonng.mobile.domain.usecase

import com.supermonng.mobile.domain.repository.SupermonRepository
import javax.inject.Inject

class NodeControlUseCase @Inject constructor(
    private val repository: SupermonRepository
) {
    suspend fun connectNode(localNode: String, nodeId: String, permanent: Boolean = false): Result<Unit> {
        return repository.connectNode(localNode, nodeId, permanent)
    }
    
    suspend fun disconnectNode(localNode: String, nodeId: String): Result<Unit> {
        return repository.disconnectNode(localNode, nodeId)
    }
    
    suspend fun monitorNode(localNode: String, nodeId: String): Result<Unit> {
        return repository.monitorNode(localNode, nodeId)
    }
    
    suspend fun localMonitorNode(localNode: String, nodeId: String): Result<Unit> {
        return repository.localMonitorNode(localNode, nodeId)
    }
    
    suspend fun sendDtmf(localNode: String, nodeId: String, dtmf: String): Result<Unit> {
        return repository.sendDtmf(localNode, nodeId, dtmf)
    }
}
