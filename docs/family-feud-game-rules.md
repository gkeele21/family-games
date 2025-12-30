# Family Feud Game Rules & Mechanics

## Overview
Family Feud is a survey-based game show where two teams compete to guess the most popular answers to survey questions. Teams earn points based on how many people gave each answer in the original survey. The team with the most points after all rounds wins.

---

## Core Concepts

### Questions & Answers
- Each question is a survey prompt (e.g., "Name something you find in a wallet")
- Each question has multiple answers (typically 5-8)
- Answers are ranked by popularity with **actual survey points** (e.g., 35 people said "Cash")
- Higher-ranked answers = more points

### Teams
- Two teams compete (typically family members, but any group)
- Each team has multiple members who answer in rotation
- One member designated as captain for decisions

### Rounds
- Game consists of multiple rounds (configurable)
- Later rounds can have point multipliers (2x, 3x)
- Optional Fast Money final round

---

## Gameplay Flow

### Round Structure

```
┌─────────────────────────────────────────────────────────────────┐
│                      ROUND STARTS                                │
│              Question revealed to host only                      │
└─────────────────────────────────────────────────────────────────┘
                               │
                               ▼
              ┌────────────────┴────────────────┐
              ▼                                 ▼
     ┌──────────────────┐              ┌──────────────────┐
     │   FACE-OFF MODE  │              │  ROTATION MODE   │
     │   (if enabled)   │              │   (if enabled)   │
     └────────┬─────────┘              └────────┬─────────┘
              │                                 │
              ▼                                 ▼
     Two players buzz in              Team in rotation
     Winner's team chooses            automatically controls
              │                                 │
              └────────────────┬────────────────┘
                               │
                               ▼
                    ┌─────────────────────┐
                    │   PLAY OR PASS?     │
                    │   (team decision)   │
                    └──────────┬──────────┘
                               │
              ┌────────────────┴────────────────┐
              ▼                                 ▼
     ┌──────────────────┐              ┌──────────────────┐
     │      PLAY        │              │      PASS        │
     │  (control board) │              │ (other team      │
     │                  │              │  must play)      │
     └────────┬─────────┘              └────────┬─────────┘
              │                                 │
              └────────────────┬────────────────┘
                               │
                               ▼
                    ┌─────────────────────┐
                    │   PLAY THE BOARD    │
                    │   (see below)       │
                    └─────────────────────┘
```

### Face-Off (Optional)

```
┌─────────────────────────────────────────────────────────────────┐
│                        FACE-OFF                                  │
│         One player from each team at the podium                  │
│                 Question is revealed                             │
└─────────────────────────────────────────────────────────────────┘
                               │
                               ▼
                    ┌─────────────────────┐
                    │  First to buzz in   │
                    │  gives their answer │
                    └──────────┬──────────┘
                               │
              ┌────────────────┴────────────────┐
              ▼                                 ▼
     ┌──────────────────┐              ┌──────────────────┐
     │  GOT #1 ANSWER   │              │  DID NOT GET #1  │
     └────────┬─────────┘              └────────┬─────────┘
              │                                 │
              ▼                                 ▼
     Their team chooses           Other player can answer
     PLAY or PASS                          │
                               ┌───────────┴───────────┐
                               ▼                       ▼
                      ┌─────────────┐         ┌─────────────┐
                      │ Higher rank │         │ Lower/Wrong │
                      │ than first  │         │             │
                      └──────┬──────┘         └──────┬──────┘
                             │                       │
                             ▼                       ▼
                      Their team             First buzzer's
                      chooses                team chooses
                      PLAY or PASS           PLAY or PASS
```

### Playing the Board

```
┌─────────────────────────────────────────────────────────────────┐
│                    PLAYING THE BOARD                             │
│           Controlling team guesses remaining answers             │
│                  Members answer in rotation                      │
└─────────────────────────────────────────────────────────────────┘
                               │
                               ▼
                    ┌─────────────────────┐
                    │  Team member gives  │
                    │     an answer       │
                    └──────────┬──────────┘
                               │
              ┌────────────────┴────────────────┐
              ▼                                 ▼
     ┌──────────────────┐              ┌──────────────────┐
     │     CORRECT      │              │      WRONG       │
     │  Answer revealed │              │   STRIKE! (X)    │
     │  Points added    │              │                  │
     └────────┬─────────┘              └────────┬─────────┘
              │                                 │
              ▼                                 ▼
     ┌──────────────────┐              Check strike count
     │ All answers      │                      │
     │ revealed?        │         ┌────────────┴────────────┐
     └────────┬─────────┘         ▼                         ▼
              │          ┌──────────────┐          ┌──────────────┐
     ┌────────┴────────┐ │ Under limit  │          │ MAX STRIKES  │
     ▼                 ▼ │ (e.g., < 3)  │          │ (e.g., 3)    │
   YES                NO └──────┬───────┘          └──────┬───────┘
    │                  │        │                         │
    ▼                  │        ▼                         ▼
  Round               │   Next team                 ┌───────────┐
  Complete!           │   member's turn             │   STEAL   │
  Team gets           │        │                    │   ROUND   │
  all points          └────────┘                    └───────────┘
```

### Steal Round

```
┌─────────────────────────────────────────────────────────────────┐
│                       STEAL ROUND                                │
│            Other team has one chance to steal                    │
└─────────────────────────────────────────────────────────────────┘
                               │
              ┌────────────────┴────────────────┐
              ▼                                 ▼
     ┌──────────────────┐              ┌──────────────────┐
     │  ONE GUESS MODE  │              │   TIMED MODE     │
     │  (if configured) │              │  (if configured) │
     └────────┬─────────┘              └────────┬─────────┘
              │                                 │
              ▼                                 ▼
     Team huddles,                    Team has X seconds
     captain gives                    to guess remaining
     ONE answer                       answers
              │                                 │
              └────────────────┬────────────────┘
                               │
              ┌────────────────┴────────────────┐
              ▼                                 ▼
     ┌──────────────────┐              ┌──────────────────┐
     │ CORRECT (any     │              │ WRONG/TIMEOUT    │
     │ remaining answer)│              │                  │
     └────────┬─────────┘              └────────┬─────────┘
              │                                 │
              ▼                                 ▼
     Stealing team gets            Controlling team
     ALL accumulated points        keeps ALL points
```

---

## Scoring

### Point Values
- Each answer worth its **actual survey count** (e.g., 35 people said "Cash" = 35 points)
- Points accumulate during a round into a "point pool"
- Round winner gets the entire pool

### Round Multipliers (Configurable)

| Round | Multiplier | Example |
|-------|------------|---------|
| 1-2 | 1x | 150 pts = 150 pts |
| 3 | 2x | 150 pts = 300 pts |
| 4+ | 3x | 150 pts = 450 pts |

Multipliers are applied to the entire round's point pool.

### Scoring Scenarios

| Scenario | Who Gets Points |
|----------|----------------|
| Team clears the board (all answers) | Controlling team gets all |
| Team strikes out, steal succeeds | Stealing team gets all |
| Team strikes out, steal fails | Original controlling team gets all |

---

## Fast Money (Optional Final Round)

### Overview
- Two players from the winning team
- 5 rapid-fire questions
- Goal: Combined score of 200+ points

### Player 1

```
┌─────────────────────────────────────────────────────────────────┐
│                    PLAYER 1 - FAST MONEY                         │
│              Timer: 20 seconds (configurable)                    │
└─────────────────────────────────────────────────────────────────┘
                               │
                               ▼
     ┌─────────────────────────────────────────────────────┐
     │  5 questions asked rapidly                          │
     │  Player gives first answer that comes to mind       │
     │  Host records answers (not revealed yet)            │
     │  If stuck, can say "PASS" to skip and return        │
     └─────────────────────────────────────────────────────┘
                               │
                               ▼
     ┌─────────────────────────────────────────────────────┐
     │  After timer or all answered:                       │
     │  Reveal answers one by one with points              │
     │  #1 answer = full points, lower = fewer             │
     │  Calculate Player 1 total                           │
     └─────────────────────────────────────────────────────┘
```

### Player 2

```
┌─────────────────────────────────────────────────────────────────┐
│                    PLAYER 2 - FAST MONEY                         │
│       (Was sequestered, didn't hear Player 1's answers)         │
│              Timer: 25 seconds (configurable)                    │
└─────────────────────────────────────────────────────────────────┘
                               │
                               ▼
     ┌─────────────────────────────────────────────────────┐
     │  Same 5 questions asked                             │
     │  CANNOT duplicate Player 1's answers                │
     │  If duplicate: buzzer, must give different answer   │
     └─────────────────────────────────────────────────────┘
                               │
                               ▼
     ┌─────────────────────────────────────────────────────┐
     │  Reveal answers with points                         │
     │  Add to Player 1's total                            │
     │  If combined >= 200: WIN!                           │
     └─────────────────────────────────────────────────────┘
```

### Fast Money Scoring
- Top answer typically worth ~40+ points
- Lower answers worth less (proportional to survey results)
- Same answer as Player 1 = 0 points + must re-answer

---

## Configuration Reference

### Default Settings

```json
{
  "rounds_per_game": 4,
  "face_off_mode": "buzzer",
  "max_strikes": 3,
  "steal_mode": "one_guess",
  "steal_timer_seconds": 30,
  "round_multipliers": {
    "1": 1,
    "2": 1,
    "3": 2,
    "4": 3
  },
  "fast_money_enabled": true,
  "fast_money_player1_seconds": 20,
  "fast_money_player2_seconds": 25,
  "fast_money_target_score": 200,
  "play_or_pass_enabled": true,
  "answers_per_question": 8,
  "winning_condition": "most_points_after_rounds"
}
```

### Configurable Settings

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `rounds_per_game` | int | 4 | Number of regular rounds |
| `face_off_mode` | enum | `buzzer` | `buzzer` or `rotation` |
| `max_strikes` | int | 3 | Strikes before losing control |
| `steal_mode` | enum | `one_guess` | `one_guess` or `timed` |
| `steal_timer_seconds` | int | 30 | Seconds for timed steal |
| `round_multipliers` | object | see above | Point multiplier per round |
| `fast_money_enabled` | bool | true | Include Fast Money round |
| `fast_money_player1_seconds` | int | 20 | Timer for Player 1 |
| `fast_money_player2_seconds` | int | 25 | Timer for Player 2 |
| `fast_money_target_score` | int | 200 | Points needed to win |
| `play_or_pass_enabled` | bool | true | Allow team to pass control |
| `answers_per_question` | int | 8 | Max answers per question |
| `winning_condition` | enum | `most_points_after_rounds` | How winner determined |

### Face-Off Modes

| Mode | Description |
|------|-------------|
| `buzzer` | Two players buzz in, higher answer's team chooses |
| `rotation` | Control alternates between teams each round |

### Steal Modes

| Mode | Description |
|------|-------------|
| `one_guess` | Team huddles, gives ONE answer only |
| `timed` | Team has X seconds to guess any remaining answers |

---

## Game State Tracking

### Session-Level State

```json
{
  "current_round": 2,
  "current_question_id": 45,
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
    "player2_answer_id": null,
    "winner_team_id": 1
  },
  "member_rotation_index": 3
}
```

### Fast Money State

```json
{
  "phase": "fast_money_player2",
  "player1_id": 15,
  "player2_id": 18,
  "questions": [
    {
      "question_id": 50,
      "player1_answer": "Car",
      "player1_points": 42,
      "player2_answer": "Truck",
      "player2_points": 18
    }
  ],
  "player1_total": 156,
  "player2_total": 44,
  "combined_total": 200,
  "timer_deadline": "2024-12-26T20:15:30Z"
}
```

### Phase States

| Phase | Description |
|-------|-------------|
| `face_off` | Two players competing for control |
| `play_or_pass` | Winning team deciding |
| `playing` | Controlling team guessing answers |
| `steal` | Other team attempting to steal |
| `round_complete` | Points awarded, transitioning |
| `fast_money_player1` | First Fast Money player |
| `fast_money_reveal1` | Revealing Player 1 answers |
| `fast_money_player2` | Second Fast Money player |
| `fast_money_reveal2` | Revealing Player 2 answers |
| `game_complete` | Game over |

---

## Database Considerations

### Questions & Answers

**questions table:**
- `question_text`: The survey prompt
- `game_type_id`: Links to Family Feud game type
- `category_id`: Optional category grouping

**answers table:**
- `answer_text`: The survey answer
- `points`: Actual survey count (e.g., 35 = 35 people said this)
- `display_order`: Rank (1 = most popular)

### Fast Money Questions

Fast Money uses different questions than regular rounds:
- Shorter, rapid-fire format
- Still survey-based with point values
- Could use same `questions` table with a `is_fast_money` flag
- Or separate `fast_money_questions` table

**Option 1: Flag on questions table**
```
questions.is_fast_money = boolean (default false)
```

**Option 2: Metadata**
```
questions.metadata = {"fast_money": true}
```

---

## Host Controls

### During Regular Rounds
- **Reveal Answer**: Mark answer as correctly guessed
- **Strike**: Add a strike for wrong answer
- **Clear Strikes**: Reset strike count (for corrections)
- **Award Points**: Complete round and award points
- **Next Round**: Advance to next round
- **Face-Off Buzzer**: Mark which player buzzed first
- **Play/Pass Selection**: Record team's choice

### During Fast Money
- **Start Timer**: Begin countdown
- **Record Answer**: Log player's response
- **Duplicate Buzzer**: Mark when Player 2 duplicates
- **Reveal Points**: Show points for each answer
- **Skip Question**: Move to next question if stuck

### During Steal
- **Start Steal Timer**: Begin steal countdown (if timed mode)
- **Correct Steal**: Award all points to stealing team
- **Failed Steal**: Award all points to original team

---

## Example Game Session

**Setup:**
- 2 Teams: Smiths, Johnsons
- 4 rounds + Fast Money
- Face-off mode: buzzer
- Multipliers: 1x, 1x, 2x, 3x

**Round 1: "Name something in a woman's purse"**

| Phase | Action | Points |
|-------|--------|--------|
| Face-off | Smith player buzzes: "Wallet" (#2, 28pts) | |
| Face-off | Johnson player: "Phone" (#1, 42pts) | |
| | Johnsons win face-off, choose PLAY | |
| Playing | "Keys" (#3) revealed | +22 |
| Playing | "Wrong answer" | Strike 1 |
| Playing | "Lipstick" (#4) revealed | +18 |
| Playing | "Wrong answer" | Strike 2 |
| Playing | "Wrong answer" | Strike 3! |
| Steal | Smiths huddle: "Tissues" (#5) - CORRECT! | |
| Result | Smiths steal! | Smiths +110 |

**Score after Round 1:** Smiths: 110, Johnsons: 0

---

## Comparison with Other Games

| Aspect | Family Feud | America Says | Oodles |
|--------|-------------|--------------|--------|
| Teams | 2 only | 2+ | 2+ |
| Answers per Q | 5-8 | 7 | 1 |
| Points | Survey counts | Flat | Variable |
| Control | Face-off or rotation | Rotation | Rotation + earn |
| Strikes | Yes (3) | No | No |
| Steal | One guess or timed | Timed only | Timed only |
| Multipliers | Yes | No | No |
| Final round | Fast Money | None | None |

---

## Edge Cases

### Tie in Face-Off
If both players buzz simultaneously:
- Host decides who was first
- Or: re-do with new players

### All Answers Guessed Before Strikes
- Team gets all points
- No steal round
- Round complete

### Fast Money Tie at 199
- Close but no cigar!
- Could offer "bonus question" (configurable)

### Player 2 Duplicates All Answers
- Each duplicate = 0 points + must re-answer
- If time runs out with duplicates, they score 0 for those

### Only 2 Team Members
- Same members answer multiple times in rotation
- Still works, just less variety

---

*Last Updated: 2024-12-26*
