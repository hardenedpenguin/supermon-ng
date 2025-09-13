package com.supermonng.mobile.domain.usecase

import com.supermonng.mobile.domain.model.Node
import com.supermonng.mobile.domain.repository.SupermonRepository
import javax.inject.Inject

class GetNodesUseCase @Inject constructor(
    private val repository: SupermonRepository
) {
    suspend operator fun invoke(): Result<List<Node>> {
        return repository.getNodes()
    }
}
