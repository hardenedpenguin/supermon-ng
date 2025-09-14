package com.supermonng.mobile.ui.screens

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.*
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
import com.supermonng.mobile.ui.viewmodel.LoginViewModel
import com.supermonng.mobile.ui.viewmodel.ViewModelFactory

@Composable
fun LoginScreen(
    onLoginSuccess: () -> Unit,
    onConfigureServer: () -> Unit,
    viewModel: LoginViewModel = viewModel(factory = ViewModelFactory(LocalContext.current))
) {
    val uiState by viewModel.uiState.collectAsState()
    
    LaunchedEffect(uiState.isLoginSuccessful) {
        if (uiState.isLoginSuccessful) {
            onLoginSuccess()
        }
    }
    
    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(16.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center
    ) {
        Text(
            text = "SupermonNG",
            style = MaterialTheme.typography.h4,
            color = MaterialTheme.colors.primary
        )
        
        Spacer(modifier = Modifier.height(8.dp))
        
        Text(
            text = "Enter any username and password to continue",
            style = MaterialTheme.typography.body2,
            color = MaterialTheme.colors.onSurface.copy(alpha = 0.7f)
        )
        
        Spacer(modifier = Modifier.height(32.dp))
        
        OutlinedTextField(
            value = uiState.username,
            onValueChange = viewModel::updateUsername,
            label = { Text("Username") },
            modifier = Modifier.fillMaxWidth(),
            enabled = !uiState.isLoading,
            singleLine = true,
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Text)
        )
        
        Spacer(modifier = Modifier.height(16.dp))
        
        OutlinedTextField(
            value = uiState.password,
            onValueChange = viewModel::updatePassword,
            label = { Text("Password") },
            modifier = Modifier.fillMaxWidth(),
            enabled = !uiState.isLoading,
            singleLine = true,
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
        
        Spacer(modifier = Modifier.height(16.dp))
        
        // Remember Me checkbox
        Row(
            modifier = Modifier.fillMaxWidth(),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Checkbox(
                checked = uiState.rememberMe,
                onCheckedChange = { viewModel.toggleRememberMe() },
                enabled = !uiState.isLoading
            )
            Spacer(modifier = Modifier.width(8.dp))
            Text(
                text = "Remember me",
                style = MaterialTheme.typography.body2,
                modifier = Modifier.clickable { 
                    if (!uiState.isLoading) {
                        viewModel.toggleRememberMe() 
                    }
                }
            )
        }
        
        Spacer(modifier = Modifier.height(24.dp))
        
        if (uiState.errorMessage != null) {
            Card(
                modifier = Modifier.fillMaxWidth(),
                backgroundColor = MaterialTheme.colors.error
            ) {
                Text(
                    text = uiState.errorMessage ?: "",
                    modifier = Modifier.padding(16.dp),
                    color = MaterialTheme.colors.onError
                )
            }
            
            Spacer(modifier = Modifier.height(16.dp))
        }
        
        Button(
            onClick = { viewModel.login() },
            modifier = Modifier.fillMaxWidth(),
            enabled = !uiState.isLoading && uiState.username.isNotBlank() && uiState.password.isNotBlank()
        ) {
            if (uiState.isLoading) {
                LoadingIndicator()
                Spacer(modifier = Modifier.width(8.dp))
                Text("Logging in...")
            } else {
                Text("Login")
            }
        }
        
        Spacer(modifier = Modifier.height(16.dp))
        
        // Configure Server Button
        OutlinedButton(
            onClick = onConfigureServer,
            modifier = Modifier.fillMaxWidth()
        ) {
            Text("Configure Server")
        }
    }
}
