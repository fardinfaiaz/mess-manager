<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\Mess;
use App\Models\Room;
use App\Models\TaskAssignment;
use App\Models\TaskType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function createMessFixture(string $suffix = 'A'): array
    {
        $managerUser = User::factory()->create(['name' => "Manager {$suffix}"]);
        $mess = Mess::create([
            'name'       => "Mess {$suffix}",
            'address'    => "123 Road {$suffix}",
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

        $room1 = Room::create([
            'mess_id'  => $mess->id,
            'name'     => '101',
            'capacity' => 4,
        ]);

        $room2 = Room::create([
            'mess_id'  => $mess->id,
            'name'     => '102',
            'capacity' => 4,
        ]);

        $leaderUser = User::factory()->create(['name' => "Leader {$suffix}"]);
        $leaderMembership = Membership::create([
            'user_id'   => $leaderUser->id,
            'mess_id'   => $mess->id,
            'room_id'   => $room1->id,
            'role'      => 'room_leader',
            'status'    => 'active',
            'joined_at' => now()->toDateString(),
        ]);

        $resident1User = User::factory()->create(['name' => "Resident1 {$suffix}"]);
        $resident1Membership = Membership::create([
            'user_id'   => $resident1User->id,
            'mess_id'   => $mess->id,
            'room_id'   => $room1->id,
            'role'      => 'resident',
            'status'    => 'active',
            'joined_at' => now()->toDateString(),
        ]);

        $resident2User = User::factory()->create(['name' => "Resident2 {$suffix}"]);
        $resident2Membership = Membership::create([
            'user_id'   => $resident2User->id,
            'mess_id'   => $mess->id,
            'room_id'   => $room2->id,
            'role'      => 'resident',
            'status'    => 'active',
            'joined_at' => now()->toDateString(),
        ]);

        $taskType = TaskType::create([
            'mess_id'     => $mess->id,
            'name'        => 'Cleaning',
            'description' => 'Sweep and mop common floor',
            'is_active'   => true,
        ]);

        return compact(
            'mess',
            'room1',
            'room2',
            'managerUser',
            'managerMembership',
            'leaderUser',
            'leaderMembership',
            'resident1User',
            'resident1Membership',
            'resident2User',
            'resident2Membership',
            'taskType',
        );
    }

    public function test_manager_can_create_edit_deactivate_task_types_and_assign_tasks(): void
    {
        $f = $this->createMessFixture();

        $this->actingAs($f['managerUser'])
            ->post(route('task-types.store'), [
                'name'        => 'Kitchen maintenance',
                'description' => 'Clean stove and sink',
            ])
            ->assertRedirect(route('task-types.index'));

        $createdType = TaskType::where('mess_id', $f['mess']->id)
            ->where('name', 'Kitchen maintenance')
            ->first();

        $this->assertNotNull($createdType);
        $this->assertTrue($createdType->isActive());

        // Edit task type
        $this->actingAs($f['managerUser'])
            ->put(route('task-types.update', $createdType), [
                'name'        => 'Kitchen & Sink Maintenance',
                'description' => 'Updated description',
            ])
            ->assertRedirect(route('task-types.index'));

        $this->assertSame('Kitchen & Sink Maintenance', $createdType->fresh()->name);

        // Assign task to a resident in any room
        $dueDate = now()->addDays(2)->toDateString();

        $this->actingAs($f['managerUser'])
            ->post(route('tasks.store'), [
                'task_type_id'  => $createdType->id,
                'membership_id' => $f['resident2Membership']->id,
                'due_date'      => $dueDate,
                'notes'         => 'Evening shift',
            ])
            ->assertRedirect(route('tasks.index'));

        $this->assertDatabaseHas('task_assignments', [
            'mess_id'                   => $f['mess']->id,
            'task_type_id'              => $createdType->id,
            'membership_id'             => $f['resident2Membership']->id,
            'assigned_by_membership_id' => $f['managerMembership']->id,
            'status'                    => TaskAssignment::STATUS_PENDING,
        ]);

        // Deactivate task type
        $this->actingAs($f['managerUser'])
            ->post(route('task-types.toggle', $createdType))
            ->assertRedirect(route('task-types.index'));

        $this->assertFalse($createdType->fresh()->isActive());

        // Room leader and resident cannot manage task types
        $this->actingAs($f['leaderUser'])
            ->post(route('task-types.store'), ['name' => 'Waste disposal'])
            ->assertForbidden();

        $this->actingAs($f['resident1User'])
            ->get(route('task-types.index'))
            ->assertForbidden();
    }

    public function test_room_leader_can_assign_tasks_in_own_room(): void
    {
        $f = $this->createMessFixture();
        $dueDate = now()->addDay()->toDateString();

        $this->actingAs($f['leaderUser'])
            ->post(route('tasks.store'), [
                'task_type_id'  => $f['taskType']->id,
                'membership_id' => $f['resident1Membership']->id,
                'due_date'      => $dueDate,
                'notes'         => 'Room 101 duty',
            ])
            ->assertRedirect(route('tasks.index'));

        $this->assertDatabaseHas('task_assignments', [
            'mess_id'                   => $f['mess']->id,
            'task_type_id'              => $f['taskType']->id,
            'membership_id'             => $f['resident1Membership']->id,
            'assigned_by_membership_id' => $f['leaderMembership']->id,
            'status'                    => TaskAssignment::STATUS_PENDING,
        ]);
    }

    public function test_room_leader_cannot_assign_tasks_outside_own_room(): void
    {
        $f = $this->createMessFixture();

        $this->actingAs($f['leaderUser'])
            ->post(route('tasks.store'), [
                'task_type_id'  => $f['taskType']->id,
                'membership_id' => $f['resident2Membership']->id,
                'due_date'      => now()->addDay()->toDateString(),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('task_assignments', 0);
    }

    public function test_resident_cannot_assign_tasks(): void
    {
        $f = $this->createMessFixture();

        $this->actingAs($f['resident1User'])
            ->post(route('tasks.store'), [
                'task_type_id'  => $f['taskType']->id,
                'membership_id' => $f['resident2Membership']->id,
                'due_date'      => now()->addDay()->toDateString(),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('task_assignments', 0);
    }

    public function test_resident_can_only_update_their_own_task_progress(): void
    {
        $f = $this->createMessFixture();

        $task = TaskAssignment::create([
            'mess_id'                   => $f['mess']->id,
            'task_type_id'              => $f['taskType']->id,
            'membership_id'             => $f['resident1Membership']->id,
            'assigned_by_membership_id' => $f['managerMembership']->id,
            'due_date'                  => now()->addDay()->toDateString(),
        ]);

        // Another resident cannot update resident1's task
        $this->actingAs($f['resident2User'])
            ->post(route('tasks.progress', $task), ['status' => 'in_progress'])
            ->assertForbidden();

        $this->assertSame(TaskAssignment::STATUS_PENDING, $task->fresh()->status);

        // Resident1 cannot jump directly to verified or missed
        $this->actingAs($f['resident1User'])
            ->post(route('tasks.progress', $task), ['status' => 'verified'])
            ->assertSessionHasErrors('status');

        // Resident1 moves pending -> in_progress -> done
        $this->actingAs($f['resident1User'])
            ->post(route('tasks.progress', $task), ['status' => 'in_progress'])
            ->assertRedirect(route('tasks.index'));

        $this->assertSame(TaskAssignment::STATUS_IN_PROGRESS, $task->fresh()->status);

        $this->actingAs($f['resident1User'])
            ->post(route('tasks.progress', $task), ['status' => 'done'])
            ->assertRedirect(route('tasks.index'));

        $task->refresh();
        $this->assertSame(TaskAssignment::STATUS_DONE, $task->status);
        $this->assertNotNull($task->completed_at);
    }

    public function test_unauthorized_verification_is_rejected_and_authorized_verification_succeeds(): void
    {
        $f = $this->createMessFixture();

        $room1Task = TaskAssignment::create([
            'mess_id'                   => $f['mess']->id,
            'task_type_id'              => $f['taskType']->id,
            'membership_id'             => $f['resident1Membership']->id,
            'assigned_by_membership_id' => $f['managerMembership']->id,
            'due_date'                  => now()->addDay()->toDateString(),
        ]);
        $room1Task->markDone();

        $room2Task = TaskAssignment::create([
            'mess_id'                   => $f['mess']->id,
            'task_type_id'              => $f['taskType']->id,
            'membership_id'             => $f['resident2Membership']->id,
            'assigned_by_membership_id' => $f['managerMembership']->id,
            'due_date'                  => now()->addDay()->toDateString(),
        ]);
        $room2Task->markDone();

        $leaderOwnTask = TaskAssignment::create([
            'mess_id'                   => $f['mess']->id,
            'task_type_id'              => $f['taskType']->id,
            'membership_id'             => $f['leaderMembership']->id,
            'assigned_by_membership_id' => $f['managerMembership']->id,
            'due_date'                  => now()->addDay()->toDateString(),
        ]);
        $leaderOwnTask->markDone();

        // Resident cannot verify anyone's task
        $this->actingAs($f['resident1User'])
            ->post(route('tasks.verify', $room1Task))
            ->assertForbidden();

        // Room leader cannot verify a task from another room
        $this->actingAs($f['leaderUser'])
            ->post(route('tasks.verify', $room2Task))
            ->assertForbidden();

        // Room leader cannot verify their own task
        $this->actingAs($f['leaderUser'])
            ->post(route('tasks.verify', $leaderOwnTask))
            ->assertForbidden();

        // Room leader CAN verify a done task in their own room
        $this->actingAs($f['leaderUser'])
            ->post(route('tasks.verify', $room1Task))
            ->assertRedirect(route('tasks.index'));

        $room1Task->refresh();
        $this->assertSame(TaskAssignment::STATUS_VERIFIED, $room1Task->status);
        $this->assertSame($f['leaderMembership']->id, $room1Task->verified_by_membership_id);
        $this->assertNotNull($room1Task->verified_at);

        // Manager CAN verify room2's task and leader's task
        $this->actingAs($f['managerUser'])
            ->post(route('tasks.verify', $room2Task))
            ->assertRedirect(route('tasks.index'));

        $this->assertSame(TaskAssignment::STATUS_VERIFIED, $room2Task->fresh()->status);

        // Manager can mark a pending task as missed
        $pendingTask = TaskAssignment::create([
            'mess_id'                   => $f['mess']->id,
            'task_type_id'              => $f['taskType']->id,
            'membership_id'             => $f['resident2Membership']->id,
            'assigned_by_membership_id' => $f['managerMembership']->id,
            'due_date'                  => now()->addDay()->toDateString(),
        ]);

        // Room leader cannot mark missed outside own room
        $this->actingAs($f['leaderUser'])
            ->post(route('tasks.missed', $pendingTask))
            ->assertForbidden();

        $this->actingAs($f['managerUser'])
            ->post(route('tasks.missed', $pendingTask))
            ->assertRedirect(route('tasks.index'));

        $this->assertSame(TaskAssignment::STATUS_MISSED, $pendingTask->fresh()->status);
    }

    public function test_cross_mess_access_returns_404(): void
    {
        $messA = $this->createMessFixture('A');
        $messB = $this->createMessFixture('B');

        $taskInB = TaskAssignment::create([
            'mess_id'                   => $messB['mess']->id,
            'task_type_id'              => $messB['taskType']->id,
            'membership_id'             => $messB['resident1Membership']->id,
            'assigned_by_membership_id' => $messB['managerMembership']->id,
            'due_date'                  => now()->addDay()->toDateString(),
        ]);
        $taskInB->markDone();

        // Manager from Mess A cannot modify Mess B's task type or verify/miss Mess B's task
        $this->actingAs($messA['managerUser'])
            ->put(route('task-types.update', $messB['taskType']), ['name' => 'Hacked'])
            ->assertNotFound();

        $this->actingAs($messA['managerUser'])
            ->post(route('task-types.toggle', $messB['taskType']))
            ->assertNotFound();

        $this->actingAs($messA['managerUser'])
            ->post(route('tasks.verify', $taskInB))
            ->assertNotFound();

        $this->actingAs($messA['managerUser'])
            ->post(route('tasks.missed', $taskInB))
            ->assertNotFound();

        // Resident from Mess A cannot update progress on Mess B's task
        $this->actingAs($messA['resident1User'])
            ->post(route('tasks.progress', $taskInB), ['status' => 'done'])
            ->assertNotFound();
    }

    public function test_inactive_memberships_cannot_receive_assignments(): void
    {
        $f = $this->createMessFixture();

        $formerUser = User::factory()->create(['name' => 'Former Resident']);
        $inactiveMembership = Membership::create([
            'user_id'   => $formerUser->id,
            'mess_id'   => $f['mess']->id,
            'room_id'   => $f['room1']->id,
            'role'      => 'resident',
            'status'    => 'left',
            'joined_at' => now()->subMonths(2)->toDateString(),
            'left_at'   => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($f['managerUser'])
            ->post(route('tasks.store'), [
                'task_type_id'  => $f['taskType']->id,
                'membership_id' => $inactiveMembership->id,
                'due_date'      => now()->addDay()->toDateString(),
            ])
            ->assertSessionHasErrors('membership_id');

        $this->assertDatabaseCount('task_assignments', 0);
    }

    public function test_managers_cannot_be_selected_as_assignees(): void
    {
        $f = $this->createMessFixture();

        $this->actingAs($f['managerUser'])
            ->post(route('tasks.store'), [
                'task_type_id'  => $f['taskType']->id,
                'membership_id' => $f['managerMembership']->id,
                'due_date'      => now()->addDay()->toDateString(),
            ])
            ->assertSessionHasErrors('membership_id');

        $this->assertDatabaseCount('task_assignments', 0);
    }

    public function test_invalid_due_dates_and_task_types_are_rejected(): void
    {
        $messA = $this->createMessFixture('A');
        $messB = $this->createMessFixture('B');

        $inactiveType = TaskType::create([
            'mess_id'   => $messA['mess']->id,
            'name'      => 'Old Chore',
            'is_active' => false,
        ]);

        // Past due date
        $this->actingAs($messA['managerUser'])
            ->post(route('tasks.store'), [
                'task_type_id'  => $messA['taskType']->id,
                'membership_id' => $messA['resident1Membership']->id,
                'due_date'      => now()->subDay()->toDateString(),
            ])
            ->assertSessionHasErrors('due_date');

        // Inactive task type
        $this->actingAs($messA['managerUser'])
            ->post(route('tasks.store'), [
                'task_type_id'  => $inactiveType->id,
                'membership_id' => $messA['resident1Membership']->id,
                'due_date'      => now()->addDay()->toDateString(),
            ])
            ->assertSessionHasErrors('task_type_id');

        // Task type belonging to another mess
        $this->actingAs($messA['managerUser'])
            ->post(route('tasks.store'), [
                'task_type_id'  => $messB['taskType']->id,
                'membership_id' => $messA['resident1Membership']->id,
                'due_date'      => now()->addDay()->toDateString(),
            ])
            ->assertSessionHasErrors('task_type_id');

        $this->assertDatabaseCount('task_assignments', 0);
    }

    public function test_status_cannot_be_overridden_via_mass_assignment(): void
    {
        $f = $this->createMessFixture();

        $task = TaskAssignment::create([
            'mess_id'                   => $f['mess']->id,
            'task_type_id'              => $f['taskType']->id,
            'membership_id'             => $f['resident1Membership']->id,
            'assigned_by_membership_id' => $f['managerMembership']->id,
            'due_date'                  => now()->addDay()->toDateString(),
            'status'                    => TaskAssignment::STATUS_VERIFIED,
            'verified_by_membership_id' => $f['managerMembership']->id,
        ]);

        $task->refresh();
        $this->assertSame(TaskAssignment::STATUS_PENDING, $task->status);
        $this->assertNull($task->verified_by_membership_id);
        $this->assertNull($task->verified_at);
    }

    public function test_existing_authentication_behavior_remains_unchanged(): void
    {
        // Guests are redirected to login for protected pages
        $this->get(route('tasks.index'))->assertRedirect(route('login'));
        $this->get(route('dashboard'))->assertRedirect(route('login'));

        // Registration, login, and logout work as before
        $this->post(route('register'), [
            'name'                  => 'New Member',
            'email'                 => 'newmember@example.com',
            'password'              => 'secret1234',
            'password_confirmation' => 'secret1234',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();

        // User with no active mess membership cannot access /tasks
        $this->get(route('tasks.index'))->assertForbidden();

        // Logout ends session and redirects to login
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
