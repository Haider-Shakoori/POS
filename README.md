# Afghanistan Supermarket POS

A modern Laravel POS, inventory, purchasing, cash-management and daily-closing application designed specifically for supermarkets and retail shops in Afghanistan.

## Product rules

- **Currency:** AFN only
- **Sales tax / VAT / GST:** intentionally not implemented
- **Languages:** English, Dari, Pashto
- **Direction:** English LTR; Dari and Pashto RTL
- **Backend:** Laravel 13 / PHP 8.3+
- **Database:** MySQL 8+
- **UI:** custom Blade + Tailwind CSS 4 + Alpine.js
- **Timezone:** Asia/Kabul

## Batch 1 setup

1. Clone the repository.
2. Run `composer install`.
3. Copy `.env.example` to `.env`.
4. Configure the MySQL database.
5. Run `php artisan key:generate`.
6. Run `php artisan migrate --seed`.
7. Create the first owner with `php artisan pos:create-owner`.
8. Run `npm install`.
9. Run `npm run build` or `npm run dev`.
10. Start the application with your preferred Laravel local server.

## Development

See `docs/ARCHITECTURE.md` and `docs/BATCH_ROADMAP.md`.

The POS is built in controlled batches. Financial correctness, inventory correctness, auditability, and checkout reliability take priority over visual extras.
