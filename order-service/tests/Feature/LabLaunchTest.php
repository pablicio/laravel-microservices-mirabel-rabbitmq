<?php

namespace Tests\Feature;

use App\Support\ArtisanLauncher;
use App\Support\ConsumerPoolManager;
use App\Support\RabbitMqMetrics;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class LabLaunchTest extends TestCase
{
    private FakeArtisanLauncher $launcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->launcher = new FakeArtisanLauncher();
        $this->app->instance(ArtisanLauncher::class, $this->launcher);
        $this->app->instance(RabbitMqMetrics::class, new RabbitMqMetrics(
            storage_path('framework/testing/launch-' . bin2hex(random_bytes(6)) . '.json'),
        ));
    }

    public function testMessageLabLaunchesTheGeneratorInTheBackground(): void
    {
        $this->post('/test/generate', ['quantity' => 25, 'delay_ms' => 10])
            ->assertRedirect('/')
            ->assertSessionHas('generation.requested', 25);

        $this->assertCount(1, $this->launcher->launched);
        [$artisan, $arguments] = $this->launcher->launched[0];
        $this->assertSame(base_path('artisan'), $artisan);
        $this->assertSame(['rabbitmq:generate', 25, '--delay-ms=10'], array_slice($arguments, 0, 3));
    }

    public function testStressTestLaunchesThePublisher(): void
    {
        $this->post('/stress-test/start', ['quantity' => 5000, 'users' => 50])
            ->assertRedirect('/stress-test')
            ->assertSessionHas('stress_run_id');

        $this->assertSame(['rabbitmq:stress', 5000, '--users=50'], array_slice($this->launcher->launched[0][1], 0, 3));
    }

    public function testConsumerLabScalesThePoolBeforePublishing(): void
    {
        Http::fake([
            'http://127.0.0.1:15672/api/queues/*' => Http::sequence()
                ->push(['messages' => 0, 'messages_ready' => 0, 'messages_unacknowledged' => 0, 'consumers' => 1, 'message_stats' => ['ack' => 40]])
                ->push(['messages' => 0, 'messages_ready' => 0, 'messages_unacknowledged' => 0, 'consumers' => 0, 'message_stats' => ['ack' => 0]])
                ->push(['messages' => 0, 'messages_ready' => 0, 'messages_unacknowledged' => 0, 'consumers' => 0, 'message_stats' => ['ack' => 0]])
                ->push(['messages' => 0, 'messages_ready' => 0, 'messages_unacknowledged' => 0, 'consumers' => 1, 'message_stats' => ['ack' => 40]])
                ->push(['messages' => 0, 'messages_ready' => 0, 'messages_unacknowledged' => 0, 'consumers' => 3, 'message_stats' => ['ack' => 40]]),
        ]);
        $this->app->instance(ConsumerPoolManager::class, new class extends ConsumerPoolManager {
            protected function workerProcessIds(string $artisanPath): array
            {
                return [];
            }
        });

        $this->post('/consumer-lab/start', ['messages' => 1000, 'consumers' => 3])
            ->assertRedirect('/consumer-lab')
            ->assertSessionHas('consumer_run_id');

        $commands = array_map(static fn (array $launch): string => (string) $launch[1][0], $this->launcher->launched);
        $this->assertSame(['rabbitmq:consume-store-orders', 'rabbitmq:consume-store-orders', 'rabbitmq:stress'], $commands);
        $this->assertContains('--ack-baseline=40', end($this->launcher->launched)[1]);
        $this->assertContains('--error-baseline=0', end($this->launcher->launched)[1]);
    }

    public function testConsumerLabStatusSurvivesServicesBeingDown(): void
    {
        $this->getJson('/consumer-lab/status?run_id=nothing')
            ->assertOk()
            ->assertJsonPath('status', 'queued')
            ->assertJsonPath('processed', null)
            ->assertJsonPath('consumers', null);
    }
}

final class FakeArtisanLauncher extends ArtisanLauncher
{
    /** @var list<array{0: string, 1: list<string|int>}> */
    public array $launched = [];

    public function launch(string $artisanPath, array $arguments): void
    {
        $this->launched[] = [$artisanPath, $arguments];
    }
}
