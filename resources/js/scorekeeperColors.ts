/**
 * Locked 15-color player palette for Scorekeeper, in auto-assign order — each
 * next color sits far in hue from the previous, vivid colors first, so a new
 * player always looks clearly different from the ones already in the game.
 *
 * Player identity is shown as COLORED SCORE TEXT (the numbers take the color),
 * not a cell background. These hexes are intentionally off the semantic token
 * palette — the same sanctioned exception the app already makes for team
 * colors — but they're tuned to read on the dark surfaces.
 *
 * Competitors cycle through the list by their Manage position; a per-player
 * color picker can override this later.
 */
export interface PlayerColor {
    name: string;
    hex: string;
}

export const PLAYER_COLORS: PlayerColor[] = [
    { name: 'Green', hex: '#57d025' },
    { name: 'Magenta', hex: '#d946ef' },
    { name: 'Gold', hex: '#eab308' },
    { name: 'Blue', hex: '#3b82f6' },
    { name: 'Red', hex: '#ef4444' },
    { name: 'Cyan', hex: '#22d3ee' },
    { name: 'Violet', hex: '#a855f7' },
    { name: 'Gray', hex: '#a3a3a3' },
    { name: 'Lime', hex: '#bef264' },
    { name: 'Pink', hex: '#f472b6' },
    { name: 'Teal', hex: '#2dd4bf' },
    { name: 'Orange', hex: '#f47612' },
    { name: 'Indigo', hex: '#818cf8' },
    { name: 'Hot Pink', hex: '#ff2d95' },
    { name: 'White', hex: '#f5f5f5' },
];

/** The color hex for a competitor at the given Manage position (wraps at 15). */
export const playerColorAt = (index: number): string => {
    const n = PLAYER_COLORS.length;
    return PLAYER_COLORS[((index % n) + n) % n].hex;
};
