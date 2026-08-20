# Game sound effects

Sounds are organized per game in subfolders and served from
`/sounds/<game>/<name>.m4a`.

## `america-says/`

Played by the TV display board (see
`resources/js/composables/useSoundEffects.ts` and
`resources/js/Pages/Display/AmericaSaysDisplay.vue`). To swap a sound, replace
the file here keeping the same name.

| File                  | Plays when…                                                       |
| --------------------- | ----------------------------------------------------------------- |
| `CorrectAnswer.m4a`   | The host reveals a correct answer on the board.                   |
| `IncorrectAnswer.m4a` | The host hits the Wrong Answer buzzer.                            |
| `EndTimer.m4a`        | The round timer runs out.                                         |
| `ShowQuestion.m4a`    | The host shows the question (plaque appears).                     |
| `AnswerBoard.m4a`     | The answer board + clock appear (Start Timer / Reveal Answers).   |
| `GameplayMusic.m4a`   | Background music bed; plays while the round timer runs (30s), pauses with it. |
| `ThemeMusic.m4a`      | Intro theme; plays over the map + team names once the sound check is done AND the game has started, then reveals gameplay. |

## `family-feud/`

Family Feud follows the same host-guided flow as America Says. Try the clips
in `/ff-soundboard.html`.

| File                        | Plays when…                                                   |
| --------------------------- | ------------------------------------------------------------- |
| `Intro.m4a`                 | Show open — the Family Feud intro (like the America Says intro). |
| `AnswerReveal.m4a`          | A correct answer flips open on the board.                     |
| `Strike.m4a`                | Wrong / no-match answer — the big red X.                      |
| `Buzzer.m4a`                | Time up / round-ending buzzer.                                |
| `FastMoneyTimer1.m4a`       | Fast Money — first player's timed pass.                       |
| `FastMoneyTimer2.m4a`       | Fast Money — second player's timed pass.                      |
| `FastMoneyAnswerReveal.m4a` | Fast Money — reveal the survey answer given.                  |
| `FastMoneyPointsReveal.m4a` | Fast Money — ding revealing points for that answer.           |
| `FastMoneyZeroPoints.m4a`   | Fast Money — buzz when an answer scored nothing.              |

## Notes

- **Format:** `.m4a` (AAC) plays across modern browsers, Chromecast, and smart-TV
  browsers. If you swap in a different format, update the paths in
  `useSoundEffects.ts` (and `AmericaSaysDisplay.vue` for the music).
- **Missing file = silent.** If a file isn't here, that effect just doesn't play
  (the timer effect falls back to the built-in synth buzzer).
- **Keep effects short** (~1–2s) so they don't run over the host's narration.
