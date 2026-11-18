<?php

namespace App\Http\Controllers;

use App\Models\Notice;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

// F20 Central Notice Board
class NoticeController extends Controller
{
    // Everyone in the mess: current notices, pinned ones first (managers also get the publish form)
    public function index(Request $request)
    {
        $membership = $this->membership($request);
        $mess = $membership->mess;

        $query = $mess->notices()->visibleTo($membership)->current()->with('room');

        if ($request->filled('category') && array_key_exists($request->category, Notice::CATEGORIES)) {
            $query->where('category', $request->category);
        }

        $notices = $query->orderByDesc('pinned')->latest()->get();
        $category = $request->category;

        if ($membership->isManager()) {
            $rooms = $mess->rooms()->orderBy('name')->get();

            return view('notices.manage', compact('mess', 'notices', 'rooms', 'category'));
        }

        return view('notices.index', compact('mess', 'notices', 'category'));
    }

    // Everyone in the mess: notices whose expiry date has passed
    public function archive(Request $request)
    {
        $membership = $this->membership($request);
        $mess = $membership->mess;

        $notices = $mess->notices()->visibleTo($membership)->previous()->with('room')->latest()->get();

        return view('notices.archive', compact('mess', 'notices'));
    }

    public function show(Request $request, Notice $notice)
    {
        $this->ensureVisible($request, $notice);

        return view('notices.show', ['notice' => $notice->load('room', 'creator')]);
    }

    // Manager: publish a notice
    public function store(Request $request)
    {
        $membership = $this->membership($request);
        $data = $this->validated($request, $membership->mess_id);

        $membership->mess->notices()->create($data + [
            'created_by'   => $request->user()->id,
            'is_automatic' => false,
        ]);

        return redirect()->route('notices.index')->with('success', 'Notice published.');
    }

    public function edit(Request $request, Notice $notice)
    {
        $this->ensureOwnMess($request, $notice);

        $rooms = $notice->mess->rooms()->orderBy('name')->get();

        return view('notices.edit', compact('notice', 'rooms'));
    }

    public function update(Request $request, Notice $notice)
    {
        $this->ensureOwnMess($request, $notice);

        $notice->update($this->validated($request, $notice->mess_id));

        return redirect()->route('notices.index')->with('success', 'Notice updated.');
    }

    public function destroy(Request $request, Notice $notice)
    {
        $this->ensureOwnMess($request, $notice);

        $notice->delete();

        return redirect()->route('notices.index')->with('success', 'Notice removed.');
    }

    // Manager: pin or unpin a notice
    public function togglePin(Request $request, Notice $notice)
    {
        $this->ensureOwnMess($request, $notice);

        $notice->update(['pinned' => ! $notice->pinned]);

        return back()->with('success', $notice->pinned ? 'Notice pinned to the top.' : 'Notice unpinned.');
    }

    // Shared validation for publishing and editing
    private function validated(Request $request, int $messId): array
    {
        $data = $request->validate([
            'title'      => ['required', 'string', 'min:3', 'max:150'],
            'body'       => ['required', 'string', 'min:5', 'max:5000'],
            'category'   => ['required', Rule::in(array_keys(Notice::CATEGORIES))],
            'room_id'    => ['nullable', 'integer', Rule::exists('rooms', 'id')->where('mess_id', $messId)],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'pinned'     => ['nullable', 'boolean'],
        ], [
            'title.required'    => 'Give the notice a title.',
            'title.min'         => 'The title must be at least :min characters.',
            'body.required'     => 'Write the notice text.',
            'body.min'          => 'The notice must be at least :min characters.',
            'category.in'       => 'Choose a valid category.',
            'room_id.exists'    => 'Choose a room of your own mess.',
            'expires_at.after'  => 'The expiry date must be in the future.',
        ]);

        return [
            'title'      => $data['title'],
            'body'       => $data['body'],
            'category'   => $data['category'],
            'room_id'    => $data['room_id'] ?? null,
            'scope'      => empty($data['room_id']) ? 'mess' : 'room',
            'expires_at' => $data['expires_at'] ?? null,
            'pinned'     => $request->boolean('pinned'),
        ];
    }

    // The signed-in user's current membership; people outside a mess have no notice board
    private function membership(Request $request)
    {
        $membership = $request->user()->activeMembership;

        if (! $membership) {
            abort(403, 'Join a mess to see its notice board.');
        }

        return $membership;
    }

    // Manager actions only touch notices of the manager's own mess
    private function ensureOwnMess(Request $request, Notice $notice): void
    {
        if ($notice->mess_id != $this->membership($request)->mess_id) {
            abort(404);
        }
    }

    // Members only open notices meant for them (own mess, and own room for room notices)
    private function ensureVisible(Request $request, Notice $notice): void
    {
        if (! $notice->isVisibleTo($this->membership($request))) {
            abort(404);
        }
    }
}
