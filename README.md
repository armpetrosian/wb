# WB Sync

# Приложение для синхронизации данных с Wildberries через API.

## Полная настройка

### Запуск системы

```bash
docker-compose up -d --build
docker-compose exec app composer install --no-dev --optimize-autoloader
```
### Создание базовых сущностей
```bash
docker-compose exec app php artisan token-type:create "WB API Token" "wb_api_token"
docker-compose exec app php artisan api-service:create "Wildberries API" "wildberries" "http://109.73.206.144:6969" --active
docker-compose exec app php artisan company:create "Моя Компания" "Wildberries интеграция"
docker-compose exec app php artisan token:create 1 1 "ВАШ_ТОКЕН" --active
docker-compose exec app php artisan account:create 1 1 "Основной аккаунт" --active
```

### Получение и обработка данных

#### Получение данных из API
```bash
# Получение продаж за конкретные даты
docker-compose exec app php artisan wb:fetch-sales --account_id=1 --dateFrom=2025-09-24 --dateTo=2025-09-24

# Получение всех типов данных (sales, orders, stocks, incomes)
docker-compose exec app php artisan wb:fetch-all --account_id=1 --dateFrom=2025-09-24 --dateTo=2025-09-24

# Обработка сырых данных
docker-compose exec app php artisan wb:process-raw --account_id=1 --fresh
```

#### Синхронизация данных
```bash
# Синхронизация конкретного типа данных для аккаунта
docker-compose exec app php artisan wb:sync sales --account_id=1 --dateFrom=2025-09-24 --dateTo=2025-09-24

# Синхронизация всех типов данных для конкретного аккаунта
docker-compose exec app php artisan wb:sync-all all --account_id=1 --dateFrom=2025-09-24 --dateTo=2025-09-24

# Синхронизация для всех активных аккаунтов
docker-compose exec app php artisan wb:sync-all sales
```

### Ежедневное обновление (Scheduler)
#### Обновление данных дважды в день: 01:00 и 13:00 по МСК.
```bash
docker-compose exec app php artisan wb:update-daily
docker-compose exec app php artisan wb:update-daily --account_id=1
docker-compose exec app php artisan wb:update-daily --all
```

### Дополнительные опции обработки данных

#### Обработка сырых данных с различными параметрами:
```bash
# Обработка всех свежих данных аккаунта (последние 7 дней)
docker-compose exec app php artisan wb:process-raw --account_id=1

# Обработка данных за последние 3 дня
docker-compose exec app php artisan wb:process-raw --account_id=1 --dateFrom=3

# Обработка данных за конкретный период
docker-compose exec app php artisan wb:process-raw --account_id=1 --dateFrom=2025-01-15 --dateTo=2025-01-20

# Только свежие данные (7 дней)
docker-compose exec app php artisan wb:process-raw --account_id=1 --fresh

# С указанием размера батча
docker-compose exec app php artisan wb:process-raw --account_id=1 --chunk=50 --fresh

# Для больших объемов данных можно увеличить размер батча
docker-compose exec app php artisan wb:process-raw --chunk=200
```

## Доступные WB API команды

| Команда | Описание | Основные параметры |
|---------|----------|--------------------|
| `wb:fetch-sales` | Получение продаж | `--account_id`, `--dateFrom`, `--dateTo` |
| `wb:fetch-all` | Получение всех типов данных | `--account_id`, `--dateFrom`, `--dateTo` |
| `wb:sync` | Синхронизация конкретного типа | `type`, `--account_id`, `--dateFrom`, `--dateTo` |
| `wb:sync-all` | Синхронизация всех/конкретного аккаунта | `type`, `--account_id`, `--dateFrom`, `--dateTo` |
| `wb:update-daily` | Ежедневное обновление | `--account_id`, `--all` |
| `wb:process-raw` | Обработка сырых данных | `--account_id`, `--dateFrom`, `--dateTo`, `--fresh`, `--chunk` |

## Особенности

- **Поддержка нестандартного порта MySQL (3308)**
- **Обработка ошибок API с retry механизмом:**
  - Too many requests (429) с экспоненциальной задержкой и джиттером (до 6 попыток)
  - Автоматические повторы при временных сбоях (400, 500 ошибки)
- **Подробный вывод отладочной информации в консоль:**
  - `DEBUG` – отладочная информация (только при включенном режиме отладки)
  - `INFO` – основная информация о процессе
  - `WARN` – предупреждения (например, при повторных попытках)
  - `ERROR` – критические ошибки
- **Гибкая архитектура:**
  - Структура базы данных: компании → аккаунты → токены → API-сервисы
  - Фабрика клиентов для унифицированного создания API клиентов
  - Репозиторий паттерн для работы с данными
- **Возможности:**
  - Создание новых компаний, аккаунтов, токенов и API-сервисов через Artisan команды
  - Пагинация больших объемов данных
  - Сохранение сырых ответов API для анализа и повторной обработки
  - Batch обработка данных с настраиваемым размером чанков

## Структура проекта

```
app/
├── Console/Commands/     # Artisan команды для работы с WB API
├── Models/              # Eloquent модели (Account, Sale, Order, Stock, Income)
├── Services/            # Сервисные классы (WbApiClient, WbSyncService)
├── Repositories/        # Репозитории для работы с данными
└── Policies/           # Политики доступа
```
