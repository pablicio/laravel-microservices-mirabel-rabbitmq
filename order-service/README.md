# order-service

Publica `StoreOrderCreatedEvent`, consome `StoreOrderReceivedWorker` e hospeda
os laboratórios de RabbitMQ. Veja o [README do projeto](../README.md).

```bash
php artisan serve --port=8001
php artisan rabbitmq:consume StoreOrderReceivedWorker
php vendor/bin/phpunit
```
