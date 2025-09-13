package com.supermonng.mobile.ui.components

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.supermonng.mobile.model.Node
import com.supermonng.mobile.model.NodeStatus

@Composable
fun NodeCard(
    node: Node,
    onConnect: () -> Unit,
    onDisconnect: () -> Unit,
    onMonitor: () -> Unit,
    modifier: Modifier = Modifier
) {
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
            
            if (!node.location.isNullOrBlank()) {
                Text(
                    text = node.location,
                    style = MaterialTheme.typography.caption,
                    color = MaterialTheme.colors.onSurface.copy(alpha = 0.6f)
                )
                Spacer(modifier = Modifier.height(8.dp))
            }
            
            // Connected nodes information
            if (!node.connected_nodes.isNullOrEmpty()) {
                Text(
                    text = "Connected Nodes:",
                    style = MaterialTheme.typography.caption,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colors.onSurface.copy(alpha = 0.8f)
                )
                Spacer(modifier = Modifier.height(4.dp))
                
                node.connected_nodes.forEach { connectedNode ->
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Column(modifier = Modifier.weight(1f)) {
                            Text(
                                text = "Node ${connectedNode.node} - ${connectedNode.info}",
                                style = MaterialTheme.typography.caption,
                                color = MaterialTheme.colors.onSurface.copy(alpha = 0.7f)
                            )
                            Text(
                                text = "Link: ${connectedNode.link} | Direction: ${connectedNode.direction}",
                                style = MaterialTheme.typography.caption,
                                color = MaterialTheme.colors.onSurface.copy(alpha = 0.6f)
                            )
                        }
                        
                        // Quick action buttons for connected nodes
                        Row(horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                            IconButton(
                                onClick = { /* TODO: Disconnect specific node */ },
                                modifier = Modifier.size(24.dp)
                            ) {
                                Text(
                                    text = "✕",
                                    style = MaterialTheme.typography.caption,
                                    color = Color.Red
                                )
                            }
                            IconButton(
                                onClick = { /* TODO: Monitor specific node */ },
                                modifier = Modifier.size(24.dp)
                            ) {
                                Text(
                                    text = "👁",
                                    style = MaterialTheme.typography.caption,
                                    color = Color.Blue
                                )
                            }
                        }
                    }
                    Spacer(modifier = Modifier.height(4.dp))
                }
                Spacer(modifier = Modifier.height(8.dp))
            } else if (node.is_online == true) {
                Text(
                    text = "No connected nodes",
                    style = MaterialTheme.typography.caption,
                    color = MaterialTheme.colors.onSurface.copy(alpha = 0.6f)
                )
                Spacer(modifier = Modifier.height(8.dp))
            }
            
            // Action buttons - contextual based on node state
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                if (node.is_online == true && !node.connected_nodes.isNullOrEmpty()) {
                    // Node is online and has connections - show disconnect and monitor
                    OutlinedButton(
                        onClick = onDisconnect,
                        modifier = Modifier.weight(1f),
                        colors = ButtonDefaults.outlinedButtonColors(contentColor = Color.Red)
                    ) {
                        Text("Disconnect All")
                    }
                    
                    OutlinedButton(
                        onClick = onMonitor,
                        modifier = Modifier.weight(1f),
                        colors = ButtonDefaults.outlinedButtonColors(contentColor = Color.Blue)
                    ) {
                        Text("Monitor")
                    }
                } else if (node.is_online == true) {
                    // Node is online but no connections - show connect and monitor
                    OutlinedButton(
                        onClick = onConnect,
                        modifier = Modifier.weight(1f),
                        colors = ButtonDefaults.outlinedButtonColors(contentColor = Color.Green)
                    ) {
                        Text("Connect")
                    }
                    
                    OutlinedButton(
                        onClick = onMonitor,
                        modifier = Modifier.weight(1f),
                        colors = ButtonDefaults.outlinedButtonColors(contentColor = Color.Blue)
                    ) {
                        Text("Monitor")
                    }
                } else {
                    // Node is offline - show connect only
                    OutlinedButton(
                        onClick = onConnect,
                        modifier = Modifier.fillMaxWidth(),
                        colors = ButtonDefaults.outlinedButtonColors(contentColor = Color.Green)
                    ) {
                        Text("Connect Node")
                    }
                }
            }
        }
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
