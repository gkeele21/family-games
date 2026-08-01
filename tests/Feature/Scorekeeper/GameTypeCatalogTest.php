<?php

namespace Tests\Feature\Scorekeeper;

use App\Models\GameType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GameTypeCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_trivia_new_game_lists_only_online_game_types(): void
    {
        $user = User::factory()->create();
        GameType::create(['name' => 'Family Feud', 'slug' => 'family-feud', 'kind' => 'online']);
        GameType::create(['name' => 'Rummy 500', 'slug' => 'rummy-500', 'kind' => 'scorekeeper']);

        // The trivia "new game" flow (now the host lobby's game picker) must
        // not surface scorekeeper games.
        $this->actingAs($user)
            ->followingRedirects()
            ->get(route('games.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Host/Lobby')
                ->has('gameTypes', 1));
    }
}
