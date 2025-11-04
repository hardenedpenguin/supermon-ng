package com.supermonng.mobile.data.local

import android.content.Context
import android.content.SharedPreferences
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKey

class PreferencesManager(private val context: Context) {
    
    private val masterKey = MasterKey.Builder(context)
        .setKeyScheme(MasterKey.KeyScheme.AES256_GCM)
        .build()
    
    private val sharedPreferences: SharedPreferences = EncryptedSharedPreferences.create(
        context,
        PREFS_NAME,
        masterKey,
        EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
        EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM
    )
    
    companion object {
        private const val PREFS_NAME = "supermon_credentials"
        private const val KEY_USERNAME = "username"
        private const val KEY_PASSWORD = "password"
        private const val KEY_REMEMBER_ME = "remember_me"
        private const val KEY_SERVER_URL = "server_url"
        private const val DEFAULT_SERVER_URL = "https://sm.w5gle.us"
    }
    
    fun saveCredentials(username: String, password: String, rememberMe: Boolean) {
        with(sharedPreferences.edit()) {
            if (rememberMe) {
                putString(KEY_USERNAME, username)
                putString(KEY_PASSWORD, password)
                putBoolean(KEY_REMEMBER_ME, true)
            } else {
                remove(KEY_USERNAME)
                remove(KEY_PASSWORD)
                putBoolean(KEY_REMEMBER_ME, false)
            }
            apply()
        }
    }
    
    fun getUsername(): String? {
        return sharedPreferences.getString(KEY_USERNAME, null)
    }
    
    fun getPassword(): String? {
        return sharedPreferences.getString(KEY_PASSWORD, null)
    }
    
    fun getRememberMe(): Boolean {
        return sharedPreferences.getBoolean(KEY_REMEMBER_ME, false)
    }
    
    fun clearCredentials() {
        with(sharedPreferences.edit()) {
            remove(KEY_USERNAME)
            remove(KEY_PASSWORD)
            remove(KEY_REMEMBER_ME)
            apply()
        }
    }
    
    fun saveServerUrl(url: String) {
        with(sharedPreferences.edit()) {
            putString(KEY_SERVER_URL, url.trim())
            apply()
        }
    }
    
    fun getServerUrl(): String {
        return sharedPreferences.getString(KEY_SERVER_URL, DEFAULT_SERVER_URL) ?: DEFAULT_SERVER_URL
    }
    
    fun hasServerUrlConfigured(): Boolean {
        return sharedPreferences.contains(KEY_SERVER_URL)
    }
    
    fun clearServerUrl() {
        with(sharedPreferences.edit()) {
            remove(KEY_SERVER_URL)
            apply()
        }
    }
}
