<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DisplayController;
use App\Http\Controllers\GameSessionController;
use App\Http\Controllers\HostController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuestionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    $auth = request()->query('auth');

    return Inertia::render('Index', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'openAuth' => in_array($auth, ['login', 'register'], true) ? $auth : null,
    ]);
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/password', [\App\Http\Controllers\Auth\PasswordController::class, 'store'])->name('password.set');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Game Session Management
    Route::get('/games', [GameSessionController::class, 'index'])->name('games.index');
    Route::get('/games/create', [GameSessionController::class, 'create'])->name('games.create');
    Route::post('/games', [GameSessionController::class, 'store'])->name('games.store');
    Route::post('/games/{gameSession}/game-type', [GameSessionController::class, 'changeGameType'])->name('games.game-type.update');
    Route::post('/games/{gameSession}/teams', [GameSessionController::class, 'addTeam'])->name('games.teams.add');
    Route::post('/games/{gameSession}/teams/count', [GameSessionController::class, 'setTeamCount'])->name('games.teams.count');
    Route::post('/games/{gameSession}/teams/reorder', [GameSessionController::class, 'reorderTeams'])->name('games.teams.reorder');
    Route::patch('/games/{gameSession}/teams/{team}', [GameSessionController::class, 'updateTeam'])->name('games.teams.update');
    Route::delete('/games/{gameSession}/teams/{team}', [GameSessionController::class, 'removeTeam'])->name('games.teams.remove');
    Route::post('/games/{gameSession}/teams/{team}/members', [GameSessionController::class, 'addTeamMember'])->name('games.teams.members.add');
    Route::delete('/games/{gameSession}/teams/{team}/members/{teamMember}', [GameSessionController::class, 'removeTeamMember'])->name('games.teams.members.remove');
    Route::post('/games/{gameSession}/start', [GameSessionController::class, 'startGame'])->name('games.start');
    Route::post('/games/{gameSession}/back-to-lobby', [GameSessionController::class, 'backToLobby'])->name('games.back-to-lobby');
    Route::post('/games/{gameSession}/resume', [GameSessionController::class, 'resume'])->name('games.resume');
    Route::patch('/games/{gameSession}/settings', [GameSessionController::class, 'updateSettings'])->name('games.settings.update');
    Route::delete('/games/{gameSession}', [GameSessionController::class, 'destroy'])->name('games.destroy');

    // Question Library (admin)
    Route::get('/questions', [QuestionController::class, 'index'])->name('questions.index');
    Route::post('/questions', [QuestionController::class, 'store'])->name('questions.store');
    Route::patch('/questions/{question}', [QuestionController::class, 'update'])->name('questions.update');
    Route::delete('/questions/{question}', [QuestionController::class, 'destroy'])->name('questions.destroy');
    Route::post('/question-categories', [QuestionController::class, 'storeCategory'])->name('questions.categories.store');

    // Host Routes
    Route::get('/host/{gameSession}/lobby', [HostController::class, 'lobby'])->name('host.lobby');
    Route::get('/host/{gameSession}/game', [HostController::class, 'game'])->name('host.game');
    Route::get('/host/{gameSession}/state', [HostController::class, 'getState'])->name('host.state');
    Route::post('/host/{gameSession}/timer/start', [HostController::class, 'startTimer'])->name('host.timer.start');
    Route::post('/host/{gameSession}/timer/pause', [HostController::class, 'pauseTimer'])->name('host.timer.pause');
    Route::post('/host/{gameSession}/timer/reset', [HostController::class, 'resetTimer'])->name('host.timer.reset');
    Route::post('/host/{gameSession}/board/reset', [HostController::class, 'resetBoard'])->name('host.board.reset');
    Route::post('/host/{gameSession}/round/reset', [HostController::class, 'resetRound'])->name('host.round.reset');
    Route::post('/host/{gameSession}/reveal', [HostController::class, 'revealAnswer'])->name('host.reveal');
    Route::post('/host/{gameSession}/unreveal', [HostController::class, 'unrevealAnswer'])->name('host.unreveal');
    Route::post('/host/{gameSession}/control', [HostController::class, 'setControllingTeams'])->name('host.control');
    Route::post('/host/{gameSession}/control/team', [HostController::class, 'setControllingTeam'])->name('host.control.team');
    Route::post('/host/{gameSession}/bonus', [HostController::class, 'awardBonus'])->name('host.bonus');
    Route::post('/host/{gameSession}/question/select', [HostController::class, 'selectQuestion'])->name('host.question.select');
    Route::post('/host/{gameSession}/question/show', [HostController::class, 'showQuestion'])->name('host.question.show');
    Route::post('/host/{gameSession}/round/intro', [HostController::class, 'roundIntro'])->name('host.round.intro');
    Route::post('/host/{gameSession}/round/end', [HostController::class, 'endRound'])->name('host.round.end');
    Route::post('/host/{gameSession}/steal/start', [HostController::class, 'stealStart'])->name('host.steal.start');
    Route::post('/host/{gameSession}/steal/reveal', [HostController::class, 'setStealReveal'])->name('host.steal.reveal');
    Route::post('/host/{gameSession}/final/show', [HostController::class, 'finalShowQuestion'])->name('host.final.show');
    Route::post('/host/{gameSession}/final/start', [HostController::class, 'finalStart'])->name('host.final.start');
    Route::post('/host/{gameSession}/final/next', [HostController::class, 'finalNext'])->name('host.final.next');
    Route::post('/host/{gameSession}/final/skip', [HostController::class, 'finalSkip'])->name('host.final.skip');
    Route::post('/host/{gameSession}/final/timeout', [HostController::class, 'finalTimeout'])->name('host.final.timeout');
    Route::post('/host/{gameSession}/final/select', [HostController::class, 'finalSelect'])->name('host.final.select');
    Route::post('/host/{gameSession}/tiebreaker/show', [HostController::class, 'tiebreakerShow'])->name('host.tiebreaker.show');
    Route::post('/host/{gameSession}/tiebreaker/reveal-board', [HostController::class, 'tiebreakerRevealBoard'])->name('host.tiebreaker.reveal-board');
    Route::post('/host/{gameSession}/tiebreaker/to-declare', [HostController::class, 'tiebreakerToDeclare'])->name('host.tiebreaker.to-declare');
    Route::post('/host/{gameSession}/tiebreaker/swap', [HostController::class, 'tiebreakerSwap'])->name('host.tiebreaker.swap');
    Route::post('/host/{gameSession}/tiebreaker/resolve', [HostController::class, 'tiebreakerResolve'])->name('host.tiebreaker.resolve');
    Route::post('/host/{gameSession}/tiebreaker/to-final', [HostController::class, 'tiebreakerToFinal'])->name('host.tiebreaker.to-final');
    Route::post('/host/{gameSession}/question/next', [HostController::class, 'nextQuestion'])->name('host.question.next');
    Route::post('/host/{gameSession}/question/previous', [HostController::class, 'previousQuestion'])->name('host.question.previous');
    Route::post('/host/{gameSession}/question/correct', [HostController::class, 'markCorrect'])->name('host.question.correct');
    Route::post('/host/{gameSession}/question/wrong', [HostController::class, 'markWrong'])->name('host.question.wrong');
    Route::post('/host/{gameSession}/buzz/wrong', [HostController::class, 'buzzWrong'])->name('host.buzz.wrong');
    Route::post('/host/{gameSession}/card/next', [HostController::class, 'nextCard'])->name('host.card.next');
    Route::post('/host/{gameSession}/end', [HostController::class, 'endGame'])->name('host.end');
    Route::post('/host/{gameSession}/attendance', [HostController::class, 'setAttendance'])->name('host.attendance');
    Route::patch('/host/{gameSession}/teams/{team}/score', [HostController::class, 'updateTeamScore'])->name('host.teams.score.update');
});

// Player Routes (no auth required)
Route::get('/play', [PlayerController::class, 'join'])->name('player.join');
Route::post('/play', [PlayerController::class, 'joinByCode'])->name('player.join.code');
Route::get('/play/{gameSession}/identify', [PlayerController::class, 'showIdentify'])->name('player.identify');
Route::post('/play/{gameSession}/join', [PlayerController::class, 'joinSession'])->name('player.join.session');
Route::post('/play/{gameSession}/login', [PlayerController::class, 'joinWithLogin'])->name('player.join.login');
Route::get('/play/{gameSession}/select-team', [PlayerController::class, 'showSelectTeam'])->name('player.select-team');
Route::post('/play/{gameSession}/join-team', [PlayerController::class, 'joinTeam'])->name('player.join.team');
Route::get('/play/{gameSession}', [PlayerController::class, 'game'])->name('player.game');
Route::get('/play/{gameSession}/state', [PlayerController::class, 'getState'])->name('player.state');

// Display Routes (no auth required - for TV/projector display)
Route::get('/display', [DisplayController::class, 'entry'])->name('display.entry');
Route::get('/display/{inviteCode}', [DisplayController::class, 'show'])->name('display.show');
Route::get('/display/{inviteCode}/state', [DisplayController::class, 'getState'])->name('display.state');

require __DIR__.'/auth.php';
