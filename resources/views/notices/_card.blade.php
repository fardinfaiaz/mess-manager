<div class="card" style="{{ $notice->pinned ? 'border-left:6px solid #1f3b57;background:#f0f5fa' : '' }}">
    <strong>@if ($notice->pinned) &#128204; @endif{{ $notice->title }}</strong>
    <div style="font-size:13px;color:#555;margin-top:2px">
        {{ $notice->categoryLabel() }} &middot; {{ $notice->audienceLabel() }}
        &middot; posted {{ $notice->created_at->diffForHumans() }}
        @if ($notice->expires_at)
            &middot; {{ $notice->isCurrent() ? 'until' : 'expired' }} {{ $notice->expires_at->format('d M Y, H:i') }}
        @endif
    </div>
    <p style="margin:8px 0">{{ \Illuminate\Support\Str::limit($notice->body, 160) }}</p>
    <a href="{{ route('notices.show', $notice) }}">Read more</a>
</div>
