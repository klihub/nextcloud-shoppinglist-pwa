# Development

## Frontend

Requirements: Node.js 20+ and npm.

```sh
npm install
npm run build
```

The build emits `js/main.js` and `js/main.css` directly into the Nextcloud
app directory. These are loaded by `Util::addScript()` and
`Util::addStyle()`. For local frontend development, `npm run dev` starts
Vite, but the app's authenticated API is normally exercised from a Nextcloud
instance.

The responsive interface supports list creation and selection, item creation,
editing, checking and deletion, and owner-managed sharing. It uses the
existing Nextcloud browser session and CSRF token; there is no separate
login.

The app includes a web app manifest and a service worker that caches only
static app-shell assets. It deliberately does not cache authenticated Nextcloud
pages, API responses, or shopping-list data. Full offline list access and
queued offline edits are not implemented yet.

## Build and package

Every push to `main` runs the frontend production build and PHP syntax
checks. A successful run also publishes a downloadable
`shoppinglist-nextcloud-app` artifact containing a ready-to-install app ZIP.
The artifact is available from the run's GitHub Actions page.

## Nextcloud app

The app ID is `shoppinglist`, with PHP namespace `OCA\\ShoppingList`.
Minimum supported Nextcloud version: 35.

Install the app in a Nextcloud 35+ development instance. Nextcloud runs the
migration automatically when the app is enabled or upgraded.

## Data model

- `shoppinglist_lists`: list name and owner.
- `shoppinglist_members`: per-user membership and role; the owner is also
  represented as a member with role `owner`.
- `shoppinglist_items`: item name, quantity, note, category, checked state,
  ordering, creator, and timestamps.

## API

All routes are under the Nextcloud app route namespace. Requests use the
logged-in Nextcloud session and normal Nextcloud CSRF protection.

### Lists

- `GET /apps/shoppinglist/api/lists` — lists visible to the current user.
- `POST /apps/shoppinglist/api/lists` — create a list; requires `name`.
- `GET /apps/shoppinglist/api/lists/{id}` — retrieve one accessible list.
- `DELETE /apps/shoppinglist/api/lists/{id}` — delete; owner only.

### Sharing

- `GET /apps/shoppinglist/api/lists/{id}/members` — list members; any member.
- `POST /apps/shoppinglist/api/lists/{id}/members` — add an existing Nextcloud
  user; requires `userId`; owner only.
- `DELETE /apps/shoppinglist/api/lists/{id}/members/{userId}` — remove a
  member; owner only. The owner cannot be removed.

### Items

- `GET /apps/shoppinglist/api/lists/{id}/items` — list items; any member.
- `POST /apps/shoppinglist/api/lists/{id}/items` — create; requires `name`.
  Optional fields: `quantity`, `note`, `category`, `sort_order`.
- `PUT /apps/shoppinglist/api/lists/{id}/items/{itemId}` — update any supplied
  fields among `name`, `quantity`, `note`, `category`, `checked`, and
  `sort_order`. Send boolean values for `checked`.
- `DELETE /apps/shoppinglist/api/lists/{id}/items/{itemId}` — delete.

## Security design

- Nextcloud is the identity provider and authorization boundary.
- The server derives the current user from `IUserSession`; clients cannot
  choose an owner or assert membership.
- Every list and item operation checks access to the parent list.
- Only the owner can add/remove members or delete a list.
- All members can collaboratively add, edit, check, and delete items.
- Item IDs are always scoped to their list, preventing cross-list access.
- The frontend is a client of a reusable API; a future Android client can use
  the same API and permission model.

## Current validation status

The frontend production build and PHP syntax checks pass in GitHub Actions.
The app has not yet been installed or exercised against the live Nextcloud 35
instance. Database migration and API runtime validation are still required
before treating it as production-ready.
