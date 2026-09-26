<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mirabel / Stress Lab</title>
    <style>
        :root { --ink:#171b20; --paper:#e9edf0; --card:#fff; --blue:#176b87; --red:#d85d4c; --line:#cbd6dc; --muted:#66727a; }
        * { box-sizing:border-box; }
        body { margin:0; background:linear-gradient(130deg,#e9edf0,#f8f1e9); color:var(--ink); font-family:Arial,sans-serif; }
        main { width:min(1100px,calc(100% - 40px)); margin:auto; padding:28px 0 70px; }
        nav { display:flex; justify-content:space-between; font-size:13px; font-weight:700; }
        nav a { color:var(--blue); text-decoration:none; }
        .eyebrow { margin-top:72px; color:var(--red); font-size:12px; font-weight:800; letter-spacing:.15em; text-transform:uppercase; }
        h1 { max-width:780px; margin:10px 0; font:400 clamp(2.8rem,7vw,6.5rem)/.9 Georgia,serif; letter-spacing:-.06em; }
        .intro { max-width:680px; color:var(--muted); font-size:16px; line-height:1.6; }
        .control { display:grid; grid-template-columns:1fr 180px; gap:20px; margin-top:38px; align-items:end; }
        section { border:1px solid var(--line); background:rgba(255,255,255,.9); padding:25px; box-shadow:8px 8px 0 rgba(23,107,135,.08); }
        label { display:block; margin-bottom:7px; color:var(--muted); font-size:11px; font-weight:800; letter-spacing:.1em; text-transform:uppercase; }
        input { width:100%; padding:15px; border:1px solid #aebcc3; font:22px Georgia,serif; }
        button { width:100%; padding:16px; border:0; background:var(--red); color:white; cursor:pointer; font-weight:800; text-transform:uppercase; }
        button:hover { background:#b94336; }
        .warning { margin-top:14px; color:var(--muted); font-size:13px; line-height:1.5; }
        .dashboard { margin-top:20px; }
        .status-line { display:flex; justify-content:space-between; gap:15px; align-items:baseline; }
        h2 { margin:0; font:400 28px Georgia,serif; }
        .state { color:var(--blue); font-weight:800; text-transform:uppercase; }
        progress { width:100%; height:18px; margin:22px 0; accent-color:var(--blue); }
        .stats { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; }
        .stat { padding:15px; border-top:3px solid var(--blue); background:#f5f9fa; }
        .stat span { display:block; color:var(--muted); font-size:10px; font-weight:800; text-transform:uppercase; }
        .stat strong { display:block; margin-top:8px; color:var(--blue); font:400 28px Georgia,serif; }
        .limit { margin-top:20px; padding:16px; background:#edf7f7; color:var(--blue); font-weight:700; }
        .report { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; margin-top:10px; color:var(--muted); font-size:12px; }
        .report strong { display:block; margin-top:4px; color:var(--ink); font-size:16px; }
        .limit.warning { background:#fff0ec; color:var(--red); }
        .health-report { margin-top:20px; border-top:1px solid var(--line); padding-top:20px; }
        .health-report h3 { margin:0 0 8px; font:400 24px Georgia,serif; }
        .health-report p { margin:6px 0; color:var(--muted); font-size:14px; line-height:1.5; }
        .health-report strong { color:var(--ink); }
        .history { margin-top:20px; overflow:auto; }
        .history table { width:100%; border-collapse:collapse; font-size:13px; }
        .history th, .history td { padding:12px 10px; border-bottom:1px solid var(--line); text-align:left; white-space:nowrap; }
        .history th { color:var(--muted); font-size:10px; text-transform:uppercase; }
        .health-badge { color:var(--blue); font-weight:800; }
        @media(max-width:760px) { main{width:min(100% - 24px,600px)} .control{display:block}.control button{margin-top:14px}.stats{grid-template-columns:repeat(2,1fr)}.stats .stat:last-child{grid-column:span 2} }
    </style>
</head>
<body>
<main>
    @include('partials.lab-navigation')
    <div class="eyebrow">Mirabel / Limite de carga</div>
    <h1>Descubra onde a fila cede.</h1>
    <p class="intro">Uma pancada controlada de mensagens reais para medir o limite do publisher, da conexão e do broker. O teste roda em segundo plano para você acompanhar sem travar a interface.</p>
    @include('partials.lab-guide', [
        'title' => 'Carga do publisher',
        'concept' => 'O teste mede quantos eventos o processo publica por segundo até o broker, além de falhas e memória do PHP.',
        'observe' => 'Aumente o lote gradualmente. Compare envios confirmados, duração e falhas; a média não é latência p95 nem vazão de consumers.',
        'limit' => '“Usuários” só distribui IDs no payload. O comando publica sequencialmente em um processo; não simula concorrência real.',
    ])

    <form class="control" method="POST" action="{{ url('/stress-test/start') }}">
        @csrf
        <section><label for="quantity">Mensagens reais</label><input id="quantity" name="quantity" type="number" min="1" max="1000000" value="1000" required><label for="users">Usuários simulados</label><input id="users" name="users" type="number" min="1" max="5000" value="250" required><p class="warning">Máximo protegido: 1.000.000 mensagens por execução. Em cargas grandes, acompanhe RAM, throughput e falhas.</p></section>
        <button type="submit">Iniciar teste de carga</button>
    </form>

    @php
        $healthLabels = ['healthy' => 'Saudável', 'degraded' => 'Atenção', 'critical' => 'Crítico'];
        $limitLabels = ['not_reached' => 'nenhum limite atingido', 'publisher_throughput' => 'velocidade de envio', 'connection_or_broker_errors' => 'falhas de conexão ou broker'];
        $currentHealth = $healthLabels[$stress['health'] ?? ''] ?? 'Aguardando teste';
        $currentLimit = $limitLabels[$stress['limit'] ?? ''] ?? 'ainda não medido';
    @endphp
    <section class="dashboard">
        <div class="status-line"><h2>Acompanhamento da carga</h2><span class="state" id="state">{{ ($stress['status'] ?? '') === 'completed' ? 'Finalizado' : ucfirst($stress['status'] ?? 'Aguardando') }}</span></div>
        <progress id="progress" max="{{ max(1, $stress['requested'] ?? 1) }}" value="{{ ($stress['published'] ?? 0) + ($stress['failed'] ?? 0) }}"></progress>
        <div class="stats">
            <div class="stat"><span>Mensagens testadas</span><strong id="requested">{{ number_format($stress['requested'] ?? 0, 0, ',', '.') }}</strong></div>
            <div class="stat"><span>Chegaram ao RabbitMQ</span><strong id="published">{{ number_format($stress['published'] ?? 0, 0, ',', '.') }}</strong></div>
            <div class="stat"><span>Não chegaram</span><strong id="failed">{{ number_format($stress['failed'] ?? 0, 0, ',', '.') }}</strong></div>
            <div class="stat"><span>Tempo total</span><strong id="elapsed">{{ number_format(($stress['elapsed_ms'] ?? 0) / 1000, 2, ',', '.') }}s</strong></div>
        </div>
        <div class="report">
            <div>Pessoas simuladas<strong id="users">{{ number_format($stress['users'] ?? 0, 0, ',', '.') }}</strong></div>
            <div>Mensagens por pessoa<strong id="messages-per-user">{{ $stress['messages_per_user'] ?? 0 }}</strong></div>
            <div>Memória usada pelo teste<strong id="memory">{{ $stress['memory_peak_mb'] ?? 0 }} MB</strong></div>
            <div>Velocidade média<strong id="throughput">{{ $stress['throughput'] ?? 0 }} msg/s</strong></div>
        </div>
        <div class="limit" id="limit">Saúde: {{ $currentHealth }} · Principal limite: {{ $currentLimit }}</div>
        <div class="health-report">
            <h3>O que esse resultado significa</h3>
            <p id="health-summary">{{ $stress['health_summary'] ?? 'Execute uma carga para gerar um diagnóstico explicado em linguagem simples.' }}</p>
            <p>Média agregada de envio por evento (não é p95): <strong id="latency">{{ $stress['latency_ms'] ?? 0 }} ms</strong> · Percentual com problema: <strong id="failure-rate">{{ $stress['failure_rate'] ?? 0 }}%</strong></p>
            <p>Próximo passo recomendado: <strong id="recommendation">{{ $stress['recommendation'] ?? 'Comece com 1.000 mensagens e aumente em etapas.' }}</strong></p>
            <p>A memória mostrada é a usada pelo processo PHP do teste, não toda a memória do computador.</p>
        </div>
    </section>
    @if (!empty($history))
        <section class="history">
            <h2>Histórico para comparar</h2>
            <p class="intro">Últimos testes concluídos. Compare velocidade, tempo, falhas e memória para saber se uma otimização realmente ajudou.</p>
            <table>
                <thead><tr><th>Quando</th><th>Mensagens</th><th>IDs de usuário</th><th>Duração</th><th>Vazão do publisher</th><th>Falhas</th><th>RAM</th><th>Saúde</th></tr></thead>
                <tbody>
                @foreach ($history as $run)
                    <tr>
                        <td>{{ $run['completed_at'] ?? '-' }}</td>
                        <td>{{ number_format($run['requested'] ?? 0, 0, ',', '.') }}</td>
                        <td>{{ number_format($run['users'] ?? 0, 0, ',', '.') }}</td>
                        <td>{{ number_format(($run['elapsed_ms'] ?? 0) / 1000, 2, ',', '.') }}s</td>
                        <td>{{ number_format($run['throughput'] ?? 0, 2, ',', '.') }} msg/s</td>
                        <td>{{ $run['failure_rate'] ?? 0 }}%</td>
                        <td>{{ $run['memory_peak_mb'] ?? 0 }} MB</td>
                        <td class="health-badge">{{ $run['health'] ?? '-' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>
    @endif
</main>
@if (session('stress_run_id'))
<script>
(function () {
    const runId = @json(session('stress_run_id'));
    const url = @json(url('/stress-test/status')) + '?run_id=' + encodeURIComponent(runId);
    const set = function (id, value) { document.getElementById(id).textContent = value; };
    const poll = function () {
        fetch(url, {headers: {Accept: 'application/json'}}).then(function (response) { return response.json(); }).then(function (run) {
            const requested = Number(run.requested || 0);
            const completed = Number(run.published || 0) + Number(run.failed || 0);
            const healthLabels = { healthy: 'Saudável', degraded: 'Atenção', critical: 'Crítico' };
            const limitLabels = { not_reached: 'nenhum limite atingido', publisher_throughput: 'velocidade de envio', connection_or_broker_errors: 'falhas de conexão ou broker' };
            set('state', run.status === 'completed' ? 'Finalizado' : (run.status || 'Aguardando'));
            set('requested', requested.toLocaleString('pt-BR'));
            set('users', Number(run.users || 0).toLocaleString('pt-BR'));
            set('published', Number(run.published || 0).toLocaleString('pt-BR'));
            set('failed', Number(run.failed || 0).toLocaleString('pt-BR'));
            set('elapsed', ((run.elapsed_ms || 0) / 1000).toFixed(2).replace('.', ',') + 's');
            set('throughput', (run.throughput || 0) + ' msg/s');
            set('messages-per-user', run.messages_per_user || 0);
            set('memory', (run.memory_peak_mb || 0) + ' MB');
            set('latency', (run.latency_ms || 0) + ' ms/message');
            set('failure-rate', (run.failure_rate || 0) + '%');
            set('health-summary', run.health_summary || 'Medindo a carga...');
            set('recommendation', run.recommendation || 'Aguardando o resultado final.');
            set('limit', 'Saúde: ' + (healthLabels[run.health] || 'Aguardando teste') + ' · Principal limite: ' + (limitLabels[run.limit] || 'ainda não medido'));
            document.getElementById('progress').max = Math.max(1, requested);
            document.getElementById('progress').value = completed;
            document.getElementById('limit').className = run.failed > 0 ? 'limit warning' : 'limit';
            if (run.status !== 'completed') window.setTimeout(poll, 500);
        }).catch(function () { window.setTimeout(poll, 1000); });
    };
    poll();
}());
</script>
@endif
</body>
</html>
