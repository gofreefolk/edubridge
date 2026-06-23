# EduBridge

Official, structured school communication for Kerala schools — a mobile-first PWA with Malayalam-first UI.

> One trusted place for every school message — simple enough for grandparents, official enough for SMC.

## Stack

| Layer | Technology |
|-------|------------|
| Backend | Laravel 13 |
| Frontend | Vue 3 + Vite |
| PWA | vite-plugin-pwa (offline shell + service worker) |
| i18n | vue-i18n (Malayalam default) + Laravel JSON locales |
| Styling | Tailwind CSS |

## Requirements

- PHP 8.3+
- Composer
- Node.js 18+
- MySQL (production) or SQLite (local default)

## Quick start

```bash
# Install PHP dependencies
composer install

# Copy environment and generate key (if needed)
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate

# Install frontend dependencies and build
npm install
npm run dev
```

In another terminal:

```bash
php artisan serve
```

Open [http://localhost:8000](http://localhost:8000). The home screen loads in **Malayalam** by default; tap the language button to switch to English.

For full local development (server, queue, logs, Vite):

```bash
composer dev
```

## Project structure

```
app/                    Laravel backend
resources/js/
  components/           Shared Vue components
  i18n/locales/         Frontend translations (ml, en)
  pages/                Route-level Vue pages
  router/               Vue Router
lang/                   Laravel JSON translations (ml, en)
routes/web.php          SPA catch-all → app.blade.php
```

## i18n

- **Default locale:** Malayalam (`ml`)
- **Fallback:** English (`en`)
- Frontend strings: `resources/js/i18n/locales/{ml,en}.json`
- Backend strings: `lang/{ml,en}.json`
- User preference is persisted in `localStorage` (`edubridge.locale`)

## PWA

The app registers a service worker via `vite-plugin-pwa` for installability and offline caching of static assets. Run `npm run build` for production PWA output in `public/build`.

## License

MIT
