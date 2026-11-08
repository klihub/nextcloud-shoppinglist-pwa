# Development

## Frontend

Requirements: Node.js 20+ and npm.

```sh
npm install
npm run dev
npm run build
```

The current Vite build is a standalone development build in `dist/`.
The Nextcloud app integration will add a production asset-copy/build step;
do not copy the development output into a live Nextcloud installation yet.

## Nextcloud app

The app ID is `shoppinglist`, with PHP namespace `OCA\\ShoppingList`.
Minimum supported Nextcloud version: 35.

Install the app in a Nextcloud 35+ development instance. Nextcloud runs the
migration automatically when the app is enabled or upgraded.

## Data model

- `shoppinglist_lists`: list name and owner.
- `shoppinglist_members`: per-user membership and role; the owner is also
  represented as a member with role `owner`.
- `shoppinglist_items`: list items, including quantity, note, category,
  checked state, and ordering.

The API currently supports listing, creating, reading, and deleting lists.
Every read is scoped to the authenticated Nextcloud user. Deletion is owner-only.
Membership management and item operations are not exposed yet.

## API

All routes are under the Nextcloud app route namespace:

- `GET /apps/shoppinglist/api/lists`
- `POST /apps/shoppinglist/api/lists` with form field `name`
- `GET /apps/shoppinglist/api/lists/{id}`
- `DELETE /apps/shoppinglist/api/lists/{id}`

Requests use the logged-in Nextcloud session and normal Nextcloud CSRF
protection. Do not disable CSRF protection for browser requests.

## Security design

- Nextcloud is the identity provider and authorization boundary.
- The server derives the current user from `IUserSession`; clients cannot
  choose an owner or assert membership.
- List membership is checked on every read.
- Only the owner can delete a list.
- Membership changes and item operations must enforce permissions server-side.
- The PWA is a client of a reusable API; a future Android client must use the
  same API and permission model.
