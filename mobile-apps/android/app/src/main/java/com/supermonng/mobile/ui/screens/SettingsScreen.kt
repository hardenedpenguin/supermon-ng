package com.supermonng.mobile.ui.screens

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.Check
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import com.supermonng.mobile.ui.components.LoadingIndicator
import com.supermonng.mobile.ui.viewmodel.SettingsViewModel
import com.supermonng.mobile.ui.viewmodel.ViewModelFactory

@Composable
fun SettingsScreen(
    onBack: () -> Unit,
    onSaveAndLogin: () -> Unit,
    viewModel: SettingsViewModel = viewModel(factory = ViewModelFactory(LocalContext.current))
) {
    val uiState by viewModel.uiState.collectAsState()
    
    LaunchedEffect(Unit) {
        viewModel.loadSettings()
    }
    
    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Settings") },
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(
                            imageVector = Icons.Default.ArrowBack,
                            contentDescription = "Back"
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
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            // Server Configuration Section
            Card(
                modifier = Modifier.fillMaxWidth(),
                elevation = 4.dp
            ) {
                Column(
                    modifier = Modifier.padding(16.dp)
                ) {
                    Text(
                        text = "Server Configuration",
                        style = MaterialTheme.typography.h6,
                        color = MaterialTheme.colors.primary
                    )
                    
                    Spacer(modifier = Modifier.height(16.dp))
                    
                    // Server URL
                    OutlinedTextField(
                        value = uiState.serverUrl,
                        onValueChange = viewModel::updateServerUrl,
                        label = { Text("Server URL") },
                        placeholder = { Text("https://your-server.com") },
                        modifier = Modifier.fillMaxWidth(),
                        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Uri),
                        isError = uiState.serverUrlError != null
                    )
                    
                    val serverUrlError = uiState.serverUrlError
                    if (serverUrlError != null) {
                        Text(
                            text = serverUrlError,
                            color = MaterialTheme.colors.error,
                            style = MaterialTheme.typography.caption,
                            modifier = Modifier.padding(start = 16.dp, top = 4.dp)
                        )
                    }
                    
                    Spacer(modifier = Modifier.height(8.dp))
                    
                    // Port (optional)
                    OutlinedTextField(
                        value = uiState.serverPort,
                        onValueChange = viewModel::updateServerPort,
                        label = { Text("Port (optional)") },
                        placeholder = { Text("443") },
                        modifier = Modifier.fillMaxWidth(),
                        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                        isError = uiState.serverPortError != null
                    )
                    
                    val serverPortError = uiState.serverPortError
                    if (serverPortError != null) {
                        Text(
                            text = serverPortError,
                            color = MaterialTheme.colors.error,
                            style = MaterialTheme.typography.caption,
                            modifier = Modifier.padding(start = 16.dp, top = 4.dp)
                        )
                    }
                    
                    Spacer(modifier = Modifier.height(16.dp))
                    
                    // Test Connection Button
                    Button(
                        onClick = { viewModel.testConnection() },
                        modifier = Modifier.fillMaxWidth(),
                        enabled = !uiState.isTestingConnection && uiState.serverUrl.isNotBlank()
                    ) {
                        if (uiState.isTestingConnection) {
                            LoadingIndicator()
                            Spacer(modifier = Modifier.width(8.dp))
                            Text("Testing...")
                        } else {
                            Text("Test Connection")
                        }
                    }
                    
                    // Connection Status
                    val connectionStatus = uiState.connectionStatus
                    if (connectionStatus != null) {
                        Spacer(modifier = Modifier.height(8.dp))
                        Row(
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Icon(
                                imageVector = if (connectionStatus == "Success") {
                                    Icons.Default.Check
                                } else {
                                    Icons.Default.ArrowBack // Using ArrowBack as placeholder for error
                                },
                                contentDescription = null,
                                tint = if (connectionStatus == "Success") {
                                    MaterialTheme.colors.primary
                                } else {
                                    MaterialTheme.colors.error
                                }
                            )
                            Spacer(modifier = Modifier.width(8.dp))
                            Text(
                                text = connectionStatus,
                                color = if (connectionStatus == "Success") {
                                    MaterialTheme.colors.primary
                                } else {
                                    MaterialTheme.colors.error
                                }
                            )
                        }
                    }
                }
            }
            
            // Authentication Section
            Card(
                modifier = Modifier.fillMaxWidth(),
                elevation = 4.dp
            ) {
                Column(
                    modifier = Modifier.padding(16.dp)
                ) {
                    Text(
                        text = "Authentication",
                        style = MaterialTheme.typography.h6,
                        color = MaterialTheme.colors.primary
                    )
                    
                    Spacer(modifier = Modifier.height(16.dp))
                    
                    // Username
                    OutlinedTextField(
                        value = uiState.username,
                        onValueChange = viewModel::updateUsername,
                        label = { Text("Username") },
                        placeholder = { Text("Enter your username") },
                        modifier = Modifier.fillMaxWidth()
                    )
                    
                    Spacer(modifier = Modifier.height(8.dp))
                    
                    // Password
                    OutlinedTextField(
                        value = uiState.password,
                        onValueChange = viewModel::updatePassword,
                        label = { Text("Password") },
                        placeholder = { Text("Enter your password") },
                        modifier = Modifier.fillMaxWidth(),
                        visualTransformation = if (uiState.passwordVisible) {
                            VisualTransformation.None
                        } else {
                            PasswordVisualTransformation()
                        },
                        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Password),
                        trailingIcon = {
                            IconButton(onClick = { viewModel.togglePasswordVisibility() }) {
                                Text(if (uiState.passwordVisible) "Hide" else "Show")
                            }
                        }
                    )
                    
                    Spacer(modifier = Modifier.height(8.dp))
                    
                    // Remember Credentials
                    Row(
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Checkbox(
                            checked = uiState.rememberCredentials,
                            onCheckedChange = viewModel::updateRememberCredentials
                        )
                        Spacer(modifier = Modifier.width(8.dp))
                        Text("Remember credentials")
                    }
                }
            }
            
            // Save Button
            Button(
                onClick = { viewModel.saveSettings() },
                modifier = Modifier.fillMaxWidth(),
                enabled = !uiState.isSaving && uiState.serverUrl.isNotBlank() && uiState.username.isNotBlank()
            ) {
                if (uiState.isSaving) {
                    LoadingIndicator()
                    Spacer(modifier = Modifier.width(8.dp))
                    Text("Saving...")
                } else {
                    Text("Save Settings")
                }
            }
            
            Spacer(modifier = Modifier.height(8.dp))
            
            // Save and Login Button
            OutlinedButton(
                onClick = {
                    viewModel.saveSettings()
                    onSaveAndLogin()
                },
                modifier = Modifier.fillMaxWidth(),
                enabled = !uiState.isSaving && uiState.serverUrl.isNotBlank() && uiState.username.isNotBlank()
            ) {
                Text("Save Settings and Login")
            }
            
            // Save Status
            val saveStatus = uiState.saveStatus
            if (saveStatus != null) {
                Row(
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Icon(
                        imageVector = if (saveStatus == "Settings saved successfully!") {
                            Icons.Default.Check
                        } else {
                            Icons.Default.ArrowBack // Using ArrowBack as placeholder for error
                        },
                        contentDescription = null,
                        tint = if (saveStatus == "Settings saved successfully!") {
                            MaterialTheme.colors.primary
                        } else {
                            MaterialTheme.colors.error
                        }
                    )
                    Spacer(modifier = Modifier.width(8.dp))
                    Text(
                        text = saveStatus,
                        color = if (saveStatus == "Settings saved successfully!") {
                            MaterialTheme.colors.primary
                        } else {
                            MaterialTheme.colors.error
                        }
                    )
                }
            }
            
            // Help Section
            Card(
                modifier = Modifier.fillMaxWidth(),
                elevation = 2.dp
            ) {
                Column(
                    modifier = Modifier.padding(16.dp)
                ) {
                    Text(
                        text = "Help",
                        style = MaterialTheme.typography.h6,
                        color = MaterialTheme.colors.primary
                    )
                    
                    Spacer(modifier = Modifier.height(8.dp))
                    
                    Text(
                        text = "• Enter your SupermonNG server URL (e.g., https://your-server.com)",
                        style = MaterialTheme.typography.body2
                    )
                    Text(
                        text = "• Use your SupermonNG login credentials",
                        style = MaterialTheme.typography.body2
                    )
                    Text(
                        text = "• Test connection before saving",
                        style = MaterialTheme.typography.body2
                    )
                    Text(
                        text = "• Settings are saved securely on your device",
                        style = MaterialTheme.typography.body2
                    )
                }
            }
        }
    }
}
