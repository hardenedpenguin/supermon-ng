package com.supermonng.mobile.domain.model

import com.google.gson.annotations.SerializedName

data class Node(
    val id: String,
    @SerializedName("node_number")
    val nodeNumber: Int? = null,
    val callsign: String? = null,
    val description: String? = null,
    val location: String? = null,
    val status: NodeStatus? = null,
    @SerializedName("last_heard")
    val lastHeard: String? = null,
    @SerializedName("connected_nodes")
    val connectedNodes: List<ConnectedNode>? = null,
    @SerializedName("cos_keyed")
    val cosKeyed: Int? = null,
    @SerializedName("tx_keyed")
    val txKeyed: Int? = null,
    @SerializedName("cpu_temp")
    val cpuTemp: String? = null,
    @SerializedName("cpu_up")
    val cpuUp: String? = null,
    @SerializedName("cpu_load")
    val cpuLoad: String? = null,
    val alert: String? = null,
    val wx: String? = null,
    val disk: String? = null,
    @SerializedName("is_online")
    val isOnline: Boolean? = null,
    @SerializedName("is_keyed")
    val isKeyed: Boolean? = null,
    @SerializedName("created_at")
    val createdAt: String? = null,
    @SerializedName("updated_at")
    val updatedAt: String? = null,
    val info: String? = null,
    @SerializedName("remote_nodes")
    val remoteNodes: List<ConnectedNode>? = null,
    val ALERT: String? = null,
    val WX: String? = null,
    val DISK: String? = null
)

data class ConnectedNode(
    val node: String,
    val info: String,
    val ip: String? = null,
    @SerializedName("last_keyed")
    val lastKeyed: String,
    val link: String,
    val direction: String,
    val elapsed: String,
    val mode: String,
    val keyed: String
)

enum class NodeStatus {
    @SerializedName("online")
    ONLINE,
    @SerializedName("offline")
    OFFLINE,
    @SerializedName("connecting")
    CONNECTING,
    @SerializedName("error")
    ERROR,
    @SerializedName("unknown")
    UNKNOWN
}

enum class NodeActionType {
    @SerializedName("connect")
    CONNECT,
    @SerializedName("disconnect")
    DISCONNECT,
    @SerializedName("monitor")
    MONITOR,
    @SerializedName("local_monitor")
    LOCAL_MONITOR,
    @SerializedName("perm_connect")
    PERM_CONNECT,
    @SerializedName("reboot")
    REBOOT,
    @SerializedName("restart")
    RESTART
}

data class NodeAction(
    val type: NodeActionType,
    val nodeId: String,
    val targetNodeId: String? = null,
    val permanent: Boolean = false
)
