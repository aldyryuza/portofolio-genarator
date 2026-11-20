# Agent extensions: sources and installation

These are **third-party** tools; this starter pack does NOT redistribute their code. Inspect repo licenses, release notes, permissions, hooks, and installation instructions prior to install. Install in Claude Code interactive terminal (slash commands), not PowerShell.

## Humanizer — rewrite prose to sound natural
Repo https://github.com/blader/humanizer
Claude Code: follow repo README marketplace install; fallback `npx skills add blader/humanizer --global --agent claude-code`.
Codex: `npx skills add blader/humanizer --global --agent codex`.
Use on bios, project narratives, CV text; don't change facts or invent achievements.

## UI UX Pro Max — design systems and UI critique
Repo https://github.com/nextlevelbuilder/ui-ux-pro-max-skill
Claude Code commands (run separately):
```
/plugin marketplace add nextlevelbuilder/ui-ux-pro-max-skill
/plugin install ui-ux-pro-max@ui-ux-pro-max-skill
```
For Codex, consult current repository CLI installation instructions. It may require Python 3.x for its search script.

## Last 30 Days — external trend research
Repo https://github.com/mvanhorn/last30days-skill
Claude Code (separate commands):
```
/plugin marketplace add mvanhorn/last30days-skill
/plugin install last30days
```
Codex: `npx skills add mvanhorn/last30days-skill -g`.
Use only when researching recent trends, not for normal implementation; can use online sources and may require optional configuration/API permissions.

## Ponytail — choose simplest sufficient implementation
Repo https://github.com/DietrichGebert/ponytail
Claude Code (separate commands):
```
/plugin marketplace add DietrichGebert/ponytail
/plugin install ponytail@ponytail
```
Codex CLI (verify plugin support in installed Codex version):
```
codex plugin marketplace add DietrichGebert/ponytail
codex plugin add ponytail@ponytail
```
Its lifecycle hooks may require Node.js. Inspect hooks before trusting. Simplicity must not bypass authorization, testing or upload validation.

## Suggested optional additions
Laravel Boost (if supported by installed Laravel), Laravel Pint, Pest, Playwright (browser/e2e), GitHub Actions. Start small: do not install multiple overlapping frontend agents by default.

## Distinguish tool type
VS Code extension ≠ Claude Code plugin ≠ portable SKILL.md ≠ Codex plugin. Not every extension is available in each mechanism. Installing this starter pack does not install third-party skills.
