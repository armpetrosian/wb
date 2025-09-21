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
docker-compose exec app php artisan api-service:create "Wildberries API" "wildberries" "https://suppliers-api.wildberries.ru" --active
docker-compose exec app php artisan company:create "Моя Компания" "Wildberries интеграция"
docker-compose exec app php artisan token:create 1 1 "ВАШ_ТОКЕН" --active
docker-compose exec app php artisan account:create 1 1 "Основной аккаунт" --active
```

### Получение и обработка данных
```bash
docker-compose exec app php artisan wb:fetch-sales --account_id=1
docker-compose exec app php artisan wb:process-raw --account_id=1 --fresh
```

### Ежедневное обновление (Scheduler)
#### Обновление данных дважды в день: 01:00 и 13:00 по МСК.
````
docker-compose exec app php artisan wb:update-daily
php artisan wb:update-daily --account_id=1
php artisan wb:update-daily --all
````

### Обработка данных
#### Обработка всех свежих данных аккаунта (последние 7 дней):
````
php artisan wb:process-raw --account_id=1
````
#### Обработка данных за последние 3 дня:
````
php artisan wb:process-raw --account_id=1 --dateFrom=3
````
#### Обработка данных за конкретный период:
````
php artisan wb:process-raw --account_id=1 --dateFrom=2025-01-15 --dateTo=2025-01-20
````
#### Только свежие данные (7 дней):
````
php artisan wb:process-raw --account_id=1 --fresh
````
#### С указанием размера батча:
````
php artisan wb:process-raw --account_id=1 --chunk=50 --fresh
````
#### Для больших объемов данных можно увеличить размер батча:
````
docker-compose exec app php artisan wb:process-raw --chunk=200
````
### Синхронизация всех данных
```
docker-compose exec app php artisan wb:sync-all all
```

# Особенности
``
Поддержка нестандартного порта MySQL (3308).
Обработка ошибок типа Too many requests (429) с экспоненциальной задержкой и джиттером (до 5 попыток).     
Подробный вывод отладочной информации в консоль:
DEBUG – отладочная информация (только при включенном режиме отладки)
INFO – основная информация о процессе
WARN – предупреждения (например, при повторных попытках)
ERROR – критические ошибки
Структура базы данных: компании → аккаунты → токены одного типа → API-сервисы.
Возможность создания новых компаний, аккаунтов, токенов и API-сервисов через консольные команды Artisan.
``
