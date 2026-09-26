<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mirabel / Message Lab</title>
    <style>
        :root { --ink: #17221c; --paper: #f4f0e8; --card: #fffdf8; --green: #235c46; --orange: #e67643; --line: #d8d4c8; }
        * { box-sizing: border-box; }
        body { margin: 0; color: var(--ink); background: radial-gradient(circle at 12% 10%, #fffaf0 0, transparent 32%), var(--paper); font-family: Georgia, 'Times New Roman', serif; }
        main { width: min(1080px, calc(100% - 40px)); margin: 0 auto; padding: 42px 0 64px; }
        header { display: flex; justify-content: space-between; gap: 24px; align-items: end; border-bottom: 1px solid var(--line); padding-bottom: 24px; }
        .kicker { color: var(--orange); font: 700 12px/1.2 Arial, sans-serif; letter-spacing: .12em; text-transform: uppercase; }
        h1 { max-width: 660px; margin: 10px 0 0; font-size: clamp(2.3rem, 6vw, 5.2rem); line-height: .94; font-weight: 500; letter-spacing: -0.04em; }
        .status { min-width: 180px; padding: 15px; border-left: 3px solid var(--green); background: rgba(255, 253, 248, .65); font: 13px/1.5 Arial, sans-serif; }
        .status strong { display: block; color: var(--green); font-size: 15px; }
        .workspace { display: grid; grid-template-columns: 1.2fr .8fr; gap: 20px; margin-top: 28px; }
        section { border: 1px solid var(--line); background: var(--card); padding: 26px; box-shadow: 7px 7px 0 rgba(35, 92, 70, .08); }
        h2 { margin: 0 0 8px; font-size: 25px; font-weight: 500; }
        p { color: #526057; font: 15px/1.55 Arial, sans-serif; }
        label { display: block; margin: 22px 0 7px; color: #526057; font: 700 12px/1.2 Arial, sans-serif; letter-spacing: .08em; text-transform: uppercase; }
        input { width: 100%; border: 1px solid #bdb9ad; background: #fff; padding: 14px; color: var(--ink); font: 20px Georgia, serif; }
        input:focus { outline: 3px solid rgba(230, 118, 67, .25); border-color: var(--orange); }
        button { width: 100%; margin-top: 24px; border: 0; padding: 16px 20px; background: var(--green); color: #fff; cursor: pointer; font: 700 14px Arial, sans-serif; letter-spacing: .05em; text-transform: uppercase; }
        button:hover { background: #174432; }
        .result { margin-top: 20px; border-left: 3px solid var(--orange); padding: 14px 16px; background: #fff4ed; font: 14px/1.5 Arial, sans-serif; }
        .result strong { color: var(--green); }
        .counters { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 28px; }
        .counter { border-top: 3px solid var(--green); padding: 14px 16px; background: var(--card); }
        .counter span { display: block; color: #526057; font: 700 11px/1.2 Arial, sans-serif; letter-spacing: .08em; text-transform: uppercase; }
        .counter strong { display: block; margin-top: 5px; color: var(--green); font-size: 30px; font-weight: 500; }
        .messages { margin-top: 20px; }
        .message-list { display: grid; gap: 10px; margin-top: 14px; }
        .message { overflow: auto; border: 1px solid var(--line); padding: 14px; background: #f8f6f0; }
        .message-meta { margin-bottom: 8px; color: #526057; font: 11px/1.4 Arial, sans-serif; }
        pre { margin: 0; color: var(--ink); font: 12px/1.5 Consolas, monospace; white-space: pre-wrap; }
        .links { display: grid; gap: 10px; margin-top: 24px; }
        .links a { color: var(--green); font: 700 14px Arial, sans-serif; text-decoration: none; }
        .links a:hover { color: var(--orange); }
        code { color: var(--green); font: 13px Consolas, monospace; }
        @media (max-width: 760px) { main { width: min(100% - 24px, 600px); padding-top: 24px; } header, .workspace { display: block; } .status { margin-top: 22px; } section + section { margin-top: 18px; } .counters { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<main>
    @include('partials.lab-navigation')
    <header>
        <div>
            <div class="kicker">Mirabel / RabbitMQ</div>
            <h1>Eventos reais.<br>Da aplicação ao broker.</h1>
        </div>
        <div class="status"><strong>Order service</strong>Publishing through <code>StoreOrderCreatedEvent</code></div>
    </header>
    @include('partials.lab-guide', [
        'title' => 'Publicação de eventos',
        'concept' => 'Um evento representa um fato da aplicação e é publicado em uma exchange para chegar às filas ligadas à sua routing key.',
        'observe' => 'Publicado significa que o publisher terminou o envio. Não significa que um consumer processou a mensagem.',
        'limit' => 'O lote é real, mas os contadores Prometheus são cumulativos do serviço; eles não são o resultado exclusivo deste lote.',
    ])

    <div class="workspace">
        <section>
            <h2>Publicar um lote</h2>
            <p>Cada evento vai para a exchange configurada com um message ID e uma chave de idempotência próprios.</p>
            <form method="POST" action="{{ url('/test/generate') }}">
                @csrf
                <label for="quantity">Mensagens</label>
                <input id="quantity" name="quantity" type="number" min="1" max="500" value="10" required>
                <label for="delay_ms">Intervalo entre envios (ms)</label>
                <input id="delay_ms" name="delay_ms" type="number" min="0" max="5000" value="100" required>
                <button type="submit">Publicar eventos</button>
            </form>
            @if ($errors->any())
                <div class="result">{{ $errors->first() }}</div>
            @endif
            @if (session('generation'))
                <div class="result">
                    Batch <strong>{{ session('generation.run_id') }}</strong> accepted for
                    <strong>{{ session('generation.requested') }}</strong> messages. Live progress is shown below.
                </div>
            @endif
        </section>

        <section>
            <h2>Observabilidade</h2>
            <p>Os contadores do serviço também são expostos ao Prometheus e ao painel Grafana.</p>
            <div class="links">
                <a href="{{ url('/rabbitmq/metrics') }}">→ Métricas Prometheus</a>
                <a href="http://localhost:3000/d/mirabel-rabbitmq-overview/mirabel-rabbitmq-overview" target="_blank" rel="noreferrer">→ Abrir painel Grafana</a>
                <a href="http://localhost:9090" target="_blank" rel="noreferrer">→ Abrir Prometheus</a>
            </div>
        </section>
    </div>

    <div class="counters">
        <div class="counter"><span>Publicadas</span><strong id="published-count">{{ $metrics['published'] ?? 0 }}</strong></div>
        <div class="counter"><span>Processadas</span><strong id="processed-count">{{ $metrics['processed'] ?? 0 }}</strong></div>
        <div class="counter"><span>Falhas de publicação</span><strong id="failed-count">{{ $metrics['publish_failed'] ?? 0 }}</strong></div>
    </div>

    @if (!empty($batch))
        <section class="messages">
            <h2>Último lote: <span id="batch-status">{{ $batch['status'] ?? 'desconhecido' }}</span></h2>
            <p id="batch-progress">Solicitadas {{ $batch['requested'] ?? 0 }} · Publicadas {{ $batch['published'] ?? 0 }} · Falhas {{ $batch['failed'] ?? 0 }}. Amostra das primeiras mensagens do lote.</p>
            <progress id="batch-progress-bar" max="{{ max(1, $batch['requested'] ?? 1) }}" value="{{ $batch['published'] ?? 0 }}"></progress>
            <div class="message-list" id="message-list">
                @foreach ($batch['samples'] ?? [] as $sample)
                    <article class="message">
                        <div class="message-meta">message_id: {{ $sample['message_id'] }} · idempotency: {{ $sample['idempotency_key'] }}</div>
                        <pre>{{ json_encode($sample['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
</main>
@if (session('generation.run_id'))
    <script>
        (function () {
            const runId = @json(session('generation.run_id'));
            const statusUrl = @json(url('/test/status')) + '?run_id=' + encodeURIComponent(runId);
            const status = document.getElementById('batch-status');
            const progress = document.getElementById('batch-progress');
            const progressBar = document.getElementById('batch-progress-bar');
            const poll = function () {
                fetch(statusUrl, { headers: { Accept: 'application/json' } })
                    .then(function (response) { return response.json(); })
                    .then(function (batch) {
                        const requested = Number(batch.requested || 0);
                        const published = Number(batch.published || 0);
                        const failed = Number(batch.failed || 0);
                        const totals = batch.metrics || {};
                        const labels = { queued: 'Na fila', running: 'Publicando', completed: 'Concluído' };
                        status.textContent = labels[batch.status] || batch.status;
                        document.getElementById('published-count').textContent = totals.published || 0;
                        document.getElementById('processed-count').textContent = totals.processed || 0;
                        document.getElementById('failed-count').textContent = totals.publish_failed || 0;
                        progress.textContent = 'Solicitadas ' + requested + ' · Publicadas ' + published + ' · Falhas ' + failed + (batch.status === 'completed' ? ' · Lote concluído.' : ' · Publicação em andamento.');
                        progressBar.max = Math.max(1, requested);
                        progressBar.value = published + failed;
                        if (batch.status === 'completed' && Array.isArray(batch.samples)) {
                            const messageList = document.getElementById('message-list');
                            messageList.replaceChildren.apply(messageList, batch.samples.map(function (sample) {
                                const article = document.createElement('article');
                                article.className = 'message';
                                const meta = document.createElement('div');
                                meta.className = 'message-meta';
                                meta.textContent = 'message_id: ' + sample.message_id + ' · idempotency: ' + sample.idempotency_key;
                                const payload = document.createElement('pre');
                                payload.textContent = JSON.stringify(sample.payload, null, 2);
                                article.append(meta, payload);
                                return article;
                            }));
                        }
                        if (batch.status !== 'completed') {
                            window.setTimeout(poll, 500);
                        }
                    })
                    .catch(function () { window.setTimeout(poll, 1000); });
            };
            poll();
        }());
    </script>
@endif
</body>
</html>
