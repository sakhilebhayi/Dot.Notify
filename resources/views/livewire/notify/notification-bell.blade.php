<div class="bell-wrap" x-data @click.outside="$wire.open = false">
    <button type="button" wire:click="toggle" class="topbar-btn" title="Notifications" aria-label="Notifications" aria-expanded="{{ $open ? 'true' : 'false' }}">
        <x-icon name="bell" />
        @if($this->unreadCount > 0)
            <span class="bell-count">{{ $this->unreadCount > 9 ? '9+' : $this->unreadCount }}</span>
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
