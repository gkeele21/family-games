# Note for Grant — America Says "who played" tracking

Short version: we added one table, `game_session_players`, to record which household
**roster players** were present at a party-game session (America Says / Family Feud).
It powers a single feature: flagging questions a group has **already been asked**, so we
don't repeat questions on the same people. This note explains why, what we reused of the
system you built, and — genuinely — asks whether there was a mechanism we should have used
instead.

## Why we needed a person↔session link at all

The goal is: *"has this specific person already seen this question?"* That needs two
persistent things joined together:

1. **what a session asked** — we already have this: `session_questions`.
2. **which persistent people were at that session** — we did **not** have this.

The existing party-game "person" tables don't provide #2 in a usable form:

- `session_players` → `user_id` (nullable) or a free-text `guest_name`. It's the join-by-code flow.
- `team_members` → `user_id` (nullable) or `guest_name`.

Both identify a participant as either **a logged-in account** or **a throwaway name string**.
Neither points at the `players` roster, so neither gives a *persistent, login-free identity*
we can follow across games. Our America Says group is ~18 people with no logins, so the
account path is out, and guest-name strings have no cross-game identity.

We also confirmed there was **no** existing per-person history anywhere — only global,
anonymous counters (`questions.times_used`, `times_correct/wrong`, `answer_reveals` at the
*team* level, `session_questions.played_at` at the *session* level). Nothing recorded who saw
what.

## What we reused (i.e. what you built, we leaned on)

We did **not** invent a new "person" concept. We reused your **households + players** roster
directly — that's exactly the login-free persistent-identity system this needed. Williams
Family is just a household; the 18 people are `players`. Owner-plays-too works the same way it
does for you (a roster player linked to the owner account).

The new pivot is a deliberate copy of your own Scorekeeper pattern:

```
Scorekeeper:  scored_games → scored_game_competitors → competitor_player → players
Party games:  game_sessions ───────── game_session_players ───────────── players
```

`game_session_players(game_session_id, player_id)` mirrors the original `scored_game_players`
one-to-one. So this extends your architecture rather than forking a parallel one.

## The alternative we considered and rejected

Add a `player_id` column to `session_players` instead of a new table. We passed because
`session_players` models the *join-by-invite-code* flow (account-or-guest, `unique(session, user)`),
and overloading it with "roster person was here" mixes two different concepts. A thin pivot,
matching your Scorekeeper precedent, was cleaner.

Deriving it indirectly (`session_players.user_id → users.id → players.user_id`) doesn't work:
both `user_id`s are nullable (drops all guests), one user maps to many players across
households, and `game_sessions` has no `household_id` anchor.

The only "bigger" alternative would be consolidating party-game participants onto the `players`
roster instead of the `session_players`/`team_members` user-or-guest model — a real refactor we
didn't think was warranted for this one feature.

## The honest question for you

Is there a mechanism you built (or intended) that we're not using? Specifically:
- Did you mean `session_players` / `team_members` to eventually reference the `players` roster?
- Is there a participant/roster path we overlooked that would make `game_session_players`
  redundant?

If so, we'll happily rip this out and use yours. From our audit it looks like the two worlds
were simply never connected, and this pivot is the smallest bridge — but you know the original
intent better than we do.

## How it ships

- **Schema** stays in a migration: `2026_08_12_000001_create_game_session_players_table`.
- **Data** (roster, the 4 backfilled games, `times_used` backfill) is a one-off idempotent SQL
  script: `database/sql/america_says_attendance_backfill.sql` — run once per environment.
- Ongoing "who played" edits happen at runtime via the lobby checklist and a dashboard editor
  (`host.attendance` endpoint), not migrations.
