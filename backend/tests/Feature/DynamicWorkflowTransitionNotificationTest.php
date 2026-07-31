<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Notifications\TicketAlertNotification;
use App\Services\WorkflowEngineService;
use App\Services\WorkflowSnapshotBuilder;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DynamicWorkflowTransitionNotificationTest extends TestCase
{
    use RefreshDatabase;

    private WorkflowEngineService $engine;

    private User $supervisor;

    private User $requester;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        $roles = collect(['supervisor_it', 'requester', 'pic_it_support', 'developer', 'qa', 'uat', 'manager'])
            ->mapWithKeys(fn (string $key): array => [$key => Role::factory()->create(['key' => $key, 'name' => $key])]);

        $this->supervisor = User::factory()->create(['role_id' => $roles['supervisor_it']->id, 'is_active' => true]);
        $this->requester = User::factory()->create(['role_id' => $roles['requester']->id, 'is_active' => true]);

        $workflow = WorkflowDefinition::factory()->active()->create(['version' => 1, 'code' => 'notification_wf']);
        $submitted = $workflow->stages()->create([
            'stage_key' => 'submitted',
            'name' => 'Submitted',
            'order' => 1,
            'is_initial' => true,
            'is_terminal' => false,
            'stage_type' => 'normal',
        ]);
        $done = $workflow->stages()->create([
            'stage_key' => 'done',
            'name' => 'Done',
            'order' => 2,
            'is_initial' => false,
            'is_terminal' => true,
            'stage_type' => 'normal',
        ]);
        $transition = $workflow->transitions()->create([
            'from_stage_id' => $submitted->id,
            'to_stage_id' => $done->id,
            'action_key' => 'complete',
            'name' => 'Complete ticket',
            'requires_notes' => false,
        ]);
        $transition->permissions()->create(['role_key' => 'supervisor_it']);

        $snapshot = app(WorkflowSnapshotBuilder::class)->build($workflow);
        $snapshot['stages'][0]['transitions'][0]['notifications'] = [
            ['recipient_type' => 'requester', 'channel' => 'database'],
            ['recipient_type' => 'primary_pic', 'channel' => 'database'],
            ['recipient_type' => 'secondary_pics', 'channel' => 'database'],
            ['recipient_type' => 'supervisor_it', 'channel' => 'database'],
            ['recipient_type' => 'specific_role', 'channel' => 'database', 'metadata' => ['role_key' => 'developer']],
            ['recipient_type' => 'specific_role', 'channel' => 'database'],
            ['recipient_type' => 'specific_role', 'channel' => 'database', 'config' => ['role_key' => 'qa']],
            ['recipient_type' => 'manager', 'channel' => 'database'],
            ['recipient_type' => 'requester', 'channel' => 'email'],
            ['recipient_type' => 'raw_query', 'channel' => 'database', 'query' => 'all users'],
            ['recipient_type' => 'callback', 'channel' => 'database', 'callback' => 'notifyAll'],
        ];

        $this->ticket = Ticket::factory()->create([
            'requester_id' => $this->requester->id,
            'status' => TicketStatus::Submitted,
            'workflow_id' => $workflow->id,
            'workflow_version' => 1,
            'workflow_snapshot' => $snapshot,
            'workflow_mode' => 'dynamic',
            'current_workflow_stage' => 'submitted',
            'workflow_stage_entered_at' => now(),
        ]);

        $this->engine = app(WorkflowEngineService::class);
    }

    public function test_snapshot_recipients_are_safely_resolved_only_after_commit(): void
    {
        Notification::fake();

        $pic = $this->userForRole('pic_it_support');
        $secondary = $this->userForRole('developer');
        $qa = $this->userForRole('qa');
        $uat = $this->userForRole('uat');
        $manager = $this->userForRole('manager');

        $this->assign($pic, 'primary');
        $this->assign($secondary, 'secondary');
        $this->assign($qa, 'primary');
        $this->assign($uat, 'secondary');
        $this->assign($manager, 'secondary');

        DB::beginTransaction();
        $updated = $this->engine->executeTransition($this->ticket, $this->supervisor, 'complete');

        $this->assertSame('done', $updated->current_workflow_stage);
        Notification::assertNothingSent();

        DB::commit();

        Notification::assertSentTo([$this->requester, $pic, $secondary, $this->supervisor], TicketAlertNotification::class);
        Notification::assertNotSentTo([$qa, $uat, $manager], TicketAlertNotification::class);
        Notification::assertCount(4);
    }

    public function test_notification_failure_is_logged_without_rolling_back_transition(): void
    {
        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('send')->once()->andThrow(new \RuntimeException('database notification failed'));
        $this->app->instance(Dispatcher::class, $dispatcher);

        $snapshot = $this->ticket->workflow_snapshot;
        $snapshot['stages'][0]['transitions'][0]['notifications'] = [
            ['recipient_type' => 'requester', 'channel' => 'database'],
        ];
        $this->ticket->update(['workflow_snapshot' => $snapshot]);

        $updated = $this->engine->executeTransition($this->ticket->fresh(), $this->supervisor, 'complete');

        $this->assertSame('done', $updated->current_workflow_stage);
        $this->assertDatabaseHas('ticket_status_histories', [
            'ticket_id' => $this->ticket->id,
            'action' => 'complete',
        ]);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_repeated_failed_and_conflicting_attempts_never_dispatch_notifications(): void
    {
        Notification::fake();

        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $this->engine->executeTransition($this->ticket, $this->supervisor, 'missing_action');
                $this->fail('Expected failed transition was not thrown.');
            } catch (HttpException $exception) {
                $this->assertSame(422, $exception->getStatusCode());
            }
        }

        $this->ticket->update(['current_workflow_stage' => 'done']);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $this->engine->executeTransition(
                    $this->ticket,
                    $this->supervisor,
                    'complete',
                    expectedCurrentStage: 'submitted'
                );
                $this->fail('Expected transition conflict was not thrown.');
            } catch (ConflictHttpException) {
                // Expected: no after-commit callback is registered before the concurrency guard.
            }
        }

        Notification::assertNothingSent();
        $this->assertDatabaseCount('ticket_status_histories', 0);
    }

    private function userForRole(string $roleKey): User
    {
        return User::factory()->create([
            'role_id' => Role::query()->where('key', $roleKey)->firstOrFail()->id,
            'is_active' => true,
        ]);
    }

    private function assign(User $user, string $type): void
    {
        $this->ticket->assignments()->create([
            'assigned_to' => $user->id,
            'assigned_by' => $this->supervisor->id,
            'assignment_type' => $type,
            'started_at' => now(),
            'is_current' => true,
        ]);
    }
}
