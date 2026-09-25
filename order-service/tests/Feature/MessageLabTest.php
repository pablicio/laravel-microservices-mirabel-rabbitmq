<?php

namespace Tests\Feature;

use Tests\TestCase;

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
}
