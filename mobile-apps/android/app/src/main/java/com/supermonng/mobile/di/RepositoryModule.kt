package com.supermonng.mobile.di

import com.supermonng.mobile.data.repository.SupermonRepositoryImpl
import com.supermonng.mobile.domain.repository.SupermonRepository
import dagger.Binds
import dagger.Module
import dagger.hilt.InstallIn
import dagger.hilt.components.SingletonComponent
import javax.inject.Singleton

@Module
@InstallIn(SingletonComponent::class)
abstract class RepositoryModule {
    
    @Binds
    @Singleton
    abstract fun bindSupermonRepository(
        supermonRepositoryImpl: SupermonRepositoryImpl
    ): SupermonRepository
}
