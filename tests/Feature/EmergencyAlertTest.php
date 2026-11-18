<?php

namespace Tests\Feature;

use App\Models\EmergencyAlert;
use App\Models\Membership;
use App\Models\Mess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// F14 Emergency Announcement
class EmergencyAlertTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private User $resident;
    private Mess $mess;
    private Membership $residentMembership;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->create();
        $this->resident = User::factory()->create();

        $this->mess = Mess::create([
            'name' => 'Test Mess', 'address' => '1 Road', 'area' => 'Badda',
            'created_by' => $this->manager->id, 'status' => 'active',
        ]);

        $this->addMember($this->manager, 'manager');
        $this->residentMembership = $this->addMember($this->resident, 'resident');
    }

    private function addMember(User $user, string $role, ?Mess $mess = null): Membership
    {
        return Membership::create([
            'user_id' => $user->id, 'mess_id' => ($mess ?? $this->mess)->id,
            'role' => $role, 'status' => 'active', 'joined_at' => now()->toDateString(),
        ]);
    }

    private function makeAlert(array $overrides = [], ?Mess $mess = null): EmergencyAlert
    {
        return EmergencyAlert::create($overrides + [
            'mess_id' => ($mess ?? $this->mess)->id, 'created_by' => $this->manager->id,
            'type' => 'water', 'message' => 'No water until evening.',
            'status' => 'active', 'expires_at' => now()->addHours(3),
        ]);
    }

    public function test_manager_can_publish_an_alert(): void
    {
        $response = $this->actingAs($this->manager)->post(route('alerts.store'), [
            'type' => 'electricity', 'message' => 'Power cut until 8 PM.', 'duration_hours' => 6,
        ]);

        $response->assertRedirect(route('alerts.index'));
        $alert = EmergencyAlert::first();
        $this->assertSame($this->mess->id, $alert->mess_id);
        $this->assertSame('active', $alert->status);
        $this->assertTrue($alert->expires_at->isFuture());
    }

    public function test_resident_cannot_publish_an_alert(): void
    {
        $this->actingAs($this->resident)->post(route('alerts.store'), [
            'type' => 'water', 'message' => 'Fake alert.', 'duration_hours' => 1,
        ])->assertForbidden();

        $this->assertSame(0, EmergencyAlert::count());
    }

    public function test_invalid_input_is_rejected(): void
    {
        $this->actingAs($this->manager)->post(route('alerts.store'), [
            'type' => 'earthquake', 'message' => '', 'duration_hours' => 5,
        ])->assertSessionHasErrors(['type', 'message', 'duration_hours']);

        // boundary: message shorter than the 5-character minimum
        $this->actingAs($this->manager)->post(route('alerts.store'), [
            'type' => 'gas', 'message' => 'abcd', 'duration_hours' => 1,
        ])->assertSessionHasErrors('message');

        $this->assertSame(0, EmergencyAlert::count());
    }

    public function test_resident_sees_active_alert_on_dashboard_and_list(): void
    {
        $this->makeAlert(['message' => 'Water shortage today']);

        $this->actingAs($this->resident)->get(route('dashboard'))->assertSee('Water shortage today');
        $this->actingAs($this->resident)->get(route('alerts.index'))->assertSee('Water shortage today');
    }

    public function test_resolved_and_expired_alerts_are_not_active(): void
    {
        $resolved = $this->makeAlert(['message' => 'Resolved one']);
        $this->actingAs($this->manager)->post(route('alerts.resolve', $resolved))
            ->assertRedirect(route('alerts.index'));

        $this->makeAlert(['message' => 'Expired one', 'expires_at' => now()->subMinute()]);

        $this->assertSame(0, EmergencyAlert::active()->count());
        $this->assertSame('Resolved', $resolved->fresh()->statusLabel());

        $this->actingAs($this->resident)->get(route('dashboard'))
            ->assertDontSee('Resolved one')->assertDontSee('Expired one');
    }

    public function test_alerts_are_hidden_from_other_messes(): void
    {
        $otherUser = User::factory()->create();
        $otherMess = Mess::create([
            'name' => 'Other Mess', 'address' => '2 Road', 'area' => 'Mirpur',
            'created_by' => $otherUser->id, 'status' => 'active',
        ]);
        $this->addMember($otherUser, 'manager', $otherMess);
        $alert = $this->makeAlert(['message' => 'Secret alert']);

        $this->actingAs($otherUser)->get(route('dashboard'))->assertDontSee('Secret alert');
        $this->actingAs($otherUser)->get(route('alerts.show', $alert))->assertNotFound();
        $this->actingAs($otherUser)->post(route('alerts.resolve', $alert))->assertNotFound();
        $this->assertSame('active', $alert->fresh()->status);
    }

    public function test_resident_can_acknowledge_once_only(): void
    {
        $alert = $this->makeAlert();

        $this->actingAs($this->resident)->post(route('alerts.acknowledge', $alert))->assertSessionHas('success');
        $this->actingAs($this->resident)->post(route('alerts.acknowledge', $alert));

        $this->assertSame(1, $alert->acknowledgements()->count());
        $this->assertTrue($alert->isAcknowledgedBy($this->residentMembership));
    }

    public function test_cannot_acknowledge_a_resolved_alert(): void
    {
        $alert = $this->makeAlert();
        $alert->resolve();

        $this->actingAs($this->resident)->post(route('alerts.acknowledge', $alert))->assertSessionHas('error');
        $this->assertSame(0, $alert->acknowledgements()->count());
    }

    public function test_pending_acknowledgements_list_excludes_acknowledged_and_manager(): void
    {
        $second = User::factory()->create();
        $secondMembership = $this->addMember($second, 'resident');
        $alert = $this->makeAlert();

        $this->actingAs($this->resident)->post(route('alerts.acknowledge', $alert));

        $pending = $alert->pendingAcknowledgements();
        $this->assertCount(1, $pending);
        $this->assertSame($secondMembership->id, $pending->first()->id);
    }

    public function test_user_without_a_mess_cannot_open_alerts(): void
    {
        $this->actingAs(User::factory()->create())->get(route('alerts.index'))->assertForbidden();
    }
}
