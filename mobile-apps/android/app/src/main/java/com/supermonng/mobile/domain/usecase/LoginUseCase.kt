package com.supermonng.mobile.domain.usecase

import com.supermonng.mobile.domain.model.LoginCredentials
import com.supermonng.mobile.domain.model.LoginData
import com.supermonng.mobile.domain.repository.SupermonRepository
import javax.inject.Inject

class LoginUseCase @Inject constructor(
    private val repository: SupermonRepository
) {
    suspend operator fun invoke(credentials: LoginCredentials): Result<LoginData> {
        return repository.login(credentials)
    }
}
