<?php

namespace Database\Factories;

use App\Models\Feed;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Feed>
 */
class FeedFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'color' => '#6366f1',
            'owner_id' => User::factory(),
        ];
    }

    /** Le créateur du fil y est abonné, comme à la création depuis l'interface. */
    public function configure(): static
    {
        return $this->afterCreating(fn (Feed $feed) => $feed->owner_id && $feed->subscribers()->attach($feed->owner_id));
    }
}
