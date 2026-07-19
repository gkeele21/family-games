<?php

namespace App\Services\Scorekeeper;

use App\Models\Scorekeeper\GameTemplate;
use App\Models\Scorekeeper\Household;
use App\Models\Scorekeeper\Round;
use App\Models\Scorekeeper\RoundScore;
use App\Models\Scorekeeper\ScoredGame;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ScoreGameService
{
    /**
     * Start a game from a template, snapshotting its config (score fields,
     * team flag, scoring rules) so later template edits never rewrite this
     * game. Each competitor is a scoring column: one player for an individual
     * game, one team (with N players) for a team game.
     *
     * @param  array<int, array{name: string, player_ids: array<int>}>  $competitors
     */
    public function startFromTemplate(
        Household $household,
        GameTemplate $template,
        array $competitors,
        User $creator,
        ?string $playedAt = null,
    ): ScoredGame {
        return DB::transaction(function () use ($household, $template, $competitors, $creator, $playedAt) {
            $game = ScoredGame::create([
                'household_id'           => $household->id,
                'game_template_id'       => $template->id,
                'template_name_snapshot' => $template->name,
                'base_game_type'         => $template->gameType?->name,
                'target_score'           => $template->target_score,
                'low_score_wins'         => $template->low_score_wins,
                'max_rounds'             => $template->max_rounds,
                'score_fields'           => $template->score_fields,
                'team_based'             => $template->team_based,
                'allow_self_scoring'     => (bool) $template->allow_self_scoring,
                // Play date: backdatable so past game nights can be recorded.
                'started_at'             => $playedAt ? \Carbon\Carbon::parse($playedAt) : now(),
                'is_complete'            => false,
                'created_by_user_id'     => $creator->id,
            ]);

            $order = 1;
            foreach ($competitors as $competitor) {
                $game->competitors()
                    ->create(['name' => $competitor['name'], 'display_order' => $order++])
                    ->players()
                    ->attach($competitor['player_ids']);
            }

            return $game;
        });
    }

    public function addRound(ScoredGame $game): Round
    {
        $next = ($game->rounds()->max('round_number') ?? 0) + 1;

        return $game->rounds()->create(['round_number' => $next]);
    }

    /**
     * Record (or overwrite) each competitor's field values for a round.
     *
     * @param  array<int, array<string, int|string|null>>  $byCompetitor  [competitorId => [fieldKey => value]]
     */
    public function recordScores(Round $round, array $byCompetitor): void
    {
        DB::transaction(function () use ($round, $byCompetitor) {
            foreach ($byCompetitor as $competitorId => $values) {
                $clean = [];
                foreach ($values as $key => $value) {
                    if ($value === null || $value === '') {
                        continue;
                    }
                    $clean[$key] = (int) $value;
                }

                RoundScore::updateOrCreate(
                    ['round_id' => $round->id, 'competitor_id' => (int) $competitorId],
                    ['values' => $clean],
                );
            }
        });
    }

    /**
     * Running total per competitor — sums only score fields flagged
     * counts_toward_total.
     *
     * @return array<int, int>
     */
    public function totals(ScoredGame $game): array
    {
        $game->loadMissing(['competitors', 'rounds.scores']);
        $countingKeys = $this->countingKeys($game);

        $sums = [];
        foreach ($game->competitors as $competitor) {
            $sums[$competitor->id] = 0;
        }
        foreach ($game->rounds as $round) {
            foreach ($round->scores as $score) {
                $values = $score->values ?? [];
                foreach ($countingKeys as $key) {
                    $sums[$score->competitor_id] =
                        ($sums[$score->competitor_id] ?? 0) + (int) ($values[$key] ?? 0);
                }
            }
        }

        return $sums;
    }

    /**
     * Per-competitor, per-field running subtotal (all fields, for display).
     *
     * @return array<int, array<string, int>>
     */
    public function fieldSubtotals(ScoredGame $game): array
    {
        $game->loadMissing(['competitors', 'rounds.scores']);
        $keys = array_map(fn ($f) => $f['key'], $game->score_fields ?? []);

        $out = [];
        foreach ($game->competitors as $competitor) {
            $out[$competitor->id] = array_fill_keys($keys, 0);
        }
        foreach ($game->rounds as $round) {
            foreach ($round->scores as $score) {
                $values = $score->values ?? [];
                foreach ($keys as $key) {
                    $out[$score->competitor_id][$key] =
                        ($out[$score->competitor_id][$key] ?? 0) + (int) ($values[$key] ?? 0);
                }
            }
        }

        return $out;
    }

    /**
     * Competitors ranked best-first (respecting low_score_wins).
     *
     * @return array<int, array{competitor_id:int, name:string, total:int, rank:int}>
     */
    public function standings(ScoredGame $game): array
    {
        $totals = $this->totals($game);
        $competitors = $game->competitors->keyBy('id');

        $rows = [];
        foreach ($totals as $competitorId => $total) {
            $rows[] = [
                'competitor_id' => $competitorId,
                'name'          => $competitors[$competitorId]->name ?? '—',
                'total'         => $total,
            ];
        }

        usort($rows, fn ($a, $b) => $game->low_score_wins
            ? $a['total'] <=> $b['total']
            : $b['total'] <=> $a['total']);

        $rank = 0;
        $prev = null;
        foreach ($rows as $i => &$row) {
            if ($prev === null || $row['total'] !== $prev) {
                $rank = $i + 1;
            }
            $row['rank'] = $rank;
            $prev = $row['total'];
        }

        return $rows;
    }

    public function completionMet(ScoredGame $game): bool
    {
        $totals = $this->totals($game);

        if ($game->target_score !== null && count($totals) > 0 && max($totals) >= $game->target_score) {
            return true;
        }

        if ($game->max_rounds !== null && $game->rounds()->count() >= $game->max_rounds) {
            return true;
        }

        return false;
    }

    public function complete(ScoredGame $game): void
    {
        $game->update([
            'is_complete' => true,
            'ended_at'    => now(),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function countingKeys(ScoredGame $game): array
    {
        return array_values(array_map(
            fn ($f) => $f['key'],
            array_filter(
                $game->score_fields ?? [],
                fn ($f) => $f['counts_toward_total'] ?? false,
            ),
        ));
    }
}
