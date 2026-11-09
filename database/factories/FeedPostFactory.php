<?php

namespace Database\Factories;

use App\Models\Feed;
use App\Models\FeedPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeedPost>
 */
class FeedPostFactory extends Factory
{
    public function definition(): array
    {
        return [
            'feed_id' => Feed::factory(),
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'body' => '<p>'.fake()->paragraph().'</p>',
            'published_on' => today(),
        ];
    }
}
