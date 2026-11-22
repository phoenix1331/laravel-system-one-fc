<?php

declare(strict_types=1);

namespace App\SystemOne;

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
