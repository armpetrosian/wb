# WB Sync Laravel Project

## Описание
Проект для выгрузки данных с Wildberries API и сохранения их в MySQL.  
Поддерживаемые сущности:
- Sales (Продажи)
- Orders (Заказы)
- Stocks (Склады)
- Incomes (Доходы)

Авторизация происходит с помощью токена (`WB_API_KEY`).

## Установка

```bash
git clone <репозиторий>
cd wb-sync
cp .env.example .env
docker-compose up -d --build
docker-compose exec app php artisan migrate

```
## Использование
## Выгрузка данных
````
docker-compose exec app php artisan wb:fetch-all --dateFrom=2025-08-01 --dateTo=2025-08-30
````

## Обработка данных
````
docker-compose exec app php artisan wb:process-raw
````
## Проверка данных
````
docker-compose exec app php artisan tinker
>>> App\Models\Sale::count();
>>> App\Models\Order::count();
>>> App\Models\Stock::count();
>>> App\Models\Income::count();
````



# wb
