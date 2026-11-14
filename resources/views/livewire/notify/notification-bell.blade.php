<div class="bell-wrap" x-data @click.outside="if ($wire.open) { $wire.$set('open', false) }" @keydown.escape.window="if ($wire.open) { $wire.$set('open', false).then(() => $refs.bellButton && $refs.bellButton.focus()) }">
    <button type="button" x-ref="bellButton" wire:click="toggle" class="dh-icon-btn nf-bell-btn" title="Notifications" aria-label="{{ $this->unreadCount > 0 ? 'Notifications, '.$this->unreadCount.' unread' : 'Notifications' }}" aria-expanded="{{ $open ? 'true' : 'false' }}">
        <svg class="dh-ic" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 17V11a6 6 0 0 1 12 0v6l1.5 2h-15L6 17zM10 21h4"/></svg>
        @if($this->unreadCount > 0)
            <span class="dh-badge" aria-hidden="true">{{ $this->unreadCount > 9 ? '9+' : $this->unreadCount }}</span>
        @endif
    </button>

    @if($open)
    <div class="bell-panel">
        <div class="bell-head">
            <span>Notifications</span>
            @if($this->unreadCount > 0)
                <button type="button" wire:click="markAllAsRead" class="nf-linkbtn">Mark all read</button>
            @endif
        </div>

        @if($this->notifications->isEmpty())
            <p class="bell-empty">No notifications yet. We'll tell you here if a channel degrades or a batch fails.</p>
        @else
            <div>
                @foreach($this->notifications as $notification)
                <div role="button" tabindex="0" @class(['notif-row', 'is-read' => $notification->read_at]) wire:click="markAsRead('{{ $notification->id }}')" wire:keydown.enter="markAsRead('{{ $notification->id }}')" wire:keydown.space.prevent="markAsRead('{{ $notification->id }}')">
                    <div class="notif-title">@unless($notification->read_at)<span class="visually-hidden">Unread: </span>@endunless{{ $notification->data['title'] ?? 'Notification' }}</div>
                    <div class="notif-body">{{ $notification->data['message'] ?? '' }}</div>
                </div>
                @endforeach
            </div>
        @endif
    </div>
    @endif
</div>
