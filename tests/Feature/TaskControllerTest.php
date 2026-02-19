<?php

namespace Tests\Feature;

use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that index returns a list of tasks ordered by created_at desc.
     */
    public function test_index_returns_tasks_list(): void
    {
        $task1 = Task::factory()->create(['created_at' => now()->subDay()]);
        $task2 = Task::factory()->create(['created_at' => now()]);
        $task3 = Task::factory()->create(['created_at' => now()->subHours(2)]);

        $response = $this->getJson('/api/tasks');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonCount(3, 'data');

        // Verify ordering (newest first)
        $data = $response->json('data');
        $this->assertEquals($task2->id, $data[0]['id']);
    }

    /**
     * Test that index returns empty array when no tasks exist.
     */
    public function test_index_returns_empty_array_when_no_tasks(): void
    {
        $response = $this->getJson('/api/tasks');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [],
            ]);
    }

    /**
     * Test that store creates a new task with valid data.
     */
    public function test_store_creates_task_with_valid_data(): void
    {
        $payload = [
            'title' => 'New Task',
            'description' => 'Test description',
            'status' => 'pending',
        ];

        $response = $this->postJson('/api/tasks', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Task created successfully.',
            ])
            ->assertJsonPath('data.title', 'New Task')
            ->assertJsonPath('data.description', 'Test description')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('tasks', [
            'title' => 'New Task',
            'description' => 'Test description',
            'status' => 'pending',
        ]);
    }

    /**
     * Test that store creates task with only required field (title).
     */
    public function test_store_creates_task_with_only_title(): void
    {
        $payload = [
            'title' => 'Task without description',
        ];

        $response = $this->postJson('/api/tasks', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('tasks', [
            'title' => 'Task without description',
            'description' => null,
        ]);
    }

    /**
     * Test that store validates required title field.
     */
    public function test_store_validates_required_title(): void
    {
        $payload = [
            'description' => 'Missing title',
        ];

        $response = $this->postJson('/api/tasks', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    /**
     * Test that store validates title max length.
     */
    public function test_store_validates_title_max_length(): void
    {
        $payload = [
            'title' => str_repeat('a', 256), // 256 characters (exceeds max:255)
        ];

        $response = $this->postJson('/api/tasks', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    /**
     * Test that store validates status enum values.
     */
    public function test_store_validates_status_enum(): void
    {
        $payload = [
            'title' => 'Valid title',
            'status' => 'invalid_status',
        ];

        $response = $this->postJson('/api/tasks', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    /**
     * Test that store accepts valid status values.
     */
    public function test_store_accepts_valid_status_values(): void
    {
        $validStatuses = ['pending', 'in_progress', 'completed'];

        foreach ($validStatuses as $status) {
            $payload = [
                'title' => "Task with status {$status}",
                'status' => $status,
            ];

            $response = $this->postJson('/api/tasks', $payload);

            $response->assertStatus(201);
            $this->assertDatabaseHas('tasks', [
                'title' => "Task with status {$status}",
                'status' => $status,
            ]);
        }
    }

    /**
     * Test that show returns a single task.
     */
    public function test_show_returns_single_task(): void
    {
        $task = Task::factory()->create([
            'title' => 'Specific Task',
            'description' => 'Specific description',
            'status' => 'in_progress',
        ]);

        $response = $this->getJson("/api/tasks/{$task->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('data.id', $task->id)
            ->assertJsonPath('data.title', 'Specific Task')
            ->assertJsonPath('data.description', 'Specific description')
            ->assertJsonPath('data.status', 'in_progress');
    }

    /**
     * Test that show returns 404 for non-existent task.
     */
    public function test_show_returns_404_for_non_existent_task(): void
    {
        $response = $this->getJson('/api/tasks/99999');

        $response->assertStatus(404);
    }

    /**
     * Test that update modifies task with valid data.
     */
    public function test_update_modifies_task_with_valid_data(): void
    {
        $task = Task::factory()->create([
            'title' => 'Old title',
            'description' => 'Old description',
            'status' => 'pending',
        ]);

        $payload = [
            'title' => 'Updated title',
            'description' => 'Updated description',
            'status' => 'completed',
        ];

        $response = $this->putJson("/api/tasks/{$task->id}", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Task updated successfully.',
            ])
            ->assertJsonPath('data.title', 'Updated title')
            ->assertJsonPath('data.description', 'Updated description')
            ->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Updated title',
            'description' => 'Updated description',
            'status' => 'completed',
        ]);
    }

    /**
     * Test that update can partially update task fields.
     */
    public function test_update_can_partially_update_task(): void
    {
        $task = Task::factory()->create([
            'title' => 'Original title',
            'description' => 'Original description',
            'status' => 'pending',
        ]);

        $payload = [
            'title' => 'Only title updated',
        ];

        $response = $this->putJson("/api/tasks/{$task->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'Only title updated');

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Only title updated',
            'description' => 'Original description', // Should remain unchanged
            'status' => 'pending', // Should remain unchanged
        ]);
    }

    /**
     * Test that update validates title when provided.
     */
    public function test_update_validates_title_when_provided(): void
    {
        $task = Task::factory()->create();

        $payload = [
            'title' => '', // Empty string should fail validation
        ];

        $response = $this->putJson("/api/tasks/{$task->id}", $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    /**
     * Test that update validates status enum when provided.
     */
    public function test_update_validates_status_enum_when_provided(): void
    {
        $task = Task::factory()->create();

        $payload = [
            'status' => 'invalid_status',
        ];

        $response = $this->putJson("/api/tasks/{$task->id}", $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    /**
     * Test that update returns 404 for non-existent task.
     */
    public function test_update_returns_404_for_non_existent_task(): void
    {
        $payload = [
            'title' => 'Updated title',
        ];

        $response = $this->putJson('/api/tasks/99999', $payload);

        $response->assertStatus(404);
    }

    /**
     * Test that destroy deletes a task.
     */
    public function test_destroy_deletes_task(): void
    {
        $task = Task::factory()->create();

        $response = $this->deleteJson("/api/tasks/{$task->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Task deleted successfully.',
            ]);

        $this->assertDatabaseMissing('tasks', [
            'id' => $task->id,
        ]);
    }

    /**
     * Test that destroy returns 404 for non-existent task.
     */
    public function test_destroy_returns_404_for_non_existent_task(): void
    {
        $response = $this->deleteJson('/api/tasks/99999');

        $response->assertStatus(404);
    }
}
