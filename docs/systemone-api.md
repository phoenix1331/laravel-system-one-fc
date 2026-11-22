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

## Choice: TODO

Not yet verified.

## Score: TODO

Not yet verified.

## Errors

| Status | Body | Cause |
|--------|------|-------|
| 404 | `{"error":"model 'nope' not found"}` | Model not pulled |
