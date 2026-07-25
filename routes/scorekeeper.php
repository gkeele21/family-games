<?php

use App\Http\Controllers\Scorekeeper\CompetitorController;
use App\Http\Controllers\Scorekeeper\GameTemplateController;
use App\Http\Controllers\Scorekeeper\HouseholdController;
use App\Http\Controllers\Scorekeeper\HouseholdInviteController;
use App\Http\Controllers\Scorekeeper\PlayerController;
use App\Http\Controllers\Scorekeeper\ScoredGameController;
use Illuminate\Support\Facades\Route;

Route::prefix('scorekeeper')->name('scorekeeper.')->group(function () {
    // Public invite preview (accepting requires auth below).
    Route::get('invites/{token}', [HouseholdInviteController::class, 'show'])->name('invites.show');

    Route::middleware('auth')->group(function () {
        Route::get('/', [HouseholdController::class, 'home'])->name('home');
        Route::get('households', [HouseholdController::class, 'index'])->name('households.index');
        Route::post('households', [HouseholdController::class, 'store'])->name('households.store');
        Route::get('households/{household}', [HouseholdController::class, 'show'])->name('households.show');
        Route::get('households/{household}/players', [HouseholdController::class, 'players'])
            ->name('households.players.index');
        Route::get('households/{household}/sharing', [HouseholdController::class, 'sharing'])
            ->name('households.sharing');
        Route::patch('households/{household}', [HouseholdController::class, 'update'])->name('households.update');
        Route::delete('households/{household}', [HouseholdController::class, 'destroy'])->name('households.destroy');
        Route::delete('households/{household}/membership', [HouseholdController::class, 'leave'])->name('households.leave');

        Route::post('households/{household}/invites', [HouseholdInviteController::class, 'store'])
            ->name('households.invites.store');
        Route::post('invites/{token}/accept', [HouseholdInviteController::class, 'accept'])
            ->name('invites.accept');

        // Game templates
        Route::get('households/{household}/templates', [GameTemplateController::class, 'index'])
            ->name('households.templates.index');
        Route::post('households/{household}/templates', [GameTemplateController::class, 'store'])
            ->name('households.templates.store');
        Route::patch('templates/{template}', [GameTemplateController::class, 'update'])
            ->name('templates.update');
        Route::delete('templates/{template}', [GameTemplateController::class, 'destroy'])
            ->name('templates.destroy');
        Route::post('templates/{template}/copy', [GameTemplateController::class, 'copy'])
            ->name('templates.copy');

        // Player roster
        Route::post('households/{household}/players', [PlayerController::class, 'store'])
            ->name('households.players.store');
        Route::patch('players/{player}', [PlayerController::class, 'update'])
            ->name('players.update');
        Route::delete('players/{player}', [PlayerController::class, 'destroy'])
            ->name('players.destroy');

        // Scored games (the round-by-round scoring engine)
        Route::get('households/{household}/games', [ScoredGameController::class, 'index'])
            ->name('households.games.index');
        Route::get('households/{household}/games/create', [ScoredGameController::class, 'create'])
            ->name('households.games.create');
        Route::post('households/{household}/games', [ScoredGameController::class, 'store'])
            ->name('households.games.store');
        Route::get('games/{scoredGame}', [ScoredGameController::class, 'show'])
            ->name('games.show');
        Route::post('games/{scoredGame}/rounds', [ScoredGameController::class, 'addRound'])
            ->name('games.rounds.add');
        Route::patch('games/{scoredGame}/rounds/{round}', [ScoredGameController::class, 'updateScores'])
            ->name('games.rounds.update');
        Route::patch('games/{scoredGame}/scores', [ScoredGameController::class, 'updateAllScores'])
            ->name('games.scores.update');
        Route::post('games/{scoredGame}/complete', [ScoredGameController::class, 'complete'])
            ->name('games.complete');
        Route::patch('games/{scoredGame}/play-date', [ScoredGameController::class, 'updatePlayDate'])
            ->name('games.playdate.update');
        Route::delete('games/{scoredGame}', [ScoredGameController::class, 'destroy'])
            ->name('games.destroy');

        // Mid-game roster editing (players / teams)
        Route::post('games/{scoredGame}/competitors', [CompetitorController::class, 'store'])
            ->name('games.competitors.store');
        Route::post('games/{scoredGame}/competitors/reorder', [CompetitorController::class, 'reorder'])
            ->name('games.competitors.reorder');
        Route::delete('games/{scoredGame}/competitors/{competitor}', [CompetitorController::class, 'destroy'])
            ->name('games.competitors.destroy');
        Route::post('games/{scoredGame}/competitors/{competitor}/members', [CompetitorController::class, 'addMember'])
            ->name('games.competitors.members.add');
        Route::delete('games/{scoredGame}/competitors/{competitor}/members/{player}', [CompetitorController::class, 'removeMember'])
            ->name('games.competitors.members.remove');
    });
});
