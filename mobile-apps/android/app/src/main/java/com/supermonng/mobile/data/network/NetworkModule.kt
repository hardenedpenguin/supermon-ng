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
    
    // Default base URL - should include /supermon-ng/api prefix
    private var baseUrl: String = "https://sm.w5gle.us/supermon-ng/api/"
    private var apiService: SupermonApiService? = null
    private var cookieManager: CookieManager? = null
    private var csrfToken: String? = null
    
    /**
     * Set the base URL for API calls
     * @param url Can be:
     *   - Full URL with path: "https://example.com/supermon-ng/api"
     *   - Full URL without path: "https://example.com" (will append /supermon-ng/api)
     *   - Relative path: "/supermon-ng/api" (will use current host)
     */
    fun setBaseUrl(url: String) {
        var normalizedUrl = url.trim()
        
        // If URL doesn't end with /api or /api/, append the path
        if (!normalizedUrl.contains("/api")) {
            // Remove trailing slash if present
            normalizedUrl = normalizedUrl.removeSuffix("/")
            // Append API path
            normalizedUrl = "$normalizedUrl/supermon-ng/api"
        }
        
        // Ensure URL ends with /
        baseUrl = if (normalizedUrl.endsWith("/")) normalizedUrl else "$normalizedUrl/"
        
        // Reset API service to force recreation with new URL
        apiService = null
    }
    
    fun getBaseUrl(): String {
        return baseUrl
    }
    
    fun setCsrfToken(token: String?) {
        csrfToken = token
    }
    
    fun getCsrfToken(): String? {
        return csrfToken
    }
    
    fun clearCsrfToken() {
        csrfToken = null
    }
    
    fun getApiService(): SupermonApiService {
        if (apiService == null) {
            apiService = createApiService()
        }
        return apiService!!
    }
    
    fun clearCookies() {
        if (cookieManager != null) {
            cookieManager!!.cookieStore.removeAll()
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
    }
    
    fun getSessionToken(): String? {
        return sessionToken
    }
    
    fun clearSessionToken() {
        sessionToken = null
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
        
        // Use standard JavaNetCookieJar for automatic cookie handling
        val cookieJar: CookieJar = JavaNetCookieJar(cookieManager!!)
        
        val client = OkHttpClient.Builder()
            // Force HTTP/1.1 to match curl behavior
            .protocols(listOf(okhttp3.Protocol.HTTP_1_1))
            .cookieJar(cookieJar)
            .addInterceptor { chain ->
                val originalRequest = chain.request()
                val requestBuilder = originalRequest.newBuilder()
                
                // Add session cookie if available (use module-level sessionToken)
                val currentSessionToken = sessionToken
                if (currentSessionToken != null) {
                    requestBuilder.addHeader("Cookie", "supermon61=$currentSessionToken; Path=/supermon-ng")
                }
                
                // Add CSRF token to headers for state-changing requests
                val method = originalRequest.method
                val currentCsrfToken = csrfToken
                if (currentCsrfToken != null && method in listOf("POST", "PUT", "DELETE", "PATCH")) {
                    requestBuilder.addHeader("X-CSRF-Token", currentCsrfToken)
                }
                
                // Add standard headers
                requestBuilder
                    .addHeader("Accept", "application/json")
                    .addHeader("Accept-Encoding", "gzip, deflate")
                    .addHeader("Connection", "keep-alive")
                
                val newRequest = requestBuilder.build()
                val response = chain.proceed(newRequest)
                
                // Extract CSRF token from response if present
                response.header("X-CSRF-Token")?.let { token ->
                    csrfToken = token
                }
                
                // Extract session cookie from Set-Cookie header if present
                response.headers("Set-Cookie").forEach { cookieHeader ->
                    if (cookieHeader.startsWith("supermon61=")) {
                        val cookieValue = cookieHeader.substringAfter("supermon61=")
                            .substringBefore(";")
                        sessionToken = cookieValue
                    }
                }
                
                response
            }
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
