# Импорт товаров из xlsx — Slim + Doctrine + Angular

Тестовое задание: асинхронный импорт товаров из `.xlsx` в PostgreSQL (через Symfony Messenger + RabbitMQ),
список товаров с серверной пагинацией и фильтрами, карточка товара с атрибутами и скачанными изображениями.

| Слой | Технологии |
|------|-----------|
| Backend | PHP 8.4, Slim 4, Doctrine ORM 3 + Migrations, Symfony Messenger (AMQP), PhpSpreadsheet, Guzzle, PHP-DI |
| Frontend | Angular 20 (standalone, Vite/esbuild builder), NgRx Store + Effects, Angular Material 3 |
| Хранилище | PostgreSQL 16 |
| Очередь | RabbitMQ 3.13 |
| Инфраструктура | Docker Compose, multi-stage образы, nginx, GitHub Actions |

## Быстрый старт

Нужны Docker (Compose v2) и `make`.

```bash
make up          # создаёт .env из .env.example, собирает образы, поднимает стек, ждёт /api/health
```

Откройте **http://localhost:8080** и войдите:

- email: `admin@example.com`
- пароль: `admin123`

Дальше: «Импорт» → выбрать `docs/import example (2) Фул.xlsx` → «Загрузить» → дождаться 100 % → «К списку товаров».

| Адрес | Что там |
|-------|---------|
| http://localhost:8080 | Angular-приложение |
| http://localhost:8080/api/docs | Swagger UI (OpenAPI 3: `backend/resources/openapi.yaml`) |
| http://localhost:8080/api/health | Health check |
| http://localhost:15672 | RabbitMQ Management (`app` / `app_secret`) |

Без `make`: `cp .env.example .env && docker compose up -d --build`.

### Команды Makefile

| Команда | Действие |
|---------|----------|
| `make up` / `make down` | Поднять / остановить стек |
| `make fresh` | Удалить все данные (БД, изображения) и поднять заново |
| `make seed` | Залить фикстуры во все три таблицы (**очищает** текущие данные) |
| `make migrate` | Применить миграции (выполняются и автоматически при старте `app`) |
| `make install` | Поставить зависимости для локальных тестов (composer — внутри docker, npm — локально) |
| `make test` | PHPUnit + unit-тесты Angular |
| `make e2e` | Playwright e2e против запущенного стека |
| `make lint` | PHPStan + PHP CS Fixer |
| `make logs` | Логи `app` и `worker` |
| `make password-hash PASSWORD=...` | Bcrypt-хэш для `ADMIN_PASSWORD_HASH` |

## Архитектура

```
 Browser ──► nginx :8080 ─┬─ /            Angular SPA (статика)
                          ├─ /uploads/    скачанные изображения (общий volume)
                          └─ /api/*  ──►  app (PHP-FPM, Slim)
                                            │  POST /api/imports → валидация → файл в volume → ImportJob(pending)
                                            ▼
                                         RabbitMQ  ──►  worker (messenger:consume)
                                                         │ XlsxProductReader → ProductRowMapper
                                                         │ ImageDownloader (параллельно, Guzzle)
                                                         │ upsert товара в транзакции
                                                         ▼
                                                      PostgreSQL ◄── GET /api/imports/{id} (polling прогресса)
```

Сервисы `docker-compose.yml`: `app` (PHP-FPM), `worker` (тот же образ, консьюмер очереди), `db` (PostgreSQL),
`rabbitmq`, `nginx` (собирается из `frontend/Dockerfile`: сборка Angular → nginx), `php-dev` (профиль `tools`,
для composer/phpunit/phpstan).

### Backend (`backend/`)

```
src/
  Config/              Settings — типизированная конфигурация из env
  Controller/          тонкие контроллеры: принять запрос → делегировать → вернуть JSON
  Http/                JSON-ответы, презентеры, JsonErrorHandler (исключение → HTTP-статус)
  Http/Request/        ProductFilterFactory, UploadedImportFileFactory (PSR-7 → DTO сервисного слоя)
  Middleware/          JwtAuthMiddleware, RateLimitMiddleware, CorsMiddleware
  Domain/              DiscountCalculator, Page
  Exception/           доменные исключения без HTTP-кодов
  Entity/              Product, ProductAttribute, ProductImage, ImportJob
  Repository/          интерфейсы ProductRepository, ImportJobRepository + ProductFilter
  Repository/Doctrine/ реализации на Doctrine
  Persistence/         UnitOfWork (транзакции), DatabaseHealthCheck + Doctrine-реализации
  Service/Auth/        AuthService (JWT HS256, bcrypt), AuthToken
  Service/Import/      ImportService (use cases API), ImportUploadValidator, ProductImporter (оркестрация),
                       ProductWriter (upsert в транзакции)
    Contract/          ProductSourceReader, ImageFetcher, ImageStorage, ImportFileStorage
    Source/            XlsxProductReader
    Row/               ColumnMap (формат файла), ProductRowMapper, MoneyParser
    Image/             HttpImageFetcher (Guzzle), LocalImageStorage, ImageDownloader
    Storage/           LocalImportFileStorage
  Messenger/           ImportProductsMessage + ImportProductsHandler
  Console/             messenger:consume, fixtures:load, import:file
  DataFixtures/        сидеры для products / product_attributes / product_images
migrations/            Doctrine Migrations
resources/             openapi.yaml
```

**Принципы.** Сервисы зависят от интерфейсов (`Repository`, `UnitOfWork`, `Contract/*`), реализации
связываются в `config/container.php`. Сервисный слой не знает про HTTP: получает DTO и бросает доменные
исключения, а статусы назначает `JsonErrorHandler`. Формат файла описан `ColumnMap` — другой формат
подключается новой картой колонок или новой реализацией `ProductSourceReader`, без правки импортёра.

**Схема БД**

- `products` — `id`, `external_code` (UNIQUE), `name`, `description`, `price` NUMERIC(12,2), `discount` NUMERIC(5,2),
  `created_at`, `updated_at`.
- `product_attributes` — `id`, `product_id` (FK, ON DELETE CASCADE), `key`, `value`.
- `product_images` — `id`, `product_id` (FK, ON DELETE CASCADE), `url` (исходная ссылка), `path` (локальный путь), `position`.
- `import_jobs` — статус, счётчики, отчёт об ошибках (JSONB) для polling'а прогресса.

**Логика импорта**

| Колонка xlsx | Куда |
|--------------|------|
| `Внешний код` | `products.external_code` — ключ upsert'а |
| `Наименование` | `products.name` |
| `Описание` | `products.description` |
| `Цена: Цена продажи` | `products.price` (`"1320,00"` → `1320.00`) |
| `Закупочная цена` | `products.discount = (Цена продажи − Закупочная) / Цена продажи × 100` |
| `Доп. поле: *` | `product_attributes` (`key` — текст после префикса, пустые значения пропускаются) |
| `Доп. поле: Ссылки на фото`, `Доп. поле: Ссылка на упаковку` | `product_images` (скачиваются в `public/uploads/products`) |

- **Асинхронно.** `POST /api/imports` только валидирует и сохраняет файл, создаёт `ImportJob` и отправляет сообщение в RabbitMQ — ответ `202 Accepted` с `id` задачи. Обработку выполняет `worker`.
- **Ошибки не прерывают импорт.** Невалидная строка (нет кода/названия, некорректная цена, закупочная цена выше продажной) пропускается, причины попадают в отчёт `errors[]` (`row`, `externalCode`, `field`, `message`, `level`). Нескачанное изображение — `warning`, товар всё равно сохраняется (`path = null`). Если в файле нет обязательных колонок, задача получает статус `failed`. Технические детали сбоев пишутся только в лог, в отчёт попадает обобщённое сообщение.
- **Upsert по `external_code`.** Повторный импорт обновляет товар и заменяет его атрибуты/изображения — дубликатов нет. Уже скачанные изображения переиспользуются (имя файла — `sha1(url)`).
- **Атомарность.** Товар + атрибуты + изображения записываются в одной транзакции; сетевые загрузки делаются до неё, чтобы транзакция была короткой.
- **Тип изображения** определяется по содержимому (`finfo`), т.к. сервер из примера отдаёт часть картинок как `binary/octet-stream`.
- **Валидация файла:** расширение `.xlsx`, MIME (`finfo`), размер ≤ `IMPORT_MAX_FILE_SIZE`, наличие `xl/workbook.xml` внутри архива.

### API

| Метод | Путь | Описание |
|-------|------|----------|
| GET | `/api/health` | Health check (проверяет БД) |
| POST | `/api/auth/login` | JWT по email/паролю |
| GET | `/api/auth/me` | Текущий пользователь |
| GET | `/api/products?page=1&limit=20&name=&price_min=&price_max=` | Список с серверной пагинацией, поиском по имени (без учёта регистра) и диапазоном цены |
| GET | `/api/products/{id}` | Карточка: поля, атрибуты, изображения |
| POST | `/api/imports` | `multipart/form-data` с полем `file`; **rate limit** 5 запросов/мин на пользователя (`429` + `Retry-After`) |
| GET | `/api/imports/{id}` | Статус, прогресс, счётчики, отчёт об ошибках |
| GET | `/api/imports?limit=10` | Последние задачи |

Все, кроме `health`, `auth/login` и `docs`, требуют `Authorization: Bearer <token>`. Подробно — в Swagger UI.

### Frontend (`frontend/`)

```
src/app/
  app.routes.ts          роутинг, все страницы — lazy loadComponent, защита authGuard (CanActivateFn)
  models/                Product, ProductAttribute, ProductImage, ImportJob, Paginated …
  services/              ProductService, ImportService, AuthService (+ SessionStore) — HttpClient только здесь
  core/                  authTokenInterceptor (Bearer), httpErrorInterceptor (глобальные 401/5xx), authGuard,
                         httpErrorMessage(), токены API_BASE_URL / IMPORT_MAX_FILE_SIZE, русский пагинатор
  store/products/        NgRx: loadProducts / loadProductsSuccess / loadProductsFailure, effects, selectors
                         (selectAllProducts, selectProductsLoading, selectTotalPages …)
  pages/                 login, import, products-list, product-detail
  shared/                import-status (прогресс + отчёт), latest-import-panel (последний импорт с polling)
```

- Список товаров берёт данные только через `store.select()` + `async` pipe; пагинация и фильтры — серверные.
- Страница импорта: загрузка → polling `GET /api/imports/{id}` раз в секунду до завершения → прогресс-бар и отчёт.
- Карточка: поля, скидка, галерея изображений, таблица атрибутов и панель «Последний импорт» с прогрессом/отчётом.
- `strict: true` + `strictTemplates` в `tsconfig.json`.

## Тесты и качество

| Что | Где | Запуск |
|-----|-----|--------|
| Unit (маппинг строк, расчёт скидки, валидация файла и фильтров) | `backend/tests/Unit` | `make test-backend` |
| Integration: CRUD репозитория, импорт примера, upsert, отчёт об ошибках (реальный PostgreSQL) | `backend/tests/Integration` | `make test-backend` |
| Functional/API e2e: auth, пагинация, фильтры, 404/422, rate limit, полный цикл upload → очередь → worker → статус | `backend/tests/Functional` | `make test-backend` |
| Angular unit: reducer, selectors, effects, сервисы, polling, guard, interceptor, страница списка | `frontend/src/**/*.spec.ts` | `make test-frontend` |
| Browser e2e (Playwright) по всему стеку | `frontend/e2e` | `make e2e` |
| PHPStan level 6 | `backend/phpstan.neon` | `make stan` |
| PHP CS Fixer (PSR-12 + Symfony) | `backend/.php-cs-fixer.php` | `make cs` |

Тесты бэкенда используют отдельную БД `products_test` (создаётся init-скриптом PostgreSQL); очередь и rate limiter
в тестах — in-memory, HTTP-сервер изображений — фейковый.

**GitHub Actions** (`.github/workflows/ci.yml`) на каждый push: CS Fixer + PHPStan + PHPUnit (с PostgreSQL),
сборка и unit-тесты Angular, затем сборка Docker-образов, запуск стека и Playwright e2e.

## Переменные окружения

Все переменные описаны с комментариями в [`.env.example`](.env.example): порты, доступы к PostgreSQL и RabbitMQ,
`JWT_SECRET` / `JWT_TTL`, учётная запись администратора (пароль хранится bcrypt-хэшем `ADMIN_PASSWORD_HASH`), лимиты импорта (`IMPORT_MAX_FILE_SIZE`,
`IMPORT_RATE_LIMIT`, `IMPORT_RATE_INTERVAL`), таймаут скачивания изображений.

## Допущения

- Пользователь один (администратор из env) — отдельной таблицы пользователей задание не требует.
- Строка, где закупочная цена больше цены продажи, считается невалидной (скидка не может быть отрицательной).
- Пустая закупочная цена допустима — тогда `discount = null`.
- Товары, отсутствующие в новом файле, не удаляются (импорт — upsert, не синхронизация).
- `make seed` очищает таблицы перед заливкой фикстур.
