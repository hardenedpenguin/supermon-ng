package com.supermonng.mobile.domain.model

import com.google.gson.annotations.SerializedName

data class User(
    val id: String? = null,
    val name: String,
    val username: String? = null,
    val email: String? = null,
    val permissions: Map<String, Boolean> = emptyMap(),
    val preferences: UserPreferences? = null,
    val roles: List<String>? = null,
    @SerializedName("created_at")
    val createdAt: String? = null,
    @SerializedName("updated_at")
    val updatedAt: String? = null
)

data class UserPreferences(
    @SerializedName("showDetail")
    val showDetail: Boolean = false,
    @SerializedName("displayedNodes")
    val displayedNodes: Int = 10,
    @SerializedName("showCount")
    val showCount: Boolean = true,
    @SerializedName("showAll")
    val showAll: Boolean = false,
    val theme: String? = null,
    @SerializedName("autoRefresh")
    val autoRefresh: Boolean? = null,
    @SerializedName("refreshInterval")
    val refreshInterval: Int? = null,
    val notifications: NotificationPreferences? = null
)

data class NotificationPreferences(
    val enabled: Boolean = true,
    val sound: Boolean = true,
    val desktop: Boolean = false,
    val types: NotificationTypes = NotificationTypes()
)

data class NotificationTypes(
    @SerializedName("nodeStatus")
    val nodeStatus: Boolean = true,
    @SerializedName("systemAlerts")
    val systemAlerts: Boolean = true,
    val errors: Boolean = true,
    val warnings: Boolean = true
)

data class LoginCredentials(
    val username: String,
    val password: String,
    val remember: Boolean = false
)

data class LoginResponse(
    val success: Boolean,
    val message: String? = null,
    val data: LoginData? = null
)

data class LoginData(
    val user: User,
    val permissions: Map<String, Boolean>,
    val authenticated: Boolean,
    val token: String? = null,
    @SerializedName("expires_at")
    val expiresAt: String? = null,
    @SerializedName("config_source")
    val configSource: String? = null
)

enum class AuthState {
    LOADING,
    AUTHENTICATED,
    UNAUTHENTICATED,
    ERROR
}
