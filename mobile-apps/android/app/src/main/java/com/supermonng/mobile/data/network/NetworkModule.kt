package com.supermonng.mobile.data.network

import com.supermonng.mobile.data.api.SupermonApiService
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import java.util.concurrent.TimeUnit

object NetworkModule {
    
    private var baseUrl: String = "https://sm.w5gle.us/"
    private var apiService: SupermonApiService? = null
    
    fun setBaseUrl(url: String) {
        baseUrl = if (url.endsWith("/")) url else "$url/"
        // Reset API service to force recreation with new URL
        apiService = null
    }
    
    fun getApiService(): SupermonApiService {
        if (apiService == null) {
            apiService = createApiService()
        }
        return apiService!!
    }
    
    private fun createApiService(): SupermonApiService {
        val loggingInterceptor = HttpLoggingInterceptor().apply {
            level = HttpLoggingInterceptor.Level.BODY
        }
        
        val client = OkHttpClient.Builder()
            .addInterceptor(loggingInterceptor)
            .connectTimeout(30, TimeUnit.SECONDS)
            .readTimeout(30, TimeUnit.SECONDS)
            .writeTimeout(30, TimeUnit.SECONDS)
            .build()
        
        val retrofit = Retrofit.Builder()
            .baseUrl(baseUrl)
            .client(client)
            .addConverterFactory(GsonConverterFactory.create())
            .build()
        
        return retrofit.create(SupermonApiService::class.java)
    }
}
