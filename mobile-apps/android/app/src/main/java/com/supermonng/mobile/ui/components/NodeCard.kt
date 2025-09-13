package com.supermonng.mobile.ui.components

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.supermonng.mobile.domain.model.Node
import com.supermonng.mobile.domain.model.NodeStatus

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
        elevation = CardDefaults.cardElevation(defaultElevation = 4.dp)
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
                        text = node.callsign ?: "Unknown",
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.Bold
                    )
                    Text(
                        text = "Node ${node.id}",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }
                
                // Status indicator
                StatusChip(status = node.status)
            }
            
            Spacer(modifier = Modifier.height(8.dp))
            
            // Node info
            if (!node.description.isNullOrBlank()) {
                Text(
                    text = node.description,
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
                Spacer(modifier = Modifier.height(4.dp))
            }
            
            if (!node.location.isNullOrBlank()) {
                Text(
                    text = node.location,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
                Spacer(modifier = Modifier.height(8.dp))
            }
            
            // Action buttons
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                OutlinedButton(
                    onClick = onConnect,
                    modifier = Modifier.weight(1f)
                ) {
                    Text("Connect")
                }
                
                OutlinedButton(
                    onClick = onDisconnect,
                    modifier = Modifier.weight(1f)
                ) {
                    Text("Disconnect")
                }
                
                OutlinedButton(
                    onClick = onMonitor,
                    modifier = Modifier.weight(1f)
                ) {
                    Text("Monitor")
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
            style = MaterialTheme.typography.labelSmall,
            color = color
        )
    }
}
