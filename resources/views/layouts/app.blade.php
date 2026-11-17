<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.head')
</head>
<body>
@php
    $user = auth()->user();
    $initials = collect(preg_split('/\s+/', trim($user->name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->join('');
    $navProjects = request()->routeIs('projects.*', 'deliverables.*');
    $unread = $user->unreadNotifications()->count();
@endphp
<div class="shell">
    <aside class="side" aria-label="Main">
        <div class="side-top">
            <a class="brand" href="{{ route('home') }}" aria-label="TIF PMO home">
                @include('partials.logo', ['class' => 'brand-logo', 'width' => 150])
                <span class="brand-app">TIF PMO · WBS</span>
            </a>
            <button class="icon-btn theme-toggle" type="button" data-theme-toggle title="Switch theme" aria-label="Switch theme">
                <span class="when-light">@include('partials.icon', ['name' => 'moon'])</span>
                <span class="when-dark">@include('partials.icon', ['name' => 'sun'])</span>
            </button>
        </div>

        <nav class="side-nav">
            <span class="side-label">Workspace</span>
            <a href="{{ route('home') }}" @if (request()->routeIs('home')) aria-current="page" @endif>
                @include('partials.icon', ['name' => 'home']) Dashboard
            </a>
            <a href="{{ route('projects.index') }}" @if ($navProjects) aria-current="page" @endif>
                @include('partials.icon', ['name' => 'folder']) Projects
            </a>
            <a href="{{ route('notifications.index') }}" @if (request()->routeIs('notifications.*')) aria-current="page" @endif>
                @include('partials.icon', ['name' => 'bell']) Notifications
                @if ($unread) <span class="nav-badge" aria-label="{{ $unread }} unread">{{ $unread > 99 ? '99+' : $unread }}</span> @endif
            </a>
            @if ($user->isAdmin())
                <span class="side-label">Admin</span>
                <a href="{{ route('admin.projects.create') }}" @if (request()->routeIs('admin.projects.create')) aria-current="page" @endif>
                    @include('partials.icon', ['name' => 'plus']) New project
                </a>
                <a href="{{ route('admin.partners.index') }}" @if (request()->routeIs('admin.partners.*')) aria-current="page" @endif>
                    @include('partials.icon', ['name' => 'building']) Partners
                </a>
                <a href="{{ route('admin.wbs-items.index') }}" @if (request()->routeIs('admin.wbs-items.*')) aria-current="page" @endif>
                    @include('partials.icon', ['name' => 'list']) WBS items
                </a>
                <a href="{{ route('admin.drive.show') }}" @if (request()->routeIs('admin.drive.*')) aria-current="page" @endif>
                    @include('partials.icon', ['name' => 'cloud']) Google Drive
                </a>
            @endif
        </nav>

        <div class="side-user">
            <span class="avatar" aria-hidden="true">{{ $initials ?: '?' }}</span>
            <span class="who"><b>{{ $user->name }}</b><span>{{ $user->roleLabel() }}{{ $user->partner ? ' · '.$user->partner->name : '' }}</span></span>
            <a class="icon-btn" href="{{ route('account.password') }}" title="Change password" aria-label="Change password">@include('partials.icon', ['name' => 'key'])</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="icon-btn" type="submit" title="Sign out" aria-label="Sign out">@include('partials.icon', ['name' => 'logout'])</button>
            </form>
        </div>
    </aside>

    <div class="content">
        <main class="wrap">
            @if (session('status'))
                <div class="notice" role="status">@include('partials.icon', ['name' => 'check']) <span>{{ session('status') }}</span></div>
            @endif
            @yield('content')
        </main>
    </div>
</div>
@yield('js')
</body>
</html>
