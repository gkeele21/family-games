# Game sound effects

These files are played by the TV display board
(see `resources/js/composables/useSoundEffects.ts` and
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

Notes:

- **Format:** `.m4a` (AAC) plays across modern browsers, Chromecast, and smart-TV
  browsers. If you swap in a different format, update the paths in
  `useSoundEffects.ts` (and `AmericaSaysDisplay.vue` for the music).
- **Missing file = silent.** If a file isn't here, that effect just doesn't play
  (the timer effect falls back to the built-in synth buzzer).
- **Keep effects short** (~1–2s) so they don't run over the host's narration.
- These files are served from `/sounds/<name>.m4a`.
