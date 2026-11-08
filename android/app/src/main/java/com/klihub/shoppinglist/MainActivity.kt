package com.klihub.shoppinglist

import android.content.Intent
import android.net.Uri
import android.os.Bundle
import androidx.activity.ComponentActivity
import com.klihub.shoppinglist.mobilebridge.Mobilebridge
import androidx.activity.compose.setContent
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.Button
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.lifecycle.lifecycleScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import org.json.JSONArray

class MainActivity : ComponentActivity() {
    private lateinit var credentialStore: CredentialStore
    private var server by mutableStateOf("https://nextcloud.klihub.dynv6.net")
    private var status by mutableStateOf("Sign in to your Nextcloud server.")
    private var busy by mutableStateOf(false)
    private var listCount by mutableStateOf<Int?>(null)

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        credentialStore = CredentialStore(this)
        val saved = runCatching { credentialStore.load() }.getOrNull()
        if (saved != null) {
            server = saved.server
            status = "Checking saved account…"
            verifySavedAccount()
        }
        setContent {
            MaterialTheme {
                Surface(modifier = Modifier.fillMaxSize()) {
                    Column(
                        modifier = Modifier.fillMaxSize().padding(24.dp),
                        verticalArrangement = Arrangement.spacedBy(16.dp),
                    ) {
                        Text("Shopping List", style = MaterialTheme.typography.headlineMedium)
                        OutlinedTextField(
                            value = server,
                            onValueChange = { server = it },
                            modifier = Modifier.fillMaxWidth(),
                            label = { Text("Nextcloud server URL") },
                            singleLine = true,
                            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Uri),
                            enabled = !busy,
                        )
                        Button(
                            onClick = ::startLogin,
                            enabled = !busy && server.isNotBlank(),
                            modifier = Modifier.fillMaxWidth(),
                        ) {
                            Text(if (busy) "Please wait…" else "Connect with Nextcloud")
                        }
                        if (busy) CircularProgressIndicator()
                        Text(status, style = MaterialTheme.typography.bodyLarge)
                        if (listCount != null) {
                            Text("Connected. ${listCount} shopping list(s) available.")
                            Button(onClick = ::logout) { Text("Disconnect") }
                        }
                    }
                }
            }
        }
    }

    private fun startLogin() {
        busy = true
        listCount = null
        status = "Starting secure sign-in…"
        lifecycleScope.launch {
            try {
                val flow = withContext(Dispatchers.IO) { LoginFlowV2().begin(server) }
                status = "Complete sign-in and grant access in your browser."
                startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(flow.loginUrl)))
                status = "Waiting for you to finish in the browser…"
                val credentials = withContext(Dispatchers.IO) {
                    LoginFlowV2().pollUntilAuthorized(flow)
                }
                verifyCredentials(credentials)
                withContext(Dispatchers.IO) { credentialStore.save(credentials) }
            } catch (error: Exception) {
                status = error.message ?: "Sign-in failed."
            } finally {
                busy = false
            }
        }
    }

    private suspend fun verifyCredentials(credentials: AccountCredentials) {
        status = "Verifying the new app password…"
        val count = withContext(Dispatchers.IO) {
            val client = Mobilebridge.openClient(
                credentials.server,
                credentials.loginName,
                credentials.appPassword,
            )
            JSONArray(client.listListsJSON()).length()
        }
        listCount = count
        status = "Nextcloud account connected successfully."
    }

    private fun verifySavedAccount() {
        lifecycleScope.launch {
            try {
                val credentials = withContext(Dispatchers.IO) {
                    credentialStore.load() ?: error("No saved account")
                }
                verifyCredentials(credentials)
            } catch (error: Exception) {
                status = "Saved sign-in could not be verified: ${error.message}"
                listCount = null
            }
        }
    }

    private fun logout() {
        credentialStore.clear()
        listCount = null
        status = "Disconnected. Your Nextcloud app password was removed from this device."
    }
}
