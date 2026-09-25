<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mirabel / Black Friday Cart</title>
    <style>
        :root { --ink: #1c2024; --paper: #f5f2ed; --card: #fff; --blue: #176b87; --red: #d85d4c; --line: #ddd6cc; --muted: #74716d; }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--paper); color: var(--ink); font-family: Arial, sans-serif; }
        main { width: min(1180px, calc(100% - 40px)); margin: auto; padding: 26px 0 70px; }
        nav { display: flex; justify-content: space-between; align-items: center; font-size: 13px; font-weight: 700; }
        nav a { color: var(--blue); text-decoration: none; }
        .eyebrow { margin-top: 70px; color: var(--red); font-size: 12px; font-weight: 800; letter-spacing: .15em; text-transform: uppercase; }
        h1 { max-width: 680px; margin: 10px 0; font-family: Georgia, serif; font-size: clamp(2.6rem, 7vw, 6.7rem); font-weight: 400; letter-spacing: -.06em; line-height: .9; }
        .intro { max-width: 650px; color: var(--muted); font-size: 16px; line-height: 1.55; }
        .shop { display: grid; grid-template-columns: 1fr 330px; gap: 22px; margin-top: 38px; align-items: start; }
        .products { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
        .product, .cart { border: 1px solid var(--line); background: var(--card); }
        .product-art { height: 145px; display: grid; place-items: center; color: white; font-family: Georgia, serif; font-size: 28px; }
        .product-body { padding: 17px; }
        .category { color: var(--muted); font-size: 11px; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; }
        h2 { margin: 6px 0 12px; font-family: Georgia, serif; font-size: 23px; font-weight: 400; }
        .price { color: var(--blue); font-size: 20px; font-weight: 800; }
        .stock { margin-top: 8px; color: var(--muted); font-size: 12px; }
        .add { width: 100%; margin-top: 17px; padding: 12px; border: 0; background: var(--ink); color: white; cursor: pointer; font-weight: 800; }
        .add:hover { background: var(--blue); }
        .cart { position: sticky; top: 18px; padding: 20px; }
        .cart h2 { display: flex; justify-content: space-between; align-items: center; }
        .cart-count { color: var(--red); font: 700 13px Arial, sans-serif; }
        .cart-list { display: grid; gap: 10px; min-height: 85px; }
        .cart-row { display: grid; grid-template-columns: 1fr auto; gap: 8px; border-bottom: 1px solid var(--line); padding-bottom: 10px; font-size: 13px; }
        .cart-row small { display: block; margin-top: 4px; color: var(--muted); }
        .stepper { display: flex; align-items: center; gap: 8px; }
        .stepper button { width: 24px; height: 24px; border: 1px solid var(--line); background: var(--paper); cursor: pointer; }
        .empty { color: var(--muted); font-size: 13px; line-height: 1.5; }
        .total { display: flex; justify-content: space-between; margin-top: 18px; padding-top: 16px; border-top: 2px solid var(--ink); font-weight: 800; }
        .checkout { width: 100%; margin-top: 18px; padding: 14px; border: 0; background: var(--red); color: white; cursor: pointer; font-weight: 800; }
        .checkout:disabled { cursor: not-allowed; opacity: .45; }
        .feedback { margin-top: 14px; padding: 13px; background: #edf7f7; color: var(--blue); font-size: 13px; line-height: 1.5; }
        .feedback.warning { background: #fff0ec; color: var(--red); }
        @media (max-width: 850px) { main { width: min(100% - 24px, 620px); } .shop, .products { display: block; } .product { margin-bottom: 14px; } .cart { position: static; margin-top: 20px; } }
    </style>
</head>
<body>
<main>
    @include('partials.lab-navigation')
    <div class="eyebrow">Mirabel / Black Friday 2026</div>
    <h1>Compre rápido.<br>Reserve certo.</h1>
    <p class="intro">Um carrinho de teste para um problema real de alta demanda: impedir que duas compras confirmem o mesmo estoque durante o pico da Black Friday.</p>

    <div class="shop">
        <div class="products">
            @foreach ($products as $product)
                <article class="product">
                    <div class="product-art" style="background: {{ $product['accent'] }}">{{ strtoupper(substr($product['name'], 0, 1)) }}</div>
                    <div class="product-body">
                        <div class="category">{{ $product['category'] }}</div>
                        <h2>{{ $product['name'] }}</h2>
                        <div class="price">R$ {{ number_format($product['price'] / 100, 2, ',', '.') }}</div>
                        <div class="stock">{{ $product['stock'] }} unidades disponíveis</div>
                        <button class="add" type="button" data-product="{{ $product['id'] }}">Adicionar ao carrinho</button>
                    </div>
                </article>
            @endforeach
        </div>

        <aside class="cart">
            <h2>Carrinho <span class="cart-count" id="cart-count">0 itens</span></h2>
            <div class="cart-list" id="cart-list"><p class="empty">Seu carrinho está vazio.</p></div>
            <div class="total"><span>Total</span><span id="cart-total">R$ 0,00</span></div>
            <button class="checkout" id="checkout" type="button" disabled>Simular checkout</button>
            <div id="feedback" hidden></div>
        </aside>
    </div>
</main>
<script>
    const products = @json($products);
    const productMap = Object.fromEntries(products.map(function (product) { return [product.id, product]; }));
    const cart = {};
    const cartList = document.getElementById('cart-list');
    const cartCount = document.getElementById('cart-count');
    const cartTotal = document.getElementById('cart-total');
    const checkout = document.getElementById('checkout');
    const feedback = document.getElementById('feedback');
    const money = function (cents) { return 'R$ ' + (cents / 100).toFixed(2).replace('.', ','); };

    function renderCart() {
        const ids = Object.keys(cart).filter(function (id) { return cart[id] > 0; });
        const totalItems = ids.reduce(function (sum, id) { return sum + cart[id]; }, 0);
        const total = ids.reduce(function (sum, id) { return sum + cart[id] * productMap[id].price; }, 0);
        cartCount.textContent = totalItems + (totalItems === 1 ? ' item' : ' itens');
        cartTotal.textContent = money(total);
        checkout.disabled = ids.length === 0;
        if (!ids.length) {
            cartList.innerHTML = '<p class="empty">Seu carrinho está vazio.</p>';
            return;
        }
        cartList.innerHTML = ids.map(function (id) {
            const product = productMap[id];
            return '<div class="cart-row"><div><strong>' + product.name + '</strong><small>' + money(product.price) + '</small></div><div class="stepper"><button type="button" data-minus="' + id + '">-</button><span>' + cart[id] + '</span><button type="button" data-plus="' + id + '">+</button></div></div>';
        }).join('');
    }

    document.querySelectorAll('[data-product]').forEach(function (button) {
        button.addEventListener('click', function () {
            const id = button.dataset.product;
            cart[id] = Math.min((cart[id] || 0) + 1, productMap[id].stock + 3);
            renderCart();
        });
    });
    cartList.addEventListener('click', function (event) {
        const plus = event.target.dataset.plus;
        const minus = event.target.dataset.minus;
        const id = plus || minus;
        if (!id) return;
        cart[id] = Math.max(0, (cart[id] || 0) + (plus ? 1 : -1));
        renderCart();
    });
    checkout.addEventListener('click', function () {
        checkout.disabled = true;
        fetch('{{ url('/black-friday/checkout') }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ items: cart })
        }).then(function (response) { return response.json(); }).then(function (result) {
            feedback.hidden = false;
            feedback.className = result.status === 'approved' ? 'feedback' : 'feedback warning';
            feedback.textContent = result.status === 'approved'
                ? 'Reserva aprovada. Total: R$ ' + result.total + '.'
                : 'Checkout parcial. ' + result.blocked.map(function (item) { return item.name + ': pediu ' + item.requested + ', mas só há ' + item.available + '.'; }).join(' ');
        }).finally(function () { checkout.disabled = false; });
    });
</script>
</body>
</html>
