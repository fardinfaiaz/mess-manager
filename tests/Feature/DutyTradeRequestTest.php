<?php

namespace Tests\Feature;

use App\Models\DutyTradeRequest;
use App\Models\Membership;
use App\Models\Mess;
use App\Models\Room;
use App\Models\TaskAssignment;
use App\Models\TaskType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DutyTradeRequestTest extends TestCase
{
    use RefreshDatabase;

    private function createMessFixture(string $suffix = 'A'): array
    {
        $managerUser = User::factory()->create(['name' => "Manager {$suffix}"]);
        $mess = Mess::create([
            'name'       => "Mess {$suffix}",
            'address'    => "10 Road {$suffix}",
            'area'       => "Area {$suffix}",
            'created_by' => $managerUser->id,
            'status'     => 'active',
        ]);

        $managerMembership = Membership::create([
            'user_id'   => $managerUser->id,
            'mess_id'   => $mess->id,
            'room_id'   => null,
            'role'      => 'manager',
            'status'    => 'active',
            'joined_at' => now()->toDateString(),
        ]);

        $room1 = Room::create(['mess_id' => $mess->id, 'name' => '101', 'capacity' => 5]);
        $room2 = Room::create(['mess_id' => $mess->id, 'name' => '102', 'capacity' => 5]);

        $leaderUser = User::factory()->create(['name' => "Leader {$suffix}"]);
        $leaderMembership = Membership::create([
            'user_id'   => $leaderUser->id,
            'mess_id'   => $mess->id,
            'room_id'   => $room1->id,
            'role'      => 'room_leader',
            'status'    => 'active',
            'joined_at' => now()->toDateString(),
        ]);

        $resAUser = User::factory()->create(['name' => "ResidentA {$suffix}"]);
        $resAMembership = Membership::create([
            'user_id'   => $resAUser->id,
            'mess_id'   => $mess->id,
            'room_id'   => $room1->id,
            'role'      => 'resident',
            'status'    => 'active',
            'joined_at' => now()->toDateString(),
        ]);

        $resBUser = User::factory()->create(['name' => "ResidentB {$suffix}"]);
        $resBMembership = Membership::create([
            'user_id'   => $resBUser->id,
            'mess_id'   => $mess->id,
            'room_id'   => $room1->id,
            'role'      => 'resident',
            'status'    => 'active',
            'joined_at' => now()->toDateString(),
        ]);

        $resCUser = User::factory()->create(['name' => "ResidentC {$suffix}"]);
        $resCMembership = Membership::create([
            'user_id'   => $resCUser->id,
            'mess_id'   => $mess->id,
            'room_id'   => $room2->id,
            'role'      => 'resident',
            'status'    => 'active',
            'joined_at' => now()->toDateString(),
        ]);

        $cleaning = TaskType::create([
            'mess_id'   => $mess->id,
            'name'      => 'Cleaning',
            'is_active' => true,
        ]);

        $waste = TaskType::create([
            'mess_id'   => $mess->id,
            'name'      => 'Waste disposal',
            'is_active' => true,
        ]);

        $taskA = TaskAssignment::create([
            'mess_id'                   => $mess->id,
            'task_type_id'              => $cleaning->id,
            'membership_id'             => $resAMembership->id,
            'assigned_by_membership_id' => $managerMembership->id,
            'due_date'                  => now()->addDays(2)->toDateString(),
        ]);

        $taskB = TaskAssignment::create([
            'mess_id'                   => $mess->id,
            'task_type_id'              => $waste->id,
            'membership_id'             => $resBMembership->id,
            'assigned_by_membership_id' => $managerMembership->id,
            'due_date'                  => now()->addDays(3)->toDateString(),
        ]);

        $taskC = TaskAssignment::create([
            'mess_id'                   => $mess->id,
            'task_type_id'              => $cleaning->id,
            'membership_id'             => $resCMembership->id,
            'assigned_by_membership_id' => $managerMembership->id,
            'due_date'                  => now()->addDays(4)->toDateString(),
        ]);

        $taskLeader = TaskAssignment::create([
            'mess_id'                   => $mess->id,
            'task_type_id'              => $waste->id,
            'membership_id'             => $leaderMembership->id,
            'assigned_by_membership_id' => $managerMembership->id,
            'due_date'                  => now()->addDays(5)->toDateString(),
        ]);

        return compact(
            'mess',
            'room1',
            'room2',
            'managerUser',
            'managerMembership',
            'leaderUser',
            'leaderMembership',
            'resAUser',
            'resAMembership',
            'resBUser',
            'resBMembership',
            'resCUser',
            'resCMembership',
            'cleaning',
            'waste',
            'taskA',
            'taskB',
            'taskC',
            'taskLeader',
        );
    }

    public function test_resident_proposes_target_accepts_and_manager_approves_swapping_both_assignments(): void
    {
        $f = $this->createMessFixture();

        // 1. Resident A proposes exchange with Resident B
        $this->actingAs($f['resAUser'])
            ->post(route('duty-trades.store'), [
                'offered_task_assignment_id'   => $f['taskA']->id,
                'requested_task_assignment_id' => $f['taskB']->id,
                'reason'                       => 'Lab exam on Tuesday',
            ])
            ->assertRedirect(route('duty-trades.index'));

        $trade = DutyTradeRequest::first();
        $this->assertNotNull($trade);
        $this->assertSame(DutyTradeRequest::STATUS_PENDING_ACCEPTANCE, $trade->status);

        // Assignments MUST NOT change before approval
        $this->assertSame($f['resAMembership']->id, $f['taskA']->fresh()->membership_id);
        $this->assertSame($f['resBMembership']->id, $f['taskB']->fresh()->membership_id);

        // Manager cannot approve before target resident accepts
        $this->actingAs($f['managerUser'])
            ->post(route('duty-trades.approve', $trade))
            ->assertRedirect(route('duty-trades.index'))
            ->assertSessionHas('error');

        $this->assertSame(DutyTradeRequest::STATUS_PENDING_ACCEPTANCE, $trade->fresh()->status);
        $this->assertSame($f['resAMembership']->id, $f['taskA']->fresh()->membership_id);
        $this->assertSame($f['resBMembership']->id, $f['taskB']->fresh()->membership_id);

        // 2. Target Resident B accepts
        $this->actingAs($f['resBUser'])
            ->post(route('duty-trades.accept', $trade))
            ->assertRedirect(route('duty-trades.index'));

        $trade->refresh();
        $this->assertSame(DutyTradeRequest::STATUS_PENDING_APPROVAL, $trade->status);
        $this->assertNotNull($trade->accepted_at);

        // Assignments STILL MUST NOT change until approved
        $this->assertSame($f['resAMembership']->id, $f['taskA']->fresh()->membership_id);
        $this->assertSame($f['resBMembership']->id, $f['taskB']->fresh()->membership_id);

        // 3. Manager approves -> assignments swap atomically
        $this->actingAs($f['managerUser'])
            ->post(route('duty-trades.approve', $trade))
            ->assertRedirect(route('duty-trades.index'))
            ->assertSessionHas('success');

        $trade->refresh();
        $this->assertSame(DutyTradeRequest::STATUS_APPROVED, $trade->status);
        $this->assertSame($f['managerMembership']->id, $trade->decided_by_membership_id);
        $this->assertNotNull($trade->decided_at);

        $this->assertSame($f['resBMembership']->id, $f['taskA']->fresh()->membership_id);
        $this->assertSame($f['resAMembership']->id, $f['taskB']->fresh()->membership_id);
    }

    public function test_target_resident_rejects_leaving_assignments_unchanged(): void
    {
        $f = $this->createMessFixture();

        $trade = DutyTradeRequest::create([
            'mess_id'                      => $f['mess']->id,
            'proposer_membership_id'       => $f['resAMembership']->id,
            'target_membership_id'         => $f['resBMembership']->id,
            'offered_task_assignment_id'   => $f['taskA']->id,
            'requested_task_assignment_id' => $f['taskB']->id,
        ]);

        $this->actingAs($f['resBUser'])
            ->post(route('duty-trades.reject', $trade), [
                'decision_reason' => 'Busy on that day',
            ])
            ->assertRedirect(route('duty-trades.index'));

        $trade->refresh();
        $this->assertSame(DutyTradeRequest::STATUS_REJECTED, $trade->status);
        $this->assertSame($f['resBMembership']->id, $trade->decided_by_membership_id);
        $this->assertSame('Busy on that day', $trade->decision_reason);

        // Assignments remain untouched
        $this->assertSame($f['resAMembership']->id, $f['taskA']->fresh()->membership_id);
        $this->assertSame($f['resBMembership']->id, $f['taskB']->fresh()->membership_id);
    }

    public function test_proposer_can_cancel_open_request_and_others_cannot(): void
    {
        $f = $this->createMessFixture();

        $trade = DutyTradeRequest::create([
            'mess_id'                      => $f['mess']->id,
            'proposer_membership_id'       => $f['resAMembership']->id,
            'target_membership_id'         => $f['resBMembership']->id,
            'offered_task_assignment_id'   => $f['taskA']->id,
            'requested_task_assignment_id' => $f['taskB']->id,
        ]);

        // Target resident cannot cancel proposer's request
        $this->actingAs($f['resBUser'])
            ->post(route('duty-trades.cancel', $trade))
            ->assertForbidden();

        // Proposer cancels
        $this->actingAs($f['resAUser'])
            ->post(route('duty-trades.cancel', $trade))
            ->assertRedirect(route('duty-trades.index'));

        $this->assertSame(DutyTradeRequest::STATUS_CANCELLED, $trade->fresh()->status);
        $this->assertSame($f['resAMembership']->id, $f['taskA']->fresh()->membership_id);
        $this->assertSame($f['resBMembership']->id, $f['taskB']->fresh()->membership_id);
    }

    public function test_room_leader_approves_within_own_room_but_not_other_room_or_own_trade(): void
    {
        $f = $this->createMessFixture();

        // Exchange between two residents in Room 101 (leader's own room)
        $sameRoomTrade = DutyTradeRequest::create([
            'mess_id'                      => $f['mess']->id,
            'proposer_membership_id'       => $f['resAMembership']->id,
            'target_membership_id'         => $f['resBMembership']->id,
            'offered_task_assignment_id'   => $f['taskA']->id,
            'requested_task_assignment_id' => $f['taskB']->id,
        ]);
        $sameRoomTrade->accept();

        // Exchange involving Resident C in Room 102 (outside leader's room)
        $crossRoomTrade = DutyTradeRequest::create([
            'mess_id'                      => $f['mess']->id,
            'proposer_membership_id'       => $f['resAMembership']->id,
            'target_membership_id'         => $f['resCMembership']->id,
            'offered_task_assignment_id'   => $f['taskA']->id,
            'requested_task_assignment_id' => $f['taskC']->id,
        ]);
        $crossRoomTrade->accept();

        // Exchange where room leader is a participant
        $leaderOwnTrade = DutyTradeRequest::create([
            'mess_id'                      => $f['mess']->id,
            'proposer_membership_id'       => $f['leaderMembership']->id,
            'target_membership_id'         => $f['resBMembership']->id,
            'offered_task_assignment_id'   => $f['taskLeader']->id,
            'requested_task_assignment_id' => $f['taskB']->id,
        ]);
        $leaderOwnTrade->accept();

        // Room leader CANNOT approve cross-room exchange
        $this->actingAs($f['leaderUser'])
            ->post(route('duty-trades.approve', $crossRoomTrade))
            ->assertForbidden();

        // Room leader CANNOT approve exchange they participate in
        $this->actingAs($f['leaderUser'])
            ->post(route('duty-trades.approve', $leaderOwnTrade))
            ->assertForbidden();

        // Room leader CAN approve exchange between two residents in their own room
        $this->actingAs($f['leaderUser'])
            ->post(route('duty-trades.approve', $sameRoomTrade))
            ->assertRedirect(route('duty-trades.index'))
            ->assertSessionHas('success');

        $this->assertSame(DutyTradeRequest::STATUS_APPROVED, $sameRoomTrade->fresh()->status);
        $this->assertSame($f['resBMembership']->id, $f['taskA']->fresh()->membership_id);
        $this->assertSame($f['resAMembership']->id, $f['taskB']->fresh()->membership_id);

        // Other open trades touching swapped tasks were automatically rejected
        $this->assertSame(DutyTradeRequest::STATUS_REJECTED, $crossRoomTrade->fresh()->status);
        $this->assertSame(DutyTradeRequest::STATUS_REJECTED, $leaderOwnTrade->fresh()->status);
    }

    public function test_non_assignee_cannot_accept_and_unauthorized_resident_cannot_approve(): void
    {
        $f = $this->createMessFixture();

        $trade = DutyTradeRequest::create([
            'mess_id'                      => $f['mess']->id,
            'proposer_membership_id'       => $f['resAMembership']->id,
            'target_membership_id'         => $f['resBMembership']->id,
            'offered_task_assignment_id'   => $f['taskA']->id,
            'requested_task_assignment_id' => $f['taskB']->id,
        ]);

        // Proposer cannot accept their own request
        $this->actingAs($f['resAUser'])
            ->post(route('duty-trades.accept', $trade))
            ->assertForbidden();

        // Third resident cannot accept
        $this->actingAs($f['resCUser'])
            ->post(route('duty-trades.accept', $trade))
            ->assertForbidden();

        // Target accepts
        $trade->accept();

        // Unauthorized resident cannot approve
        $this->actingAs($f['resCUser'])
            ->post(route('duty-trades.approve', $trade))
            ->assertForbidden();

        $this->actingAs($f['resAUser'])
            ->post(route('duty-trades.approve', $trade))
            ->assertForbidden();

        // Assignments remain unchanged
        $this->assertSame($f['resAMembership']->id, $f['taskA']->fresh()->membership_id);
        $this->assertSame($f['resBMembership']->id, $f['taskB']->fresh()->membership_id);
    }

    public function test_cross_mess_requests_return_404(): void
    {
        $messA = $this->createMessFixture('A');
        $messB = $this->createMessFixture('B');

        // Resident in Mess A cannot propose trading with a task from Mess B
        $this->actingAs($messA['resAUser'])
            ->post(route('duty-trades.store'), [
                'offered_task_assignment_id'   => $messA['taskA']->id,
                'requested_task_assignment_id' => $messB['taskB']->id,
            ])
            ->assertNotFound();

        $tradeInB = DutyTradeRequest::create([
            'mess_id'                      => $messB['mess']->id,
            'proposer_membership_id'       => $messB['resAMembership']->id,
            'target_membership_id'         => $messB['resBMembership']->id,
            'offered_task_assignment_id'   => $messB['taskA']->id,
            'requested_task_assignment_id' => $messB['taskB']->id,
        ]);
        $tradeInB->accept();

        // Mess A users get 404 on Mess B's trade request endpoints
        $this->actingAs($messA['resBUser'])
            ->post(route('duty-trades.accept', $tradeInB))
            ->assertNotFound();

        $this->actingAs($messA['resAUser'])
            ->post(route('duty-trades.cancel', $tradeInB))
            ->assertNotFound();

        $this->actingAs($messA['managerUser'])
            ->post(route('duty-trades.reject', $tradeInB))
            ->assertNotFound();

        $this->actingAs($messA['managerUser'])
            ->post(route('duty-trades.approve', $tradeInB))
            ->assertNotFound();
    }

    public function test_inactive_membership_cannot_participate_in_exchange(): void
    {
        $f = $this->createMessFixture();

        // Mark Resident B inactive before proposal
        $f['resBMembership']->update([
            'status'  => 'left',
            'left_at' => now()->toDateString(),
        ]);

        $this->actingAs($f['resAUser'])
            ->post(route('duty-trades.store'), [
                'offered_task_assignment_id'   => $f['taskA']->id,
                'requested_task_assignment_id' => $f['taskB']->id,
            ])
            ->assertSessionHasErrors('requested_task_assignment_id');

        $this->assertDatabaseCount('duty_trade_requests', 0);

        // Also test leaving AFTER acceptance but BEFORE approval
        $trade = DutyTradeRequest::create([
            'mess_id'                      => $f['mess']->id,
            'proposer_membership_id'       => $f['resAMembership']->id,
            'target_membership_id'         => $f['resCMembership']->id,
            'offered_task_assignment_id'   => $f['taskA']->id,
            'requested_task_assignment_id' => $f['taskC']->id,
        ]);
        $trade->accept();

        $f['resCMembership']->update([
            'status'  => 'left',
            'left_at' => now()->toDateString(),
        ]);

        $this->actingAs($f['managerUser'])
            ->post(route('duty-trades.approve', $trade))
            ->assertRedirect(route('duty-trades.index'))
            ->assertSessionHas('error');

        $this->assertSame(DutyTradeRequest::STATUS_PENDING_APPROVAL, $trade->fresh()->status);
        $this->assertSame($f['resAMembership']->id, $f['taskA']->fresh()->membership_id);
        $this->assertSame($f['resCMembership']->id, $f['taskC']->fresh()->membership_id);
    }

    public function test_duplicate_and_conflicting_open_exchanges_are_prevented(): void
    {
        $f = $this->createMessFixture();

        // First valid proposal A <-> B
        $this->actingAs($f['resAUser'])
            ->post(route('duty-trades.store'), [
                'offered_task_assignment_id'   => $f['taskA']->id,
                'requested_task_assignment_id' => $f['taskB']->id,
            ])
            ->assertRedirect(route('duty-trades.index'));

        // Exact duplicate proposal A <-> B is rejected
        $this->actingAs($f['resAUser'])
            ->post(route('duty-trades.store'), [
                'offered_task_assignment_id'   => $f['taskA']->id,
                'requested_task_assignment_id' => $f['taskB']->id,
            ])
            ->assertSessionHasErrors('offered_task_assignment_id');

        // Conflicting proposal C <-> B (taskB is already in an open exchange) is rejected
        $this->actingAs($f['resCUser'])
            ->post(route('duty-trades.store'), [
                'offered_task_assignment_id'   => $f['taskC']->id,
                'requested_task_assignment_id' => $f['taskB']->id,
            ])
            ->assertSessionHasErrors('offered_task_assignment_id');

        $this->assertDatabaseCount('duty_trade_requests', 1);
    }

    public function test_completed_verified_and_missed_tasks_cannot_be_traded(): void
    {
        $f = $this->createMessFixture();

        // Mark taskA as done
        $f['taskA']->markDone();

        $this->actingAs($f['resAUser'])
            ->post(route('duty-trades.store'), [
                'offered_task_assignment_id'   => $f['taskA']->id,
                'requested_task_assignment_id' => $f['taskB']->id,
            ])
            ->assertSessionHasErrors('offered_task_assignment_id');

        // Mark taskC as missed
        $f['taskC']->markMissed();

        $this->actingAs($f['resBUser'])
            ->post(route('duty-trades.store'), [
                'offered_task_assignment_id'   => $f['taskB']->id,
                'requested_task_assignment_id' => $f['taskC']->id,
            ])
            ->assertSessionHasErrors('requested_task_assignment_id');

        $this->assertDatabaseCount('duty_trade_requests', 0);
    }

    public function test_stale_or_duplicate_approval_cannot_execute_twice(): void
    {
        $f = $this->createMessFixture();

        $trade = DutyTradeRequest::create([
            'mess_id'                      => $f['mess']->id,
            'proposer_membership_id'       => $f['resAMembership']->id,
            'target_membership_id'         => $f['resBMembership']->id,
            'offered_task_assignment_id'   => $f['taskA']->id,
            'requested_task_assignment_id' => $f['taskB']->id,
        ]);
        $trade->accept();

        // First approval succeeds and swaps owners
        $this->actingAs($f['managerUser'])
            ->post(route('duty-trades.approve', $trade))
            ->assertRedirect(route('duty-trades.index'))
            ->assertSessionHas('success');

        $this->assertSame($f['resBMembership']->id, $f['taskA']->fresh()->membership_id);
        $this->assertSame($f['resAMembership']->id, $f['taskB']->fresh()->membership_id);

        // Second (duplicate/stale) approval is rejected and does NOT swap back
        $this->actingAs($f['managerUser'])
            ->post(route('duty-trades.approve', $trade))
            ->assertRedirect(route('duty-trades.index'))
            ->assertSessionHas('error');

        $this->assertSame(DutyTradeRequest::STATUS_APPROVED, $trade->fresh()->status);
        $this->assertSame($f['resBMembership']->id, $f['taskA']->fresh()->membership_id);
        $this->assertSame($f['resAMembership']->id, $f['taskB']->fresh()->membership_id);
    }

    public function test_trade_status_and_decision_fields_cannot_be_mass_assigned(): void
    {
        $f = $this->createMessFixture();

        $trade = DutyTradeRequest::create([
            'mess_id'                      => $f['mess']->id,
            'proposer_membership_id'       => $f['resAMembership']->id,
            'target_membership_id'         => $f['resBMembership']->id,
            'offered_task_assignment_id'   => $f['taskA']->id,
            'requested_task_assignment_id' => $f['taskB']->id,
            'status'                       => DutyTradeRequest::STATUS_APPROVED,
            'decided_by_membership_id'     => $f['managerMembership']->id,
        ]);

        $trade->refresh();
        $this->assertSame(DutyTradeRequest::STATUS_PENDING_ACCEPTANCE, $trade->status);
        $this->assertNull($trade->decided_by_membership_id);
        $this->assertNull($trade->decided_at);
    }
}
