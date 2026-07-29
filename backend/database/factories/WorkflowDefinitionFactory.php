<?php

namespace Database\Factories;

use App\Models\WorkflowDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkflowDefinitionFactory extends Factory
{
    protected $model = WorkflowDefinition::class;

    public function definition(): array
    {
        return [
            'code' => 'wf_'.$this->faker->unique()->lexify('????'),
            'name' => $this->faker->words(3, true).' Workflow',
            'description' => $this->faker->sentence(),
            'version' => 1,
            'is_active' => false,
            'config_status' => 'draft',
            'published_at' => null,
            'created_by' => null,
            'metadata' => [],
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'config_status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'config_status' => 'active',
            'is_active' => true,
            'published_at' => now(),
        ]);
    }
}
