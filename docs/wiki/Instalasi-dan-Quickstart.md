# Instalasi dan Quickstart

## Instalasi manual (lokal)
1. `composer install`
2. `cp .env.example .env`
3. Atur koneksi database di `.env`
4. `php artisan key:generate`
5. `php artisan migrate`
6. Opsional: `php artisan db:seed`

## Quickstart Docker
1. Jalankan service:
```bash
docker compose up -d
```
2. Akses:
- App: `http://localhost:8000`
- phpMyAdmin: `http://localhost:8080`
- Meilisearch: `http://localhost:7700`
3. Masuk container app:
```bash
docker compose exec app bash
```
4. Jalankan migrasi/seed manual:
```bash
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed
```

## Shortcut Makefile
- `make up`
- `make down`
- `make bash`
- `make migrate`
- `make test`
