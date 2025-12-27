# Oodles Game Rules & Mechanics

## Overview
Oodles is a word-guessing party game where teams compete to answer questions. All answers on a single card share the same starting letter. Teams take turns controlling questions, with opportunities to steal when the controlling team answers incorrectly.

---

## Core Concepts

### Cards
- Each game session consists of multiple **cards** (default: 15)
- Each card has a **letter constraint** (A-Z) - all answers on that card start with that letter
- Cards contain a configurable number of questions:
  - **Fixed mode**: Host specifies exact number (e.g., 5 questions per card)
  - **Random mode**: System picks randomly within a range (default: 3-10)
- Cards are **dynamically generated** per session - the same question may appear on different card numbers across sessions
- Letters may repeat across cards in a session (configurable)

### Questions & Answers
- Each question has exactly **one correct answer**
- The answer always starts with the card's designated letter
- Questions are randomly assigned to cards when a game session starts
- Each question has a **point value** (variable, stored in the question bank)

### Teams
- A team may consist of **1 or more members**
- Teams are assigned a rotation order at the start of the game
- Teams accumulate points throughout the session

---

## Gameplay Flow

### Game Setup
1. Host creates a game session and configures settings
2. Teams join the session (via invite code)
3. System generates cards by:
   - Selecting N letters (based on `cards_per_game` setting)
   - For each letter, randomly selecting X questions with matching `answer_letter`
   - Shuffling question order within each card
4. Team rotation order is established (random or host-defined)

### Card Play

```
┌─────────────────────────────────────────────────────────────────┐
│                        CARD STARTS                               │
│                    (Letter: "B" revealed)                        │
└─────────────────────────────────────────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────────┐
│                      QUESTION DISPLAYED                          │
│              Controlling Team: Team A (rotation)                 │
│                    Timer: 30 seconds                             │
└─────────────────────────────────────────────────────────────────┘
                               │
              ┌────────────────┴────────────────┐
              ▼                                 ▼
        ┌──────────┐                     ┌───────────┐
        │ CORRECT  │                     │   WRONG   │
        └────┬─────┘                     └─────┬─────┘
             │                                 │
             ▼                                 ▼
   ┌───────────────────┐           ┌─────────────────────┐
   │ Team A scores     │           │      ALL PLAY       │
   │ FULL points       │           │   Timer: 10 seconds │
   │                   │           │   Any team can buzz │
   │ Rotation advances │           └──────────┬──────────┘
   │ Team B controls   │                      │
   │ next question     │     ┌────────────────┼────────────────┐
   └───────────────────┘     ▼                ▼                ▼
                       ┌──────────┐     ┌──────────┐     ┌──────────┐
                       │ Team C   │     │ Team A   │     │ Timeout  │
                       │ STEALS   │     │ REDEEMS  │     │ (none)   │
                       └────┬─────┘     └────┬─────┘     └────┬─────┘
                            │                │                │
                            ▼                ▼                ▼
                   ┌─────────────┐  ┌─────────────┐  ┌─────────────┐
                   │ Team C gets │  │ Team A gets │  │ No points   │
                   │ STEAL pts   │  │ STEAL pts   │  │ awarded     │
                   │             │  │             │  │             │
                   │ Team C      │  │ Team A      │  │ Team A      │
                   │ EARNS       │  │ RETAINS     │  │ SURVIVES    │
                   │ control     │  │ control     │  │ keeps ctrl  │
                   └─────────────┘  └─────────────┘  └─────────────┘
                            │                │                │
                            └────────────────┴────────────────┘
                                             │
                                             ▼
                               ┌─────────────────────────┐
                               │    NEXT QUESTION        │
                               │  (or next card if done) │
                               └─────────────────────────┘
```

---

## Control Rules

### How Control is Determined

| Scenario | Who Controls Next Question |
|----------|---------------------------|
| Controller answers correctly | Rotation advances to next team |
| Controller wrong → other team steals | Stealing team earns control |
| Controller wrong → controller redeems in All Play | Controller retains control |
| Controller wrong → timeout (no one answers) | Controller survives, retains control |

### Control Status States

| Status | Description |
|--------|-------------|
| `team_control` | A specific team has exclusive first-answer rights |
| `open` | Question is open after wrong answer, any team can answer |
| `all_play` | Triggered by timeout scenario (rarely used) |

---

## Scoring

### Point Values
- Each question has a configurable point value (stored in question bank)
- Points are variable - harder questions can be worth more

### Scoring Scenarios

| Scenario | Points Awarded |
|----------|---------------|
| Controlling team answers correctly | Full points |
| Team steals after wrong answer | Steal percentage (configurable, default 100%) |
| Controller redeems during All Play | Steal percentage |
| Timeout - no correct answer | 0 points |

### Steal Points Configuration
- `steal_points_percentage`: 0-100 (default: 100)
- Examples:
  - 100% = Steal is worth same as controlling
  - 50% = Steal is worth half points
  - 0% = No points for steals (defensive play)

---

## Timers

### Control Timer
- Duration: Configurable (default: 30 seconds)
- Applies when: A team controls a question
- Host can: End timer early if team answers wrong
- When expires: Question goes to All Play

### All Play Timer
- Duration: Configurable (default: 10 seconds)
- Applies when: Question is open to all teams
- When expires: No points, controlling team survives

---

## Winning Conditions

The host selects one of these winning conditions at game setup:

| Condition | Description |
|-----------|-------------|
| `most_points_after_cards` | Highest score after N cards are played |
| `first_to_points` | First team to reach X points wins immediately |
| `host_ends` | Host manually ends game, highest score wins |

The host can always end the game early regardless of winning condition.

---

## Host Controls

### During Game
- **Skip Question**: Move to next question without scoring
- **Skip Card**: Move to next card entirely
- **End Timer**: Manually end control timer (triggers All Play)
- **End Game**: Conclude game and determine winner
- **Pause/Resume**: Pause gameplay if needed

### Pre-Game Configuration
- Number of cards per game
- Questions per card (min/max range)
- Timer durations (control and all play)
- Steal points percentage
- Winning condition and threshold
- Allow letter reuse across cards

---

## Configuration Reference

### Default Settings

```json
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
  "winning_condition": "most_points_after_cards",
  "winning_condition_options": {
    "first_to_points": null,
    "cards_to_play": 15
  }
}
```

### Configurable Settings

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `cards_per_game` | int | 15 | Number of cards in session |
| `questions_per_card_mode` | enum | `random` | `random` or `fixed` |
| `fixed_questions_per_card` | int | null | Exact # if mode is `fixed` |
| `min_questions_per_card` | int | 3 | Minimum questions (if random) |
| `max_questions_per_card` | int | 10 | Maximum questions (if random) |
| `allow_letter_reuse` | bool | true | Can same letter appear on multiple cards |
| `control_timer_seconds` | int | 30 | Seconds for controlling team |
| `all_play_timer_seconds` | int | 10 | Seconds during All Play |
| `steal_points_percentage` | int | 100 | Points % for steals (0-100) |
| `winning_condition` | enum | `most_points_after_cards` | How winner is determined |

### Questions Per Card Mode

| Mode | Behavior |
|------|----------|
| `random` | Each card gets a random count between `min` and `max` |
| `fixed` | Every card gets exactly `fixed_questions_per_card` questions |

---

## Game State Tracking

### Session-Level State

```json
{
  "current_card_id": 12,
  "current_card_letter": "B",
  "current_question_index": 3,
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

### State Fields Explained

| Field | Description |
|-------|-------------|
| `current_card_id` | FK to session_cards |
| `current_card_letter` | The letter for current card (denormalized) |
| `current_question_index` | Position within current card |
| `team_order` | Array of team IDs in rotation order |
| `controlling_team_id` | Team with control rights |
| `control_earned_by` | `rotation`, `steal`, or `survival` |
| `control_status` | `team_control`, `open`, or `all_play` |
| `timer_mode` | `control` or `all_play` |
| `timer_deadline` | ISO timestamp when timer expires |
| `last_question_result` | `correct`, `steal`, `timeout`, `skipped` |

---

## Database Schema (Oodles-Specific)

### Question Bank

**questions table additions:**
| Column | Type | Notes |
|--------|------|-------|
| `answer_letter` | char(1) | The letter (A-Z) for Oodles questions |

**answers table:**
| Column | Type | Notes |
|--------|------|-------|
| `points` | int | Point value for this answer (variable per question) |

### Session Tables

**session_cards (new table):**
| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | PK |
| `game_session_id` | FK | Links to game session |
| `card_number` | int | Order in session (1, 2, 3...) |
| `letter` | char(1) | The card's letter |

**session_questions additions:**
| Column | Type | Notes |
|--------|------|-------|
| `session_card_id` | FK | Links to session_cards |
| `controlling_team_id` | FK | Team with control (nullable) |
| `control_status` | enum | `team_control`, `open`, `all_play` |

---

## Card Generation Algorithm

When a game session starts:

```
1. Get configuration:
   - cards_per_game (N)
   - questions_per_card_mode ("random" or "fixed")
   - fixed_questions_per_card (if mode is "fixed")
   - min_questions_per_card (if mode is "random")
   - max_questions_per_card (if mode is "random")
   - allow_letter_reuse

2. Get available letters:
   - Query distinct answer_letters from questions
   - Filter to letters with sufficient questions

3. For each card (1 to N):
   a. Select a letter:
      - If allow_letter_reuse: random from available
      - If not: random from unused letters

   b. Determine question count:
      - If mode is "fixed": use fixed_questions_per_card
      - If mode is "random": random between min and max

   c. Select questions:
      - Query questions with matching answer_letter
      - Exclude already-used questions in this session
      - Random selection of X questions

   d. Create session_card record

   e. Create session_questions records:
      - Link to session_card
      - Set display_order (shuffled)
      - Set initial control based on team rotation

4. Set initial game state:
   - current_card_id = first card
   - controlling_team_id = first team in rotation
   - control_status = 'team_control'
```

---

## Example Game Session

**Setup:**
- 3 Teams: Red, Blue, Green
- Team order: [Red, Blue, Green]
- 5 cards, 4 questions each
- Letter reuse: allowed

**Card 1 (Letter: M):**
| Q# | Controller | Result | Points | Next Controller |
|----|-----------|--------|--------|-----------------|
| 1 | Red | Correct (200 pts) | Red +200 | Blue (rotation) |
| 2 | Blue | Wrong → Green steals | Green +150 | Green (earned) |
| 3 | Green | Wrong → timeout | 0 | Green (survival) |
| 4 | Green | Correct (100 pts) | Green +100 | Red (rotation) |

**Card 1 Totals:** Red: 200, Blue: 0, Green: 250

**Card 2 (Letter: S):**
- Red controls first question (rotation continued from Card 1)
- ...and so on

---

## Edge Cases

### Not Enough Questions
If there aren't enough questions for a letter to fill the minimum requirement:
- Skip that letter when selecting
- Log warning for admin

### All Letters Exhausted
If `allow_letter_reuse = false` and all letters with sufficient questions are used:
- Game ends early
- Or allow reuse for remaining cards (configurable)

### Team Leaves Mid-Game
- Remove from rotation
- If controlling, move to next team
- Their score remains (for historical tracking)

### Single Team
- Game still playable (practice mode)
- No steal scenarios
- Just timed Q&A for points

---

*Last Updated: 2024-12-26*
