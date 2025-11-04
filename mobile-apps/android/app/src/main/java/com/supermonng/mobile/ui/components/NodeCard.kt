package com.supermonng.mobile.ui.components

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Close
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import com.supermonng.mobile.model.Node
import com.supermonng.mobile.model.NodeStatus

@Composable
fun NodeCard(
    node: Node,
    onConnect: (targetNodeId: String) -> Unit,
    onDisconnect: (targetNodeId: String) -> Unit,
    onMonitor: (targetNodeId: String) -> Unit,
    modifier: Modifier = Modifier,
    enableActions: Boolean = true  // New parameter to control action visibility
) {
    var showConnectDialog by remember { mutableStateOf(false) }
    var showMonitorDialog by remember { mutableStateOf(false) }
    var targetNodeId by remember { mutableStateOf("") }
    var monitorTargetNodeId by remember { mutableStateOf("") }
    
    Card(
        modifier = modifier.fillMaxWidth(),
        shape = RoundedCornerShape(8.dp),
        elevation = 4.dp
    ) {
        Column(
            modifier = Modifier.padding(16.dp)
        ) {
            // Node header
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Column {
                    Text(
                        text = node.getDisplayName(),
                        style = MaterialTheme.typography.h6,
                        fontWeight = FontWeight.Bold
                    )
                    Text(
                        text = "Node ${node.id}",
                        style = MaterialTheme.typography.caption,
                        color = MaterialTheme.colors.onSurface.copy(alpha = 0.6f)
                    )
                }
                
                // Status indicator
                StatusChip(status = node.getNodeStatus())
            }
            
            Spacer(modifier = Modifier.height(8.dp))
            
            // Node info
            Text(
                text = node.getDisplayDescription(),
                style = MaterialTheme.typography.body2,
                color = MaterialTheme.colors.onSurface.copy(alpha = 0.7f)
            )
            Spacer(modifier = Modifier.height(4.dp))
            
            // Location field removed - description already shows location info
            
            // Connected nodes information
            val connectedNodes = node.connected_nodes ?: node.remote_nodes
            if (!connectedNodes.isNullOrEmpty()) {
                Text(
                    text = "Connected Nodes:",
                    style = MaterialTheme.typography.caption,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colors.onSurface.copy(alpha = 0.8f)
                )
                Spacer(modifier = Modifier.height(4.dp))
                
                connectedNodes.forEach { connectedNode ->
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Column(modifier = Modifier.weight(1f)) {
                            Text(
                                text = "Node ${connectedNode.node}",
                                style = MaterialTheme.typography.caption,
                                fontWeight = FontWeight.Medium,
                                color = MaterialTheme.colors.onSurface.copy(alpha = 0.7f)
                            )
                            Text(
                                text = connectedNode.info,
                                style = MaterialTheme.typography.caption,
                                color = MaterialTheme.colors.onSurface.copy(alpha = 0.6f)
                            )
                        }
                        Column(horizontalAlignment = Alignment.End) {
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                Column(horizontalAlignment = Alignment.End) {
                                    Text(
                                        text = connectedNode.elapsed,
                                        style = MaterialTheme.typography.caption,
                                        color = MaterialTheme.colors.onSurface.copy(alpha = 0.6f)
                                    )
                                    Text(
                                        text = connectedNode.mode,
                                        style = MaterialTheme.typography.caption,
                                        color = MaterialTheme.colors.onSurface.copy(alpha = 0.6f)
                                    )
                                }
                                Spacer(modifier = Modifier.width(8.dp))
                                if (enableActions) {
                                    IconButton(
                                        onClick = { onDisconnect(connectedNode.node) },
                                        modifier = Modifier.size(24.dp)
                                    ) {
                                        Icon(
                                            imageVector = Icons.Default.Close,
                                            contentDescription = "Disconnect",
                                            tint = MaterialTheme.colors.error,
                                            modifier = Modifier.size(16.dp)
                                        )
                                    }
                                }
                            }
                        }
                    }
                    Spacer(modifier = Modifier.height(4.dp))
                }
                Spacer(modifier = Modifier.height(8.dp))
            } else {
                Text(
                    text = "No connected nodes",
                    style = MaterialTheme.typography.caption,
                    color = MaterialTheme.colors.onSurface.copy(alpha = 0.5f)
                )
                Spacer(modifier = Modifier.height(8.dp))
            }
            
            // Action buttons (only show if actions are enabled)
            if (enableActions) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    OutlinedButton(
                        onClick = { showConnectDialog = true },
                        modifier = Modifier.weight(1f)
                    ) {
                        Text("Connect")
                    }
                    
                    OutlinedButton(
                        onClick = { showMonitorDialog = true },
                        modifier = Modifier.weight(1f)
                    ) {
                        Text("Monitor")
                    }
                }
            }
        }
    }
    
    // Connect Dialog
    if (showConnectDialog) {
        AlertDialog(
            onDismissRequest = { showConnectDialog = false },
            title = { Text("Connect Node") },
            text = {
                Column {
                    Text("Enter the target node number to connect to:")
                    Spacer(modifier = Modifier.height(8.dp))
                    OutlinedTextField(
                        value = targetNodeId,
                        onValueChange = { targetNodeId = it },
                        label = { Text("Target Node ID") },
                        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                        singleLine = true
                    )
                }
            },
            confirmButton = {
                TextButton(
                    onClick = {
                        if (targetNodeId.isNotBlank()) {
                            onConnect(targetNodeId)
                            showConnectDialog = false
                            targetNodeId = ""
                        }
                    }
                ) {
                    Text("Connect")
                }
            },
            dismissButton = {
                TextButton(onClick = { showConnectDialog = false }) {
                    Text("Cancel")
                }
            }
        )
    }
    
    // Monitor Dialog
    if (showMonitorDialog) {
        AlertDialog(
            onDismissRequest = { showMonitorDialog = false },
            title = { Text("Monitor Node") },
            text = {
                Column {
                    Text("Enter the target node number to monitor:")
                    Spacer(modifier = Modifier.height(8.dp))
                    OutlinedTextField(
                        value = monitorTargetNodeId,
                        onValueChange = { monitorTargetNodeId = it },
                        label = { Text("Target Node ID") },
                        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                        singleLine = true
                    )
                }
            },
            confirmButton = {
                TextButton(
                    onClick = {
                        if (monitorTargetNodeId.isNotBlank()) {
                            onMonitor(monitorTargetNodeId)
                            showMonitorDialog = false
                            monitorTargetNodeId = ""
                        }
                    }
                ) {
                    Text("Monitor")
                }
            },
            dismissButton = {
                TextButton(onClick = { showMonitorDialog = false }) {
                    Text("Cancel")
                }
            }
        )
    }
}

@Composable
private fun StatusChip(status: NodeStatus?) {
    val (text, color) = when (status) {
        NodeStatus.ONLINE -> "Online" to Color(0xFF4CAF50)
        NodeStatus.OFFLINE -> "Offline" to Color(0xFFF44336)
        NodeStatus.CONNECTING -> "Connecting" to Color(0xFFFF9800)
        NodeStatus.ERROR -> "Error" to Color(0xFFF44336)
        NodeStatus.UNKNOWN, null -> "Unknown" to Color(0xFF9E9E9E)
    }
    
    Surface(
        shape = RoundedCornerShape(12.dp),
        color = color.copy(alpha = 0.1f)
    ) {
        Text(
            text = text,
            modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp),
            style = MaterialTheme.typography.caption,
            color = color
        )
    }
}
