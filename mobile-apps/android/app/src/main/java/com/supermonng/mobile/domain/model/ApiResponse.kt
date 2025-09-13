package com.supermonng.mobile.domain.model

import com.google.gson.annotations.SerializedName

data class ApiResponse<T>(
    val success: Boolean,
    val message: String? = null,
    val data: T? = null,
    val error: String? = null,
    val errors: Map<String, List<String>>? = null,
    val timestamp: String? = null
)

data class ApiError(
    val message: String,
    val status: Int? = null,
    val code: String? = null,
    val details: Any? = null
)

data class PaginatedResponse<T>(
    val data: List<T>,
    val total: Int,
    val page: Int,
    @SerializedName("per_page")
    val perPage: Int,
    @SerializedName("last_page")
    val lastPage: Int,
    @SerializedName("has_more")
    val hasMore: Boolean
)
