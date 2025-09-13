package com.supermonng.mobile.data.local

import android.content.Context
import android.content.SharedPreferences

class PreferencesManager(context: Context) {
    
    private val prefs: SharedPreferences = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
    
    companion object {
        private const val PREFS_NAME = "supermon_prefs"
        private const val KEY_USERNAME = "username"
        private const val KEY_PASSWORD = "password"
        private const val KEY_REMEMBER_ME = "remember_me"
        private const val KEY_SERVER_URL = "server_url"
        private const val KEY_SERVER_PORT = "server_port"
    }
    
    fun saveCredentials(username: String, password: String, rememberMe: Boolean) {
        prefs.edit().apply {
            putString(KEY_USERNAME, username)
            putBoolean(KEY_REMEMBER_ME, rememberMe)
            if (rememberMe) {
                putString(KEY_PASSWORD, password)
            } else {
                remove(KEY_PASSWORD)
            }
            apply()
        }
    }
    
    fun getUsername(): String? {
        return prefs.getString(KEY_USERNAME, null)
    }
    
    fun getPassword(): String? {
        return prefs.getString(KEY_PASSWORD, null)
    }
    
    fun getRememberMe(): Boolean {
        return prefs.getBoolean(KEY_REMEMBER_ME, false)
    }
    
    fun saveServerSettings(url: String, port: String) {
        prefs.edit().apply {
            putString(KEY_SERVER_URL, url)
            putString(KEY_SERVER_PORT, port)
            apply()
        }
    }
    
    fun getServerUrl(): String? {
        return prefs.getString(KEY_SERVER_URL, null)
    }
    
    fun getServerPort(): String? {
        return prefs.getString(KEY_SERVER_PORT, null)
    }
    
    fun clearCredentials() {
        prefs.edit().apply {
            remove(KEY_USERNAME)
            remove(KEY_PASSWORD)
            remove(KEY_REMEMBER_ME)
            apply()
        }
    }
}
