import { ref, watch } from 'vue';

/**
 * PropOff theme composable — accent color + background mode, persisted to
 * localStorage. DOM-free: PropOffLayout binds the returned refs as classes on
 * the .propoff-app wrapper, which is what scopes the dark mode to the module.
 */

const STORAGE_KEY = 'propoff-theme';
const STORAGE_KEY_BG = 'propoff-bg-mode';
const DEFAULT_THEME = 'green';
const DEFAULT_BG_MODE = 'slate';
const VALID_THEMES = ['green', 'blue', 'orange'];
const VALID_BG_MODES = ['slate', 'cream'];

const currentTheme = ref(DEFAULT_THEME);
const currentBgMode = ref(DEFAULT_BG_MODE);
let initialized = false;

function load(key, valid, fallback) {
    if (typeof localStorage === 'undefined') return fallback;
    const stored = localStorage.getItem(key);
    return stored && valid.includes(stored) ? stored : fallback;
}

export function useTheme() {
    if (!initialized) {
        currentTheme.value = load(STORAGE_KEY, VALID_THEMES, DEFAULT_THEME);
        currentBgMode.value = load(STORAGE_KEY_BG, VALID_BG_MODES, DEFAULT_BG_MODE);

        watch(currentTheme, (t) => {
            if (typeof localStorage !== 'undefined') localStorage.setItem(STORAGE_KEY, t);
        });
        watch(currentBgMode, (m) => {
            if (typeof localStorage !== 'undefined') localStorage.setItem(STORAGE_KEY_BG, m);
        });

        initialized = true;
    }

    function setTheme(theme) {
        if (VALID_THEMES.includes(theme)) currentTheme.value = theme;
    }

    function cycleTheme() {
        const i = VALID_THEMES.indexOf(currentTheme.value);
        currentTheme.value = VALID_THEMES[(i + 1) % VALID_THEMES.length];
    }

    function setBgMode(mode) {
        if (VALID_BG_MODES.includes(mode)) currentBgMode.value = mode;
    }

    return {
        theme: currentTheme,
        setTheme,
        themes: VALID_THEMES,
        cycleTheme,
        bgMode: currentBgMode,
        setBgMode,
        bgModes: VALID_BG_MODES,
    };
}

export default useTheme;
