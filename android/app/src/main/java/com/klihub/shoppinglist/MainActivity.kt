package com.klihub.shoppinglist

import android.content.Intent
import android.net.Uri
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.weight
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.Checkbox
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextDecoration
import androidx.compose.ui.unit.dp
import androidx.lifecycle.lifecycleScope
import com.klihub.shoppinglist.mobilebridge.Mobilebridge
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import org.json.JSONArray
import org.json.JSONObject

private data class ShoppingList(val id: Long, val name: String)
private data class ShoppingItem(
    val id: Long,
    val name: String,
    val quantity: String?,
    val note: String?,
    val checked: Boolean,
)

private fun parseLists(json: String): List<ShoppingList> {
    val array = JSONArray(json)
    return (0 until array.length()).map { index ->
        val value = array.getJSONObject(index)
        ShoppingList(value.getLong("id"), value.optString("name"))
    }
}

private fun parseItems(json: String): List<ShoppingItem> {
    val array = JSONArray(json)
    return (0 until array.length()).map { index ->
        val value = array.getJSONObject(index)
        ShoppingItem(
            id = value.getLong("id"),
            name = value.optString("name"),
            quantity = value.optString("quantity").takeIf { it.isNotBlank() && it != "null" },
            note = value.optString("note").takeIf { it.isNotBlank() && it != "null" },
            checked = value.optBoolean("checked"),
        )
    }
}

class MainActivity : ComponentActivity() {
    private lateinit var credentialStore: CredentialStore
    private var server by mutableStateOf("https://nextcloud.klihub.dynv6.net")
    private var status by mutableStateOf("Sign in to your Nextcloud server.")
    private var busy by mutableStateOf(false)
    private var account by mutableStateOf<AccountCredentials?>(null)
    private var lists by mutableStateOf<List<ShoppingList>>(emptyList())
    private var items by mutableStateOf<List<ShoppingItem>>(emptyList())
    private var selectedListId by mutableStateOf<Long?>(null)
    private var newItemName by mutableStateOf("")
    private var newListName by mutableStateOf("")
    private var confirmDeleteList by mutableStateOf(false)

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        credentialStore = CredentialStore(this)
        val saved = runCatching { credentialStore.load() }.getOrNull()
        if (saved != null) {
            server = saved.server
            account = saved
            status = "Checking saved account…"
            refreshData(saved)
        }

        setContent {
            MaterialTheme {
                Surface(modifier = Modifier.fillMaxSize()) {
                    Scaffold { innerPadding ->
                        Column(
                            modifier = Modifier
                                .fillMaxSize()
                                .padding(innerPadding)
                                .navigationBarsPadding()
                                .padding(horizontal = 20.dp, vertical = 16.dp),
                            verticalArrangement = Arrangement.spacedBy(12.dp),
                        ) {
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.SpaceBetween,
                            ) {
                                Text("Shopping List", style = MaterialTheme.typography.headlineMedium)
                                if (account != null) {
                                    TextButton(onClick = ::logout, enabled = !busy) {
                                        Text("Disconnect")
                                    }
                                }
                            }

                            if (account == null) {
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
                            } else {
                                Text(
                                    account?.loginName.orEmpty(),
                                    style = MaterialTheme.typography.bodyMedium,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                                )

                                if (lists.isNotEmpty()) {
                                    Text("Your lists", style = MaterialTheme.typography.titleMedium)
                                    LazyColumn(
                                        modifier = Modifier.fillMaxWidth(),
                                        verticalArrangement = Arrangement.spacedBy(4.dp),
                                    ) {
                                        items(lists, key = { it.id }) { list ->
                                            if (selectedListId == list.id) {
                                                Button(
                                                    onClick = { selectList(list.id) },
                                                    modifier = Modifier.fillMaxWidth(),
                                                ) { Text(list.name) }
                                            } else {
                                                TextButton(
                                                    onClick = { selectList(list.id) },
                                                    modifier = Modifier.fillMaxWidth(),
                                                ) { Text(list.name) }
                                            }
                                        }
                                    }
                                } else if (!busy) {
                                    Text("No shopping lists yet.")
                                }

                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                                    verticalAlignment = Alignment.CenterVertically,
                                ) {
                                    OutlinedTextField(
                                        value = newListName,
                                        onValueChange = { newListName = it },
                                        modifier = Modifier.weight(1f),
                                        label = { Text("New list") },
                                        singleLine = true,
                                        keyboardOptions = KeyboardOptions(imeAction = ImeAction.Done),
                                        keyboardActions = KeyboardActions(onDone = { createList() }),
                                        enabled = !busy,
                                    )
                                    Button(onClick = ::createList, enabled = !busy && newListName.isNotBlank()) {
                                        Text("Create")
                                    }
                                }

                                val selectedList = lists.firstOrNull { it.id == selectedListId }
                                if (selectedList != null) {
                                    Row(
                                        modifier = Modifier.fillMaxWidth(),
                                        verticalAlignment = Alignment.CenterVertically,
                                        horizontalArrangement = Arrangement.SpaceBetween,
                                    ) {
                                        Text(selectedList.name, style = MaterialTheme.typography.titleLarge)
                                        TextButton(
                                            onClick = { confirmDeleteList = true },
                                            enabled = !busy,
                                        ) { Text("Delete list") }
                                    }

                                    LazyColumn(
                                        modifier = Modifier.weight(1f).fillMaxWidth(),
                                        verticalArrangement = Arrangement.spacedBy(2.dp),
                                    ) {
                                        items(items, key = { it.id }) { item ->
                                            Row(
                                                modifier = Modifier.fillMaxWidth().padding(vertical = 3.dp),
                                                verticalAlignment = Alignment.CenterVertically,
                                            ) {
                                                Checkbox(
                                                    checked = item.checked,
                                                    onCheckedChange = { checked -> setItemChecked(item.id, checked) },
                                                    enabled = !busy,
                                                )
                                                Column(modifier = Modifier.weight(1f)) {
                                                    Text(
                                                        text = buildString {
                                                            append(item.name)
                                                            item.quantity?.let { append(" · ").append(it) }
                                                        },
                                                        style = MaterialTheme.typography.bodyLarge,
                                                        textDecoration = if (item.checked) TextDecoration.LineThrough else TextDecoration.None,
                                                        color = if (item.checked) MaterialTheme.colorScheme.onSurfaceVariant else MaterialTheme.colorScheme.onSurface,
                                                    )
                                                    item.note?.takeIf { it.isNotBlank() }?.let {
                                                        Text(it, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                                                    }
                                                }
                                                TextButton(
                                                    onClick = { deleteItem(item.id) },
                                                    enabled = !busy,
                                                ) { Text("Delete") }
                                            }
                                        }
                                        item(key = "new-item-row") {
                                            Row(
                                                modifier = Modifier.fillMaxWidth().padding(top = 8.dp, bottom = 8.dp),
                                                verticalAlignment = Alignment.CenterVertically,
                                                horizontalArrangement = Arrangement.spacedBy(8.dp),
                                            ) {
                                                OutlinedTextField(
                                                    value = newItemName,
                                                    onValueChange = { newItemName = it },
                                                    modifier = Modifier.weight(1f),
                                                    label = { Text("Add an item…") },
                                                    singleLine = true,
                                                    keyboardOptions = KeyboardOptions(imeAction = ImeAction.Done),
                                                    keyboardActions = KeyboardActions(onDone = { createItem() }),
                                                    enabled = !busy,
                                                )
                                                Button(
                                                    onClick = ::createItem,
                                                    enabled = !busy && newItemName.isNotBlank(),
                                                ) { Text("Add") }
                                            }
                                        }
                                    }
                                } else {
                                    Spacer(modifier = Modifier.weight(1f))
                                }
                            }

                            if (busy) {
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.Center,
                                    verticalAlignment = Alignment.CenterVertically,
                                ) {
                                    CircularProgressIndicator()
                                }
                            }
                            Text(status, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                        }
                    }
                }
            }

            if (confirmDeleteList) {
                AlertDialog(
                    onDismissRequest = { confirmDeleteList = false },
                    title = { Text("Delete this list?") },
                    text = { Text("The list and all its items will be deleted from Nextcloud.") },
                    confirmButton = {
                        TextButton(onClick = {
                            confirmDeleteList = false
                            deleteSelectedList()
                        }) { Text("Delete") }
                    },
                    dismissButton = {
                        TextButton(onClick = { confirmDeleteList = false }) { Text("Cancel") }
                    },
                )
            }
        }
    }

    private fun startLogin() {
        busy = true
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
                withContext(Dispatchers.IO) { credentialStore.save(credentials) }
                account = credentials
                status = "Connected. Loading your shopping lists…"
                refreshData(credentials)
            } catch (error: Exception) {
                status = error.message ?: "Sign-in failed."
                account = null
            } finally {
                busy = false
            }
        }
    }

    private fun refreshData(credentials: AccountCredentials) {
        lifecycleScope.launch {
            busy = true
            try {
                val data = withContext(Dispatchers.IO) {
                    val client = Mobilebridge.openClient(credentials.server, credentials.loginName, credentials.appPassword)
                    val fetchedLists = parseLists(client.listListsJSON())
                    val target = fetchedLists.firstOrNull { it.id == selectedListId } ?: fetchedLists.firstOrNull()
                    val fetchedItems = if (target == null) emptyList() else parseItems(client.listItemsJSON(target.id))
                    Pair(fetchedLists, Pair(target?.id, fetchedItems))
                }
                lists = data.first
                selectedListId = data.second.first
                items = data.second.second
                status = if (lists.isEmpty()) "Connected. Create your first shopping list." else "Nextcloud account connected successfully."
            } catch (error: Exception) {
                status = "Could not load shopping lists: ${error.message}"
                if (account == null) account = credentials
            } finally {
                busy = false
            }
        }
    }

    private fun selectList(listId: Long) {
        if (selectedListId == listId || busy) return
        selectedListId = listId
        newItemName = ""
        val credentials = account ?: return
        lifecycleScope.launch {
            busy = true
            try {
                items = withContext(Dispatchers.IO) {
                    val client = Mobilebridge.openClient(credentials.server, credentials.loginName, credentials.appPassword)
                    parseItems(client.listItemsJSON(listId))
                }
                status = "Showing ${lists.firstOrNull { it.id == listId }?.name.orEmpty()}."
            } catch (error: Exception) {
                status = "Could not load list: ${error.message}"
            } finally {
                busy = false
            }
        }
    }

    private fun createList() {
        val name = newListName.trim()
        val credentials = account ?: return
        if (name.isEmpty() || busy) return
        lifecycleScope.launch {
            busy = true
            try {
                val created = withContext(Dispatchers.IO) {
                    val client = Mobilebridge.openClient(credentials.server, credentials.loginName, credentials.appPassword)
                    JSONObject(client.createListJSON(name)).getLong("id")
                }
                newListName = ""
                selectedListId = created
                refreshData(credentials)
            } catch (error: Exception) {
                status = "Could not create list: ${error.message}"
            } finally {
                busy = false
            }
        }
    }

    private fun createItem() {
        val name = newItemName.trim()
        val credentials = account ?: return
        val listId = selectedListId ?: return
        if (name.isEmpty() || busy) return
        lifecycleScope.launch {
            busy = true
            try {
                withContext(Dispatchers.IO) {
                    val client = Mobilebridge.openClient(credentials.server, credentials.loginName, credentials.appPassword)
                    client.createItemJSON(listId, name)
                }
                newItemName = ""
                items = withContext(Dispatchers.IO) {
                    val client = Mobilebridge.openClient(credentials.server, credentials.loginName, credentials.appPassword)
                    parseItems(client.listItemsJSON(listId))
                }
                status = "Item added."
            } catch (error: Exception) {
                status = "Could not add item: ${error.message}"
            } finally {
                busy = false
            }
        }
    }

    private fun setItemChecked(itemId: Long, checked: Boolean) {
        val credentials = account ?: return
        val listId = selectedListId ?: return
        if (busy) return
        lifecycleScope.launch {
            busy = true
            try {
                withContext(Dispatchers.IO) {
                    val client = Mobilebridge.openClient(credentials.server, credentials.loginName, credentials.appPassword)
                    client.updateItemCheckedJSON(listId, itemId, checked)
                }
                items = withContext(Dispatchers.IO) {
                    val client = Mobilebridge.openClient(credentials.server, credentials.loginName, credentials.appPassword)
                    parseItems(client.listItemsJSON(listId))
                }
                status = if (checked) "Item checked." else "Item unchecked."
            } catch (error: Exception) {
                status = "Could not update item: ${error.message}"
            } finally {
                busy = false
            }
        }
    }

    private fun deleteItem(itemId: Long) {
        val credentials = account ?: return
        val listId = selectedListId ?: return
        if (busy) return
        lifecycleScope.launch {
            busy = true
            try {
                withContext(Dispatchers.IO) {
                    val client = Mobilebridge.openClient(credentials.server, credentials.loginName, credentials.appPassword)
                    client.deleteItem(listId, itemId)
                }
                items = items.filterNot { it.id == itemId }
                status = "Item deleted."
            } catch (error: Exception) {
                status = "Could not delete item: ${error.message}"
            } finally {
                busy = false
            }
        }
    }

    private fun deleteSelectedList() {
        val credentials = account ?: return
        val listId = selectedListId ?: return
        if (busy) return
        lifecycleScope.launch {
            busy = true
            try {
                withContext(Dispatchers.IO) {
                    val client = Mobilebridge.openClient(credentials.server, credentials.loginName, credentials.appPassword)
                    client.deleteList(listId)
                }
                selectedListId = null
                items = emptyList()
                status = "List deleted."
                refreshData(credentials)
            } catch (error: Exception) {
                status = "Could not delete list: ${error.message}"
            } finally {
                busy = false
            }
        }
    }

    private fun logout() {
        credentialStore.clear()
        account = null
        lists = emptyList()
        items = emptyList()
        selectedListId = null
        newItemName = ""
        newListName = ""
        status = "Disconnected. Your Nextcloud app password was removed from this device."
    }
}
