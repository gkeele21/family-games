# PropOff → family_games data migration

One-time (re-runnable) migration of the standalone **PropOff** production
database into the `propoff_*` tables of the unified **family_games** database,
merging PropOff's `users` into the shared `users` table.

- Command: `app/Console/Commands/ImportPropOff.php` — `php artisan propoff:import` (`--dry-run` reports source counts only).
- Source connection: `propoff_source` (config/database.php, `PROPOFF_SOURCE_*` in `.env`) → database `propoff`.
- Target: the app's default connection → database `family_games`.
- Pre-migration rollback point: `/Users/bert/WebProjects/KeelerGames/DBs/family_games_pre_migration_2026_07_21.dump`.
- Old→new user id map persisted at `storage/app/propoff_user_id_map.json`.

## Strategy

1. **Users first** (drives all FK remapping). PropOff `users` merge into the
   existing `family_games.users`. Build `old_user_id → new_user_id` map.
2. **Domain tables**: target `propoff_*` tables are empty, so **original
   primary keys are preserved** (all inter-table FKs — event/group/question/
   entry ids — stay valid). Only user-referencing columns are remapped through
   the id map. Loaded in FK-dependency order inside one transaction.
3. **Idempotent**: `propoff_*` tables are truncated and re-imported on each run
   (never `users` or any non-PropOff table). Users are matched by `guest_token`
   then `email`, so re-runs reuse the same accounts instead of duplicating.

## Users merge

`propoff.users` (single `name`) → `family_games.users` (`first_name` + `last_name`).

| Source column | Target column | Handling |
|---|---|---|
| `name` | `first_name`, `last_name` | Split on first whitespace (`User::splitName`). Empty first name → `'Player'`. |
| `email` | `email` | Copied. Dedup key (case-insensitive). |
| `role` (enum) | `role` (varchar 20) | Copied verbatim. For matched existing accounts, role only **elevates** to admin/manager — a source `guest` never downgrades an existing account. |
| `password` | `password` | Copied **raw** via `DB::table` update to bypass the `hashed` cast (already-hashed value must not be re-hashed). |
| `email_verified_at` | `email_verified_at` | Preserved (all NULL in source). |
| `remember_token` | `remember_token` | Preserved (3 rows populated). |
| `guest_token` | `guest_token` | Copied. |
| `avatar` | `avatar` | Copied. |
| `created_at`, `updated_at` | same | **Preserved** via raw update (Eloquent would otherwise stamp "now"). |

**Dedup outcome:** `family_games.users` held 2 accounts pre-migration, both of
which collided by email and were reused (not re-inserted):

- `bertkeele@gmail.com` — source id 1 (role `manager`) → existing id **2**; elevated to `manager`.
- `grantkeele@gmail.com` — source id 4 (role `guest`) → existing id **1**; role left as `user` (no downgrade).

Result: 138 source users → 2 matched + 136 created = **138 total** users.

## Domain table column maps

All domain pairs are structurally identical except that MySQL `enum`
columns in `propoff` were widened to `varchar` in `family_games` (values are a
subset, so they carry over unchanged). Primary keys preserved; `created_at`/
`updated_at` copied verbatim. Row arrays are inserted raw via `DB::table`, so
JSON columns (`options`, `variables`, `default_options`) transfer as-is.

| Source → Target | User-ref columns remapped | Schema notes |
|---|---|---|
| `events` → `propoff_events` | `created_by` | `event_type`/`status` enum→varchar. |
| `question_templates` → `propoff_question_templates` | `created_by` | `question_type` enum→varchar; `category` relaxed NOT NULL→nullable (all populated). |
| `question_template_answers` → `propoff_question_template_answers` | — | `answer_text` varchar(255)→text. 0 rows. |
| `event_questions` → `propoff_event_questions` | — | `question_type` enum→varchar; `display_order` gains default 0. |
| `groups` → `propoff_groups` | `created_by` | `grading_source` enum→varchar. |
| `user_groups` → `propoff_group_user` | `user_id` | **Confirmed pivot mapping** — identical column set (`id, user_id, group_id, joined_at, is_captain, created_at, updated_at`); target `joined_at` relaxed to nullable. |
| `group_questions` → `propoff_group_questions` | — | `question_type` enum→varchar; `display_order` gains default 0. |
| `event_answers` → `propoff_event_answers` | `set_by` | Identical. `correct_answer` NOT NULL in both — verified 0 NULLs in source. |
| `group_question_answers` → `propoff_group_question_answers` | — | Target `correct_answer` is NOT NULL vs source nullable — verified 0 NULLs in source, so no default needed. |
| `entries` → `propoff_entries` | `user_id`, `submitted_by_captain_id` | Identical. |
| `user_answers` → `propoff_user_answers` | — (user via `entry_id`) | Identical. |
| `leaderboards` → `propoff_leaderboards` | `user_id` | Identical; several ints gain defaults in target (all values present). |
| `event_invitations` → `propoff_event_invitations` | — (no `created_by` column) | Identical. |
| `captain_invitations` → `propoff_captain_invitations` | `created_by` | Identical. |

### Skipped

- `america_says_game_states` (0 rows): **no target table exists** in
  `family_games` (there is no `propoff_america_says_game_states`). Empty in
  source, so nothing to migrate. **If it had data, a new target table would be
  required first** — flagged, not silently dropped.
- `question_template_answers` (0 rows): mapped but empty.
- Framework tables (`migrations`, `sessions`, `cache*`, `jobs*`, `failed_jobs`,
  `*_tokens`): intentionally not migrated.

## Load order (FK-safe, single transaction)

`users` → `events` → `question_templates` → `question_template_answers` →
`event_questions` → `groups` → `group_user` → `group_questions` →
`event_answers` → `group_question_answers` → `entries` → `user_answers` →
`leaderboards` → `event_invitations` → `captain_invitations`.

## Verification (post-run)

| Table | Source | Migrated |
|---|---|---|
| users | 138 | 138 (2 merged + 136 created) |
| user_answers | 4878 | 4878 |
| group_questions | 387 | 387 |
| group_question_answers | 234 | 234 |
| leaderboards | 213 | 213 |
| group_user (user_groups) | 123 | 123 |
| entries | 105 | 105 |
| event_questions | 51 | 51 |
| event_answers | 51 | 51 |
| event_invitations | 8 | 8 |
| groups | 8 | 8 |
| question_templates | 50 | 50 |
| events | 1 | 1 |
| captain_invitations | 1 | 1 |

- **Zero orphaned user references** across every remapped column
  (`group_user.user_id`, `entries.user_id`, `entries.submitted_by_captain_id`,
  `leaderboards.user_id`, `events/groups/question_templates/captain_invitations.created_by`,
  `event_answers.set_by`).
- **Spot check** — source user 5 (Jackson) → new id 3: 2 leaderboard rows and
  51 user_answers in both; leaderboard row content (event/group/rank/score/
  percentage) identical; `created_at`/`updated_at` preserved exactly.
