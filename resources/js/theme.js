// Light/dark theme. The initial value is set by the inline script in <head>
// (resources/views/components/theme-script.blade.php); this module wires up
// the toggle buttons and keeps everything in sync.

const STORAGE_KEY = 'theme';
const systemDark = window.matchMedia('(prefers-color-scheme: dark)');

function savedTheme() {
    try {
        const value = localStorage.getItem(STORAGE_KEY);

        return value === 'dark' || value === 'light' ? value : null;
    } catch {
        return null;
    }
}

function preferredTheme() {
    return savedTheme() ?? (systemDark.matches ? 'dark' : 'light');
}

function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.hidden = false;
        button.setAttribute('aria-pressed', String(theme === 'dark'));
    });
}

// Event delegation keeps working after Livewire swaps the page with wire:navigate.
document.addEventListener('click', (event) => {
    if (!event.target.closest('[data-theme-toggle]')) {
        return;
    }

    const next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';

    try {
        localStorage.setItem(STORAGE_KEY, next);
    } catch {
        // Storage can be blocked; the choice then lasts for this page only.
    }

    applyTheme(next);
});

// Follow the operating system until the visitor makes an explicit choice.
systemDark.addEventListener('change', () => {
    if (savedTheme() === null) {
        applyTheme(preferredTheme());
    }
});

document.addEventListener('livewire:navigated', () => applyTheme(preferredTheme()));

applyTheme(preferredTheme());
