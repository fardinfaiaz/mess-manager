@extends('layouts.app')

@section('title', $notice->title)

@section('content')
    <h2>@if ($notice->pinned) &#128204; @endif{{ $notice->title }}</h2>
    <p style="font-size:13px;color:#555">
        {{ $notice->categoryLabel() }} &middot; {{ $notice->audienceLabel() }}
        &middot; posted {{ $notice->created_at->format('d M Y, H:i') }} by {{ $notice->creator?->name ?? 'the manager' }}
        @if ($notice->expires_at)
            &middot; {{ $notice->isCurrent() ? 'visible until' : 'expired on' }} {{ $notice->expires_at->format('d M Y, H:i') }}
        @endif
    </p>

    <div class="card">{!! nl2br(e($notice->body)) !!}</div>

    <p><a href="{{ route('notices.index') }}">Back to notice board</a></p>
@endsection
