package com.klihub.shoppinglist

import org.json.JSONObject
import java.io.IOException
import java.net.HttpURLConnection
import java.net.URL
import java.net.URLEncoder

data class LoginFlowStart(
    val loginUrl: String,
    val pollEndpoint: String,
    val pollToken: String,
)

class LoginFlowV2 {
    fun begin(serverInput: String): LoginFlowStart {
        val server = normalizeServer(serverInput)
        val response = request(
            url = "$server/index.php/login/v2",
            method = "POST",
        )
        if (response.code != HttpURLConnection.HTTP_OK) {
            throw IOException("Could not start Nextcloud login (HTTP ${response.code})")
        }
        val json = JSONObject(response.body)
        val poll = json.getJSONObject("poll")
        val loginUrl = json.getString("login")
        val pollEndpoint = poll.getString("endpoint")
        val token = poll.getString("token")
        requireSameOrigin(server, loginUrl)
        requireSameOrigin(server, pollEndpoint)
        return LoginFlowStart(loginUrl, pollEndpoint, token)
    }

    fun pollUntilAuthorized(
        flow: LoginFlowStart,
        onWaiting: () -> Unit = {},
    ): AccountCredentials {
        val deadline = System.nanoTime() + LOGIN_TIMEOUT_NANOS
        while (System.nanoTime() < deadline) {
            val body = "token=" + URLEncoder.encode(flow.pollToken, Charsets.UTF_8.name())
            val response = request(
                url = flow.pollEndpoint,
                method = "POST",
                body = body,
            )
            when (response.code) {
                HttpURLConnection.HTTP_OK -> {
                    val json = JSONObject(response.body)
                    val server = normalizeServer(json.getString("server"))
                    requireSameOrigin(server, flow.pollEndpoint)
                    return AccountCredentials(
                        server = server,
                        loginName = json.getString("loginName"),
                        appPassword = json.getString("appPassword"),
                    )
                }
                HttpURLConnection.HTTP_NOT_FOUND -> {
                    onWaiting()
                    Thread.sleep(POLL_INTERVAL_MS)
                }
                else -> throw IOException("Nextcloud login polling failed (HTTP ${response.code})")
            }
        }
        throw IOException("Nextcloud login expired; please try again")
    }

    private fun request(url: String, method: String, body: String? = null): HttpResponse {
        val connection = URL(url).openConnection() as HttpURLConnection
        try {
            connection.requestMethod = method
            connection.connectTimeout = CONNECT_TIMEOUT_MS
            connection.readTimeout = READ_TIMEOUT_MS
            connection.setRequestProperty("Accept", "application/json")
            connection.setRequestProperty("OCS-APIRequest", "true")
            if (body != null) {
                connection.doOutput = true
                connection.setRequestProperty("Content-Type", "application/x-www-form-urlencoded; charset=UTF-8")
                connection.outputStream.use { it.write(body.toByteArray(Charsets.UTF_8)) }
            }
            val code = connection.responseCode
            val stream = if (code in 200..299) connection.inputStream else connection.errorStream
            val responseBody = stream?.bufferedReader(Charsets.UTF_8)?.use { it.readText() }.orEmpty()
            return HttpResponse(code, responseBody)
        } finally {
            connection.disconnect()
        }
    }

    private fun normalizeServer(input: String): String {
        val trimmed = input.trim().trimEnd('/')
        val url = URL(trimmed)
        require(url.protocol == "https") { "Use your HTTPS Nextcloud address" }
        require(url.host.isNotBlank()) { "Enter a valid Nextcloud server address" }
        require(url.userInfo == null && url.query == null && url.ref == null) {
            "Enter the server address only, without credentials, query, or fragment"
        }
        return trimmed
    }

    private fun requireSameOrigin(server: String, endpoint: String) {
        val base = URL(server)
        val target = URL(endpoint)
        require(target.protocol == "https" && target.host.equals(base.host, ignoreCase = true) &&
            target.port == base.port) {
            "Nextcloud returned a login URL on a different server"
        }
    }

    private data class HttpResponse(val code: Int, val body: String)

    private companion object {
        const val CONNECT_TIMEOUT_MS = 15_000
        const val READ_TIMEOUT_MS = 20_000
        const val POLL_INTERVAL_MS = 1_000L
        val LOGIN_TIMEOUT_NANOS = 20L * 60L * 1_000_000_000L
    }
}
