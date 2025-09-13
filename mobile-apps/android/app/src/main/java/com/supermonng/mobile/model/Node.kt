package com.supermonng.mobile.model

data class Node(
    val id: String,
    val callsign: String? = null,
    val description: String? = null,
    val location: String? = null,
    val status: NodeStatus? = null
)

enum class NodeStatus {
    ONLINE,
    OFFLINE,
    CONNECTING,
    ERROR,
    UNKNOWN
}
