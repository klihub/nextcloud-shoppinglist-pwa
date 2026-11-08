# Shopping List API contract

This document describes the API implemented by the Nextcloud app in this repository. It is the contract shared by the PWA and the native Android client.

## Base URL and authentication

The API base path is:

```
/index.php/apps/shoppinglist/api
```

Append it to the Nextcloud installation URL (including any installation subpath). The PWA currently obtains its URL through `OC.generateUrl`; the native client uses the canonical `index.php` route.

Native clients must use a per-device Nextcloud app password, not the user's account password. The Android sign-in flow should use Nextcloud Login Flow v2 (`POST /index.php/login/v2`, open the returned `login` URL in the browser, then poll the returned `poll.endpoint` with its token). The successful poll returns `server`, `loginName`, and `appPassword`. Store the app password using Android's secure credential storage and provide it to the Go client. Do not log credentials or include them in URLs.

The Go client authenticates API requests with HTTP Basic authentication using `loginName:appPassword`. This is the standard Nextcloud app-password authentication mechanism; confirm it against the target server during integration testing. The current PWA instead uses the logged-in browser session and sends the `requesttoken` CSRF header.

All API request and response bodies are JSON-compatible except request bodies, which use `application/x-www-form-urlencoded` fields. Responses are JSON. Successful deletes return HTTP 204 with no body.

## Data shapes

A list contains `id` (integer), `name`, `owner`, `role` (`owner` or `member`), `created_at`, and `updated_at`.

An item contains `id`, `list_id`, `name`, nullable `quantity`, `note`, and `category`, `checked` (boolean; clients should tolerate 0/1), `sort_order`, `created_by`, `created_at`, and `updated_at`.

A member contains `user_id`, `display_name`, `role`, and `created_at`.

## Endpoints

| Method | Path | Request fields | Response |
|---|---|---|---|
| GET | `/lists` | — | `{ "lists": [...] }` |
| POST | `/lists` | `name` | `{ "list": {...} }` (201) |
| GET | `/lists/{id}` | — | `{ "list": {...} }` |
| DELETE | `/lists/{id}` | — | 204 |
| GET | `/lists/{id}/members` | — | `{ "members": [...] }` |
| POST | `/lists/{id}/members` | `userId` | `{ "member": {...} }` (201) |
| DELETE | `/lists/{id}/members/{userId}` | — | 204 |
| GET | `/lists/{id}/items` | — | `{ "items": [...] }` |
| POST | `/lists/{id}/items` | `name`; optional `quantity`, `note`, `category`, `sort_order` | `{ "item": {...} }` (201) |
| PUT | `/lists/{id}/items/{itemId}` | Any supplied fields: `name`, `quantity`, `note`, `category`, `checked`, `sort_order` | `{ "item": {...} }` |
| DELETE | `/lists/{id}/items/{itemId}` | — | 204 |

## Authorization and errors

The server identifies the current user from Nextcloud authentication. It checks list membership for list and item access. Only the owner may delete a list or manage members; all members may manage items. Clients must treat the server as authoritative.

Expected status codes include 400 for invalid input, 403 for forbidden owner-only operations, 404 for inaccessible or missing lists/items, and 409 for duplicate membership. Error responses normally contain `{ "error": "..." }`.

## Android implementation notes

- Keep Login Flow v2 and Android secure credential storage in the Kotlin/platform layer.
- Keep HTTP API calls, JSON models, and business rules in the Go package under `go-client/`.
- Do not assume item ordering is stable beyond the server's returned order (unchecked first, then `sort_order`, then ID).
- Offline writes and conflict resolution are not part of the current API contract.
