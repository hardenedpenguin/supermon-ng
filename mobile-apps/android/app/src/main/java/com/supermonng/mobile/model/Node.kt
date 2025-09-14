package com.supermonng.mobile.model

data class Node(
    val id: Int,
    val node_number: Int? = null,
    val callsign: String? = null,
    val description: String? = null,
    val location: String? = null,
    val status: String? = null,
    val last_heard: String? = null,
    val connected_nodes: List<ConnectedNode>? = null,
    val cos_keyed: Int? = null,
    val tx_keyed: Int? = null,
    val cpu_temp: String? = null,
    val cpu_up: String? = null,
    val cpu_load: String? = null,
    val alert: String? = null,
    val wx: String? = null,
    val disk: String? = null,
    val is_online: Boolean? = null,
    val is_keyed: Boolean? = null,
    val created_at: String? = null,
    val updated_at: String? = null,
    val info: String? = null,
    val remote_nodes: List<ConnectedNode>? = null,
    val ALERT: String? = null,
    val WX: String? = null,
    val DISK: String? = null
) {
    fun getNodeStatus(): NodeStatus {
        return when {
            is_online == true -> NodeStatus.ONLINE
            is_online == false -> NodeStatus.OFFLINE
            status == "online" -> NodeStatus.ONLINE
            status == "offline" -> NodeStatus.OFFLINE
            else -> NodeStatus.UNKNOWN
        }
    }
    
    fun getDisplayName(): String {
        // Parse callsign from info field if callsign is null
        val parsedCallsign = callsign ?: parseCallsignFromInfo()
        return parsedCallsign ?: "Node $id"
    }
    
    fun getDisplayDescription(): String {
        // Parse location from info field if location is null
        val parsedLocation = location ?: parseLocationFromInfo()
        return description ?: parsedLocation ?: "No description"
    }
    
    private fun parseCallsignFromInfo(): String? {
        return info?.let { infoText ->
            // Parse format like "W5GLE  Portable Node"
            val match = Regex("^([A-Z0-9/]+)\\s+(.+)$").find(infoText)
            match?.groupValues?.get(1)
        }
    }
    
    private fun parseLocationFromInfo(): String? {
        return info?.let { infoText ->
            // Parse format like "W5GLE  Portable Node"
            val match = Regex("^([A-Z0-9/]+)\\s+(.+)$").find(infoText)
            match?.groupValues?.get(2)
        }
    }
}

data class ConnectedNode(
    val node: String,
    val info: String,
    val ip: String? = null,
    val last_keyed: String,
    val link: String,
    val direction: String,
    val elapsed: String,
    val mode: String,
    val keyed: String
)

enum class NodeStatus {
    ONLINE,
    OFFLINE,
    CONNECTING,
    ERROR,
    UNKNOWN
}
