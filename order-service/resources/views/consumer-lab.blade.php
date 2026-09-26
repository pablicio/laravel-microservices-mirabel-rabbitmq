<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mirabel / Consumer Lab</title>
    <style>
        :root { --ink:#17212b; --paper:#edf2f3; --blue:#176b87; --green:#31745c; --red:#d85d4c; --line:#cad8dc; --muted:#68777d; }
        * { box-sizing:border-box; }
        body { margin:0; background:linear-gradient(135deg,#edf2f3,#f8eee7); color:var(--ink); font-family:Arial,sans-serif; }
        main { width:min(1080px,calc(100% - 40px)); margin:auto; padding:28px 0 70px; }
        header { margin:52px 0 22px; max-width:760px; }
        .eyebrow { color:var(--red); font-size:12px; font-weight:800; text-transform:uppercase; }
        h1 { margin:10px 0; font:400 54px/.98 Georgia,serif; }
        h2 { margin:0; font:400 25px Georgia,serif; }
        .intro,.note { color:var(--muted); font-size:14px; line-height:1.6; }
        .layout { display:grid; grid-template-columns:300px 1fr; gap:20px; align-items:start; }
        section { min-width:0; border-top:3px solid var(--blue); padding:20px 0; }
        label { display:block; margin:17px 0 6px; color:var(--muted); font-size:11px; font-weight:800; text-transform:uppercase; }
        input { width:100%; padding:12px; border:1px solid #afc0c5; font:20px Georgia,serif; }
        button { width:100%; margin-top:20px; padding:14px; border:0; background:var(--red); color:#fff; cursor:pointer; font-weight:800; text-transform:uppercase; }
        button:hover { background:#b94336; }
        .run-title { display:flex; justify-content:space-between; gap:12px; align-items:center; }
        .state { color:var(--blue); font-size:12px; font-weight:800; text-align:right; text-transform:uppercase; }
        progress { width:100%; height:16px; margin:18px 0; accent-color:var(--blue); }
        .stats { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:9px; }
        .stat { min-width:0; padding:12px; border-top:2px solid var(--blue); background:rgba(255,255,255,.72); }
        .stat span { display:block; color:var(--muted); font-size:10px; font-weight:800; text-transform:uppercase; }
        .stat strong { display:block; margin-top:7px; color:var(--blue); font:400 24px Georgia,serif; overflow-wrap:anywhere; }
        .explain { margin-top:16px; padding:14px; background:#edf7f1; color:var(--green); font-size:13px; line-height:1.55; }
        .explain.warning { background:#fff0ec; color:var(--red); }
        .history { margin-top:22px; overflow:auto; }
        table { width:100%; border-collapse:collapse; font-size:12px; }
        th,td { padding:10px 8px; border-bottom:1px solid var(--line); text-align:left; white-space:nowrap; }
        th { color:var(--muted); font-size:10px; text-transform:uppercase; }
        @media(max-width:760px) {
            main { width:min(100% - 24px,620px); }
            h1 { font-size:42px; }
            .layout { display:block; }
            .layout section+section { margin-top:18px; }
            .stats { grid-template-columns:repeat(2,minmax(0,1fr)); }
        }
    </style>
</head>
<body>
<main>
    @include('partials.lab-navigation')
    <header>
        <div class="eyebrow">Mirabel / Consumer Lab</div>
        <h1>Publique. Consuma. Meça.</h1>
        <p class="intro">Acompanhe eventos reais no RabbitMQ e veja como o número de consumers afeta a fila, as confirmações e o tempo de processamento.</p>
    </header>
    @include('partials.lab-guide', [
        'title' => 'Vazão e concorrência de consumers',
        'concept' => 'Consumers retiram mensagens da fila; se a entrada supera o processamento, o backlog cresce. Acks confirmados indicam entregas finalizadas.',
        'observe' => 'Compare processadas, falhas, retries pendentes, consumers ativos e tempo até as filas estabilizarem.',
        'limit' => 'A medição usa estatísticas cumulativas da fila compartilhada. Tráfego externo e a worker real também influenciam a vazão.',
    ])

    @if ($errors->has('consumers'))
        <p class="explain warning" role="alert">{{ $errors->first('consumers') }}</p>
    @endif

    <div class="layout">
        <section>
            <h2>Cenário</h2>
            <form method="POST" action="{{ url('/consumer-lab/start') }}">
                @csrf
                <label for="messages">Mensagens a publicar</label>
                <input id="messages" name="messages" type="number" min="1" max="100000" value="1000" required>
                <label for="consumers">Consumers ativos</label>
                <input id="consumers" name="consumers" type="number" min="1" max="8" value="1" required>
                <p class="note">O teste precisa acessar RabbitMQ Management e começa somente com as filas principal e retry vazias. Até 8 workers reais.</p>
                <button type="submit">Iniciar medição</button>
            </form>
        </section>

        <section aria-live="polite">
            <div class="run-title">
                <h2>Execução atual</h2>
                <span class="state" id="state">{{ session('consumer_run_id') ? 'Na fila' : 'Ocioso' }}</span>
            </div>
            <progress id="progress" max="1" value="0"></progress>
            <div class="stats">
                <div class="stat"><span>Publicadas</span><strong id="published">—</strong></div>
                <div class="stat"><span>Processadas</span><strong id="processed">—</strong></div>
                <div class="stat"><span>Falhas finais</span><strong id="errors">—</strong></div>
                <div class="stat"><span>Error queue atual</span><strong id="error-backlog">—</strong></div>
                <div class="stat"><span>Fila principal</span><strong id="backlog">—</strong></div>
                <div class="stat"><span>Fila retry</span><strong id="retry-backlog">—</strong></div>
                <div class="stat"><span>Aguardando ack</span><strong id="unacknowledged">—</strong></div>
                <div class="stat"><span>Consumers ativos</span><strong id="consumers">—</strong></div>
                <div class="stat"><span>Tempo total</span><strong id="elapsed">—</strong></div>
                <div class="stat"><span>Não contabilizadas</span><strong id="difference">—</strong></div>
                <div class="stat"><span>Vazão efetiva</span><strong id="throughput">—</strong></div>
            </div>
            <div class="explain" id="explain">Inicie uma medição para observar confirmações do broker, backlog e retries.</div>
        </section>
    </div>

    @if (!empty($history))
        @php($statusLabels = ['completed' => 'Concluído', 'completed_with_errors' => 'Concluído com erros', 'incomplete' => 'Incompleto', 'legacy' => 'Medição anterior'])
        <section class="history">
            <h2>Histórico de consumos</h2>
            <p class="note">Execuções anteriores não registravam acks do broker; seus contadores e vazão não são comparáveis. Novas medições separam processadas, falhas e filas pendentes.</p>
            <table>
                <thead><tr>
                    <th>Encerrado em</th><th>Situação</th><th>Publicadas</th><th>Processadas</th><th>Falhas</th>
                    <th>Diferença</th><th>Configurados / ativos</th><th>Tempo total</th><th>Vazão efetiva</th><th>Principal / retry / erro</th>
                </tr></thead>
                <tbody>
                @foreach ($history as $run)
                    @php($legacyMeasurement = ($run['measurement'] ?? null) !== 'queue_ack_v1')
                    @php($historyStatus = $legacyMeasurement ? 'legacy' : ($run['status'] ?? ((int) ($run['processed'] ?? 0) >= (int) ($run['published'] ?? 0) ? 'completed' : 'incomplete')))
                    @php($historyErrors = (int) ($run['errors'] ?? 0))
                    @php($historyDifference = max(0, (int) ($run['published'] ?? 0) - (int) ($run['processed'] ?? 0) - $historyErrors))
                    <tr>
                        <td>{{ isset($run['completed_at']) ? \Illuminate\Support\Carbon::parse($run['completed_at'])->format('d/m/Y H:i') : '—' }}</td>
                        <td>{{ $statusLabels[$historyStatus] ?? 'Incompleto' }}</td>
                        <td>{{ number_format($run['published'] ?? 0, 0, ',', '.') }}</td>
                        <td>{{ $legacyMeasurement ? '—' : number_format($run['processed'] ?? 0, 0, ',', '.') }}</td>
                        <td>{{ $legacyMeasurement ? '—' : number_format($historyErrors, 0, ',', '.') }}</td>
                        <td>{{ $legacyMeasurement ? '—' : number_format($historyDifference, 0, ',', '.') }}</td>
                        <td>{{ $run['requested_consumers'] ?? '—' }} / {{ $run['consumers'] ?? 0 }}</td>
                        <td>{{ number_format(($run['consumer_elapsed_ms'] ?? 0) / 1000, 2, ',', '.') }}s</td>
                        <td>{{ !$legacyMeasurement && in_array($historyStatus, ['completed', 'completed_with_errors'], true) ? number_format($run['throughput'] ?? 0, 2, ',', '.') . ' msg/s' : '—' }}</td>
                        <td>{{ $run['backlog'] ?? '—' }} / {{ $legacyMeasurement ? '— / —' : (($run['retry_backlog'] ?? 0) . ' / ' . ($run['error_backlog'] ?? 0)) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>
    @endif
</main>
@if (session('consumer_run_id'))
<script>
(function () {
    const runId = @json(session('consumer_run_id'));
    const url = @json(url('/consumer-lab/status')) + '?run_id=' + encodeURIComponent(runId);
    const terminal = ['completed', 'completed_with_errors', 'incomplete'];
    const labels = {
        queued: 'Na fila', running: 'Publicando', draining: 'Aguardando retries',
        monitoring_unavailable: 'Monitoramento indisponível', completed: 'Concluído',
        completed_with_errors: 'Concluído com erros', incomplete: 'Incompleto'
    };
    const set = function (id, value) { document.getElementById(id).textContent = value; };
    const count = function (value) { return value === null || value === undefined ? '—' : Number(value).toLocaleString('pt-BR'); };
    const explain = document.getElementById('explain');

    function poll() {
        fetch(url, { headers: { Accept: 'application/json' } })
            .then(function (response) {
                if (!response.ok) throw new Error('status');
                return response.json();
            })
            .then(function (run) {
                const available = run.status !== 'monitoring_unavailable';
                const processed = run.processed === null ? null : Number(run.processed);
                const errors = run.errors === null ? null : Number(run.errors);
                const published = Number(run.published || 0);
                const difference = run.difference === undefined ? null : run.difference;
                const elapsed = Number(run.consumer_elapsed_ms || 0);

                set('state', labels[run.status] || run.status);
                set('published', count(run.published));
                set('processed', count(run.processed));
                set('errors', count(run.errors));
                set('error-backlog', count(run.error_backlog));
                set('backlog', count(run.backlog));
                set('retry-backlog', count(run.retry_backlog));
                set('unacknowledged', count(run.unacknowledged));
                set('consumers', count(run.consumers));
                set('elapsed', elapsed ? (elapsed / 1000).toFixed(2).replace('.', ',') + 's' : '—');
                set('difference', count(difference));
                set('throughput', run.throughput === undefined ? '—' : Number(run.throughput).toLocaleString('pt-BR') + ' msg/s');
                document.getElementById('progress').max = Math.max(1, published);
                document.getElementById('progress').value = processed === null || errors === null ? 0 : processed + errors;
                explain.className = 'explain' + (run.status === 'incomplete' || run.status === 'monitoring_unavailable' ? ' warning' : '');

                if (run.status === 'monitoring_unavailable') {
                    explain.textContent = 'Não foi possível consultar as filas ou os acks no RabbitMQ Management. Os números aparecem como indisponíveis, não como zero.';
                } else if (run.status === 'draining') {
                    explain.textContent = 'O publisher terminou, mas ainda há mensagens na fila principal ou em retry. A execução só fecha quando estabilizar ou atingir 30 segundos.';
                } else if (run.status === 'completed_with_errors') {
                    explain.textContent = errors + ' mensagem(ns) foram encaminhadas à error queue; ' + processed + ' terminaram sem falha.';
                } else if (run.status === 'incomplete') {
                    explain.textContent = 'A execução não foi totalmente contabilizada. Verifique a diferença e as filas principal/retry; falhas de monitoramento não são convertidas em sucesso.';
                } else if (run.status === 'completed') {
                    explain.textContent = 'Todas as mensagens foram confirmadas e as filas principal e retry estabilizaram. Vazão efetiva: ' + Number(run.throughput || 0).toLocaleString('pt-BR') + ' msg/s.';
                } else if (available && Number(run.backlog || 0) > 0) {
                    explain.textContent = 'A fila principal ainda tem mensagens aguardando processamento.';
                } else {
                    explain.textContent = 'Aguardando o publisher e os consumers iniciarem; os contadores ainda não estão disponíveis.';
                }

                if (!terminal.includes(run.status)) window.setTimeout(poll, 800);
            })
            .catch(function () {
                explain.className = 'explain warning';
                explain.textContent = 'Não foi possível consultar o estado da execução. Tentando novamente.';
                window.setTimeout(poll, 1200);
            });
    }

    poll();
}());
</script>
@endif
</body>
</html>
