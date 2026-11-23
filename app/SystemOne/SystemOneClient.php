<?php

declare(strict_types=1);

namespace App\SystemOne;

use App\Enums\ModelStatus;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;

/**
 * Talks to Ollama's System One endpoint: a state and typed questions in, probabilities out.
 */
final readonly class SystemOneClient
{
    public function __construct(
        private Http $http,
        private string $baseUrl,
        private string $model,
        private int $timeout,
    ) {}

    public function model(): string
    {
        return $this->model;
    }

    /**
     * The same client pointed at a different model, e.g. d3:4b for one game.
     */
    public function withModel(string $model): self
    {
        return new self($this->http, $this->baseUrl, $model, $this->timeout);
    }

    /**
     * Load the model into memory if Ollama has unloaded it, so the next real
     * question is not eaten by a cold start. Ollama unloads idle models after
     * five minutes, and loading d3:2b takes longer than a normal decision.
     *
     * Decision models cannot be preloaded with an empty /api/generate call,
     * so this sends the smallest possible System One question instead.
     * Any failure is ignored: the real request will report it.
     */
    public function warmUp(int $timeout = 180): void
    {
        // Already loaded, or not an Ollama we can reach or read, so leave it alone.
        if ($this->status() !== ModelStatus::Asleep) {
            return;
        }

        try {
            $this->http->baseUrl($this->baseUrl)->acceptJson()->timeout($timeout)->post('/v1/systemone', $this->payload('warm up', [
                'ready' => ['type' => 'noul', 'instructions' => 'Is this a test?'],
            ]));
        } catch (ConnectionException) {
            return;
        }
    }

    /**
     * Whether this model is loaded, by asking Ollama's /api/ps what is in memory.
     */
    public function status(int $timeout = 3): ModelStatus
    {
        try {
            $loaded = $this->http->baseUrl($this->baseUrl)->acceptJson()->timeout($timeout)->get('/api/ps')->json('models');
        } catch (ConnectionException) {
            return ModelStatus::Offline;
        }

        if (! is_array($loaded)) {
            return ModelStatus::Offline;
        }

        foreach ($loaded as $model) {
            if (is_array($model) && ($model['name'] ?? null) === $this->model) {
                return ModelStatus::Ready;
            }
        }

        return ModelStatus::Asleep;
    }

    /**
     * Every question is answered in one request.
     *
     * @param  string|array<string, mixed>  $state  plain text, or structured data sent as JSON
     * @param  array<string, array<string, mixed>>  $questions  keyed by question id, shaped as in config/manager.php
     *
     * @throws SystemOneException
     */
    public function ask(string|array $state, array $questions): Answers
    {
        $started = hrtime(true);

        try {
            $response = $this->http
                ->baseUrl($this->baseUrl)
                ->acceptJson()
                ->timeout($this->timeout)
                ->post('/v1/systemone', $this->payload($state, $questions));
        } catch (ConnectionException $e) {
            throw new SystemOneException("Could not reach Ollama at {$this->baseUrl}: {$e->getMessage()}", previous: $e);
        }

        $responseMs = (int) round((hrtime(true) - $started) / 1_000_000);

        if ($response->failed()) {
            $error = $response->json('error') ?? $response->body();

            throw new SystemOneException("Ollama returned HTTP {$response->status()}: ".(is_string($error) ? $error : 'unknown error'));
        }

        $answers = $response->json('answers');

        if (! is_array($answers)) {
            throw new SystemOneException('Ollama response has no answers object.');
        }

        return new Answers($answers, $responseMs);
    }

    /**
     * The request body, exactly as sent to /v1/systemone.
     *
     * @param  string|array<string, mixed>  $state
     * @param  array<string, array<string, mixed>>  $questions
     * @return array{model: string, state: string|array<string, mixed>, questions: array<string, array<string, mixed>>}
     */
    public function payload(string|array $state, array $questions): array
    {
        return [
            'model' => $this->model,
            'state' => $state,
            'questions' => $questions,
        ];
    }
}
