# Backend: Laravel API

Laravel 13 on PHP 8.4, serving a JSON REST API under `/api`. In Docker it runs on Apache and talks to the `postgres` service. See the [root README](../../README.md) for the full API reference and data model.

## Layout

| Path | What's there |
|---|---|
| `routes/api.php` | `apiResource` routes for categories, products and orders |
| `app/Http/Controllers/Api/` | `ProductController`, `CategoryController`, `OrderController` |
| `app/Http/Controllers/Concerns/ListsResources.php` | Shared list handling: `page`, `per_page`, `q`, `sort` (whitelisted fields, `-` for descending) |
| `app/Http/Resources/` | JSON shapes for each model |
| `app/Support/ApiError.php` | Turns every exception under `/api` into `{ "error": { status, code, message, details? } }`; wired up in `bootstrap/app.php` |
| `app/Models/` | `Category`, `Product`, `Order` (with a `withTotal()` scope), `OrderItem`, `User` |
| `database/migrations/` | Schema, including FKs, indexes and `CHECK` constraints on Postgres |
| `database/seeders/` | Idempotent demo data: categories, products, test user, orders |
| `tests/Feature/` | API tests (products, categories, orders, error shapes) |
| `docker-entrypoint.sh` | Container startup: APP_KEY fallback, `migrate`, optional `db:seed`, config/route cache |

## Running in Docker

From the repo root, `./setup.sh` or `docker compose up`. The container takes all configuration from environment variables set in `docker-compose.yml`, so no `.env` file is needed inside the image. On each start it:

1. generates a temporary `APP_KEY` if none was provided,
2. runs `php artisan migrate --force`,
3. runs `php artisan db:seed --force` when `SEED_DEMO_DATA=true`,
4. caches config and routes, then starts Apache.

The image installs the `pdo_pgsql` and `intl` PHP extensions. After changing PHP code, rebuild it: `docker compose up -d --build backend`.

Useful commands:

```bash
docker compose exec backend php artisan migrate:status
docker compose exec backend php artisan db:show
docker compose exec backend php artisan route:list --path=api
```

## Running locally (without Docker for the API)

Needs PHP 8.3+ (with `pdo_pgsql`) and Composer. Start only the database with `docker compose up -d postgres`, then:

```bash
cd src/backend
composer install
cp .env.example .env
php artisan key:generate
```

Set the database in `.env` to match the container (defaults from the root `.env.example`):

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=coe
DB_USERNAME=coe
DB_PASSWORD=secret
```

```bash
php artisan migrate --seed
php artisan serve --port=8000
```

## Tests

```bash
php artisan test
```

`phpunit.xml` points the tests at in-memory SQLite, so they don't touch Postgres. The Postgres-only `CHECK` constraints are skipped there; everything else is the same.

## Adding a list endpoint

Use the `ListsResources` trait so the new endpoint behaves like the others:

```php
$filters = $request->validate([...$this->listRules(), 'status' => ['sometimes', 'string']]);
$query = Thing::query()->when($filters['q'] ?? null, fn ($q, $term) => $q->whereLike('name', "%{$term}%"));
$this->applySort($query, $request, ['name' => 'name', 'created_at' => 'created_at'], '-created_at');

return ThingResource::collection($query->paginate($this->perPage($request))->withQueryString());
```

Errors need no extra work: validation failures, `findOrFail`/route-model-binding misses and `abort(409, '...')` all come out in the standard error shape.
