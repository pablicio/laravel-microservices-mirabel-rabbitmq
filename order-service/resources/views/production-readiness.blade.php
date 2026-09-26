<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mirabel / Production Readiness</title>
    <style>
        :root { --ink:#17212b; --paper:#eef3f4; --card:#fff; --blue:#176b87; --red:#d85d4c; --green:#31745c; --line:#c9d8dc; --muted:#64747b; }
        * { box-sizing:border-box; }
        body { margin:0; background:linear-gradient(135deg,#eef3f4,#f7eee6); color:var(--ink); font-family:Arial,sans-serif; }
        main { width:min(1160px,calc(100% - 40px)); margin:auto; padding:28px 0 70px; }
        nav { display:flex; justify-content:space-between; font-size:13px; font-weight:700; } nav a { color:var(--blue); text-decoration:none; }
        header { margin:65px 0 30px; max-width:780px; } .eyebrow { color:var(--red); font-size:12px; font-weight:800; letter-spacing:.14em; text-transform:uppercase; }
        h1 { margin:10px 0; font:400 clamp(2.7rem,7vw,6.2rem)/.9 Georgia,serif; letter-spacing:-.06em; } .intro { color:var(--muted); font-size:16px; line-height:1.6; }
        .layout { display:grid; grid-template-columns:340px 1fr; gap:20px; align-items:start; } section { border:1px solid var(--line); background:rgba(255,255,255,.9); padding:24px; box-shadow:8px 8px 0 rgba(23,107,135,.08); }
        label { display:block; margin:16px 0 6px; color:var(--muted); font-size:11px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; } input { width:100%; padding:12px; border:1px solid #b5c5c9; font-size:17px; }
        .checks { display:grid; gap:9px; margin-top:16px; } .check { display:flex; gap:9px; align-items:center; color:var(--muted); font-size:13px; } .check input { width:auto; }
        button { width:100%; margin-top:22px; padding:15px; border:0; background:var(--red); color:#fff; cursor:pointer; font-weight:800; text-transform:uppercase; }
        h2 { margin:0 0 8px; font:400 28px Georgia,serif; } h3 { margin:0 0 8px; font:400 22px Georgia,serif; }
        .verdict { display:flex; justify-content:space-between; gap:12px; align-items:center; padding:17px; background:#edf7f1; color:var(--green); } .verdict.pilot { background:#fff6df; color:#8b6f32; } .verdict.not_ready { background:#fff0ec; color:var(--red); }
        .verdict strong { font-size:30px; } .stats { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; margin:20px 0; } .stat { padding:14px; border-top:3px solid var(--blue); background:#f5f9fa; } .stat span { display:block; color:var(--muted); font-size:10px; font-weight:800; text-transform:uppercase; } .stat strong { display:block; margin-top:7px; color:var(--blue); font:400 25px Georgia,serif; }
        .checks-result { display:grid; gap:10px; margin-top:18px; } .result-row { display:grid; grid-template-columns:145px 1fr; gap:12px; border-bottom:1px solid var(--line); padding:12px 0; font-size:13px; } .pass { color:var(--green); font-weight:800; } .warn { color:#8b6f32; font-weight:800; } .fail { color:var(--red); font-weight:800; }
        .empty { color:var(--muted); line-height:1.6; } .note { color:var(--muted); font-size:12px; line-height:1.5; }
        @media(max-width:800px) { main{width:min(100% - 24px,620px)} .layout{display:block}.layout section+section{margin-top:18px}.stats{grid-template-columns:repeat(2,1fr)} }
    </style>
</head>
<body>
<main>
    @include('partials.lab-navigation')
    <header><div class="eyebrow">Mirabel / Production gate</div><h1>Está pronto para produção?</h1><p class="intro">Monte um cenário parecido com o seu pico real e receba uma leitura simples: o que está saudável, o que falta testar e onde a fila pode crescer.</p></header>
    @include('partials.lab-guide', [
        'title' => 'Capacidade e critérios de prontidão',
        'concept' => 'Backlog por segundo = máximo entre zero e (entrada sustentada − capacidade de consumo). Se o resultado for positivo, a fila cresce.',
        'observe' => 'Compare capacidade, latência p95, falhas, publisher confirms e evidência de failover.',
        'limit' => 'A pontuação é uma triagem heurística com valores informados, não certificação. Valide as taxas na infraestrutura alvo.',
    ])
    <div class="layout">
        <section>
            <h2>Cenário</h2>
            <form method="POST" action="{{ url('/production-readiness/evaluate') }}">
                @csrf
                <label for="messages">Mensagens do pico</label><input id="messages" name="messages" type="number" min="1000" max="1000000" value="100000" required>
                <label for="users">Usuários simultâneos estimados</label><input id="users" name="users" type="number" min="1" max="100000" value="5000" required>
                <label for="publishers">Publishers concorrentes</label><input id="publishers" name="publishers" type="number" min="1" max="8" value="1" required>
                <label for="consumers">Consumers concorrentes</label><input id="consumers" name="consumers" type="number" min="1" max="8" value="4" required>
                <label for="message_rate">Entrada esperada (msg/s)</label><input id="message_rate" name="message_rate" type="number" min="1" max="50000" value="7000" required>
                <label for="consumer_rate">Processamento por consumer (msg/s)</label><input id="consumer_rate" name="consumer_rate" type="number" min="100" max="20000" value="2000" required>
                <label for="p95_ms">Latência p95 (ms)</label><input id="p95_ms" name="p95_ms" type="number" min="1" max="10000" value="120" required>
                <label for="failure_rate">Falhas observadas (%)</label><input id="failure_rate" name="failure_rate" type="number" min="0" max="100" step="0.1" value="0" required>
                <div class="checks"><label class="check"><input type="checkbox" name="confirms" value="1"> Publisher confirms habilitado</label><label class="check"><input type="checkbox" name="failover" value="1"> Failover já foi testado</label></div>
                <button type="submit">Avaliar cenário</button>
            </form>
            @if ($errors->any()) <p class="note">{{ $errors->first() }}</p> @endif
        </section>
        <section>
            @if (session('readiness'))
                @php($report = session('readiness'))
                @php($verdictLabel = ['production_candidate' => 'Candidato a produção', 'pilot' => 'Liberar como piloto', 'not_ready' => 'Ainda não pronto'][$report['verdict']])
                <div class="verdict {{ $report['verdict'] }}"><span>{{ $verdictLabel }}</span><strong>{{ $report['score'] }}/100</strong></div>
                <div class="stats"><div class="stat"><span>Mensagens</span><strong>{{ number_format($report['messages'], 0, ',', '.') }}</strong></div><div class="stat"><span>Pico estimado</span><strong>{{ number_format($report['duration_seconds'], 0, ',', '.') }}s</strong></div><div class="stat"><span>Capacidade publisher</span><strong>{{ number_format($report['publisher_capacity'], 0, ',', '.') }}/s</strong></div><div class="stat"><span>Backlog por segundo</span><strong>{{ number_format($report['backlog_per_second'], 0, ',', '.') }}</strong></div></div>
                <h3>O que precisa de atenção</h3>
                <div class="checks-result">@foreach ($report['checks'] as $check)<div class="result-row"><div class="{{ $check['status'] }}">{{ $check['status'] === 'pass' ? 'OK' : ($check['status'] === 'warn' ? 'Atenção' : 'Bloqueio') }}</div><div><strong>{{ $check['name'] }}</strong><br><span class="note">{{ $check['detail'] }}</span></div></div>@endforeach</div>
            @else
                <h2>Resultado</h2><p class="empty">Preencha o cenário ao lado. O relatório compara a entrada de mensagens com a capacidade estimada de publishers e consumers e verifica os controles que costumam faltar antes de uma entrada em produção.</p>
            @endif
        </section>
    </div>
</main>
</body>
</html>
