package com.supermonng.mobile.data.network

import com.supermonng.mobile.data.api.SupermonApiService
import okhttp3.Cookie
import okhttp3.CookieJar
import okhttp3.HttpUrl
import okhttp3.JavaNetCookieJar
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import java.net.CookieManager
import java.net.CookiePolicy
import java.net.HttpCookie
import java.util.concurrent.TimeUnit

object NetworkModule {
    
    private var baseUrl: String = "https://sm.w5gle.us/"
    private var apiService: SupermonApiService? = null
    private var cookieManager: CookieManager? = null
    
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
    
    fun clearCookies() {
        if (cookieManager != null) {
            val cookiesBefore = cookieManager!!.cookieStore.cookies
            println("DEBUG: Cookies before clearing: $cookiesBefore")
            cookieManager!!.cookieStore.removeAll()
            println("DEBUG: Cleared all cookies from cookie jar")
        }
    }
    
    fun getCookies(): List<HttpCookie> {
        return if (cookieManager != null) {
            cookieManager!!.cookieStore.cookies
        } else {
            emptyList()
        }
    }
    
    private var sessionToken: String? = null
    
    fun setSessionToken(token: String) {
        sessionToken = token
        println("DEBUG: Set session token: $token")
    }
    
    fun getSessionToken(): String? {
        return sessionToken
    }
    
    fun clearSessionToken() {
        sessionToken = null
        println("DEBUG: Cleared session token")
    }
    
    
    private fun createApiService(): SupermonApiService {
        val loggingInterceptor = HttpLoggingInterceptor().apply {
            level = HttpLoggingInterceptor.Level.NONE
        }
        
            // Use singleton cookie manager to maintain session across requests
            if (cookieManager == null) {
                cookieManager = CookieManager()
                cookieManager!!.setCookiePolicy(CookiePolicy.ACCEPT_ALL)
            }
            
            // Use standard JavaNetCookieJar
            val cookieJar: CookieJar = JavaNetCookieJar(cookieManager!!)
        
        val client = OkHttpClient.Builder()
            // Force HTTP/1.1 to match curl behavior
            .protocols(listOf(okhttp3.Protocol.HTTP_1_1))
            // Disable cookie jar and use manual session handling
            .addInterceptor { chain ->
                val request = chain.request()
                val sessionToken = sessionToken
                println("DEBUG: Request to ${request.url}")
                println("DEBUG: Current session token: $sessionToken")
                
                val newRequest = if (sessionToken != null) {
                    request.newBuilder()
                        .addHeader("Cookie", "supermon61=$sessionToken")
                        .addHeader("Accept", "*/*")
                        .addHeader("Accept-Encoding", "gzip, deflate")
                        .addHeader("Connection", "keep-alive")
                        .build()
                } else {
                    request.newBuilder()
                        .addHeader("Accept", "*/*")
                        .addHeader("Accept-Encoding", "gzip, deflate")
                        .addHeader("Connection", "keep-alive")
                        .build()
                }
                
                println("DEBUG: Request headers: ${newRequest.headers}")
                println("DEBUG: Request method: ${newRequest.method}")
                println("DEBUG: Request URL: ${newRequest.url}")
                if (newRequest.body != null) {
                    println("DEBUG: Request body content type: ${newRequest.body!!.contentType()}")
                    println("DEBUG: Request body size: ${newRequest.body!!.contentLength()}")
                } else {
                    println("DEBUG: Request body: null")
                }
                val response = chain.proceed(newRequest)
                println("DEBUG: Response code: ${response.code}")
                println("DEBUG: Response headers: ${response.headers}")
                println("DEBUG: Set-Cookie headers: ${response.headers.values("Set-Cookie")}")
                
                response
            }
            .addInterceptor(loggingInterceptor)
            .addInterceptor { chain ->
                val request = chain.request()
                println("DEBUG: Making request to ${request.url}")
                println("DEBUG: Request headers: ${request.headers}")
                println("DEBUG: Request cookies: ${request.header("Cookie")}")
                val response = chain.proceed(request)
                println("DEBUG: Response code: ${response.code}")
                println("DEBUG: Response headers: ${response.headers}")
                println("DEBUG: Set-Cookie headers: ${response.headers("Set-Cookie")}")
                println("DEBUG: Expires header: ${response.header("Expires")}")
                response
            }
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
