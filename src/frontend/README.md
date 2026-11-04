# Frontend: Nuxt 4 + Nuxt UI

Nuxt 4 app using Nuxt UI 4 (Tailwind CSS 4) and Lucide icons. It's the back-office UI for the [Laravel API](../backend/README.md); see the [root README](../../README.md) for the API reference.

## Pages

| Route | File | What it does |
|---|---|---|
| `/` | `app/pages/index.vue` | Landing page |
| `/products` | `app/pages/products.vue` | Product list with search, category/status/stock filters, sorting and pagination; create, edit and delete in modals |

## Layout and components

| File | Role |
|---|---|
| `app/app.vue` | `UApp` (needed for toasts, modals and tooltips) → `NuxtLayout` → `NuxtPage` |
| `app/layouts/default.vue` | Wraps every page in `Main` |
| `app/components/Main.vue` | Page frame: sidebar, top bar with the sidebar toggle, page content, footer |
| `app/components/Sidebar.vue` | Navigation (`USidebar` + `UNavigationMenu`); add pages to its `items` |
| `app/components/Splitter.vue` | Reusable resizable two-pane layout (`#sidebar` slot + default slot) |
| `app/components/AppFooter.vue`, `AppAlert.vue` | Footer and a simple alert box |
| `app/assets/css/main.css` | Imports Tailwind and Nuxt UI |

## Talking to the API

The API address comes from runtime config:

```ts
const { public: { apiBase } } = useRuntimeConfig()   // e.g. http://localhost:8000/api
```

It defaults to `http://localhost:8000/api` (`nuxt.config.ts`) and can be overridden at runtime with `NUXT_PUBLIC_API_BASE`. `docker-compose.yml` sets it from `BACKEND_PORT`.

The address is used by the **browser**, so it must be the backend as seen from your machine, not from inside Docker. For the same reason, data fetches use `useFetch(..., { server: false })`: during server-side rendering inside the container, `localhost:8000` would not reach the backend.

API responses the page relies on:

- Lists: `{ data: T[], meta: { current_page, last_page, per_page, total } }`
- Errors: `{ error: { status, code, message, details? } }`. For 422s, `details` maps field names to messages and the form shows them under the matching fields.

## Running in Docker

From the repo root: `./setup.sh` or `docker compose up`. The image does a production build (`npm ci && npm run build`) and serves `.output/server/index.mjs` on port 3000. After changing frontend code: `docker compose up -d --build frontend`.

## Running locally

Needs Node 22. With the API running (e.g. `docker compose up -d backend`):

```bash
cd src/frontend
npm install
npm run dev          # http://localhost:3000 with hot reload
```

Other scripts: `npm run build` (production build), `npm run start` (preview the build).
