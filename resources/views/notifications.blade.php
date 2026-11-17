@extends('layouts.app')

@section('title', 'Notifications · TIF PMO')

@section('css')
    .nlist{list-style:none;margin:0;padding:0}
    .nlist li+li{border-top:1px solid var(--line)}
    .nitem{display:grid;grid-template-columns:36px minmax(0,1fr) auto;gap:14px;align-items:start;padding:16px 20px;color:inherit}
    a.nitem:hover{background:var(--hover);text-decoration:none;color:inherit}
    .nitem.unread{background:color-mix(in srgb,var(--accent-soft) 55%,transparent)}
    .nicon{width:36px;height:36px;border-radius:10px;display:grid;place-items:center}
    .nicon svg{width:18px;height:18px}
    .nicon.submitted{background:var(--warn-bg);color:var(--warn)}
    .nicon.accepted{background:var(--good-bg);color:var(--good)}
    .nicon.returned{background:var(--crit-bg);color:var(--crit)}
    .nitem .t{font-weight:600;color:var(--ink)}
    .nitem .m{font-size:14px;color:var(--ink-2);margin-top:2px;overflow-wrap:anywhere}
    .nitem .w{font-size:12.5px;color:var(--muted);margin-top:4px}
    .nitem .when{font-size:12.5px;color:var(--muted);white-space:nowrap;text-align:right}
    .dot{display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--brand);margin-left:6px;vertical-align:middle}
@endsection

@section('content')
    @php $unreadCount = $notifications->whereNull('read_at')->count(); @endphp

    <header class="page-head">
        <div class="titles">
            <h1>Notifications</h1>
            <p class="sub">
                @if (auth()->user()->isAdmin())
                    WBS steps submitted for your review.
                @else
                    Decisions on the WBS steps your company submitted.
                @endif
            </p>
        </div>
        @if ($unreadCount)
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button class="btn" type="submit">@include('partials.icon', ['name' => 'check']) Mark all as read</button>
            </form>
        @endif
    </header>

    <section class="panel">
        @if ($notifications->isEmpty())
            <div class="empty">@include('partials.icon', ['name' => 'bell'])<b>No notifications yet</b>
                <p>{{ auth()->user()->isAdmin() ? 'You will be notified when a Project Manager submits a step for review.' : 'You will be notified when a TIF admin accepts or returns a submitted step.' }}</p>
            </div>
        @else
            <ul class="nlist">
                @foreach ($notifications as $n)
                    @php $event = $n->data['event'] ?? 'submitted'; @endphp
                    <li>
                        <a class="nitem {{ $n->read_at ? '' : 'unread' }}" href="{{ route('notifications.open', $n->id) }}">
                            <span class="nicon {{ $event }}">@include('partials.icon', ['name' => ['submitted' => 'send', 'accepted' => 'check', 'returned' => 'undo'][$event] ?? 'bell'])</span>
                            <span style="min-width:0">
                                <span class="t">{{ $n->data['title'] ?? 'Notification' }}</span>@unless ($n->read_at)<span class="dot" aria-label="Unread"></span>@endunless
                                <span class="m" style="display:block">{{ $n->data['message'] ?? '' }}</span>
                                <span class="w" style="display:block">{{ $n->data['where'] ?? '' }}</span>
                            </span>
                            <span class="when" title="{{ $n->created_at->format('j M Y H:i') }}">{{ $n->created_at->diffForHumans() }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
