# Laravel microservices com Mirabel RabbitMQ

Dois serviços Laravel que conversam só por mensagens, usando a biblioteca
[`mirabel/rabbitmq`](../mirabel-rabbitmq), e um conjunto de laboratórios para
ver, medir e quebrar essa conversa de propósito.

```text
            store-services.order.created                  ┌──────────────────────────┐
┌───────────────┐ ────────────────────────▶ my-exchange ─▶│ store-services.orders.   │─▶ StoreOrderCreatedWorker
│ order-service │                            (topic)      │ created (+ .retry/.error)│   (store-service)
│    :8001      │                                         └──────────────────────────┘
│  labs + API   │◀─ StoreOrderReceivedWorker ◀─ store-services.orders.received ◀─┐
└───────────────┘                                                               │ order-services.order.received
                                          TestOrderDoneWorker ◀─ order-services.order-test.done ◀─┤
┌───────────────┐                                                               │
│ store-service │ ── OrderReceivedEvent ──────────────▶ my-exchange ─────────────┘
│    :8000      │
└───────────────┘
```

| Serviço | Publica | Consome |
| --- | --- | --- |
| `order-service` | `StoreOrderCreatedEvent` (`store-services.order.created`) | `StoreOrderReceivedWorker` |
| `store-service` | `OrderReceivedEvent` (`order-services.order.received`) | `StoreOrderCreatedWorker`, `TestOrderDoneWorker` |

`order-services.order.received` tem **duas** filas ligadas, uma em cada serviço:
cada fila recebe sua própria cópia. É o exemplo de fan-out do projeto.

## Subindo tudo

Pré-requisitos: PHP 8.2+, Composer, Docker.

```bash
docker compose up -d                      # RabbitMQ, Prometheus e Grafana

cd order-service && composer install && cp .env.example .env && php artisan key:generate && cd ..
cd store-service && composer install && cp .env.example .env && php artisan key:generate && cd ..
```

Os dois serviços usam o checkout local da biblioteca em `../mirabel-rabbitmq`
por um repositório Composer do tipo `path` com symlink: uma mudança na
biblioteca vale na hora. Depois de mudar o `composer.json` da biblioteca, rode
`composer update mirabel/rabbitmq` em cada serviço.

Em quatro terminais:

```bash
cd store-service && php artisan serve --port=8000
cd order-service && php artisan serve --port=8001
cd store-service && php artisan rabbitmq:consume StoreOrderCreatedWorker
cd order-service && php artisan rabbitmq:consume StoreOrderReceivedWorker
```

Publique um pedido:

```bash
cd order-service
php artisan tinker --execute="(new App\Events\StoreOrderCreatedEvent(['id' => 123]))->publish();"
```

E a confirmação de volta, que as duas filas recebem:

```bash
cd store-service
php artisan tinker --execute="(new App\Events\OrderReceivedEvent(['id' => 123]))->publish();"
```

O payload `"test"` sempre falha no `StoreOrderReceivedWorker`: publique
`OrderReceivedEvent('test')` e acompanhe, no painel do RabbitMQ
(`http://localhost:15672`), a mensagem passar três vezes pela fila `.retry` e
parar na `.error` com os headers `x-mirabel-*` explicando o motivo.

## Configuração

As variáveis `MB_RABBITMQ_*` do `.env` vão para `config/mirabel_rabbitmq.php` e
são exportadas para o ambiente do processo no boot (`App\Support\MirabelEnvironment`),
que é onde a biblioteca as lê. Isso mantém tudo funcionando depois de
`php artisan config:cache`, quando o Laravel deixa de carregar o `.env`.

| Variável | Padrão | Uso |
| --- | --- | --- |
| `MB_RABBITMQ_HOST` / `PORT` / `USER` / `PASSWORD` / `VHOST` | `127.0.0.1` / `5672` / `guest` / `guest` / `/` | conexão AMQP |
| `MB_RABBITMQ_EXCHANGE` | `my-exchange` | exchange principal (topic) |
| `MB_RABBITMQ_PUBLISHER_CONFIRMS` | `false` | espera o broker confirmar cada publicação |
| `MB_RABBITMQ_PUBLISH_RETRIES` | `3` | novas tentativas de `publish()` |
| `RABBITMQ_MANAGEMENT_URL` | `http://127.0.0.1:15672` | API HTTP usada pelos labs (order-service) |
| `STORE_SERVICE_URL` | `http://127.0.0.1:8000` | métricas do store-service (order-service) |

## Laboratórios (order-service, `http://localhost:8001`)

| Página | O que mostra |
| --- | --- |
| **Message Lab** `/` | publica um lote pequeno de mensagens reais e acompanha |
| **Applications Test** `/applications-test` | simula taxa de falha e duplicidade contra `max_attempts` |
| **Black Friday** `/black-friday` | um carrinho que não vende além do estoque |
| **Stress Test** `/stress-test` | publica até um milhão de mensagens e mede o publisher |
| **Consumer Lab** `/consumer-lab` | escala de 1 a 8 consumidores e mede quanto tempo a fila leva para esvaziar |
| **Production Gate** `/production-readiness` | transforma os números de um teste de carga em veredito |

O Stress Test e o Consumer Lab disparam comandos Artisan em segundo plano
(`App\Support\ArtisanLauncher`), em Windows, Linux e macOS.

## Observabilidade

Cada serviço expõe `GET /rabbitmq/metrics` no formato do Prometheus. O
`docker compose` sobe o Prometheus lendo os dois serviços (`monitoring/prometheus.yml`)
e o Grafana com o painel `RabbitMQ overview` já provisionado.

## Testes

```bash
cd order-service && php vendor/bin/phpunit
cd store-service && php vendor/bin/phpunit
```

Os testes não dependem de RabbitMQ nem do outro serviço estar no ar: toda
chamada HTTP que não foi simulada com `Http::fake()` falha o teste, e os
comandos em segundo plano são trocados por um launcher falso. Os testes que
rodam contra um broker de verdade ficam na biblioteca (`composer test:integration`).

## Estrutura

```text
docker-compose.yml          RabbitMQ + Prometheus + Grafana
monitoring/                 configuração do Prometheus e do Grafana
scripts/                    gerador de carga sintética para as métricas
order-service/
  app/Events/               StoreOrderCreatedEvent
  app/Workers/              StoreOrderReceivedWorker
  app/Http/Controllers/     um controller por laboratório
  app/Support/              métricas, pool de consumidores, launcher, clientes HTTP
  app/Console/Commands/     rabbitmq:generate, rabbitmq:stress, rabbitmq:consume
store-service/
  app/Events/               OrderReceivedEvent
  app/Workers/              StoreOrderCreatedWorker, TestOrderDoneWorker
  app/Console/Commands/     rabbitmq:consume-store-orders, rabbitmq:consume
```

![Fluxo original do artigo](https://user-images.githubusercontent.com/19760320/233184662-e45add33-8107-45f2-908f-2d8bf3a5416a.png)
