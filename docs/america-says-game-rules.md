# America Says Game Rules & Mechanics

## Overview
America Says is a survey-based guessing game where teams try to guess the top answers to survey questions. One team controls each question and attempts to guess all answers within a time limit. If answers remain when time expires, another team gets a chance to steal.

---

## Core Concepts

### Questions & Answers
- Each question is a survey prompt (e.g., "Name something you hang on a Christmas tree")
- Each question has multiple answers (default: 7, configurable)
- Answers are ranked by popularity (Rank 1 = most Americans said this)
- All answers are worth the same points (flat scoring)
- Teams can guess answers in any order

### Answer Display
- **Unrevealed**: First letter + underscores (e.g., "O_______" for "Ornaments")
- **Revealed**: Full text with animation when correctly guessed
- Underscore count formula: `floor((word.length - 1) * 1.5)` to prevent length-based guessing

### Teams
- A team may consist of 1 or more members
- Teams rotate control in a fixed order
- Control always passes to next team in rotation (no earning control via steals)

---

## Gameplay Flow

### Game Setup
1. Host creates a game session and configures settings
2. Teams join the session (via invite code)
3. System selects questions (random, by category, or host-picked)
4. Team rotation order is established

### Question Play

```
┌─────────────────────────────────────────────────────────────────┐
│                    QUESTION DISPLAYED                            │
│              "Name something you hang on a Christmas tree"       │
│                                                                  │
│                    7 answers (obfuscated):                       │
│              O_______ L_____ S___ T_____ G______                │
│                       A____ C____ C____                          │
│                                                                  │
│               Team A controls (timer: 30 seconds)                │
└─────────────────────────────────────────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────────┐
│                      TEAM A GUESSING                             │
│                                                                  │
│  Team shouts guesses → Host reveals correct ones                 │
│  Wrong guesses: buzzer sound, no penalty, continue               │
│  Guesses can be in ANY order (no passing needed)                 │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
                               │
              ┌────────────────┴────────────────┐
              ▼                                 ▼
     ┌──────────────────┐              ┌──────────────────┐
     │ ALL ANSWERS      │              │ TIMER EXPIRES    │
     │ guessed in time  │              │ answers remain   │
     └────────┬─────────┘              └────────┬─────────┘
              │                                 │
              ▼                                 ▼
     Team A gets FULL pts         ┌──────────────────────┐
     for ALL answers              │    STEAL ROUND       │
                                  │  Next team in order  │
                                  │  Timer: 10s (config) │
                                  │  Only remaining shown│
                                  └──────────┬───────────┘
                                             │
                        ┌────────────────────┼────────────────────┐
                        ▼                    ▼                    ▼
                  ┌───────────┐        ┌───────────┐        ┌───────────┐
                  │ Gets some │        │ Gets all  │        │ Timeout   │
                  │ remaining │        │ remaining │        │ (none)    │
                  └─────┬─────┘        └─────┬─────┘        └─────┬─────┘
                        │                    │                    │
                        ▼                    ▼                    ▼
               Steal % points         Steal % points        No steal pts
               for each correct       for ALL remaining     Host reveals
                                                            remaining
                        │                    │                    │
                        └────────────────────┴────────────────────┘
                                             │
                                             ▼
                              ┌───────────────────────────┐
                              │   NEXT QUESTION           │
                              │   Team B now controls     │
                              │   (rotation advances)     │
                              └───────────────────────────┘
```

---

## Control Rules

### Team Rotation
- Teams rotate in a fixed order: A → B → C → A → B → C...
- Control **always** advances to next team after each question
- Stealing does NOT earn control of next question (unlike Oodles)

| Scenario | Who Controls Next Question |
|----------|---------------------------|
| Controller gets all answers | Next team in rotation |
| Controller times out, steal succeeds | Next team in rotation |
| Controller times out, steal fails | Next team in rotation |

---

## Scoring

### Points Model
- **Flat scoring**: All answers worth the same points
- Points per answer is configurable (default: 100)
- Question total = `answers_per_question × points_per_answer`

### Scoring Scenarios

| Scenario | Points Awarded |
|----------|---------------|
| Controlling team guesses answer | Full points (e.g., 100) |
| Stealing team guesses answer | Steal % (e.g., 50% = 50 points) |
| Answer not guessed (timeout) | 0 points |

### Example Scoring

**Question with 7 answers, 100 points each:**
- Team A controls, guesses 5 of 7 before time expires
- Team A scores: 5 × 100 = **500 points**
- Steal round: Team B has 10 seconds for remaining 2 answers
- Team B guesses 1 of 2 remaining
- Team B scores: 1 × 50 (50% steal) = **50 points**
- Remaining 1 answer: revealed, 0 points

---

## Timers

### Control Timer
- Duration: Configurable (default: 30 seconds)
- Applies when: A team controls a question
- When expires: Unrevealed answers go to steal round

### Steal Timer
- Duration: Configurable (default: 10 seconds)
- Applies when: Answers remain after control timer expires
- When expires: Remaining answers revealed, no points

### Timer Controls (Host)
- **Start**: Begin countdown
- **Pause**: Freeze countdown
- **Resume**: Continue from paused time
- **Reset**: Return to full duration

---

## Gameplay Modes

| Mode | Description | Default |
|------|-------------|---------|
| `host_reveal` | Host clicks to reveal correct guesses | ✓ Yes |
| `team_buzzer` | Teams buzz in, system auto-validates | Future feature |

### Host Reveal Mode (Default)
1. Team shouts guesses verbally
2. Host clicks the matching answer to reveal it
3. If guess is wrong, host clicks buzzer button (sound plays)
4. Host controls timer start/pause

### Team Buzzer Mode (Future)
1. Teams have buzzer buttons (physical or on-screen)
2. Team types or speaks their answer
3. System validates against answer list (fuzzy matching)
4. Correct answers auto-reveal
5. Requires: answer synonyms, fuzzy matching logic

---

## Winning Conditions

The host selects one of these winning conditions at game setup:

| Condition | Description |
|-----------|-------------|
| `most_points_after_questions` | Highest score after N questions |
| `first_to_points` | First team to reach X points wins |
| `host_ends` | Host manually ends, highest score wins |

The host can always end the game early regardless of winning condition.

---

## Host Controls

### During Game
- **Reveal Answer**: Mark an answer as correctly guessed
- **Buzzer**: Play wrong-answer sound (no penalty)
- **Start/Pause/Reset Timer**: Control the countdown
- **Next Question**: Move to next question in sequence
- **Previous Question**: Go back (for corrections)
- **Skip Question**: Skip without scoring
- **End Game**: Conclude game and determine winner

### Pre-Game Configuration
- Number of questions
- Answers per question
- Timer durations (control and steal)
- Steal points percentage
- Winning condition and threshold
- Gameplay mode (host reveal vs buzzer)
- Theme selection

---

## Configuration Reference

### Default Settings

```json
{
  "questions_per_game": 10,
  "answers_per_question": 7,
  "control_timer_seconds": 30,
  "steal_timer_seconds": 10,
  "steal_points_percentage": 50,
  "points_per_answer": 100,
  "gameplay_mode": "host_reveal",
  "winning_condition": "most_points_after_questions",
  "winning_condition_options": {
    "first_to_points": null,
    "questions_to_play": 10
  }
}
```

### Configurable Settings

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `questions_per_game` | int | 10 | Number of questions in session |
| `answers_per_question` | int | 7 | Answers per question |
| `control_timer_seconds` | int | 30 | Seconds for controlling team |
| `steal_timer_seconds` | int | 10 | Seconds for steal round |
| `steal_points_percentage` | int | 50 | Points % for steals (0-100) |
| `points_per_answer` | int | 100 | Points for each answer |
| `gameplay_mode` | enum | `host_reveal` | `host_reveal` or `team_buzzer` |
| `winning_condition` | enum | `most_points_after_questions` | How winner is determined |

---

## Game State Tracking

### Session-Level State

```json
{
  "current_question_id": 45,
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
```

### State Fields Explained

| Field | Description |
|-------|-------------|
| `current_question_id` | FK to session_questions |
| `current_question_index` | Position in question sequence |
| `team_order` | Array of team IDs in rotation order |
| `team_rotation_index` | Current position in rotation |
| `controlling_team_id` | Team with control |
| `phase` | `control`, `steal`, or `reveal` |
| `stealing_team_id` | Team attempting steal (null if in control phase) |
| `timer_mode` | `control` or `steal` |
| `timer_deadline` | ISO timestamp when timer expires |
| `revealed_answer_ids` | Array of answer IDs already revealed |
| `answers_guessed_by_controller` | Count for scoring |
| `answers_guessed_by_stealer` | Count for scoring |

### Phase States

| Phase | Description |
|-------|-------------|
| `control` | Controlling team is guessing |
| `steal` | Steal team is guessing remaining answers |
| `reveal` | All answers being revealed (after steal timeout) |

---

## Database Schema (America Says-Specific)

### Questions & Answers

Uses the shared `questions` and `answers` tables:

**questions table:**
- `question_text`: The survey prompt
- `game_type_id`: Links to America Says game type
- `category_id`: Optional category grouping

**answers table:**
- `answer_text`: The survey answer
- `points`: Points for this answer (flat, e.g., 100)
- `display_order`: Popularity rank (1 = most popular)

### Session State

Uses `game_state.state_data` JSON for America Says-specific tracking (see above).

### Answer Reveals

**answer_reveals table:**
| Column | Type | Notes |
|--------|------|-------|
| `session_question_id` | FK | The question being played |
| `answer_id` | FK | Which answer was revealed |
| `team_id` | FK | Who guessed it (controller or stealer) |
| `revealed_at` | timestamp | When revealed |
| `points_awarded` | int | Full or steal percentage |

---

## Theming Support

Inherited from propoff implementation:

| Theme | Trigger | Colors |
|-------|---------|--------|
| Classic | Default | Blue, Purple, Amber |
| Christmas | Category contains "christmas" or "holiday" | Red, Green, Gold |
| Halloween | Category contains "halloween" or "spooky" | Orange, Purple, Black |
| Sports | Category contains "sport" | Green, Blue, Yellow |

Themes include:
- Color schemes (primary, secondary, background, accent)
- Fonts (question, answer, timer)
- Visual effects (snowflakes, spiderwebs, etc.)
- Sound effects (reveal ding, wrong buzzer)

---

## Example Game Session

**Setup:**
- 3 Teams: Red, Blue, Green
- Team order: [Red, Blue, Green]
- 5 questions, 7 answers each, 100 points/answer
- Steal percentage: 50%

**Question 1: "Name something you hang on a Christmas tree"**

| Phase | Action | Points |
|-------|--------|--------|
| Control (Red) | Guesses: Ornaments, Lights, Star, Tinsel, Garland | Red +500 |
| Timer expires | 2 answers remain | |
| Steal (Blue) | Guesses: Angel | Blue +50 |
| Steal timeout | Candy Canes revealed | 0 |

**Totals after Q1:** Red: 500, Blue: 50, Green: 0

**Question 2: "Name a reason to call in sick"**
- Blue controls (rotation advanced)
- ...and so on

---

## Comparison with Oodles

| Aspect | America Says | Oodles |
|--------|--------------|--------|
| Answers per question | 7 (configurable) | 1 |
| Answer grouping | None | Cards with shared letter |
| Scoring | Flat (all equal) | Variable per question |
| After steal timeout | Rotation advances | Controller survives |
| Steal earns control? | No | Yes |
| Pass mechanic | No | No |

---

## Edge Cases

### Not Enough Questions
If the question bank doesn't have enough questions:
- Game ends early
- Winner determined by current scores

### Tie Score
At game end, if teams are tied:
- Sudden death: next question winner takes all
- Or: host declares co-winners

### Single Team
- Game still playable (practice mode)
- No steal scenarios
- Just timed guessing for points

### All Answers Guessed Instantly
If controlling team guesses all answers before any time elapses:
- Full points awarded
- No steal round
- Move to next question immediately

---

*Last Updated: 2024-12-26*
