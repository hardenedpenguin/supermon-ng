package com.supermonng.mobile.ui.screens

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ExitToApp
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material.icons.filled.Settings
import androidx.compose.material.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import com.supermonng.mobile.ui.components.LoadingIndicator
import com.supermonng.mobile.ui.components.NodeCard
import com.supermonng.mobile.ui.viewmodel.NodesViewModel

@Composable
fun NodesScreen(
    onLogout: () -> Unit,
    onSettings: () -> Unit,
    viewModel: NodesViewModel = androidx.lifecycle.viewmodel.compose.viewModel(),
    enableActions: Boolean = true  // Control whether action buttons are shown
) {
    val uiState by viewModel.uiState.collectAsState()
    
    LaunchedEffect(Unit) {
        viewModel.loadNodes()
    }
    
    DisposableEffect(Unit) {
        onDispose {
            viewModel.stopPolling()
        }
    }
    
    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("AllStar Nodes") },
                actions = {
                    IconButton(onClick = { viewModel.loadNodes() }) {
                        Icon(
                            imageVector = Icons.Default.Refresh,
                            contentDescription = "Refresh"
                        )
                    }
                    IconButton(onClick = onSettings) {
                        Icon(
                            imageVector = Icons.Default.Settings,
                            contentDescription = "Settings"
                        )
                    }
                    IconButton(onClick = onLogout) {
                        Icon(
                            imageVector = Icons.Default.ExitToApp,
                            contentDescription = "Logout"
                        )
                    }
                }
            )
        }
    ) { paddingValues ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues)
        ) {
            if (uiState.isLoading) {
                Box(
                    modifier = Modifier.fillMaxSize(),
                    contentAlignment = Alignment.Center
                ) {
                    LoadingIndicator()
                }
            } else if (uiState.errorMessage != null) {
                Card(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(16.dp),
                    backgroundColor = MaterialTheme.colors.error
                ) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(16.dp),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(
                            text = uiState.errorMessage ?: "",
                            color = MaterialTheme.colors.onError,
                            modifier = Modifier.weight(1f)
                        )
                        TextButton(
                            onClick = { viewModel.clearErrorMessage() }
                        ) {
                            Text(
                                text = "Dismiss",
                                color = MaterialTheme.colors.onError
                            )
                        }
                    }
                }
            } else if (uiState.nodes.isEmpty()) {
                Box(
                    modifier = Modifier.fillMaxSize(),
                    contentAlignment = Alignment.Center
                ) {
                    Text(
                        text = "No nodes configured",
                        style = MaterialTheme.typography.body1
                    )
                }
            } else {
                LazyColumn(
                    modifier = Modifier.fillMaxSize(),
                    contentPadding = PaddingValues(16.dp),
                    verticalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    items(uiState.nodes) { node ->
                        NodeCard(
                            node = node,
                            onConnect = { targetNodeId -> viewModel.connectNode(node.id.toString(), targetNodeId) },
                            onDisconnect = { targetNodeId -> viewModel.disconnectNode(node.id.toString(), targetNodeId) },
                            onMonitor = { targetNodeId -> viewModel.monitorNode(node.id.toString(), targetNodeId) },
                            enableActions = enableActions  // Pass enableActions parameter
                        )
                    }
                }
            }
        }
    }
}
