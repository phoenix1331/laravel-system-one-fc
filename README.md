# System One FC

> Can you beat a System One manager?

Pick a Welsh club and manage it for 90 minutes against a manager run by a **decision model** on your own machine. Both of you make the same tactical calls at the same moments, without seeing each other's choice. At full time you see the result and every decision the model made, with the probabilities behind it. It's a Laravel 13 playground that shows how to use a decision model from PHP: you send typed questions and get probabilities back. There's no generated text, no API key and no cloud.

<!-- GIF of a match goes here -->

## What is a System One model?

Most language models write text. A decision model like [d3](https://ollama.com/library/d3) answers typed questions about some **state** and returns **probabilities**:

| Question type | You send | You get back |
|---------------|----------|--------------|
| `noul` | a yes/no question | the probability of yes |
| `choice` | named options with descriptions | the chosen option and a probability for each |
| `score` | 2 to 10 ordered levels | the expected level and a probability for each |

The name comes from Daniel Kahneman's **System 1**: fast, intuitive judgement as opposed to slow, deliberate reasoning. The model never explains itself. It just says how likely each answer is, which is exactly what a football manager needs from a quick look at the touchline. Ollama serves it at `/v1/systemone`, and every request and response shape used here is in [`docs/systemone-api.md`](docs/systemone-api.md).

## What this demonstrates

- **Typed questions in, probabilities out**: one request per decision point asks for a mentality (choice), whether to press (yes/no), a substitution from 55' (choice) and how worried the manager is (score). (`app/SystemOne/SystemOneClient.php`, `config/manager.php`)
- **Interchangeable managers**: a human, a rule-based bot and the model all implement one `Manager` interface, so the model can be swapped for the bot in one line. (`app/Managers/`)
- **Graceful fallback**: if the model times out, errors or answers in a shape we don't understand, the rule-based bot decides and the decision log marks it. (`app/Managers/SystemOneManager.php`)
- **A deterministic match engine**: pure PHP with no Laravel inside. Every random number comes from a seeded `Random\Randomizer`, so a seed replays a match exactly, even across web requests. (`app/Engine/`, `app/Games/GameRunner.php`)
- **Prompt wording as configuration**: the question wording lives in config, and real-model scenario tests catch wording that makes the model behave badly. (`config/manager.php`, `tests/Scenarios/`)
- **Thinking in the background**: the model decides in a queued job while you decide, so the page never waits on a 15 second model call. (`app/Jobs/DecideForSystemOne.php`)

## How the game works

The match is simulated minute by minute. Each minute the team with the stronger effective midfield is more likely to have the ball, the team in possession may shoot, and a shot may go in. Every rating is scaled by fitness, which drains every minute and faster when pressing or going all out.

Play pauses for both managers at **kick-off, 15', 30', half time, 60', 75' and the minute after every goal**. You each choose a mentality, whether to press, and optionally a substitution (three per match). System One's choice stays hidden until you lock yours in.

| Mentality | Attack | Defence |
|-----------|--------|---------|
| Park the bus | x0.60 | x1.30 |
| Defensive | x0.80 | x1.15 |
| Balanced | x1.00 | x1.00 |
| Attacking | x1.15 | x0.90 |
| All out attack | x1.30 | x0.75 |

| Option | Effect |
|--------|--------|
| Press high | Midfield x1.10, fitness drains 0.3 a minute faster |
| Fresh legs | Fitness +15 (up to 100) |
| Extra striker | Attack +8, defence -5 for the rest of the match |
| Extra defender | Defence +8, attack -5 for the rest of the match |

Every constant is in `config/engine.php`. A calibration test plays 1,000 matches between evenly rated teams and checks they average 2.2 to 3.2 goals and 9 to 14 shots a side, so tuning can't drift into unrealistic scores.

## Tech stack

| Concern | Choice |
|---------|--------|
| Framework | Laravel 13, PHP 8.4 |
| UI | Livewire 4, Blade, Tailwind CSS 4, self-hosted fonts |
| Database | MySQL 8 (tests: in-memory SQLite) |
| Decision model | Ollama 0.40.9+ with `d3:2b` (or `d3:4b`), called with Laravel's HTTP client |
| Runtime | Native PHP with `php artisan dev`: no Docker needed |
| Quality | Pest (90% coverage minimum), Larastan level 6, Pint, Husky git hooks, osv-scanner |

## Getting started

### Prerequisites

- PHP 8.4 with `pdo_mysql`, `pdo_sqlite` and `pcov`, plus Composer
- Node.js and npm (run `npm install` once to activate the Husky git hooks)
- MySQL 8 on `127.0.0.1:3306`, user `root`, password `secret` (or change the `DB_*` values in `.env`)
- [Ollama](https://ollama.com) 0.40.9 or later, then `ollama pull d3:2b`
- `osv-scanner` for the pre-commit audit: `go install github.com/google/osv-scanner/cmd/osv-scanner@latest`
- For Claude Code's MCP servers: `gh auth login` (GitHub), and `MYSQL_HOST`/`MYSQL_PORT`/`MYSQL_USER`/`MYSQL_PASSWORD`/`MYSQL_DATABASE` exported to match your MySQL

### Run it

1. `mysql -uroot -psecret -e 'CREATE DATABASE system_one_fc'`
2. `make install` copies `.env.example` to `.env`, installs dependencies, migrates, seeds the clubs and builds assets
3. `make dev` starts the server, queue worker and Vite

Open http://localhost:8000 and pick a club. Run `make` to list every command, including:

| Command | Does |
|---------|------|
| `make play ARGS="Bala Penybont --seed=42"` | Plays bot vs bot in the terminal with commentary; add `--model` to let System One manage the away side and print its probabilities |
| `make simulate ARGS="20 --model=d3:4b"` | Benchmarks System One against the rule-based bot |
| `make scenarios` | Runs the real-model sanity checks against your Ollama |
| `make coverage` / `make analyse` / `make lint` | Tests with coverage, Larastan, Pint |

### Ollama on Windows, app in WSL

`localhost` only reaches Windows from WSL when mirrored networking is on (`networkingMode=mirrored` in `%UserProfile%\.wslconfig`). Otherwise set `OLLAMA_HOST=0.0.0.0` on Windows, restart Ollama, and point `OLLAMA_URL` at the Windows host IP (`ip route show default | awk '{print $3}'` from WSL).

## Running on 8 GB

- Use `d3:2b`. `d3:4b` wants 16 GB.
- Keep the context small: set `OLLAMA_CONTEXT_LENGTH=4096` for Ollama. A decision request is under 1,000 tokens.
- WSL gets half the machine's RAM by default, which is why `d3:4b` can be killed for running out of memory there. Running Ollama natively on Windows avoids that.
- On a CPU, `d3:2b` takes 11 to 30 seconds per decision, and longer once the machine starts swapping, so `SYSTEM_ONE_TIMEOUT` defaults to 60. Close memory-hungry apps while you play, and never run `make simulate` during a game: Ollama answers one request at a time, so both slow to a crawl.
- The substitution question is only asked from 55' (`SYSTEM_ONE_SUBSTITUTIONS_FROM`), which keeps most requests about a quarter smaller. Ollama unloads idle models after five minutes; the manager reloads it with a tiny warm-up question before deciding, so a cold start doesn't eat the decision's own timeout.

## Environment variables

| Key | Description | Default |
|-----|-------------|---------|
| `APP_URL` | Base URL | `http://localhost:8000` |
| `DB_HOST` / `DB_PORT` | MySQL host and port | `127.0.0.1` / `3306` |
| `DB_DATABASE` | Database name | `system_one_fc` |
| `DB_USERNAME` / `DB_PASSWORD` | Database credentials | `root` / `secret` |
| `OLLAMA_URL` | Where Ollama listens | `http://localhost:11434` |
| `SYSTEM_ONE_MODEL` | Default decision model | `d3:2b` |
| `SYSTEM_ONE_TIMEOUT` | Seconds to wait for a decision before the bot takes over | `60` |
| `SYSTEM_ONE_SUBSTITUTIONS_FROM` | Minute from which the model is asked about substitutions | `55` |
| `GAME_SECONDS_PER_MINUTE` | Real seconds per match minute | `2` |
| `APP_REPOSITORY_URL` | GitHub link in the header (hidden when empty) | empty |
| `RUN_OLLAMA_SCENARIOS` | Set to `true` to run `tests/Scenarios` | unset |

## Requests

The app's own pages are Livewire, so there's no JSON API. `request.http` holds ready-made calls to Ollama's System One endpoint (yes/no, choice, score and a full decision point) to try in VS Code REST Client or the JetBrains HTTP Client.

## Tuning the manager

The model's behaviour depends heavily on how the questions are worded, and all of that wording is in `config/manager.php`. The first version described each mentality only by what it does ("Push extra players forward to create more chances"). `d3:2b` anchored on `balanced` and even leaned defensive when a goal down at 80'. Two changes fixed it:

1. Saying **when** each option is used: "Throw everyone forward to chase a goal **when losing near the end**".
2. Saying what the state fields mean: "`score.us` is my_team's goals and `score.them` is the opponent's".

After that, 2-0 up at 88' leans defensive (+0.58) and 0-1 down at 80' leans attacking (-0.39). `tests/Scenarios` holds those checks as tests. Run `make scenarios` after any wording change.

## Benchmark

`make simulate ARGS="10 --seed=1"` plays System One against the rule-based bot on random fixtures. It then replays every fixture, with the same seed, with the bot in System One's seat. That second column is the baseline: same clubs, same luck, so any difference comes from the manager.

| `d3:2b`, 10 matches, CPU only | System One | Bot in its seat |
|-------------------------------|------------|-----------------|
| Won / drawn / lost | 4 / 3 / 3 | 4 / 3 / 3 |
| Points (3 a win) | 15 | 15 |
| Goals for / against | 22 / 12 | 23 / 13 |
| Fallbacks | 0 of 92 decisions | - |
| Average response | 16.0 s | - |

Over these ten fixtures, `d3:2b` with no examples and no training manages exactly as well as a set of hand-written rules. That isn't a bad result for a model that only ever reads a JSON snapshot and a few sentences of instructions. Ten matches is a small sample, though, so run more (`make simulate ARGS="50"`), try `d3:4b`, or tune the wording and see whether the left column pulls ahead.

An early version of the benchmark always gave the bot the stronger club, because the random pick kept the table's strongest-first order. The model looked hopeless: won 0, drew 1, lost 9. The bot in the same seat did no better. That bug is why the baseline column now exists.

## Architecture notes

**The engine knows nothing about Laravel.** `app/Engine` takes an `EngineConfig` value object and a `Random\Randomizer` and plays one step at a time. That keeps it fast to test: the calibration test plays 1,000 matches in under a second. It also means the same engine runs the terminal commands, the benchmark and the web game.

**A game survives between requests.** The browser polls once per match minute. `GameRunner::tick()` locks the game row, rebuilds the match from its JSON state and the serialised `Randomizer`, plays one minute and saves both. Because the random state is saved rather than re-seeded, a stored game replays exactly from its seed. Locking the row stops two overlapping polls playing the same minute twice.

**Both managers decide blind.** When play pauses, `DecideForSystemOne` is queued straight away, so the model thinks while you do. Its answer is stored but not shown until you lock in. Then both decisions are revealed side by side, and play resumes with both applied at once.

**Failure is part of the design.** A local model on a laptop will sometimes be slow or unavailable. The model never gets a second try: the rule-based bot steps in, the decision is flagged, and the decision log shows a dashed outline with "Model timed out, bot chose". The game always finishes.

**Rejected:** generating commentary with a language model (fixed templates are instant and keep the repo fully offline), and running the engine in the browser (the server decides every outcome, so it can't be fiddled).

## Credits

- **Club badges** are provided by [footylogos.com](https://www.footylogos.com/), the same source used by [Over the Terraces](https://github.com/phoenix1331/over-the-terraces). They are resized copies in `public/img/badges/`, named after each club, and a club without a badge file falls back to a coloured initial (`resources/views/components/club-badge.blade.php`).
- Club names, badges and crests are the property of their clubs and are shown only to identify them. This is an independent, non-commercial project, not affiliated with or endorsed by any club, league or governing body. If you own a mark used here and want it removed, open an issue and it will be taken down.
- **Fonts**: Space Grotesk, Inter and JetBrains Mono, self-hosted from [Fontsource](https://fontsource.org/).

## Licence

MIT for the code. See [LICENSE](LICENSE). The licence does not cover the club badges. Club names are real Cymru Premier clubs; every rating is invented for the game.
