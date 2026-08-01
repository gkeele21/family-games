# Spec — America Says: rounds, escalating points & sweep bonus

Status: **backend built** (setup UI pending) · Owner: Bert · Mockup: [setup-consolidation-mockup.html](setup-consolidation-mockup.html)

> **Implementation status.** Backend done & tested: migration (`bonus_points`, `round_number` on
> `session_questions`), `SessionQuestion` model, `GameInitializationService::initializeAmericaSays`
> (rounds = one question per team; stamps per-round `points_available`/`bonus_points`; flat fallback
> when `round_scoring` is absent), `HostController::revealAnswer` (per-round points + sweep bonus,
> steal excluded), and `nextQuestion` round advancement. **Remaining:** the setup screen doesn't yet
> write `round_scoring` — until it does, games use the flat fallback.

## Goal

Make America Says score like the show:

- The game plays a set number of **rounds**. A round plays **one question per team**
  (2 teams → 2 questions per round). Questions pulled = `rounds × teams`.
- Every round has a **points-per-answer** value that escalates: Round 1 = 100, Round 2 = 200,
  Round 3 = 300, …
- A team earns that value for **each correct answer** it reveals.
- If a team **sweeps the board** (reveals every answer in the question), it earns that round's
  **sweep bonus** on top. Default bonus = `10 × points-per-answer` (1,000 / 2,000 / 3,000…),
  editable per round.

Reference totals (7 answers/question):

| Round | Pts/answer | Max from answers | Sweep bonus | Swept total |
|------:|-----------:|-----------------:|------------:|------------:|
| 1 | 100 | 700 | 1,000 | 1,700 |
| 2 | 200 | 1,400 | 2,000 | 3,400 |
| 3 | 300 | 2,100 | 3,000 | 5,100 |

## Design principle — keep the columns generic

Per-question scoring lives on `session_questions` as **game-agnostic** columns. Each game's
engine decides how to use them. America Says uses `points_available` as the per-answer value and
`bonus_points` as the sweep bonus; another game could interpret them differently.

## Data model

`session_questions` today has `points_available` (unsignedInteger). Add:

- **`bonus_points`** `unsignedInteger default 0` — generic per-question bonus pot. America Says
  awards it on a board sweep. New migration; add to `SessionQuestion` `$fillable` + integer cast.
- **`round_number`** `unsignedInteger nullable` — which round this question belongs to (for
  grouping + display, e.g. "Round 2"). New migration; add to `$fillable` + integer cast.

No change to the `questions`/`answers` tables — per-answer value comes from the round, not the
stored answer points.

### Rounds representation — no separate table

Rounds are **not** a new DB table. A round is a lightweight tag + values stamped onto each
question at init: `round_number` groups the questions, `points_available` / `bonus_points` carry
the scoring. The per-round values the host enters live in `settings.round_scoring[]` (JSON) and are
copied onto the questions during initialization. Gameplay reads `round_number` off the current
question and mirrors it into `game_states.round_number` for display.

> A dedicated `session_rounds` table would only be warranted if a round needed its own persisted
> state (round winner, round status, per-round metadata). This model doesn't — round outcome is
> derivable from the questions — so we stay tag-based.
>
> Note: the existing `rounds` table belongs to the separate **Scorekeeper** feature and is
> unrelated to this flow.

## Settings shape (game `settings` JSON)

Add to America Says config:

```jsonc
{
  "rounds": 3,                       // number of rounds
  "round_scoring": [                 // one entry per round, index 0 = round 1
    { "points_per_answer": 100, "bonus_points": 1000 },
    { "points_per_answer": 200, "bonus_points": 2000 },
    { "points_per_answer": 300, "bonus_points": 3000 }
  ]
}
```

`questions_per_game` is derived (`rounds × teams`) and no longer set directly. `number_of_teams`
(already added) determines questions-per-round.

## Init — `GameInitializationService::initializeAmericaSays`

Currently pulls `questions_per_game` random questions into a flat list with no `points_available`.
Change to:

1. Read `rounds` and `round_scoring` from config; read team count.
2. Pull `rounds × teams` random active questions.
3. For each round `r` (1-based), assign `teams` questions to that round; for each:
   - `round_number = r`
   - `points_available = round_scoring[r-1].points_per_answer`
   - `bonus_points = round_scoring[r-1].bonus_points`
   - `display_order` sequential across the whole game
4. Set the first question active.

## Scoring — `HostController` (America Says path)

- **Per answer:** when an answer is revealed/credited, award the question's `points_available`
  (not the flat `points_per_answer` config). Steal-round percentage still applies on top.
- **Sweep bonus (new):** when the **controlling team** reveals the **last** remaining answer of a
  question (completing the board itself), award `bonus_points` to that team. Guard so it fires once
  per question. **A steal never earns the sweep bonus** — a stealing team gets only the per-answer
  round points.

## Round progression

`round_number` on `game_states` is currently stuck at 1 and never advances. On advancing to the
next question, set the game-state `round_number` from the incoming question's `round_number` so the
host/display can show "Round 2". (Cosmetic grouping; scoring reads the per-question columns.)

## Frontend

- **Setup:** the unified one-screen setup (this mockup) — Rounds stepper + a per-round editor with
  **Points / answer** and **Sweep bonus** (bonus defaults to 10× value, editable). Flat mode =
  all rounds share round 1's values.
- **Host game / display:** show current round number and its point value; surface the sweep bonus
  when earned.

## Decisions locked

- Round = one question per team; questions = `rounds × teams`.
- Per-answer scoring escalates by round; sweep bonus stacks on top; bonus default = 10× value.
- Bonus stored generically as `session_questions.bonus_points`.
- Rounds are tag-based (no `session_rounds` table).
- **Steal earns round points only — never the sweep bonus.** Sweep bonus is exclusive to the
  controlling team completing the board itself.
- **Sweep = all answers for the question, regardless of count** (7 for America Says; generic per
  game). Sweep bonus is a flat per-round amount, not scaled by answer count.
- **Winning condition = most points after all rounds** — highest team total at the end wins. No
  change to the existing scoring/winner logic; it reads final team totals.

## Open questions

None — spec is locked and ready to build.
