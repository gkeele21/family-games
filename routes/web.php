<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DisplayController;
use App\Http\Controllers\GameSessionController;
use App\Http\Controllers\HostController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Game Session Management
    Route::get('/games', [GameSessionController::class, 'index'])->name('games.index');
    Route::get('/games/create', [GameSessionController::class, 'create'])->name('games.create');
    Route::post('/games', [GameSessionController::class, 'store'])->name('games.store');
    Route::post('/games/{gameSession}/teams', [GameSessionController::class, 'addTeam'])->name('games.teams.add');
    Route::post('/games/{gameSession}/teams/reorder', [GameSessionController::class, 'reorderTeams'])->name('games.teams.reorder');
    Route::delete('/games/{gameSession}/teams/{team}', [GameSessionController::class, 'removeTeam'])->name('games.teams.remove');
    Route::post('/games/{gameSession}/teams/{team}/members', [GameSessionController::class, 'addTeamMember'])->name('games.teams.members.add');
    Route::delete('/games/{gameSession}/teams/{team}/members/{teamMember}', [GameSessionController::class, 'removeTeamMember'])->name('games.teams.members.remove');
    Route::post('/games/{gameSession}/start', [GameSessionController::class, 'startGame'])->name('games.start');
    Route::patch('/games/{gameSession}/settings', [GameSessionController::class, 'updateSettings'])->name('games.settings.update');
    Route::delete('/games/{gameSession}', [GameSessionController::class, 'destroy'])->name('games.destroy');

    // Host Routes
    Route::get('/host/{gameSession}/lobby', [HostController::class, 'lobby'])->name('host.lobby');
    Route::get('/host/{gameSession}/game', [HostController::class, 'game'])->name('host.game');
    Route::get('/host/{gameSession}/state', [HostController::class, 'getState'])->name('host.state');
    Route::post('/host/{gameSession}/timer/start', [HostController::class, 'startTimer'])->name('host.timer.start');
    Route::post('/host/{gameSession}/timer/pause', [HostController::class, 'pauseTimer'])->name('host.timer.pause');
    Route::post('/host/{gameSession}/timer/reset', [HostController::class, 'resetTimer'])->name('host.timer.reset');
    Route::post('/host/{gameSession}/reveal', [HostController::class, 'revealAnswer'])->name('host.reveal');
    Route::post('/host/{gameSession}/control', [HostController::class, 'setControllingTeams'])->name('host.control');
    Route::post('/host/{gameSession}/question/select', [HostController::class, 'selectQuestion'])->name('host.question.select');
    Route::post('/host/{gameSession}/question/next', [HostController::class, 'nextQuestion'])->name('host.question.next');
    Route::post('/host/{gameSession}/question/correct', [HostController::class, 'markCorrect'])->name('host.question.correct');
    Route::post('/host/{gameSession}/question/wrong', [HostController::class, 'markWrong'])->name('host.question.wrong');
    Route::post('/host/{gameSession}/card/next', [HostController::class, 'nextCard'])->name('host.card.next');
    Route::post('/host/{gameSession}/end', [HostController::class, 'endGame'])->name('host.end');
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
Route::get('/display/{inviteCode}', [DisplayController::class, 'show'])->name('display.show');
Route::get('/display/{inviteCode}/state', [DisplayController::class, 'getState'])->name('display.state');

require __DIR__.'/auth.php';
