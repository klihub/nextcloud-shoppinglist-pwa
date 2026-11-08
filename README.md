# Shopping List for Nextcloud

A collaborative shopping-list application for Nextcloud, starting with an installable Progressive Web App (PWA).

## Project goals

- Use Nextcloud accounts for authentication and authorization.
- Let users create multiple shopping lists and collaborate on shared lists.
- Store data in Nextcloud's database and expose it through a reusable REST API.
- Deliver the first client as a responsive, installable PWA.
- Keep the API client-independent so a native Android client can be added later.

## Initial scope

- Create, rename, and delete lists.
- Add, edit, delete, and check off items. New items are entered in the final row of the item list; selecting an existing item opens its editor.
- Item quantities and optional notes.
- Basic item categories.
- Share lists with other Nextcloud users.
- Owner and member permissions.
- Responsive mobile-first interface and PWA installation support.

Offline editing, recurring items, and a native Android client are outside the initial scope.

## Architecture

This repository contains two parts:

1. **Nextcloud app** — PHP app using the Nextcloud App Framework, REST routes, database abstraction, and Nextcloud sharing/user services.
2. **PWA** — React and TypeScript client communicating with the app's REST API.

The Nextcloud app owns authorization and data access. The browser client must never be trusted to enforce list permissions.

## Development and build

The frontend is built with:

```sh
npm install
npm run build
```

GitHub Actions runs the frontend build and PHP syntax checks on every push to `main`. The packaging job combines the compiled JavaScript and stylesheet with the Nextcloud app files and publishes a `shoppinglist-nextcloud-app` artifact containing `shoppinglist-app.zip`.

The compiled frontend assets are placed in the app's `js/` and `css/` directories, respectively.

## Requirements

- Nextcloud 35 or newer (initial target).
- PHP version supported by the targeted Nextcloud release.
- Node.js LTS for PWA development.

## License

GNU Affero General Public License v3.0 (AGPL-3.0).
