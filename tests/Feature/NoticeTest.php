<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\Mess;
use App\Models\Notice;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// F20 Central Notice Board
class NoticeTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private User $resident;
    private Mess $mess;
    private Room $roomA;
    private Room $roomB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->create();
        $this->resident = User::factory()->create();

        $this->mess = Mess::create([
            'name' => 'Test Mess', 'address' => '1 Road', 'area' => 'Badda',
            'created_by' => $this->manager->id, 'status' => 'active',
        ]);
        $this->roomA = Room::create(['mess_id' => $this->mess->id, 'name' => 'A101', 'capacity' => 3]);
        $this->roomB = Room::create(['mess_id' => $this->mess->id, 'name' => 'B202', 'capacity' => 3]);

        $this->addMember($this->manager, 'manager');
        $this->addMember($this->resident, 'resident', $this->roomA);
    }

    private function addMember(User $user, string $role, ?Room $room = null, ?Mess $mess = null): Membership
    {
        return Membership::create([
            'user_id' => $user->id, 'mess_id' => ($mess ?? $this->mess)->id,
            'room_id' => $room?->id, 'role' => $role, 'status' => 'active',
            'joined_at' => now()->toDateString(),
        ]);
    }

    private function makeNotice(array $overrides = [], ?Mess $mess = null): Notice
    {
        return Notice::create($overrides + [
            'mess_id' => ($mess ?? $this->mess)->id, 'created_by' => $this->manager->id,
            'title' => 'Monthly meeting', 'body' => 'Meeting on Friday night.',
            'category' => 'meeting', 'scope' => 'mess', 'pinned' => false,
        ]);
    }

    public function test_manager_can_publish_a_notice(): void
    {
        $this->actingAs($this->manager)->post(route('notices.store'), [
            'title' => 'Rent deadline', 'body' => 'Pay by the 5th of the month.',
            'category' => 'payment', 'pinned' => '1',
        ])->assertRedirect(route('notices.index'));

        $notice = Notice::first();
        $this->assertSame($this->mess->id, $notice->mess_id);
        $this->assertSame('mess', $notice->scope);
        $this->assertTrue($notice->pinned);
        $this->assertNull($notice->room_id);
    }

    public function test_resident_cannot_publish_edit_pin_or_delete(): void
    {
        $notice = $this->makeNotice();

        $this->actingAs($this->resident)->post(route('notices.store'), [
            'title' => 'Fake', 'body' => 'Not allowed.', 'category' => 'general',
        ])->assertForbidden();
        $this->actingAs($this->resident)->put(route('notices.update', $notice), [])->assertForbidden();
        $this->actingAs($this->resident)->post(route('notices.pin', $notice))->assertForbidden();
        $this->actingAs($this->resident)->delete(route('notices.destroy', $notice))->assertForbidden();

        $this->assertSame(1, Notice::count());
    }

    public function test_invalid_input_is_rejected(): void
    {
        $this->actingAs($this->manager)->post(route('notices.store'), [
            'title' => '', 'body' => '', 'category' => 'party',
        ])->assertSessionHasErrors(['title', 'body', 'category']);

        // boundary: title and body just under their minimum lengths
        $this->actingAs($this->manager)->post(route('notices.store'), [
            'title' => 'ab', 'body' => 'abcd', 'category' => 'general',
        ])->assertSessionHasErrors(['title', 'body']);

        $this->assertSame(0, Notice::count());
    }

    public function test_expiry_must_be_in_the_future(): void
    {
        $base = ['title' => 'Water tank', 'body' => 'Tank cleaning on Sunday.', 'category' => 'maintenance'];

        $this->actingAs($this->manager)->post(route('notices.store'), $base + [
            'expires_at' => now()->subHour()->format('Y-m-d H:i:s'),
        ])->assertSessionHasErrors('expires_at');

        $this->actingAs($this->manager)->post(route('notices.store'), $base + [
            'expires_at' => now()->addDay()->format('Y-m-d H:i:s'),
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, Notice::count());
    }

    public function test_room_notice_must_use_a_room_of_the_same_mess(): void
    {
        $otherUser = User::factory()->create();
        $otherMess = Mess::create([
            'name' => 'Other Mess', 'address' => '2 Road', 'area' => 'Mirpur',
            'created_by' => $otherUser->id, 'status' => 'active',
        ]);
        $foreignRoom = Room::create(['mess_id' => $otherMess->id, 'name' => 'X1', 'capacity' => 2]);

        $this->actingAs($this->manager)->post(route('notices.store'), [
            'title' => 'Sneaky', 'body' => 'Targets a foreign room.',
            'category' => 'general', 'room_id' => $foreignRoom->id,
        ])->assertSessionHasErrors('room_id');

        $this->assertSame(0, Notice::count());
    }

    public function test_resident_sees_mess_notices_and_own_room_notices_only(): void
    {
        $this->makeNotice(['title' => 'For everyone']);
        $this->makeNotice(['title' => 'For room A', 'room_id' => $this->roomA->id, 'scope' => 'room']);
        $this->makeNotice(['title' => 'For room B', 'room_id' => $this->roomB->id, 'scope' => 'room']);

        $this->actingAs($this->resident)->get(route('notices.index'))
            ->assertSee('For everyone')->assertSee('For room A')->assertDontSee('For room B');

        // the manager sees every notice of the mess
        $this->actingAs($this->manager)->get(route('notices.index'))
            ->assertSee('For everyone')->assertSee('For room A')->assertSee('For room B');
    }

    public function test_room_notice_cannot_be_opened_by_another_room(): void
    {
        $roomBNotice = $this->makeNotice(['room_id' => $this->roomB->id, 'scope' => 'room']);

        $this->actingAs($this->resident)->get(route('notices.show', $roomBNotice))->assertNotFound();
    }

    public function test_notices_are_hidden_from_other_messes(): void
    {
        $otherUser = User::factory()->create();
        $otherMess = Mess::create([
            'name' => 'Other Mess', 'address' => '2 Road', 'area' => 'Mirpur',
            'created_by' => $otherUser->id, 'status' => 'active',
        ]);
        $this->addMember($otherUser, 'manager', null, $otherMess);
        $notice = $this->makeNotice(['title' => 'Secret notice']);

        $this->actingAs($otherUser)->get(route('notices.index'))->assertDontSee('Secret notice');
        $this->actingAs($otherUser)->get(route('notices.show', $notice))->assertNotFound();
        $this->actingAs($otherUser)->get(route('notices.edit', $notice))->assertNotFound();
        $this->actingAs($otherUser)->delete(route('notices.destroy', $notice))->assertNotFound();
        $this->assertSame(1, Notice::count());
    }

    public function test_expired_notice_moves_to_the_archive(): void
    {
        $this->makeNotice(['title' => 'Live notice', 'expires_at' => now()->addDay()]);
        $this->makeNotice(['title' => 'Old notice', 'expires_at' => now()->subMinute()]);

        $this->actingAs($this->resident)->get(route('notices.index'))
            ->assertSee('Live notice')->assertDontSee('Old notice');
        $this->actingAs($this->resident)->get(route('notices.archive'))
            ->assertSee('Old notice')->assertDontSee('Live notice');
    }

    public function test_pinned_notices_come_first(): void
    {
        $this->makeNotice(['title' => 'Plain newer notice']);
        $this->makeNotice(['title' => 'Pinned older notice', 'pinned' => true, 'created_at' => now()->subDay()]);

        $this->actingAs($this->resident)->get(route('notices.index'))
            ->assertSeeInOrder(['Pinned older notice', 'Plain newer notice']);
    }

    public function test_category_filter_narrows_the_list(): void
    {
        $this->makeNotice(['title' => 'Meeting notice', 'category' => 'meeting']);
        $this->makeNotice(['title' => 'Payment notice', 'category' => 'payment']);

        $this->actingAs($this->resident)->get(route('notices.index', ['category' => 'payment']))
            ->assertSee('Payment notice')->assertDontSee('Meeting notice');
    }

    public function test_manager_can_edit_pin_and_remove_a_notice(): void
    {
        $notice = $this->makeNotice();

        $this->actingAs($this->manager)->put(route('notices.update', $notice), [
            'title' => 'Updated title', 'body' => 'Updated body text.', 'category' => 'rule',
        ])->assertRedirect(route('notices.index'));
        $this->assertSame('Updated title', $notice->fresh()->title);

        $this->actingAs($this->manager)->post(route('notices.pin', $notice));
        $this->assertTrue($notice->fresh()->pinned);
        $this->actingAs($this->manager)->post(route('notices.pin', $notice));
        $this->assertFalse($notice->fresh()->pinned);

        $this->actingAs($this->manager)->delete(route('notices.destroy', $notice));
        $this->assertSame(0, Notice::count());
    }

    public function test_dashboard_shows_latest_notices(): void
    {
        $this->makeNotice(['title' => 'Dashboard notice']);

        $this->actingAs($this->resident)->get(route('dashboard'))->assertSee('Dashboard notice');
    }

    public function test_user_without_a_mess_cannot_open_the_notice_board(): void
    {
        $this->actingAs(User::factory()->create())->get(route('notices.index'))->assertForbidden();
    }
}
