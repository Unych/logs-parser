# Logs Parser

Веб-приложение для асинхронного парсинга и анализа access-логов nginx (combined-формат). Загруженный файл попадает в очередь Redis, фоновый воркер парсит его батчами и пишет в MySQL. На дашборде — графики (запросы людей/ботов по дням, доля топ-3 браузеров) и таблица (запросы за день, самый популярный URL и браузер) с фильтрами по дате, ОС, архитектуре и ботам.

Стек: **Laravel 11, PHP 8.3, MySQL 8, Redis 7**. Всё в Docker Compose.

## Запуск

```bash
git clone <repo-url> logs-parser
cd logs-parser

cp .env.example .env

docker compose up -d --build

docker compose exec app composer install --no-interaction --prefer-dist
docker compose exec app php artisan key:generate --force
docker compose exec app php artisan migrate --force
```

Открой **http://127.0.0.1:8080**.

Воркер `lp-worker` стартует автоматически и слушает очередь.

## Использование

1. На странице загрузить файл `.log` или `.txt` в combined-формате nginx.
2. Под формой пойдёт прогресс импорта (обновляется без перезагрузки).
3. После импорта применяй фильтры — графики и таблица обновятся.
4. Сортировка таблицы — клик по заголовку колонки.

Повторная загрузка того же файла дублей не создаёт (дедуп по SHA-256 файла + по уникальному индексу строк).

## Тесты

```bash
docker compose exec app php vendor/bin/phpunit --testsuite Unit
```

Покрывают парсер строки лога и парсер User-Agent — 51 тест.

## API

| Метод | URL                              | Описание                         |
|-------|----------------------------------|----------------------------------|
| GET   | `/`                              | Дашборд                          |
| POST  | `/imports`                       | Загрузка файла (form-data `log_file`) |
| GET   | `/imports/{id}/progress`         | Прогресс импорта                 |
| GET   | `/api/stats/requests`            | Данные графика «запросы по дням» |
| GET   | `/api/stats/browsers`            | Данные графика «доля браузеров»  |
| GET   | `/api/stats/table`               | Данные таблицы                   |

## Порты

- nginx → `127.0.0.1:8080`
- mysql → `127.0.0.1:33306` (нестандартный, чтобы не конфликтовать с локальным)
- redis → `127.0.0.1:16379`
