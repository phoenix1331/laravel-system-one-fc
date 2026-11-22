# Context

System One FC is a Laravel 13 playground: the player manages a Welsh club for 90 simulated minutes against a manager driven by a local **System One decision model** (d3, served by Ollama at `/v1/systemone`). Both managers decide at the same points; at full time the player sees the model's decision log with its probabilities. It teaches Laravel developers how to call a decision model from PHP: typed questions in, probabilities out, no generated text, no API keys, fully offline.

## Architecture

| Path | Holds |
|------|-------|
| `app/Engine/` | Pure PHP match engine: enums, value objects, per-minute loop. No Laravel or AI dependencies; randomness through an injected seeded `Random\Randomizer` |
| `app/Managers/` | `Manager` interface and implementations: human, rule-based, System One |
| `app/SystemOne/` | HTTP client for Ollama `/v1/systemone`, request payload and response mapping |
| `app/Console/Commands/` | `fc:play`, `fc:simulate` |
| `app/Livewire/` | Home, match and full-time screens |
| `app/Models/` | `Team`, `Game`, `Decision`, `GameEvent` |
| `config/engine.php` | Every engine constant (tactic multipliers, shot and goal rates, fitness drain) |
| `config/manager.php` | System One question wording, tunable without code changes |
| `docs/systemone-api.md` | Verified request and response shapes for `noul`, `choice`, `score` |
| `tests/` | Pest on in-memory SQLite; `tests/Scenarios` hit a real model (group `ollama`, opt-in) |

Match flow: Livewire poll : `GameRunner` advances the engine a minute : decision point pauses : both `Manager`s decide : engine applies both decisions and resumes.

## Runtime

| Piece | Where | Notes |
|-------|-------|-------|
| PHP 8.4, Composer, Node | Host (WSL or Linux) | `php artisan dev` runs server, queue worker, logs and Vite |
| MySQL 8 | Host, `127.0.0.1:3306` | `root` / `secret`, database `system_one_fc` |
| Ollama 0.40.9+ with `d3:2b` | Host or Windows | `OLLAMA_URL`, default `http://localhost:11434` |

No Docker, FrankenPHP or Herd: the brief targets a plain laptop setup anyone can reproduce.

## Integrations

| Integration | Used for | Config |
|-------------|----------|--------|
| Ollama System One API | Model manager decisions | `OLLAMA_URL`, `SYSTEM_ONE_MODEL` (`d3:2b`, or `d3:4b` on 16 GB), `SYSTEM_ONE_TIMEOUT` (seconds) |

## Decisions

| Decision | Why |
|----------|-----|
| Tests on in-memory SQLite | Fast, isolated, no MySQL needed for `make test` |
| Laravel HTTP client, no Ollama SDK | One endpoint; `Http::fake()` keeps tests offline |
| Engine free of Laravel | Easy to read, unit test and replay from a seed |
| Livewire 4 | Current major at install time; the brief's Livewire 3 patterns still apply |
| Model class `Game`, not `Match` | `match` is a PHP keyword |
| Pint (`declare_strict_types`), Larastan level 6, Husky hooks | Style, static analysis and commit format enforced locally and in CI |
| Laravel Boost MCP runs `php artisan boost:mcp` on the host | No container to exec into |

## Gotchas

- Tests must never need a live Ollama: use `Http::fake()`. Real-model checks only run with `RUN_OLLAMA_SCENARIOS=true`
- Ollama on Windows, app in WSL: `localhost` only reaches Windows with WSL mirrored networking (`networkingMode=mirrored` in `.wslconfig`). Otherwise set `OLLAMA_HOST=0.0.0.0` on Windows and point `OLLAMA_URL` at the Windows host IP
- WSL gets half the machine's RAM by default; `d3:4b` can be OOM-killed on 8 GB machines
