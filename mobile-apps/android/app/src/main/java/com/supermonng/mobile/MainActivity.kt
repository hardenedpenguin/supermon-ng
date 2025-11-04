package com.supermonng.mobile

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ExitToApp
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material.icons.filled.Settings
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.unit.dp
import com.supermonng.mobile.ui.screens.NodesScreen
import com.supermonng.mobile.ui.theme.SupermonNGTheme
import com.supermonng.mobile.data.repository.SupermonRepository
import com.supermonng.mobile.data.local.PreferencesManager
import com.supermonng.mobile.data.network.NetworkModule
import com.supermonng.mobile.ui.viewmodel.NodesViewModel
import com.supermonng.mobile.ui.viewmodel.LoginViewModel
import com.supermonng.mobile.ui.viewmodel.ViewModelFactory
import androidx.lifecycle.viewmodel.compose.viewModel
import kotlinx.coroutines.launch

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent {
            SupermonNGTheme {
                Surface(
                    modifier = Modifier.fillMaxSize(),
                    color = MaterialTheme.colors.background
                ) {
                    SupermonApp()
                }
            }
        }
    }
}

@Composable
fun SupermonApp() {
    var isLoggedIn by remember { mutableStateOf(false) }
    var showSettingsDialog by remember { mutableStateOf(false) }
    var showServerConfigDialog by remember { mutableStateOf(false) }
    val repository = remember { SupermonRepository() }
    val scope = rememberCoroutineScope()
    val context = LocalContext.current
    val preferencesManager = remember { PreferencesManager(context) }
    
    // Separate ViewModels for pre-login (allmon.ini) and post-login (user-specific INI)
    val preLoginNodesViewModel = remember { NodesViewModel() }
    val postLoginNodesViewModel = remember { NodesViewModel() }
    val loginViewModel = viewModel<LoginViewModel>(factory = ViewModelFactory(context))
    
    // Check if server URL is configured on app start
    LaunchedEffect(Unit) {
        val savedServerUrl = preferencesManager.getServerUrl()
        // Always set the URL and load nodes
        NetworkModule.setBaseUrl(savedServerUrl)
        preLoginNodesViewModel.loadNodes()
        
        // Only show config dialog if server URL has never been configured
        if (!preferencesManager.hasServerUrlConfigured()) {
            showServerConfigDialog = true
        }
    }
    
    // Refresh nodes after login to get user-specific INI file
    LaunchedEffect(isLoggedIn) {
        if (isLoggedIn) {
            // Small delay to ensure session is established
            kotlinx.coroutines.delay(100)
            postLoginNodesViewModel.loadNodes()
        }
    }
    
    if (!isLoggedIn) {
        // Before login: Show full-width nodes from allmon.ini with login button
        LoginWithNodesScreen(
            loginViewModel = loginViewModel,
            nodesViewModel = preLoginNodesViewModel,
            onLoginSuccess = {
                isLoggedIn = true
            },
            onConfigureServer = {
                showServerConfigDialog = true
            }
        )
        
        // Server Configuration Dialog
        if (showServerConfigDialog) {
            ServerConfigDialog(
                currentUrl = preferencesManager.getServerUrl(),
                onDismiss = { 
                    showServerConfigDialog = false
                    // If URL is still default after dismiss, keep dialog open
                    if (preferencesManager.getServerUrl() == "https://sm.w5gle.us") {
                        // Optionally show again, but let user dismiss it
                    }
                },
                onSave = { newUrl ->
                    preferencesManager.saveServerUrl(newUrl)
                    NetworkModule.setBaseUrl(newUrl)
                    // Reload nodes after server URL change
                    preLoginNodesViewModel.loadNodes()
                    showServerConfigDialog = false
                }
            )
        }
    } else {
        // After login: Show nodes from user-specific INI file with controls enabled
        NodesScreen(
            viewModel = postLoginNodesViewModel,
            enableActions = true,  // Enable actions when logged in
            onLogout = {
                // Clear session on server side and reset login state
                android.util.Log.d("SupermonApp", "Logout button clicked")
                // Reset login viewmodel state first
                loginViewModel.resetLoginState()
                // Update state immediately to switch UI
                isLoggedIn = false
                // Perform logout in background
                scope.launch {
                    try {
                        repository.logout()
                    } catch (e: Exception) {
                        android.util.Log.e("SupermonApp", "Logout error: ${e.message}")
                    }
                    // Reload nodes from allmon.ini after logout
                    preLoginNodesViewModel.loadNodes()
                }
            },
            onSettings = {
                // Show settings dialog
                android.util.Log.d("SupermonApp", "Settings button clicked")
                showSettingsDialog = true
            }
        )
        
        // Settings Dialog
        if (showSettingsDialog) {
            AlertDialog(
                onDismissRequest = { showSettingsDialog = false },
                title = { 
                    Text("Settings")
                },
                text = {
                    Column {
                        Text("Settings screen coming soon...")
                        Spacer(modifier = Modifier.height(8.dp))
                        Text("Configure server URL and other preferences here.")
                    }
                },
                confirmButton = {
                    TextButton(onClick = { showSettingsDialog = false }) {
                        Text("OK")
                    }
                }
            )
        }
    }
}

@Composable
fun LoginWithNodesScreen(
    loginViewModel: LoginViewModel,
    nodesViewModel: NodesViewModel,
    onLoginSuccess: () -> Unit,
    onConfigureServer: () -> Unit
) {
    val loginUiState by loginViewModel.uiState.collectAsState()
    val nodesUiState by nodesViewModel.uiState.collectAsState()
    var showLoginDialog by remember { mutableStateOf(false) }
    
    LaunchedEffect(loginUiState.isLoginSuccessful) {
        if (loginUiState.isLoginSuccessful) {
            showLoginDialog = false
            onLoginSuccess()
        }
    }
    
    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("SupermonNG") },
                actions = {
                    IconButton(onClick = { nodesViewModel.loadNodes() }) {
                        Icon(
                            imageVector = Icons.Default.Refresh,
                            contentDescription = "Refresh"
                        )
                    }
                    IconButton(onClick = onConfigureServer) {
                        Icon(
                            imageVector = Icons.Default.Settings,
                            contentDescription = "Configure Server"
                        )
                    }
                    // Login button in app bar
                    TextButton(onClick = { showLoginDialog = true }) {
                        Text("Login", color = MaterialTheme.colors.onPrimary)
                    }
                }
            )
        }
    ) { paddingValues ->
        // Full-width nodes display
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues)
        ) {
            // Header showing this is from allmon.ini
            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(16.dp),
                backgroundColor = MaterialTheme.colors.primary.copy(alpha = 0.1f)
            ) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(16.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Column {
                        Text(
                            text = "Available Nodes (allmon.ini)",
                            style = MaterialTheme.typography.h6,
                            fontWeight = FontWeight.Bold
                        )
                        Text(
                            text = "Login to access your configured nodes",
                            style = MaterialTheme.typography.body2,
                            color = MaterialTheme.colors.onSurface.copy(alpha = 0.7f)
                        )
                    }
                    Button(onClick = { showLoginDialog = true }) {
                        Text("Login")
                    }
                }
            }
            
            // Nodes list - full width
            if (nodesUiState.isLoading) {
                Box(
                    modifier = Modifier.fillMaxSize(),
                    contentAlignment = Alignment.Center
                ) {
                    com.supermonng.mobile.ui.components.LoadingIndicator()
                }
            } else if (nodesUiState.errorMessage != null) {
                Card(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(16.dp),
                    backgroundColor = MaterialTheme.colors.error
                ) {
                    Text(
                        text = nodesUiState.errorMessage ?: "",
                        modifier = Modifier.padding(16.dp),
                        color = MaterialTheme.colors.onError
                    )
                }
            } else if (nodesUiState.nodes.isEmpty()) {
                Box(
                    modifier = Modifier.fillMaxSize(),
                    contentAlignment = Alignment.Center
                ) {
                    Column(
                        horizontalAlignment = Alignment.CenterHorizontally,
                        verticalArrangement = Arrangement.spacedBy(16.dp)
                    ) {
                        Text(
                            text = "No nodes configured",
                            style = MaterialTheme.typography.body1
                        )
                        Button(onClick = { showLoginDialog = true }) {
                            Text("Login")
                        }
                    }
                }
            } else {
                LazyColumn(
                    modifier = Modifier.fillMaxSize(),
                    contentPadding = PaddingValues(16.dp),
                    verticalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    items(nodesUiState.nodes) { node ->
                        com.supermonng.mobile.ui.components.NodeCard(
                            node = node,
                            onConnect = { _ -> }, // Disable actions before login (no-op)
                            onDisconnect = { _ -> },
                            onMonitor = { _ -> },
                            enableActions = false  // Hide action buttons before login
                        )
                    }
                }
            }
        }
    }
    
    // Login Dialog Modal
    if (showLoginDialog) {
        AlertDialog(
            onDismissRequest = { showLoginDialog = false },
            title = {
                Text("Login")
            },
            text = {
                Column(
                    modifier = Modifier.fillMaxWidth(),
                    verticalArrangement = Arrangement.spacedBy(16.dp)
                ) {
                    Text(
                        text = "Enter any username and password to continue",
                        style = MaterialTheme.typography.body2,
                        color = MaterialTheme.colors.onSurface.copy(alpha = 0.7f)
                    )
                    
                    OutlinedTextField(
                        value = loginUiState.username,
                        onValueChange = loginViewModel::updateUsername,
                        label = { Text("Username") },
                        modifier = Modifier.fillMaxWidth(),
                        enabled = !loginUiState.isLoading,
                        singleLine = true
                    )
                    
                    OutlinedTextField(
                        value = loginUiState.password,
                        onValueChange = loginViewModel::updatePassword,
                        label = { Text("Password") },
                        modifier = Modifier.fillMaxWidth(),
                        enabled = !loginUiState.isLoading,
                        singleLine = true,
                        visualTransformation = if (loginUiState.passwordVisible) {
                            VisualTransformation.None
                        } else {
                            PasswordVisualTransformation()
                        },
                        trailingIcon = {
                            IconButton(onClick = { loginViewModel.togglePasswordVisibility() }) {
                                Text(if (loginUiState.passwordVisible) "Hide" else "Show")
                            }
                        }
                    )
                    
                    // Remember Me checkbox
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Checkbox(
                            checked = loginUiState.rememberMe,
                            onCheckedChange = { loginViewModel.toggleRememberMe() },
                            enabled = !loginUiState.isLoading
                        )
                        Spacer(modifier = Modifier.width(8.dp))
                        Text(
                            text = "Remember me",
                            style = MaterialTheme.typography.body2,
                            modifier = Modifier.clickable(enabled = !loginUiState.isLoading) {
                                loginViewModel.toggleRememberMe()
                            }
                        )
                    }
                    
                    if (loginUiState.errorMessage != null) {
                        Card(
                            modifier = Modifier.fillMaxWidth(),
                            backgroundColor = MaterialTheme.colors.error
                        ) {
                            Text(
                                text = loginUiState.errorMessage ?: "",
                                modifier = Modifier.padding(16.dp),
                                color = MaterialTheme.colors.onError
                            )
                        }
                    }
                }
            },
            confirmButton = {
                Button(
                    onClick = { loginViewModel.login() },
                    enabled = !loginUiState.isLoading && loginUiState.username.isNotBlank() && loginUiState.password.isNotBlank()
                ) {
                    if (loginUiState.isLoading) {
                        CircularProgressIndicator(
                            modifier = Modifier.size(16.dp),
                            color = MaterialTheme.colors.onPrimary
                        )
                        Spacer(modifier = Modifier.width(8.dp))
                        Text("Logging in...")
                    } else {
                        Text("Login")
                    }
                }
            },
            dismissButton = {
                TextButton(onClick = { showLoginDialog = false }) {
                    Text("Cancel")
                }
            }
        )
    }
}

@Composable
fun ServerConfigDialog(
    currentUrl: String,
    onDismiss: () -> Unit,
    onSave: (String) -> Unit
) {
    var serverUrl by remember { mutableStateOf(currentUrl) }
    var errorMessage by remember { mutableStateOf<String?>(null) }
    
    AlertDialog(
        onDismissRequest = onDismiss,
        title = {
            Text("Configure Server")
        },
        text = {
            Column {
                Text(
                    text = "Enter the server URL (e.g., https://sm.w5gle.us or http://10.0.0.5)",
                    style = MaterialTheme.typography.body2,
                    color = MaterialTheme.colors.onSurface.copy(alpha = 0.7f)
                )
                Spacer(modifier = Modifier.height(8.dp))
                OutlinedTextField(
                    value = serverUrl,
                    onValueChange = { 
                        serverUrl = it
                        errorMessage = null
                    },
                    label = { Text("Server URL") },
                    modifier = Modifier.fillMaxWidth(),
                    singleLine = true,
                    isError = errorMessage != null
                )
                if (errorMessage != null) {
                    Spacer(modifier = Modifier.height(4.dp))
                    Text(
                        text = errorMessage ?: "",
                        color = MaterialTheme.colors.error,
                        style = MaterialTheme.typography.caption
                    )
                }
                Spacer(modifier = Modifier.height(8.dp))
                Text(
                    text = "The app will automatically append /supermon-ng/api to the URL if needed.",
                    style = MaterialTheme.typography.caption,
                    color = MaterialTheme.colors.onSurface.copy(alpha = 0.6f)
                )
            }
        },
        confirmButton = {
            TextButton(
                onClick = {
                    val trimmedUrl = serverUrl.trim()
                    if (trimmedUrl.isEmpty()) {
                        errorMessage = "Server URL cannot be empty"
                    } else if (!trimmedUrl.matches(Regex("^(https?://)?[^\\s/]+"))) {
                        errorMessage = "Please enter a valid URL (e.g., https://example.com or http://10.0.0.5)"
                    } else {
                        // Normalize URL - ensure it starts with http:// or https://
                        val normalizedUrl = when {
                            trimmedUrl.startsWith("http://") -> trimmedUrl
                            trimmedUrl.startsWith("https://") -> trimmedUrl
                            else -> "https://$trimmedUrl"
                        }
                        onSave(normalizedUrl)
                    }
                }
            ) {
                Text("Save")
            }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) {
                Text("Cancel")
            }
        }
    )
}
