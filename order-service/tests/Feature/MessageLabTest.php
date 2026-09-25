<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Support\ConsumerPoolManager;
use App\Support\RabbitMqMetrics;
use Illuminate\Support\Facades\Http;

final class MessageLabTest extends TestCase
{
    public function testMessageLabIsAvailable(): void
    {
        $this->get('/')->assertOk()->assertSee('Message lab');
    }

    public function testMessageQuantityMustBeWithinTheSupportedRange(): void
    {
        $this->from('/')
            ->post('/test/generate', ['quantity' => 0, 'delay_ms' => 0])
            ->assertRedirect('/')
            ->assertSessionHasErrors('quantity');
    }

    public function testBatchStatusEndpointReturnsProgressFields(): void
    {
        $this->getJson('/test/status')
            ->assertOk()
            ->assertJsonStructure(['status', 'requested', 'published', 'failed', 'samples', 'metrics']);
    }

    public function testApplicationsTestPageIsAvailable(): void
    {
        $this->get('/applications-test')
            ->assertOk()
            ->assertSee('Teste antes de quebrar em produção.');
    }

    public function testApplicationsTestRejectsInvalidFailureRate(): void
    {
        $this->from('/applications-test')
            ->post('/applications-test/simulate', [
                'quantity' => 10,
                'failure_rate' => 101,
                'duplicate_rate' => 5,
                'max_attempts' => 3,
            ])
            ->assertRedirect('/applications-test')
            ->assertSessionHasErrors('failure_rate');
    }

    public function testBlackFridayCartPageIsAvailable(): void
    {
        $this->get('/black-friday')
            ->assertOk()
            ->assertSee('Compre rápido.');
    }

    public function testBlackFridayCheckoutBlocksOrdersAboveStock(): void
    {
        $this->postJson('/black-friday/checkout', [
            'items' => ['camera' => 3],
        ])->assertOk()
            ->assertJsonPath('status', 'partial')
            ->assertJsonPath('blocked.0.available', 2);
    }

    public function testStressTestCapsTheRequestedVolume(): void
    {
        $this->from('/stress-test')
            ->post('/stress-test/start', ['quantity' => 1000001, 'users' => 100])
            ->assertRedirect('/stress-test')
            ->assertSessionHasErrors('quantity');
    }

    public function testProductionReadinessPageIsAvailable(): void
    {
        $this->get('/production-readiness')
            ->assertOk()
            ->assertSee('Está pronto para produção?');
    }

    public function testProductionReadinessDetectsConsumerBacklog(): void
    {
        $this->from('/production-readiness')
            ->post('/production-readiness/evaluate', [
                'messages' => 100000,
                'users' => 5000,
                'publishers' => 1,
                'consumers' => 1,
                'message_rate' => 7000,
                'consumer_rate' => 1000,
                'p95_ms' => 120,
                'failure_rate' => 0,
            ])
            ->assertRedirect('/production-readiness')
            ->assertSessionHas('readiness');
    }

    public function testConsumerLabPageIsAvailable(): void
    {
        $this->get('/consumer-lab')
            ->assertOk()
            ->assertSee('Publique. Consuma. Meça.');
    }

    public function testConsumerLabStatusReturnsQueueFields(): void
    {
        $this->getJson('/consumer-lab/status?run_id=missing-run')
            ->assertOk()
            ->assertJsonStructure(['status', 'requested', 'published', 'processed', 'backlog', 'consumers']);
    }

    public function testConsumerHistoryIsAvailable(): void
    {
        $metrics = new RabbitMqMetrics(storage_path('framework/testing/consumer-history-' . bin2hex(random_bytes(6)) . '.json'));
        $metrics->saveConsumerRun([
            'run_id' => bin2hex(random_bytes(8)),
            'published' => 100,
            'processed' => 100,
            'requested_consumers' => 2,
            'consumers' => 2,
            'consumer_elapsed_ms' => 5000,
            'throughput' => 20,
            'completed_at' => '2026-09-25T12:00:00+00:00',
            'backlog' => 0,
        ]);
        $metrics->saveConsumerRun([
            'run_id' => bin2hex(random_bytes(8)),
            'status' => 'incomplete',
            'published' => 1000,
            'processed' => 789,
            'requested_consumers' => 1,
            'consumers' => 20,
            'consumer_elapsed_ms' => 1980,
            'throughput' => 399.29,
            'completed_at' => '2026-09-25T18:37:00+00:00',
            'backlog' => 0,
        ]);
        $this->app->instance(RabbitMqMetrics::class, $metrics);

        $this->get('/consumer-lab')
            ->assertOk()
            ->assertSee('Fila em tempo real')
            ->assertSee('Situação')
            ->assertSee('Incompleto')
            ->assertSee('211')
            ->assertSee('1 / 20')
            ->assertDontSee('399,29');
    }

    public function testLabPagesShareNavigationAndMarkTheCurrentPage(): void
    {
        $pages = [
            '/' => 'Message Lab',
            '/applications-test' => 'Applications Test',
            '/black-friday' => 'Black Friday',
            '/stress-test' => 'Stress Test',
            '/consumer-lab' => 'Consumer Lab',
            '/production-readiness' => 'Production Gate',
        ];

        foreach ($pages as $currentPath => $currentLabel) {
            $response = $this->get($currentPath)->assertOk();
            foreach ($pages as $path => $label) {
                $response->assertSee($label)
                    ->assertSee('href="' . url($path) . '"', false);
            }

            $response->assertSee('aria-current="page"', false);
            $this->assertSame(1, substr_count($response->getContent(), 'aria-current="page"'), $currentLabel);
        }
    }

    public function testConsumerRunWithUnaccountedMessagesIsMarkedIncomplete(): void
    {
        $metrics = new RabbitMqMetrics(storage_path('framework/testing/consumer-status-' . bin2hex(random_bytes(6)) . '.json'));
        $metrics->saveStress([
            'run_id' => 'partial-consumer-run-' . bin2hex(random_bytes(4)),
            'status' => 'completed',
            'requested' => 1000,
            'published' => 1000,
            'requested_consumers' => 1,
            'processed_baseline' => 100,
            'started_at_ms' => (int) (microtime(true) * 1000) - 2000,
        ]);
        $runId = $metrics->stress()['run_id'];
        $this->app->instance(RabbitMqMetrics::class, $metrics);

        Http::fake([
            'http://127.0.0.1:8000/rabbitmq/metrics' => Http::response(
                'rabbitmq_messages_total{event="processed"} 889' . "\n",
            ),
            'http://127.0.0.1:15672/api/queues/*' => Http::response([
                'messages' => 0,
                'consumers' => 20,
            ]),
        ]);

        $this->getJson('/consumer-lab/status?run_id=' . $runId)
            ->assertOk()
            ->assertJsonPath('status', 'incomplete')
            ->assertJsonPath('published', 1000)
            ->assertJsonPath('processed', 789)
            ->assertJsonPath('difference', 211)
            ->assertJsonPath('requested_consumers', 1)
            ->assertJsonPath('throughput', 0);

        $this->assertSame('incomplete', $metrics->consumerHistory()[0]['status']);
    }

    public function testConsumerPoolManagerStopsOnlyTheExcessWorkers(): void
    {
        $manager = new class extends ConsumerPoolManager {
            public array $stoppedPids = [];

            protected function workerProcessIds(string $artisanPath): array
            {
                return range(1000, 1032);
            }

            protected function stopWorker(int $pid): bool
            {
                $this->stoppedPids[] = $pid;

                return true;
            }
        };
        $activeCounts = [33, 1];
        $startCalls = 0;

        $active = $manager->reconcile(
            1,
            'store-service/artisan',
            static function () use (&$activeCounts): int {
                return array_shift($activeCounts);
            },
            static function () use (&$startCalls): void {
                $startCalls++;
            },
        );

        $this->assertSame(1, $active);
        $this->assertCount(32, $manager->stoppedPids);
        $this->assertSame(0, $startCalls);
    }

    public function testConsumerPoolManagerStartsOnlyMissingWorkers(): void
    {
        $manager = new class extends ConsumerPoolManager {
            protected function workerProcessIds(string $artisanPath): array
            {
                return [];
            }
        };
        $activeCounts = [2, 4];
        $startCalls = 0;

        $active = $manager->reconcile(
            4,
            'store-service/artisan',
            static function () use (&$activeCounts): int {
                return array_shift($activeCounts);
            },
            static function () use (&$startCalls): void {
                $startCalls++;
            },
        );

        $this->assertSame(4, $active);
        $this->assertSame(2, $startCalls);
    }
}
