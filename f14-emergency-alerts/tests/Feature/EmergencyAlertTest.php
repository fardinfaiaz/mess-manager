<?php

namespace Tests\Feature;

use App\Models\EmergencyAlert;
use App\Models\Membership;
use App\Models\Mess;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmergencyAlertTest extends TestCase
{
    use RefreshDatabase;

    private Mess $mess;
    private User $manager;
    private User $resident;
    private Membership $residentMembership;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->create();
        $this->mess = Mess::create([
            'name' => 'Green House', 'address' => '12 Road', 'area' => 'Badda', 'created_by' => $this->manager->id,
        ]);
        $room = Room::create(['mess_id' => $this->mess->id, 'name' => 'A1', 'capacity' => 4]);

        $this->makeMember($this->manager, $this->mess, 'manager');
        $this->resident = User::factory()->create();
        $this->residentMembership = $this->makeMember($this->resident, $this->mess, 'resident', $room);
    }

    private function makeMember(User $user, Mess $mess, string $role, ?Room $room = null): Membership
    {
        return Membership::create([
            'user_id' => $user->id, 'mess_id' => $mess->id, 'room_id' => $room?->id,
            'role' => $role, 'status' => 'active', 'joined_at' => now()->toDateString(),
        ]);
    }

    private function makeAlert(array $overrides = [], ?Mess $mess = null): EmergencyAlert
    {
        $mess ??= $this->mess;

        return EmergencyAlert::create(array_merge([
            'mess_id' => $mess->id, 'created_by' => $mess->created_by,
            'type' => 'water', 'message' => 'No water until evening', 'status' => 'active',
        ], $overrides));
    }

    private function otherMess(): Mess
    {
        $owner = User::factory()->create();
        $mess = Mess::create(['name' => 'Blue House', 'address' => '5 Lane', 'area' => 'Mirpur', 'created_by' => $owner->id]);
        $this->makeMember($owner, $mess, 'manager');

        return $mess;
    }

    // ---- Normal cases ----

    public function test_manager_can_publish_an_alert(): void
    {
        $this->actingAs($this->manager)->post(route('alerts.store'), [
            'type' => 'electricity', 'message' => 'Power cut for two hours',
        ])->assertRedirect(route('alerts.index'));

        $this->assertDatabaseHas('emergency_alerts', [
            'mess_id' => $this->mess->id, 'type' => 'electricity', 'status' => 'active',
        ]);
    }

    public function test_resident_sees_active_alert_of_own_mess(): void
    {
        $this->makeAlert(['message' => 'Gas leak near kitchen', 'type' => 'gas']);

        $this->actingAs($this->resident)->get(route('alerts.index'))
            ->assertOk()->assertSee('Gas leak near kitchen');
    }

    public function test_manager_can_resolve_an_alert(): void
    {
        $alert = $this->makeAlert();

        $this->actingAs($this->manager)->post(route('alerts.resolve', $alert))
            ->assertRedirect(route('alerts.index'));

        $this->assertSame('resolved', $alert->fresh()->status);
        $this->assertNotNull($alert->fresh()->resolved_at);
    }

    // ---- Authorization ----

    public function test_resident_cannot_publish_or_resolve(): void
    {
        $alert = $this->makeAlert();

        $this->actingAs($this->resident)->post(route('alerts.store'), [
            'type' => 'water', 'message' => 'Fake alert from a resident',
        ])->assertForbidden();
        $this->actingAs($this->resident)->post(route('alerts.resolve', $alert))->assertForbidden();

        $this->assertDatabaseCount('emergency_alerts', 1);
    }

    public function test_user_without_a_mess_cannot_open_alerts(): void
    {
        $this->actingAs(User::factory()->create())->get(route('alerts.index'))->assertForbidden();
    }

    public function test_alerts_of_another_mess_are_hidden_and_protected(): void
    {
        $other = $this->otherMess();
        $foreign = $this->makeAlert(['message' => 'Secret of Blue House'], $other);

        $this->actingAs($this->resident)->get(route('alerts.index'))->assertDontSee('Secret of Blue House');
        $this->actingAs($this->resident)->post(route('alerts.acknowledge', $foreign))->assertForbidden();
        $this->actingAs($this->manager)->post(route('alerts.resolve', $foreign))->assertForbidden();
    }

    // ---- Invalid input ----

    public function test_publish_requires_valid_type_and_message(): void
    {
        $this->actingAs($this->manager)->post(route('alerts.store'), ['type' => 'flood', 'message' => ''])
            ->assertSessionHasErrors(['type', 'message']);

        $this->actingAs($this->manager)->post(route('alerts.store'), ['type' => 'water', 'message' => 'abc'])
            ->assertSessionHasErrors('message');

        $this->assertDatabaseCount('emergency_alerts', 0);
    }

    public function test_expiry_must_be_in_the_future(): void
    {
        $this->actingAs($this->manager)->post(route('alerts.store'), [
            'type' => 'water', 'message' => 'Water tank cleaning',
            'expires_at' => now()->subHour()->format('Y-m-d H:i:s'),
        ])->assertSessionHasErrors('expires_at');
    }

    // ---- Boundary cases ----

    public function test_expired_and_resolved_alerts_are_not_active(): void
    {
        $this->makeAlert(['message' => 'Old expired alert', 'expires_at' => now()->subMinute()]);
        $this->makeAlert(['message' => 'Closed alert', 'status' => 'resolved', 'resolved_at' => now()]);
        $live = $this->makeAlert(['message' => 'Still live alert', 'expires_at' => now()->addHour()]);

        $this->assertEquals([$live->id], EmergencyAlert::active()->pluck('id')->all());
    }

    public function test_cannot_acknowledge_an_inactive_alert(): void
    {
        $alert = $this->makeAlert(['status' => 'resolved', 'resolved_at' => now()]);

        $this->actingAs($this->resident)->post(route('alerts.acknowledge', $alert))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('alert_acknowledgements', 0);
    }

    public function test_acknowledging_twice_records_only_once(): void
    {
        $alert = $this->makeAlert();

        $this->actingAs($this->resident)->post(route('alerts.acknowledge', $alert));
        $this->actingAs($this->resident)->post(route('alerts.acknowledge', $alert));

        $this->assertDatabaseCount('alert_acknowledgements', 1);
    }

    public function test_manager_cannot_acknowledge_own_alert(): void
    {
        $alert = $this->makeAlert();

        $this->actingAs($this->manager)->post(route('alerts.acknowledge', $alert))->assertSessionHas('error');

        $this->assertDatabaseCount('alert_acknowledgements', 0);
    }

    // ---- Integration: publish -> see -> acknowledge -> manager tracking ----

    public function test_full_alert_workflow(): void
    {
        $this->actingAs($this->manager)->post(route('alerts.store'), [
            'type' => 'water', 'message' => 'Water supply stopped today',
        ]);
        $alert = EmergencyAlert::firstOrFail();

        // Resident sees the banner on the dashboard
        $this->actingAs($this->resident)->get(route('dashboard'))->assertSee('Water supply stopped today');

        // Manager sees the resident as pending
        $this->assertCount(1, $alert->pendingAcknowledgements());

        // Resident acknowledges: banner goes away and nobody is pending
        $this->actingAs($this->resident)->post(route('alerts.acknowledge', $alert));
        $this->actingAs($this->resident)->get(route('dashboard'))->assertDontSee('Water supply stopped today');
        $this->assertCount(0, $alert->fresh()->pendingAcknowledgements());

        // Manager resolves it: it is no longer active
        $this->actingAs($this->manager)->post(route('alerts.resolve', $alert));
        $this->assertFalse($alert->fresh()->isActive());
    }
}
