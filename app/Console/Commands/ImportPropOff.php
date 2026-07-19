<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time import of the PropOff production database into the propoff_
 * tables. Point the propoff_source connection (config/database.php /
 * PROPOFF_SOURCE_* env) at a local restore of the prod dump, then:
 *
 *   php artisan propoff:import
 *
 * Idempotent: truncates the propoff_ tables (never any other table) and
 * re-imports. Users are inserted fresh (names split into first/last, roles
 * and guest tokens carried) except when an email matches an existing keeler
 * user — then the existing account is reused.
 */
class ImportPropOff extends Command
{
    protected $signature = 'propoff:import {--dry-run : Report counts without writing}';

    protected $description = 'Import the PropOff production data into the propoff_ tables';

    /** @var array<int, int> old user id => new user id */
    private array $userMap = [];

    public function handle(): int
    {
        $src = DB::connection('propoff_source');

        try {
            $counts = [
                'users'    => $src->table('users')->count(),
                'events'   => $src->table('events')->count(),
                'groups'   => $src->table('groups')->count(),
                'entries'  => $src->table('entries')->count(),
                'answers'  => $src->table('user_answers')->count(),
            ];
        } catch (\Throwable $e) {
            $this->error('Cannot read propoff_source: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->table(['source table', 'rows'], collect($counts)->map(
            fn ($n, $t) => [$t, $n],
        )->values()->all());

        if ($this->option('dry-run')) {
            $this->info('Dry run — nothing written.');

            return self::SUCCESS;
        }

        // TRUNCATE implicitly commits in MySQL, so the wipe must happen
        // before the transaction starts.
        $this->wipePropOffTables();

        DB::transaction(function () use ($src) {
            $this->importUsers($src);
            $this->importTable($src, 'events', 'propoff_events', userCols: ['created_by']);
            $this->importTable($src, 'question_templates', 'propoff_question_templates', userCols: ['created_by']);
            $this->importTable($src, 'question_template_answers', 'propoff_question_template_answers');
            $this->importTable($src, 'event_questions', 'propoff_event_questions');
            $this->importTable($src, 'groups', 'propoff_groups', userCols: ['created_by']);
            $this->importTable($src, 'user_groups', 'propoff_group_user', userCols: ['user_id']);
            $this->importTable($src, 'group_questions', 'propoff_group_questions');
            $this->importTable($src, 'event_answers', 'propoff_event_answers', userCols: ['set_by']);
            $this->importTable($src, 'group_question_answers', 'propoff_group_question_answers');
            $this->importTable($src, 'entries', 'propoff_entries', userCols: ['user_id', 'submitted_by_captain_id']);
            $this->importTable($src, 'user_answers', 'propoff_user_answers');
            $this->importTable($src, 'leaderboards', 'propoff_leaderboards', userCols: ['user_id']);
            $this->importTable($src, 'event_invitations', 'propoff_event_invitations');
            $this->importTable($src, 'captain_invitations', 'propoff_captain_invitations', userCols: ['created_by']);
        });

        $this->info('Import complete.');

        return self::SUCCESS;
    }

    private function wipePropOffTables(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ([
            'propoff_captain_invitations', 'propoff_event_invitations',
            'propoff_leaderboards', 'propoff_group_question_answers',
            'propoff_event_answers', 'propoff_user_answers', 'propoff_entries',
            'propoff_group_questions', 'propoff_event_questions',
            'propoff_question_template_answers', 'propoff_question_templates',
            'propoff_group_user', 'propoff_groups', 'propoff_events',
        ] as $table) {
            DB::table($table)->truncate();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function importUsers($src): void
    {
        $matched = 0;
        $created = 0;

        foreach ($src->table('users')->orderBy('id')->cursor() as $old) {
            // Match previously imported/known users: by guest token first
            // (guests), then by email — makes re-runs fully idempotent.
            $existing = null;
            if ($old->guest_token) {
                $existing = User::where('guest_token', $old->guest_token)->first();
            }
            if (! $existing && $old->email) {
                $existing = User::whereRaw('LOWER(email) = ?', [mb_strtolower($old->email)])->first();
            }

            if ($existing) {
                // Reuse the account. Roles only ever ELEVATE (to admin or
                // manager) — a source 'guest' must never downgrade an
                // existing keeler account.
                if (in_array($old->role ?? 'user', ['admin', 'manager'], true)
                    && ! $existing->hasAdminAccess()) {
                    $existing->update(['role' => $old->role]);
                }
                $this->userMap[$old->id] = $existing->id;
                $matched++;

                continue;
            }

            $names = User::splitName($old->name ?? '');
            $new = User::create([
                'first_name'  => $names['first_name'] !== '' ? $names['first_name'] : 'Player',
                'last_name'   => $names['last_name'],
                'email'       => $old->email,
                'password'    => null, // hashed values copied raw below
                'role'        => $old->role ?? 'user',
                'guest_token' => $old->guest_token,
                'avatar'      => $old->avatar ?? null,
            ]);
            // Copy the already-hashed password verbatim (bypass the cast).
            if ($old->password) {
                DB::table('users')->where('id', $new->id)
                    ->update(['password' => $old->password]);
            }
            $this->userMap[$old->id] = $new->id;
            $created++;
        }

        $this->line("users: {$matched} matched to existing, {$created} created");
    }

    /**
     * Copy a table verbatim (ids preserved) with user FKs remapped.
     */
    private function importTable($src, string $from, string $to, array $userCols = []): void
    {
        $count = 0;
        foreach ($src->table($from)->orderBy('id')->cursor() as $row) {
            $data = (array) $row;
            foreach ($userCols as $col) {
                if (isset($data[$col]) && $data[$col] !== null) {
                    $data[$col] = $this->userMap[$data[$col]]
                        ?? throw new \RuntimeException("{$from}.{$col}: unmapped user {$data[$col]}");
                }
            }
            DB::table($to)->insert($data);
            $count++;
        }
        $this->line("{$from} -> {$to}: {$count}");
    }
}
