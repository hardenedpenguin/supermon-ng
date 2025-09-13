package com.supermonng.mobile.data.local

import android.content.Context
import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import com.google.gson.Gson
import com.supermonng.mobile.domain.model.LoginCredentials
import dagger.hilt.android.qualifiers.ApplicationContext
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.map
import javax.inject.Inject
import javax.inject.Singleton

private val Context.dataStore: DataStore<Preferences> by preferencesDataStore(name = "supermon_preferences")

@Singleton
class SupermonLocalDataSource @Inject constructor(
    @ApplicationContext private val context: Context
) {
    private val gson = Gson()
    
    companion object {
        private val CREDENTIALS_KEY = stringPreferencesKey("stored_credentials")
        private val SERVER_URL_KEY = stringPreferencesKey("server_url")
    }
    
    fun getStoredCredentials(): Flow<LoginCredentials?> {
        return context.dataStore.data.map { preferences ->
            val credentialsJson = preferences[CREDENTIALS_KEY]
            if (credentialsJson != null) {
                try {
                    gson.fromJson(credentialsJson, LoginCredentials::class.java)
                } catch (e: Exception) {
                    null
                }
            } else {
                null
            }
        }
    }
    
    suspend fun storeCredentials(credentials: LoginCredentials) {
        context.dataStore.edit { preferences ->
            val credentialsJson = gson.toJson(credentials)
            preferences[CREDENTIALS_KEY] = credentialsJson
        }
    }
    
    suspend fun clearCredentials() {
        context.dataStore.edit { preferences ->
            preferences.remove(CREDENTIALS_KEY)
        }
    }
    
    fun getStoredServerUrl(): Flow<String?> {
        return context.dataStore.data.map { preferences ->
            preferences[SERVER_URL_KEY]
        }
    }
    
    suspend fun storeServerUrl(url: String) {
        context.dataStore.edit { preferences ->
            preferences[SERVER_URL_KEY] = url
        }
    }
}
