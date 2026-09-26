# store-service

Publica `OrderReceivedEvent` e consome `StoreOrderCreatedWorker` e
`TestOrderDoneWorker`. Veja o [README do projeto](../README.md).

```bash
php artisan serve --port=8000
php artisan rabbitmq:consume StoreOrderCreatedWorker
php vendor/bin/phpunit
```
