@php
    $currentPath = '/' . ltrim(request()->path(), '/');
    $labPages = [
        ['path' => '/', 'label' => 'Eventos'],
        ['path' => '/applications-test', 'label' => 'Resiliência'],
        ['path' => '/black-friday', 'label' => 'Estoque'],
        ['path' => '/stress-test', 'label' => 'Carga do publisher'],
        ['path' => '/consumer-lab', 'label' => 'Consumers'],
        ['path' => '/production-readiness', 'label' => 'Prontidão'],
    ];
@endphp
<style>
    .lab-guide { display:grid; grid-template-columns:1.2fr repeat(3,1fr); gap:18px; margin:22px 0 30px; padding:16px 0; border-top:1px solid var(--line,#d8d4c8); border-bottom:1px solid var(--line,#d8d4c8); }
    .lab-guide__item { min-width:0; }
    .lab-guide__label { display:block; margin-bottom:7px; color:var(--blue,var(--green,#176b87)); font:700 10px/1.3 Arial,sans-serif; text-transform:uppercase; }
    .lab-guide__title { margin:0; color:var(--ink,#17212b); font:400 21px/1.2 Georgia,serif; }
    .lab-guide__text { margin:0; color:var(--muted,#68777d); font:13px/1.55 Arial,sans-serif; }
    .lab-navigation { display:flex; justify-content:space-between; align-items:center; gap:18px; margin-bottom:24px; padding-bottom:12px; border-bottom:1px solid var(--line, #d8d4c8); font:700 12px/1.2 Arial,sans-serif; }
    .lab-navigation__brand { flex:none; color:var(--ink, #17212b); text-decoration:none; white-space:nowrap; }
    .lab-navigation__links { display:flex; flex-wrap:wrap; justify-content:flex-end; gap:3px; }
    .lab-navigation__link { padding:9px 10px; border:1px solid transparent; color:var(--muted, #68777d); text-decoration:none; white-space:nowrap; }
    .lab-navigation__link:hover, .lab-navigation__link:focus-visible { border-color:var(--line, #d8d4c8); color:var(--blue, var(--green, #176b87)); }
    .lab-navigation__link.is-active { border-color:var(--blue, var(--green, #176b87)); background:var(--blue, var(--green, #176b87)); color:#fff; }
    .lab-navigation__link:focus-visible { outline:2px solid var(--orange, var(--coral, #d85d4c)); outline-offset:2px; }
    @media (max-width:760px) {
        .lab-guide { grid-template-columns:repeat(2,minmax(0,1fr)); }
        .lab-navigation { align-items:flex-start; flex-direction:column; gap:10px; }
        .lab-navigation__links { justify-content:flex-start; width:100%; }
        .lab-navigation__link { padding:8px; }
    }
    @media (max-width:460px) { .lab-guide { grid-template-columns:1fr; gap:12px; } }
</style>
<nav class="lab-navigation" aria-label="Navegação das páginas de teste">
    <a class="lab-navigation__brand" href="{{ url('/') }}">MIRABEL / LABS</a>
    <div class="lab-navigation__links">
        @foreach ($labPages as $page)
            <a href="{{ url($page['path']) }}" class="lab-navigation__link{{ $currentPath === $page['path'] ? ' is-active' : '' }}" @if ($currentPath === $page['path']) aria-current="page" @endif>{{ $page['label'] }}</a>
        @endforeach
    </div>
</nav>