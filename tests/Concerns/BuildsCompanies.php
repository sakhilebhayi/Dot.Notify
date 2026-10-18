<?php

namespace Tests\Concerns;

use App\Models\NotifyLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Builders for the Analytics publishing tests: a company is a non-personal team linked to an InfoDot uuid.
 */
trait BuildsCompanies
{
    protected function makeCompany(array $attributes = []): Team
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(array_merge(['user_id' => $owner->id, 'personal_team' => false], $attributes));
        $owner->forceFill(['current_team_id' => $team->id])->save();
        $team->forceFill(['infodot_team_uuid' => (string) Str::uuid()])->save();

        return $team->fresh();
    }

    /**
     * One delivery-log row, with its timestamps pinned (no model clock involved).
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function makeLog(Team $team, string $status, ?string $sentAt = null, ?string $updatedAt = null, array $attributes = []): NotifyLog
    {
        $log = NotifyLog::withoutGlobalScopes()->create(array_merge([
            'team_id' => $team->id,
            'recipient' => 'person-'.Str::lower(Str::random(8)).'@example.test',
            'subject' => 'Subject',
            'body_preview' => 'Body',
            'status' => $status,
        ], $attributes));

        DB::table('notify_logs')->where('id', $log->id)->update(array_filter([
            'sent_at' => $sentAt,
            'created_at' => '2026-09-01 10:00:00',
            'updated_at' => $updatedAt ?? '2026-09-01 10:00:00',
        ]));

        return $log->fresh();
    }
}
