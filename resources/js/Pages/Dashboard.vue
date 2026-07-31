<script setup lang="ts">
import StandardLayout from '@/Layouts/StandardLayout.vue';
import GameWordmark from '@/Components/GameWordmark.vue';
import GameBadge from '@/Components/GameBadge.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface ActiveGame {
    kind: 'trivia' | 'scorekeeper' | 'propoff';
    id: number;
    name: string | null;
    status: 'lobby' | 'playing' | 'paused' | 'scoring' | 'open' | 'locked' | 'in_progress';
    invite_code: string | null;
    code: string | null;
    competitor_count: number;
    team_based: boolean;
    game_type: { name: string; slug: string };
    updated_at: string;
}

interface Winner {
    name: string;
    color: string | null;
    score: number;
}

interface RecentGame {
    kind: 'trivia' | 'scorekeeper' | 'propoff';
    id: number;
    code: string | null;
    name: string;
    game_type: { name: string; slug: string };
    finished_at: string;
    player_count: number;
    players: string[];
    winners: Winner[];
}

defineProps<{
    activeGames: ActiveGame[];
    recentGames: RecentGame[];
}>();

// ---- join by code ----
const joinCode = ref('');
const join = () => {
    const code = joinCode.value.trim().toUpperCase();
    router.get(route('player.join'), code ? { code } : {});
};

const getStatusBadge = (status: string) => {
    switch (status) {
        case 'lobby':
            return { class: 'bg-warning/15 text-warning', text: 'In Lobby' };
        case 'playing':
            return { class: 'bg-success/15 text-success', text: 'Playing' };
        case 'paused':
            return { class: 'bg-warning/15 text-warning', text: 'Paused' };
        case 'scoring':
            return { class: 'bg-warning/15 text-warning', text: 'Scoring' };
        case 'open':
            return { class: 'bg-info/15 text-info', text: 'Open' };
        case 'locked':
            return { class: 'bg-danger/15 text-danger', text: 'Locked' };
        case 'in_progress':
            return { class: 'bg-success/15 text-success', text: 'In Progress' };
        case 'completed':
            return { class: 'bg-white/10 text-muted', text: 'Completed' };
        default:
            return { class: 'bg-white/10 text-muted', text: status };
    }
};

const formatDate = (dateString: string) =>
    new Date(dateString).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });

const getRecentGameLink = (game: RecentGame) => {
    if (game.kind === 'scorekeeper') return route('scorekeeper.games.show', game.id);
    if (game.kind === 'propoff') return route('propoff.play.leaderboard', game.code ?? '');
    return route('host.game', game.id);
};

const getActiveGameLink = (game: ActiveGame) => {
    if (game.kind === 'scorekeeper') return { href: route('scorekeeper.games.show', game.id), text: 'Score' };
    if (game.kind === 'propoff') return { href: route('propoff.play.hub', game.code ?? ''), text: 'Play' };
    return game.status === 'lobby'
        ? { href: route('host.lobby', game.id), text: 'Setup' }
        : { href: route('host.game', game.id), text: 'Resume' };
};
</script>

<template>
    <Head title="Dashboard" />

    <StandardLayout>
        <div class="mx-auto max-w-[1440px] space-y-8 px-4 py-8 sm:px-6 lg:px-8">
            <!-- ===== Hub — what do you want to do ===== -->
            <div>
                <p class="eyebrow mb-3">Start something</p>
                <div class="hub">
                    <!-- Party Games -->
                    <div class="tile tile-party">
                        <GameWordmark first="PARTY" second="GAMES" color="rgb(var(--color-info))" />
                        <p class="desc">Host a live game-show battle on the big screen.</p>
                        <p class="cap">Includes</p>
                        <ul class="gamelist">
                            <li>
                                <GameBadge slug="america-says" variant="bare" />
                                <span class="gname" style="color: rgb(var(--color-text))">America Says</span>
                            </li>
                            <li>
                                <GameBadge slug="family-feud" variant="bare" />
                                <span class="gname" style="color: rgb(var(--color-warning))">Family Feud</span>
                            </li>
                            <li>
                                <GameBadge slug="oodles" variant="bare" />
                                <span class="gname" style="color: rgb(var(--color-danger))">Oodles</span>
                            </li>
                        </ul>
                        <Link :href="route('games.create')" class="btn">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                <path d="M12 5v14M5 12h14" stroke-linecap="round" />
                            </svg>
                            Host a Game
                        </Link>
                    </div>

                    <!-- Score Keeper -->
                    <div class="tile tile-score">
                        <GameWordmark first="SCORE" second="KEEPER" color="rgb(var(--color-danger))" />
                        <p class="desc">Keep a running score for any card or board game — players update from their phones.</p>
                        <Link :href="route('scorekeeper.home')" class="btn">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                <path d="M9 11l3 3L22 4M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            Keep Score
                        </Link>
                    </div>

                    <!-- PropOff -->
                    <div class="tile tile-prop">
                        <GameWordmark first="PROP" second="OFF" color="rgb(var(--color-success))" />
                        <p class="desc">Go head-to-head on prop-bet predictions. Set the questions, invite your group, crown a winner.</p>
                        <Link :href="route('propoff.home')" class="btn">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                <path d="M8 5v14l11-7z" stroke-linejoin="round" />
                            </svg>
                            Play PropOff
                        </Link>
                    </div>

                    <!-- ShotMadness (coming soon) -->
                    <div class="tile tile-shot">
                        <GameWordmark first="SHOT" second="MADNESS" color="rgb(var(--color-warning))" />
                        <p class="desc">Bracket-style basketball showdown — call the shots, ride the upsets, and top the leaderboard.</p>
                        <span class="btn btn-static">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="9" />
                                <path d="M3 12h18M12 3v18M5.6 5.6l12.8 12.8M18.4 5.6L5.6 18.4" stroke-linecap="round" />
                            </svg>
                            Coming Soon
                        </span>
                    </div>
                </div>
            </div>

            <!-- ===== Join by code ===== -->
            <div class="join">
                <div>
                    <h3 class="text-lg font-extrabold text-body">Have a game code?</h3>
                    <p class="mt-0.5 text-sm text-muted">Drop into a game that's already in progress.</p>
                </div>
                <form class="ml-auto flex min-w-[280px] max-w-[420px] flex-1 gap-2.5" @submit.prevent="join">
                    <input
                        v-model="joinCode"
                        maxlength="6"
                        placeholder="GAME01"
                        aria-label="Game code"
                        class="code-input flex-1 rounded-xl border border-border bg-surface-inset px-4 py-3 text-center text-base font-bold uppercase tracking-[0.3em] text-body placeholder:tracking-[0.3em] placeholder:text-subtle focus:outline-none"
                    />
                    <button type="submit" class="btn-join">Join</button>
                </form>
            </div>

            <!-- ===== Active now ===== -->
            <div v-if="activeGames.length" class="overflow-hidden rounded-[18px] border border-border bg-surface">
                <div class="border-b border-border px-6 py-4">
                    <h3 class="text-base font-semibold text-body">Active now</h3>
                </div>
                <div class="divide-y divide-border">
                    <div
                        v-for="game in activeGames"
                        :key="`${game.kind}-${game.id}`"
                        class="flex items-center gap-4 px-6 py-4 transition hover:bg-white/5"
                    >
                        <GameBadge :slug="game.game_type.slug" />
                        <div class="min-w-0 flex-1">
                            <h4 class="font-semibold text-body">{{ game.name || game.game_type.name }}</h4>
                            <div class="flex items-center gap-2 text-sm text-muted">
                                <template v-if="game.invite_code">
                                    <span class="font-mono">{{ game.invite_code }}</span>
                                    <span>&middot;</span>
                                </template>
                                <span>{{ game.competitor_count }} {{ game.team_based ? 'teams' : 'players' }}</span>
                            </div>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="getStatusBadge(game.status).class">
                            {{ getStatusBadge(game.status).text }}
                        </span>
                        <Link :href="getActiveGameLink(game).href" class="btn-resume">
                            {{ getActiveGameLink(game).text }}
                        </Link>
                    </div>
                </div>
            </div>

            <!-- ===== Recent games ===== -->
            <div v-if="recentGames.length" class="overflow-hidden rounded-[18px] border border-border bg-surface">
                <div class="border-b border-border px-6 py-4">
                    <h3 class="text-base font-semibold text-body">Recent games</h3>
                </div>
                <div class="divide-y divide-border">
                    <Link
                        v-for="game in recentGames"
                        :key="`${game.kind}-${game.id}`"
                        :href="getRecentGameLink(game)"
                        class="flex items-center gap-4 px-6 py-4 transition hover:bg-white/5"
                    >
                        <GameBadge :slug="game.game_type.slug" />
                        <div class="min-w-0 flex-1">
                            <h4 class="font-semibold text-body">{{ game.name || game.game_type.name }}</h4>
                            <div class="truncate text-sm text-muted">
                                {{ formatDate(game.finished_at) }}
                                <template v-if="game.player_count > 0">
                                    &middot; {{ game.player_count }} {{ game.player_count === 1 ? 'player' : 'players' }}
                                </template>
                                <template v-if="game.players.length"> &middot; {{ game.players.join(', ') }}</template>
                            </div>
                        </div>
                        <div class="whitespace-nowrap text-right text-sm">
                            <div v-if="game.winners.length" class="flex items-center gap-2">
                                <span class="text-lg text-gold">&#127942;</span>
                                <template v-for="(winner, index) in game.winners" :key="winner.name">
                                    <span v-if="index > 0" class="text-muted">&amp;</span>
                                    <span class="font-semibold" :style="winner.color ? { color: winner.color } : {}">
                                        {{ winner.name }}
                                    </span>
                                </template>
                                <span class="text-muted">({{ game.winners[0].score }} pts)</span>
                            </div>
                            <div v-else class="text-subtle">No winner</div>
                        </div>
                    </Link>
                </div>
            </div>
        </div>
    </StandardLayout>
</template>

<style scoped>
.eyebrow {
    text-transform: uppercase;
    letter-spacing: 0.16em;
    font-size: 12px;
    font-weight: 700;
    color: rgb(var(--color-text-subtle));
}

/* ---- hub of neon game tiles ---- */
.hub {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
}
@media (max-width: 1080px) {
    .hub {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 560px) {
    .hub {
        grid-template-columns: 1fr;
    }
}

.tile {
    --ac: var(--color-success);
    position: relative;
    display: flex;
    flex-direction: column;
    border-radius: 20px;
    padding: 26px 24px 24px;
    background: rgb(var(--color-surface));
    border: 1px solid rgb(var(--ac));
    box-shadow:
        0 0 0 1px rgb(var(--ac)),
        0 0 30px rgb(var(--ac) / 0.22);
    transition: transform 0.18s ease, box-shadow 0.18s ease;
}
.tile:hover {
    transform: translateY(-3px);
    box-shadow:
        0 0 0 1px rgb(var(--ac)),
        0 0 44px rgb(var(--ac) / 0.34);
}
.tile-party {
    --ac: var(--color-info);
}
.tile-score {
    --ac: var(--color-danger);
}
.tile-prop {
    --ac: var(--color-success);
}
.tile-shot {
    --ac: var(--color-warning);
}

.tile .desc {
    margin: 14px 0 0;
    color: rgb(var(--color-text-muted));
    font-size: 14.5px;
    line-height: 1.5;
}

.tile .cap {
    margin: 16px 0 9px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: rgb(var(--color-text-subtle));
}
.gamelist {
    list-style: none;
    margin: 0 0 4px;
    padding: 0;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 9px 16px;
}
.gamelist li {
    display: flex;
    align-items: center;
    gap: 9px;
}
.gname {
    font-weight: 600;
    font-size: 14px;
    white-space: nowrap;
}

.btn {
    margin-top: auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-weight: 700;
    font-size: 14.5px;
    padding: 11px 18px;
    border-radius: 11px;
    background: rgb(var(--ac));
    color: #fff;
    border: 2px solid transparent;
    transition: 0.16s;
}
.btn:hover {
    background: rgb(var(--color-text));
    color: rgb(var(--ac));
    border-color: rgb(var(--ac));
}
.btn svg {
    width: 17px;
    height: 17px;
}
/* orange button that isn't clickable (ShotMadness "Coming Soon") */
.btn-static {
    cursor: default;
}
.btn-static:hover {
    background: rgb(var(--ac));
    color: #fff;
    border-color: transparent;
}

/* mt-auto needs a flex-grow above the pinned button so tiles keep equal button rows */
.tile > :is(.desc, .gamelist) {
    flex: 0 0 auto;
}

/* ---- join by code ---- */
.join {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    flex-wrap: wrap;
    border-radius: 18px;
    padding: 22px 24px;
    background: rgb(var(--color-surface));
    border: 1px solid rgb(var(--color-border));
}
.code-input:focus {
    border-color: transparent;
    box-shadow:
        0 0 0 1px rgb(var(--color-text)),
        0 0 0 3px rgb(var(--color-info)),
        0 0 15px rgb(var(--color-info) / 0.3);
}
.btn-join {
    padding: 12px 24px;
    border-radius: 12px;
    font-weight: 700;
    font-size: 14.5px;
    background: rgb(var(--color-info));
    color: #fff;
    border: 2px solid transparent;
    transition: 0.16s;
}
.btn-join:hover {
    background: rgb(var(--color-text));
    color: rgb(var(--color-info));
    border-color: rgb(var(--color-info));
}

/* ---- list action button ---- */
.btn-resume {
    padding: 8px 16px;
    border-radius: 10px;
    font-weight: 700;
    font-size: 13.5px;
    white-space: nowrap;
    background: rgb(var(--color-success));
    color: #fff;
    border: 2px solid transparent;
    transition: 0.16s;
}
.btn-resume:hover {
    background: rgb(var(--color-text));
    color: rgb(var(--color-success));
    border-color: rgb(var(--color-success));
}
</style>
