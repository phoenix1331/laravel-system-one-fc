# System One API

Verified against Ollama 0.40.13 with `d3:2b`. Requests below can be run from `request.http`.

## Endpoint

`POST {OLLAMA_URL}/v1/systemone`, JSON body, no auth.

| Field | Type | Notes |
|-------|------|-------|
| `model` | string | e.g. `d3:2b`. Unknown model: `404 {"error":"model 'nope' not found"}` |
| `state` | string or object | What the model reasons about. Structured JSON works; the game sends the match state from the deciding team's perspective |
| `questions` | object | Keyed by your own question id. Every question is answered in one forward pass |

Every answer carries its `type` back. `usage.output_tokens` is always `0`: no text is generated.

## Yes/no: `noul`

`criteria` is optional; without it the model answers the bare `instructions`.

```json
{
  "model": "d3:2b",
  "state": "Hello World",
  "questions": {
    "says_hello": {
      "type": "noul",
      "instructions": "Does the state text contain a greeting?",
      "criteria": {
        "true": "The state text contains a greeting.",
        "false": "The state text does not contain a greeting."
      }
    }
  }
}
```

```json
{
  "model": "d3:2b",
  "answers": {
    "says_hello": { "type": "noul", "noul": 0.9849897505640454 }
  },
  "usage": { "input_tokens": 104, "output_tokens": 0 }
}
```

`noul` is the probability of yes.

## Choice: `choice`

`criteria` is required: an object of option key to description. Describe what each option does, not just its name; the descriptions are what the model weighs.

```json
{
  "model": "d3:2b",
  "state": "My laptop will not turn on",
  "questions": {
    "team": {
      "type": "choice",
      "instructions": "Which team should handle this?",
      "criteria": {
        "billing": "Payments and refunds",
        "technical": "Software and hardware problems",
        "sales": "New purchases"
      }
    }
  }
}
```

```json
{
  "model": "d3:2b",
  "answers": {
    "team": {
      "type": "choice",
      "choice": "technical",
      "probabilities": {
        "billing": 0.00011169342479141011,
        "technical": 0.999674080976457,
        "sales": 0.0002142255987516466
      },
      "confidence": 0.9971308082354641
    }
  },
  "usage": { "input_tokens": 108, "output_tokens": 0 }
}
```

| Field | Meaning |
|-------|---------|
| `choice` | Key of the most likely option |
| `probabilities` | Every option key with its probability, summing to 1 |
| `confidence` | The model's own confidence in the pick, 0 to 1. Not the same number as the winning probability |

## Score: `score`

`criteria` is required: an **array** of 2 to 10 level descriptions, lowest first. Levels come back zero-indexed.

```json
{
  "model": "d3:2b",
  "state": "The server room is on fire",
  "questions": {
    "urgency": {
      "type": "score",
      "instructions": "How urgent is this?",
      "criteria": ["Routine", "Soon", "Immediate"]
    }
  }
}
```

```json
{
  "model": "d3:2b",
  "answers": {
    "urgency": {
      "type": "score",
      "score": 1.9800315612390276,
      "legend": { "0": "Routine", "1": "Soon", "2": "Immediate" },
      "probabilities": {
        "0": 0.0027400004144923973,
        "1": 0.014488437931987593,
        "2": 0.98277156165352
      },
      "confidence": 0.9138963570653966
    }
  },
  "usage": { "input_tokens": 95, "output_tokens": 0 }
}
```

| Field | Meaning |
|-------|---------|
| `score` | Expected level, a float from 0 to levels - 1 (probability-weighted mean) |
| `legend` | Level index to the description you sent |
| `probabilities` | Level index to probability |
| `confidence` | 0 to 1 |

The game's `concern` question sends five levels and maps `round(score) + 1` to 1 to 5.

## Several questions, JSON state

All questions go in one request and come back keyed by id. This is the shape the game sends at a decision point:

```json
{
  "model": "d3:2b",
  "state": {
    "minute": 88,
    "my_team": "Bala Town",
    "opponent": "Penybont",
    "score": { "us": 2, "them": 0 },
    "possession_last_15": 38,
    "shots_last_15": { "us": 1, "them": 5 },
    "fitness": { "us": 61, "them": 74 },
    "current_mentality": "balanced",
    "pressing": false,
    "subs_left": 2
  },
  "questions": {
    "mentality": { "type": "choice", "instructions": "...", "criteria": { "park_the_bus": "...", "balanced": "..." } },
    "press_high": { "type": "noul", "instructions": "...", "criteria": { "true": "...", "false": "..." } },
    "concern": { "type": "score", "instructions": "...", "criteria": ["Calm", "Slightly uneasy", "Uneasy", "Worried", "Very worried"] }
  }
}
```

The answers object holds `mentality` (choice shape), `press_high` (noul shape) and `concern` (score shape). With wording close to `config/manager.php` this request used about 700 input tokens.

## Limits and timing

| Item | Observed |
|------|----------|
| Question types | `choice`, `score`, `noul` only |
| Score levels | 2 to 10, enforced (`score criteria must be an array of 2–10 levels`) |
| Choice options | 27 accepted; Ollama documents 26 as the maximum, so stay at or below it |
| Latency, `d3:2b` on CPU in WSL | First call (model load) about 17 s; warm three-question call 11 to 12.5 s |

Latency is why `SYSTEM_ONE_TIMEOUT` defaults to 30 seconds: at 10 seconds a CPU-only machine falls back to the rule-based bot on every decision. Machines with a GPU answer far faster.

## Errors

| Status | Body | Cause |
|--------|------|-------|
| 400 | `{"error":"questions.team: \"criteria\" must be a non-empty object"}` | `choice` without criteria |
| 400 | `{"error":"question \"urgency\": score criteria must be an array of 2–10 levels"}` | `score` criteria missing, an object, or the wrong length |
| 400 | `{"error":"questions.q: \"type\" must be one of: choice, score, noul"}` | Unknown question type |
| 404 | `{"error":"model 'nope' not found"}` | Model not pulled |
