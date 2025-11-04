package com.supermonng.mobile.data.network

import com.supermonng.mobile.data.api.SupermonApiService
import okhttp3.Cookie
import okhttp3.CookieJar
import okhttp3.HttpUrl
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import java.net.CookieManager
import java.net.CookiePolicy
import java.net.HttpCookie
import java.util.concurrent.TimeUnit
import kotlin.math.pow

object NetworkModule {
    
    // Default base URL - should include /supermon-ng/api prefix
    private var baseUrl: String = "https://sm.w5gle.us/supermon-ng/api/"
    private var apiService: SupermonApiService? = null
    private var cookieManager: CookieManager? = null
    private var csrfToken: String? = null
    
    // Rate limiting info
    data class RateLimitInfo(
        val limit: Int?,
        val remaining: Int?,
        val reset: Long?  // Unix timestamp
    )
    
    private var lastRateLimitInfo: RateLimitInfo? = null
    
    fun getRateLimitInfo(): RateLimitInfo? = lastRateLimitInfo
    
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
        
        // Custom CookieJar implementation
        val cookieJar: CookieJar = object : CookieJar {
            private val cookieStore = mutableMapOf<String, MutableList<Cookie>>()
            
            override fun saveFromResponse(url: HttpUrl, cookies: List<Cookie>) {
                val key = url.host
                val existingCookies = cookieStore.getOrPut(key) { mutableListOf() }
                
                // Remove expired cookies and update existing ones
                existingCookies.removeIf { existingCookie ->
                    cookies.any { it.name == existingCookie.name && it.domain == existingCookie.domain && it.path == existingCookie.path } ||
                    existingCookie.expiresAt < System.currentTimeMillis()
                }
                
                // Add new cookies and update existing ones
                cookies.forEach { cookie ->
                    if (cookie.expiresAt >= System.currentTimeMillis()) {
                        existingCookies.removeAll { it.name == cookie.name && it.domain == cookie.domain && it.path == cookie.path }
                        existingCookies.add(cookie)
                    }
                }
            }
            
            override fun loadForRequest(url: HttpUrl): List<Cookie> {
                val now = System.currentTimeMillis()
                return cookieStore.flatMap { (_, cookies) ->
                    cookies.filter { cookie ->
                        cookie.matches(url) && cookie.expiresAt > now
                    }
                }
            }
        }
        
        val client = OkHttpClient.Builder()
            // Force HTTP/1.1 to match curl behavior
            .protocols(listOf(okhttp3.Protocol.HTTP_1_1))
            .cookieJar(cookieJar)
            // Rate limiting interceptor - handles 429 responses and parses rate limit headers
            .addInterceptor { chain ->
                var request = chain.request()
                var response = chain.proceed(request)
                var retryCount = 0
                val maxRetries = 3
                
                // Handle 429 Too Many Requests with exponential backoff
                while (response.code == 429 && retryCount < maxRetries) {
                    // Parse Retry-After header if present
                    val retryAfter = response.header("Retry-After")?.toLongOrNull()
                    
                    // Calculate wait time: use Retry-After header if available, otherwise exponential backoff
                    val waitTime = if (retryAfter != null) {
                        retryAfter * 1000 // Convert seconds to milliseconds
                    } else {
                        // Exponential backoff: 1s, 2s, 4s
                        (2.0.pow(retryCount) * 1000).toLong()
                    }
                    
                    // Close the response before waiting
                    response.close()
                    
                    // Wait before retrying
                    Thread.sleep(waitTime)
                    
                    // Retry the request
                    response = chain.proceed(request)
                    retryCount++
                }
                
                // Parse rate limit headers if present
                val rateLimit = response.header("X-RateLimit-Limit")?.toIntOrNull()
                val rateLimitRemaining = response.header("X-RateLimit-Remaining")?.toIntOrNull()
                val rateLimitReset = response.header("X-RateLimit-Reset")?.toLongOrNull()
                
                if (rateLimit != null || rateLimitRemaining != null || rateLimitReset != null) {
                    lastRateLimitInfo = RateLimitInfo(rateLimit, rateLimitRemaining, rateLimitReset)
                }
                
                response
            }
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
