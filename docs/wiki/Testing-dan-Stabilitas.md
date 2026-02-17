# Testing dan Stabilitas

## Prinsip environment
- Gunakan database testing terpisah (`notobuku_test`).
- Hindari command destruktif di DB non-testing.

## Command penting
- Full suite stabil:
```bash
composer run test:stable
```
- Smoke sirkulasi stabil:
```bash
composer run test:circulation:stable
```
- Contoh regresi modul klasik:
```bash
php artisan test tests/Feature/StockTakeWorkflowTest.php --env=testing
php artisan test tests/Feature/CopyCatalogingWorkflowTest.php --env=testing
```

## Ops pack (contoh command)
- `php artisan notobuku:search-zero-triage`
- `php artisan notobuku:opac-slo-alert`
- `php artisan notobuku:catalog-scale-proof --samples=60`
- `php artisan notobuku:readiness-certificate --strict-ready`
- `php artisan notobuku:uat-auto-signoff --strict-ready --window-days=30`
