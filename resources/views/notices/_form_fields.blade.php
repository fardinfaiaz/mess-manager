@php $n = $notice ?? null; @endphp

<label for="title">Title</label>
<input type="text" id="title" name="title" maxlength="150" value="{{ old('title', $n?->title) }}" required>
@error('title') <div class="error">{{ $message }}</div> @enderror

<label for="body">Notice text</label>
<textarea id="body" name="body" rows="5" maxlength="5000" required>{{ old('body', $n?->body) }}</textarea>
@error('body') <div class="error">{{ $message }}</div> @enderror

<label for="category">Category</label>
<select id="category" name="category" required>
    @foreach (\App\Models\Notice::CATEGORIES as $value => $label)
        <option value="{{ $value }}" @selected(old('category', $n?->category ?? 'general') === $value)>{{ $label }}</option>
    @endforeach
</select>
@error('category') <div class="error">{{ $message }}</div> @enderror

<label for="room_id">Audience</label>
<select id="room_id" name="room_id">
    <option value="">Whole mess</option>
    @foreach ($rooms as $room)
        <option value="{{ $room->id }}" @selected((string) old('room_id', $n?->room_id) === (string) $room->id)>Room: {{ $room->name }}</option>
    @endforeach
</select>
@error('room_id') <div class="error">{{ $message }}</div> @enderror

<label for="expires_at">Hide after (optional)</label>
<input type="datetime-local" id="expires_at" name="expires_at"
       value="{{ old('expires_at', $n?->expires_at?->format('Y-m-d\TH:i')) }}"
       style="width:100%;padding:9px;margin-top:4px;box-sizing:border-box;border:1px solid #bbb;border-radius:4px">
@error('expires_at') <div class="error">{{ $message }}</div> @enderror

<label style="font-weight:normal">
    <input type="checkbox" name="pinned" value="1" @checked(old('pinned', $n?->pinned))> Pin this notice to the top
</label>
