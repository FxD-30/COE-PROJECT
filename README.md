# Test Mart

A small store back office: a **Laravel 13** REST API on **PostgreSQL 17**, and a **Nuxt 4 + Nuxt UI** frontend for managing products. Everything runs in Docker.

| Component | Path | Port | Notes |
|---|---|---|---|
| Frontend | [`src/frontend`](src/frontend/README.md) | 3000 | Nuxt 4, Nuxt UI 4, Tailwind 4 |
| Backend (API) | [`src/backend`](src/backend/README.md) | 8000 | Laravel 13, PHP 8.4, Apache |
| Database | (Docker image) | 5432 | PostgreSQL 17 |

## Quick start

Requirements: [Docker](https://docs.docker.com/get-docker/) with the Compose plugin. Nothing else (no PHP, Node or Postgres on your machine).

```bash
git clone <repo-url> coe-project
cd coe-project
./setup.sh
```

`setup.sh` checks that Docker is running, creates `.env` from [`.env.example`](.env.example) with a generated `APP_KEY`, checks that the ports are free, builds and starts the containers, and waits until the API and frontend respond. Then open:

- Frontend: http://localhost:3000 (products page: http://localhost:3000/products)
- API: http://localhost:8000/api/products

### Without the script

```bash
docker compose up        # or: docker-compose up
```

This also works on a fresh clone: every setting has a default in `docker-compose.yml`. Without a `.env` the backend generates a temporary `APP_KEY` on each start, which is fine for this API (it doesn't encrypt stored data); `setup.sh` makes it permanent.

On every start the backend runs migrations and seeds demo data (4 categories, 5 products, a test user `test@example.com` / `password`, 3 orders). Seeding is idempotent, so restarting never duplicates rows. Set `SEED_DEMO_DATA=false` in `.env` to turn it off.

### Everyday commands

```bash
docker compose logs -f backend     # follow API logs
docker compose down                # stop (data is kept in the postgres_data volume)
./setup.sh --reset                 # wipe the database volume and start fresh
docker compose exec backend php artisan tinker
docker compose exec postgres psql -U coe -d coe
```

### Configuration

All settings live in the root `.env` (see [`.env.example`](.env.example)); every one has a default.

| Variable | Default | Purpose |
|---|---|---|
| `FRONTEND_PORT` / `BACKEND_PORT` / `POSTGRES_PORT` | 3000 / 8000 / 5432 | Host ports. The frontend's API URL follows `BACKEND_PORT`. |
| `POSTGRES_DB` / `POSTGRES_USER` / `POSTGRES_PASSWORD` | coe / coe / secret | Database credentials, shared by the `postgres` and `backend` containers. Postgres only applies them when the volume is first created; after changing them run `./setup.sh --reset`. |
| `APP_KEY` | generated | Laravel encryption key. |
| `SEED_DEMO_DATA` | true | Seed demo data on startup. |

### Troubleshooting

- **"Port 5432 is already in use"**: a local PostgreSQL is probably running. Stop it (`sudo systemctl stop postgresql`) or set `POSTGRES_PORT=5433` in `.env`. The same applies to 3000 and 8000.
- **The frontend shows "Unable to load products"**: check the API with `curl http://localhost:8000/api/products` and `docker compose logs backend`.
- **Changed DB credentials and the backend can't connect**: the old volume still has the old credentials. Run `./setup.sh --reset` (deletes all data).

## Architecture

```
Browser ──▶ frontend :3000 (Nuxt SSR shell; data is fetched in the browser)
   │
   └──────▶ backend :8000 /api/* (Laravel, JSON) ──▶ postgres :5432
```

The browser calls the API directly at `http://localhost:${BACKEND_PORT}/api` (CORS allows any origin). Product data is fetched client-side, so the frontend container never needs to reach the backend.

## Data model

```
categories 1──* products 1──* order_items *──1 orders *──1 users
```

| Table | Key columns | Constraints and indexes |
|---|---|---|
| `categories` | name, slug, description | `slug` unique; index on `name` |
| `products` | category_id, name, sku, description, price, **stock**, image, is_active | `sku` unique; FK `category_id` → categories (`ON DELETE SET NULL`); indexes on `category_id`, `name`, `price`, `(is_active, created_at)`; `CHECK (stock >= 0)` and `CHECK (price >= 0)` |
| `orders` | order_number, user_id, status, notes | `order_number` unique; FK `user_id` → users (`ON DELETE RESTRICT`); indexes on `(user_id, created_at)` and `(status, created_at)` |
| `order_items` | order_id, product_id, quantity, unit_price | FK `order_id` → orders (`ON DELETE CASCADE`); FK `product_id` → products (`ON DELETE RESTRICT`); unique `(order_id, product_id)`; index on `product_id` |

Design notes:

- `order_items.unit_price` copies the product price at the time of ordering, so later price changes don't rewrite history.
- Order totals are not stored; the API computes `SUM(quantity * unit_price)`, so they can't drift out of sync with their items.
- Placing an order locks the product rows and decrements `stock` in the same transaction, so concurrent orders can't oversell. Cancelling an order returns its stock.
- A product that is on an order can't be deleted (deactivate it with `is_active: false`). Deleting a category leaves its products uncategorised.

## API

Base URL: `http://localhost:8000/api`. Requests and responses are JSON.

| Resource | Endpoints |
|---|---|
| Products | `GET /products`, `POST /products`, `GET /products/{id}`, `PUT/PATCH /products/{id}`, `DELETE /products/{id}` |
| Categories | `GET /categories`, `POST /categories`, `GET /categories/{id}`, `PUT/PATCH /categories/{id}`, `DELETE /categories/{id}` |
| Orders | `GET /orders`, `POST /orders`, `GET /orders/{id}`, `PUT/PATCH /orders/{id}` (status and notes; no delete) |

### List endpoints

Every `GET` collection supports:

| Parameter | Example | Notes |
|---|---|---|
| `page`, `per_page` | `?page=2&per_page=25` | `per_page` 1–100, default 10 |
| `q` | `?q=apple` | Case-insensitive search |
| `sort` | `?sort=-price,name` | Comma-separated; `-` prefix = descending. Unknown fields return 422. |

| Endpoint | Search (`q`) | Filters | Sort fields |
|---|---|---|---|
| `/products` | name, SKU, description | `category_id`, `is_active`, `in_stock` (true/false), `min_price`, `max_price` | `name`, `sku`, `price`, `stock`, `created_at` (default `-created_at`) |
| `/categories` | name, slug | | `name`, `created_at`, `products_count` (default `name`) |
| `/orders` | order number | `status`, `user_id`, `from`, `to` (dates) | `created_at`, `total`, `status` (default `-created_at`) |

Lists return `data`, `links` and `meta` (`current_page`, `last_page`, `per_page`, `total`, …):

```bash
curl 'http://localhost:8000/api/products?q=apple&in_stock=true&sort=-price&per_page=2'
```

```json
{
  "data": [
    { "id": 1, "category_id": 1, "category": { "id": 1, "name": "Phones", "slug": "phones" },
      "name": "iPhone 16 Pro", "sku": "IP16-PRO", "price": "4999.00", "stock": 19, "is_active": true, "...": "..." }
  ],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": { "current_page": 1, "last_page": 2, "per_page": 2, "total": 4 }
}
```

### Errors

Every error under `/api` has the same shape, with no stack traces:

```json
{ "error": { "status": 422, "code": "validation_failed", "message": "The given data was invalid.",
             "details": { "sku": ["The sku has already been taken."] } } }
```

| Status | `code` | When |
|---|---|---|
| 404 | `not_found` | Unknown ID (`"Product 42 not found."`) or route |
| 405 | `method_not_allowed` | e.g. `DELETE /orders/1` |
| 409 | `conflict` | Deleting a product that is on an order; changing a completed or cancelled order |
| 422 | `validation_failed` | Invalid body or query string; `details` maps each field to its messages |
| 500 | `server_error` | Anything unexpected (the real error is logged, not returned) |

### Examples

```bash
# Create a product
curl -X POST http://localhost:8000/api/products -H 'Content-Type: application/json' \
  -d '{"name":"Pixel 10","sku":"PX10","price":3299,"stock":4,"category_id":1}'

# Place an order (prices are copied from the products; stock is reserved)
curl -X POST http://localhost:8000/api/orders -H 'Content-Type: application/json' \
  -d '{"user_id":1,"items":[{"product_id":2,"quantity":1}]}'

# Cancel it (returns the stock)
curl -X PATCH http://localhost:8000/api/orders/4 -H 'Content-Type: application/json' -d '{"status":"cancelled"}'
```

## Tests

```bash
cd src/backend && composer install && php artisan test   # needs PHP 8.3+ with pdo_sqlite locally
```

The feature tests cover product CRUD, pagination, search, filters, sorting, the error shape, order stock handling and the delete rules. They run against in-memory SQLite.
