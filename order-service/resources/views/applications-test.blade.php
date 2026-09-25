<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mirabel / Applications Test</title>
    <style>
        :root { --ink: #18212a; --paper: #eef2f1; --card: #fff; --blue: #176b87; --coral: #d85d4c; --line: #cedbd9; }
        * { box-sizing: border-box; }
        body { margin: 0; color: var(--ink); background: linear-gradient(135deg, #eef2f1, #f8f4ed); font-family: Georgia, 'Times New Roman', serif; }
        main { width: min(1120px, calc(100% - 40px)); margin: auto; padding: 34px 0 60px; }
        nav { display: flex; justify-content: space-between; align-items: center; gap: 16px; font: 700 13px Arial, sans-serif; }
        nav a { color: var(--blue); text-decoration: none; }
        .tab { padding: 10px 14px; border: 1px solid var(--blue); }
        header { max-width: 760px; margin: 56px 0 30px; }
        .kicker { color: var(--coral); font: 700 12px Arial, sans-serif; letter-spacing: .14em; text-transform: uppercase; }
        h1 { margin: 12px 0; font-size: clamp(2.6rem, 7vw, 6rem); line-height: .9; font-weight: 500; letter-spacing: -.045em; }
        .intro { max-width: 620px; color: #52616a; font: 16px/1.6 Arial, sans-serif; }
        .lab { display: grid; grid-template-columns: 330px 1fr; gap: 20px; align-items: start; }
        section { border: 1px solid var(--line); background: rgba(255,255,255,.88); padding: 25px; box-shadow: 8px 8px 0 rgba(23,107,135,.08); }
        h2 { margin: 0 0 8px; font-size: 25px; font-weight: 500; }
        label { display: block; margin: 19px 0 6px; color: #52616a; font: 700 11px Arial, sans-serif; letter-spacing: .08em; text-transform: uppercase; }
        input { width: 100%; padding: 12px; border: 1px solid #aebfbd; font: 18px Georgia, serif; }
        button { width: 100%; margin-top: 24px; padding: 15px; border: 0; background: var(--blue); color: white; cursor: pointer; font: 700 13px Arial, sans-serif; text-transform: uppercase; }
        button:hover { background: #0e5065; }
        .why { color: #52616a; font: 14px/1.55 Arial, sans-serif; }
        .result { margin-top: 20px; border-left: 3px solid var(--coral); padding: 14px; background: #fff2ed; font: 14px/1.5 Arial, sans-serif; }
        .result strong { color: var(--blue); }
        .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
        .stat { padding: 16px; border-top: 3px solid var(--blue); background: #f8fbfa; }
        .stat span { display: block; color: #52616a; font: 700 10px Arial, sans-serif; text-transform: uppercase; }
        .stat strong { display: block; margin-top: 8px; color: var(--blue); font-size: 32px; font-weight: 500; }
        .table-wrap { margin-top: 20px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font: 13px Arial, sans-serif; }
        th, td { padding: 12px 10px; border-bottom: 1px solid var(--line); text-align: left; }
        th { color: #52616a; font-size: 11px; text-transform: uppercase; }
        .outcome { color: var(--blue); font-weight: 700; }
        .outcome.error_queue { color: var(--coral); }
        @media (max-width: 800px) { main { width: min(100% - 24px, 600px); } .lab { display: block; } .lab section + section { margin-top: 18px; } .stats { grid-template-columns: repeat(2, 1fr); } }
    </style>
</head>
<body>
<main>
    <nav>
        <a href="{{ url('/') }}">← Message Lab</a>
        <span><a class="tab" href="{{ url('/applications-test') }}">Aplicações teste</a> <a href="{{ url('/black-friday') }}">Black Friday Cart</a> · <a href="{{ url('/stress-test') }}">Stress Lab →</a></span>
    </nav>
    <header>
        <div class="kicker">Mirabel / Resilience playground</div>
        <h1>Teste antes de quebrar em produção.</h1>
        <p class="intro">Uma simulação controlada para descobrir como seus pedidos se comportam diante de falhas transitórias, mensagens duplicadas e limite de tentativas.</p>
    </header>

    <div class="lab">
        <section>
            <h2>Scenario</h2>
            <p class="why">A simulação não publica no RabbitMQ. Ela executa a mesma decisão operacional: processar, tentar novamente ou enviar para a error queue.</p>
            <form method="POST" action="{{ url('/applications-test/simulate') }}">
                @csrf
                <label for="quantity">Orders</label>
                <input id="quantity" name="quantity" type="number" min="1" max="1000" value="100" required>
                <label for="failure_rate">Transient failure rate (%)</label>
                <input id="failure_rate" name="failure_rate" type="number" min="0" max="100" step="0.1" value="18" required>
                <label for="duplicate_rate">Duplicate rate (%)</label>
                <input id="duplicate_rate" name="duplicate_rate" type="number" min="0" max="100" step="0.1" value="6" required>
                <label for="max_attempts">Maximum attempts</label>
                <input id="max_attempts" name="max_attempts" type="number" min="1" max="5" value="3" required>
                <button type="submit">Run simulation</button>
            </form>
            @if ($errors->any())
                <div class="result">{{ $errors->first() }}</div>
            @endif
        </section>

        <section>
            <h2>Decision surface</h2>
            @if (session('simulation'))
                @php($simulation = session('simulation'))
                <p class="result">Simulation finished: <strong>{{ $simulation['quantity'] }} orders evaluated</strong> with {{ $simulation['max_attempts'] }} maximum attempts.</p>
                <div class="stats">
                    <div class="stat"><span>Processed</span><strong>{{ $simulation['summary']['processed'] }}</strong></div>
                    <div class="stat"><span>Retries</span><strong>{{ $simulation['summary']['retried'] }}</strong></div>
                    <div class="stat"><span>Duplicates</span><strong>{{ $simulation['summary']['duplicates'] }}</strong></div>
                    <div class="stat"><span>Error queue</span><strong>{{ $simulation['summary']['error_queue'] }}</strong></div>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Order</th><th>Attempts</th><th>Outcome</th></tr></thead>
                        <tbody>
                        @foreach ($simulation['sample'] as $order)
                            <tr><td>{{ $order['order_id'] }}</td><td>{{ $order['attempts'] ?: 'deduped' }}</td><td class="outcome {{ $order['outcome'] }}">{{ $order['outcome'] }}</td></tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="why">Configure a scenario on the left and run it to see where the traffic ends up. This makes retry and idempotency behavior visible before connecting a real consumer.</p>
            @endif
        </section>
    </div>
</main>
</body>
</html>
