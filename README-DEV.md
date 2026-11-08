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

The app ID is `shoppinglist`, with PHP namespace
`OCA\\ShoppingList`. Install the app in a Nextcloud 35+ development
instance and enable it after the backend and asset integration are completed.

The list API route is deliberately a 501 placeholder. It must not be treated
as a working endpoint until list persistence, membership checks, and
authorization are implemented.

## Design constraints

- Nextcloud is the identity provider and authorization boundary.
- List membership and permissions are enforced by the server on every request.
- The PWA is a client of a reusable API; a future Android client must not need
  a separate backend or a different permission model.
- Do not trust client-supplied owner IDs or membership claims.
