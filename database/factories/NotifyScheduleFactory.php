<?php

namespace Database\Factories;

use App\Models\NotifySchedule;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotifySchedule>
 */
class NotifyScheduleFactory extends Factory
{
    protected $model = NotifySchedule::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'rule_id' => null,
            'name' => $this->faker->words(3, true),
            'cron_expression' => '0 9 * * 1', // every Monday 9am
            'timezone' => 'Africa/Johannesburg',
            'recipients' => [$this->faker->safeEmail()],
            'is_active' => true,
        ];
    }
}
