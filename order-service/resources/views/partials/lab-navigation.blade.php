@php
    $currentPath = '/' . ltrim(request()->path(), '/');
    $labPages = [
        ['path' => '/', 'label' => 'Message Lab'],
        ['path' => '/applications-test', 'label' => 'Applications Test'],
        ['path' => '/black-friday', 'label' => 'Black Friday'],
        ['path' => '/stress-test', 'label' => 'Stress Test'],
        ['path' => '/consumer-lab', 'label' => 'Consumer Lab'],
        ['path' => '/production-readiness', 'label' => 'Production Gate'],
    ];
@endphp
<style>
    .lab-navigation { display:flex; justify-content:space-between; align-items:center; gap:18px; margin-bottom:24px; padding-bottom:12px; border-bottom:1px solid var(--line, #d8d4c8); font:700 12px/1.2 Arial,sans-serif; }
    .lab-navigation__brand { flex:none; color:var(--ink, #17212b); text-decoration:none; white-space:nowrap; }
    .lab-navigation__links { display:flex; flex-wrap:wrap; justify-content:flex-end; gap:3px; }
    .lab-navigation__link { padding:9px 10px; border:1px solid transparent; color:var(--muted, #68777d); text-decoration:none; white-space:nowrap; }
    .lab-navigation__link:hover, .lab-navigation__link:focus-visible { border-color:var(--line, #d8d4c8); color:var(--blue, var(--green, #176b87)); }
    .lab-navigation__link.is-active { border-color:var(--blue, var(--green, #176b87)); background:var(--blue, var(--green, #176b87)); color:#fff; }
    .lab-navigation__link:focus-visible { outline:2px solid var(--orange, var(--coral, #d85d4c)); outline-offset:2px; }
    @media (max-width:760px) {
        .lab-navigation { align-items:flex-start; flex-direction:column; gap:10px; }
        .lab-navigation__links { justify-content:flex-start; width:100%; }
        .lab-navigation__link { padding:8px; }
    }
</style>
<nav class="lab-navigation" aria-label="Navegação das páginas de teste">
    <a class="lab-navigation__brand" href="{{ url('/') }}">MIRABEL / LABS</a>
    <div class="lab-navigation__links">
        @foreach ($labPages as $page)
            <a href="{{ url($page['path']) }}" class="lab-navigation__link{{ $currentPath === $page['path'] ? ' is-active' : '' }}" @if ($currentPath === $page['path']) aria-current="page" @endif>{{ $page['label'] }}</a>
        @endforeach
    </div>
</nav>