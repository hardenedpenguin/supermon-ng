package com.supermonng.mobile.ui.navigation

import androidx.compose.runtime.Composable
import androidx.navigation.NavHostController
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import com.supermonng.mobile.ui.screens.LoginScreen
import com.supermonng.mobile.ui.screens.NodesScreen
import com.supermonng.mobile.ui.screens.SettingsScreen

@Composable
fun SupermonNavigation(navController: NavHostController) {
    NavHost(
        navController = navController,
        startDestination = "login"
    ) {
        composable("login") {
            LoginScreen(
                onLoginSuccess = {
                    navController.navigate("nodes") {
                        popUpTo("login") { inclusive = true }
                    }
                },
                onConfigureServer = {
                    navController.navigate("settings")
                }
            )
        }
        
        composable("nodes") {
            NodesScreen(
                onLogout = {
                    navController.navigate("login") {
                        popUpTo("nodes") { inclusive = true }
                    }
                },
                onSettings = {
                    navController.navigate("settings")
                }
            )
        }
        
        composable("settings") {
            SettingsScreen(
                onBack = {
                    navController.popBackStack()
                },
                onSaveAndLogin = {
                    navController.navigate("login") {
                        popUpTo("settings") { inclusive = true }
                    }
                }
            )
        }
    }
}
