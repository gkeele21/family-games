# Family Games - Data Model

## Overview
This document defines the database schema for the Family Games application, supporting multiple game types including Family Feud, America Says, and Oodles.

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        QUESTION BANK                            │
├─────────────────────────────────────────────────────────────────┤
│  game_types ──< categories ──< questions ──< answers            │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                        GAME SESSIONS                            │
├─────────────────────────────────────────────────────────────────┤
│  game_sessions ──< teams ──< team_members                       │
│        │                                                        │
│        ├──< session_cards (Oodles)                              │
│        │         │                                              │
│        ├──< session_questions ──< answer_reveals                │
│        │                                                        │
│        └──○ game_state (1:1, current state)                     │
└─────────────────────────────────────────────────────────────────┘
```

---

## Question Bank Tables

### game_types

Defines the supported games.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| name | string | "Family Feud", "America Says", "Oodles" |
| slug | string | "family-feud", "america-says", "oodles" |
| description | text | |
| default_config | json | Default timer, rounds, team size, etc. |
| created_at | timestamp | |
| updated_at | timestamp | |

**Example default_config:**

```json
// Family Feud
{
  "rounds_per_game": 4,
  "face_off_mode": "buzzer",
  "max_strikes": 3,
  "steal_mode": "one_guess",
  "steal_timer_seconds": 30,
  "round_multipliers": {"1": 1, "2": 1, "3": 2, "4": 3},
  "fast_money_enabled": true,
  "fast_money_player1_seconds": 20,
  "fast_money_player2_seconds": 25,
  "fast_money_target_score": 200,
  "play_or_pass_enabled": true,
  "answers_per_question": 8
}

// America Says
{
  "questions_per_game": 10,
  "answers_per_question": 7,
  "control_timer_seconds": 30,
  "steal_timer_seconds": 10,
  "steal_points_percentage": 50,
  "points_per_answer": 100,
  "gameplay_mode": "host_reveal",
  "winning_condition": "most_points_after_questions"
}

// Oodles
{
  "cards_per_game": 15,
  "questions_per_card_mode": "random",
  "fixed_questions_per_card": null,
  "min_questions_per_card": 3,
  "max_questions_per_card": 10,
  "allow_letter_reuse": true,
  "control_timer_seconds": 30,
  "all_play_timer_seconds": 10,
  "steal_points_percentage": 100,
  "winning_condition": "most_points_after_cards"
}
```

---

### categories

Groups questions by topic within a game type.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| game_type_id | FK | References game_types |
| name | string | "Food & Drink", "Pop Culture", etc. |
| description | text | nullable |
| is_active | boolean | |
| created_at | timestamp | |
| updated_at | timestamp | |

---

### questions

The master question bank.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| game_type_id | FK | References game_types |
| category_id | FK | References categories, nullable |
| question_text | text | The prompt shown to players |
| difficulty | enum | easy, medium, hard |
| answer_letter | char(1) | For Oodles: the letter (A-Z), nullable |
| is_fast_money | boolean | For Family Feud: Fast Money question |
| metadata | json | Game-specific data |
| created_by | FK | References users |
| is_active | boolean | |
| times_used | int | Track popularity |
| created_at | timestamp | |
| updated_at | timestamp | |

**Indexes:**
- `game_type_id`
- `category_id`
- `answer_letter` (for Oodles card generation)
- `is_fast_money` (for Family Feud Fast Money selection)
- `is_active`

---

### answers

Answers associated with each question.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| question_id | FK | References questions |
| answer_text | string | |
| points | int | Family Feud: survey rank; Oodles: question value |
| display_order | int | For reveal ordering |
| created_at | timestamp | |
| updated_at | timestamp | |

**Notes by Game Type:**
- **Family Feud**: Multiple answers per question, points based on survey popularity
- **America Says**: Multiple answers, flat or varied points
- **Oodles**: Single answer per question, variable point value

---

## Game Session Tables

### game_sessions

When someone creates a game to play.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| game_type_id | FK | References game_types |
| host_user_id | FK | References users |
| name | string | "Keele Family Game Night" |
| status | enum | lobby, playing, paused, completed |
| settings | json | Host overrides for default_config |
| invite_code | string | For joining (e.g., "ABC123") |
| started_at | timestamp | nullable |
| completed_at | timestamp | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |

**Indexes:**
- `invite_code` (unique)
- `host_user_id`
- `status`

---

### teams

Teams within a game session.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| game_session_id | FK | References game_sessions |
| name | string | "Team A" |
| color | string | "#FF5733" for UI |
| display_order | int | Rotation order |
| total_score | int | Denormalized for quick access |
| created_at | timestamp | |
| updated_at | timestamp | |

---

### team_members

Players on each team.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| team_id | FK | References teams |
| user_id | FK | References users, nullable (guest players) |
| guest_name | string | If no user account |
| is_captain | boolean | |
| created_at | timestamp | |
| updated_at | timestamp | |

---

### session_cards

Cards generated for Oodles sessions.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| game_session_id | FK | References game_sessions |
| card_number | int | Order in session (1, 2, 3...) |
| letter | char(1) | The card's letter |
| status | enum | pending, active, completed, skipped |
| created_at | timestamp | |
| updated_at | timestamp | |

**Notes:**
- Only used for Oodles game type
- Created when game session starts

---

### session_questions

Questions selected for a game session.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| game_session_id | FK | References game_sessions |
| session_card_id | FK | References session_cards, nullable (Oodles only) |
| question_id | FK | References questions |
| display_order | int | Order in this session/card |
| status | enum | pending, active, completed, skipped |
| controlling_team_id | FK | References teams, nullable |
| control_status | enum | team_control, open, all_play |
| points_available | int | Calculated when loaded |
| played_at | timestamp | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |

---

### game_state

Current game status (singleton per session).

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| game_session_id | FK | References game_sessions (unique) |
| current_question_id | FK | References session_questions, nullable |
| current_card_id | FK | References session_cards, nullable |
| active_team_id | FK | References teams |
| round_number | int | |
| timer_started_at | timestamp | nullable |
| timer_duration | int | seconds |
| state_data | json | Game-specific state |
| created_at | timestamp | |
| updated_at | timestamp | |

**Example state_data by Game Type:**

```json
// Family Feud
{
  "current_round": 2,
  "round_multiplier": 1,
  "phase": "playing",
  "controlling_team_id": 1,
  "other_team_id": 2,
  "strikes": 2,
  "point_pool": 127,
  "revealed_answer_ids": [101, 103, 105],
  "face_off_state": {
    "player1_team_id": 1,
    "player2_team_id": 2,
    "player1_answer_id": 101,
    "winner_team_id": 1
  },
  "member_rotation_index": 3,
  "fast_money": null
}

// America Says
{
  "current_question_index": 3,
  "team_order": [1, 3, 2, 4],
  "team_rotation_index": 2,
  "controlling_team_id": 2,
  "phase": "control",
  "stealing_team_id": null,
  "timer_mode": "control",
  "timer_deadline": "2024-12-26T20:15:30Z",
  "revealed_answer_ids": [101, 103, 105],
  "answers_guessed_by_controller": 3,
  "answers_guessed_by_stealer": 0
}

// Oodles
{
  "current_card_letter": "B",
  "team_order": [1, 3, 2, 4],
  "controlling_team_id": 3,
  "control_earned_by": "steal",
  "control_status": "team_control",
  "timer_mode": "control",
  "timer_deadline": "2024-12-26T20:15:30Z",
  "questions_played_this_card": 3,
  "last_question_result": "steal"
}
```

---

### answer_reveals

Tracks guessed/revealed answers.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| session_question_id | FK | References session_questions |
| answer_id | FK | References answers |
| team_id | FK | References teams, nullable (host reveal) |
| revealed_at | timestamp | |
| points_awarded | int | |
| created_at | timestamp | |
| updated_at | timestamp | |

---

## Users Table

Standard Laravel authentication table.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| name | string | |
| email | string | unique |
| password | string | |
| email_verified_at | timestamp | nullable |
| remember_token | string | nullable |
| created_at | timestamp | |
| updated_at | timestamp | |

---

## Relationships Summary

### Eloquent Relationships

```php
// User
User::hasMany(GameSession::class, 'host_user_id');
User::hasMany(Question::class, 'created_by');
User::hasMany(TeamMember::class);

// GameType
GameType::hasMany(Category::class);
GameType::hasMany(Question::class);
GameType::hasMany(GameSession::class);

// Category
Category::belongsTo(GameType::class);
Category::hasMany(Question::class);

// Question
Question::belongsTo(GameType::class);
Question::belongsTo(Category::class);
Question::belongsTo(User::class, 'created_by');
Question::hasMany(Answer::class);

// Answer
Answer::belongsTo(Question::class);

// GameSession
GameSession::belongsTo(GameType::class);
GameSession::belongsTo(User::class, 'host_user_id');
GameSession::hasMany(Team::class);
GameSession::hasMany(SessionCard::class);
GameSession::hasMany(SessionQuestion::class);
GameSession::hasOne(GameState::class);

// Team
Team::belongsTo(GameSession::class);
Team::hasMany(TeamMember::class);
Team::hasMany(AnswerReveal::class);

// TeamMember
TeamMember::belongsTo(Team::class);
TeamMember::belongsTo(User::class);

// SessionCard (Oodles)
SessionCard::belongsTo(GameSession::class);
SessionCard::hasMany(SessionQuestion::class);

// SessionQuestion
SessionQuestion::belongsTo(GameSession::class);
SessionQuestion::belongsTo(SessionCard::class);
SessionQuestion::belongsTo(Question::class);
SessionQuestion::belongsTo(Team::class, 'controlling_team_id');
SessionQuestion::hasMany(AnswerReveal::class);

// GameState
GameState::belongsTo(GameSession::class);
GameState::belongsTo(SessionQuestion::class, 'current_question_id');
GameState::belongsTo(SessionCard::class, 'current_card_id');
GameState::belongsTo(Team::class, 'active_team_id');

// AnswerReveal
AnswerReveal::belongsTo(SessionQuestion::class);
AnswerReveal::belongsTo(Answer::class);
AnswerReveal::belongsTo(Team::class);
```

---

## Enums

### Question Difficulty
```php
enum QuestionDifficulty: string
{
    case EASY = 'easy';
    case MEDIUM = 'medium';
    case HARD = 'hard';
}
```

### Game Session Status
```php
enum GameSessionStatus: string
{
    case LOBBY = 'lobby';
    case PLAYING = 'playing';
    case PAUSED = 'paused';
    case COMPLETED = 'completed';
}
```

### Session Question Status
```php
enum SessionQuestionStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case COMPLETED = 'completed';
    case SKIPPED = 'skipped';
}
```

### Control Status (Oodles)
```php
enum ControlStatus: string
{
    case TEAM_CONTROL = 'team_control';
    case OPEN = 'open';
    case ALL_PLAY = 'all_play';
}
```

---

## Key Design Decisions

1. **Flexible state_data JSON** - Each game type has different mechanics; JSON lets us store game-specific state without dozens of nullable columns

2. **Separate session_questions from questions** - The master question bank is reusable; each game session picks from it

3. **answer_reveals as separate table** - Enables real-time tracking of what's been guessed, by whom, for scoring and replay

4. **Guest players supported** - team_members.user_id is nullable with a guest_name fallback

5. **Denormalized total_score** - Avoids constant SUM queries during active gameplay

6. **Invite codes** - Simple way for players to join a session without complex auth

7. **Session cards for Oodles** - Dynamically generated groupings that don't affect the master question bank

8. **answer_letter on questions** - Enables efficient card generation for Oodles without affecting other game types

---

## Migration Order

1. `create_users_table`
2. `create_game_types_table`
3. `create_categories_table`
4. `create_questions_table`
5. `create_answers_table`
6. `create_game_sessions_table`
7. `create_teams_table`
8. `create_team_members_table`
9. `create_session_cards_table`
10. `create_session_questions_table`
11. `create_game_state_table`
12. `create_answer_reveals_table`

---

*Last Updated: 2024-12-26*
