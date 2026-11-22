<?php

declare(strict_types=1);

namespace App\SystemOne;

/**
 * The answers from one /v1/systemone request, read by question id.
 *
 * Each accessor checks the shape it expects and throws SystemOneException
 * if the model answered something else, so callers can fall back safely.
 */
final readonly class Answers
{
    /**
     * @param  array<string, mixed>  $answers  the raw "answers" object from the response
     * @param  int  $responseMs  how long the request took
     */
    public function __construct(
        public array $answers,
        public int $responseMs,
    ) {}

    /**
     * The winning option of a choice question.
     */
    public function choice(string $id): string
    {
        $answer = $this->answer($id, 'choice');
        $choice = $answer['choice'] ?? null;

        if (! is_string($choice)) {
            throw new SystemOneException("Answer \"{$id}\" has no choice.");
        }

        return $choice;
    }

    /**
     * Every option's probability for a choice or score question, keyed by option (or level index).
     *
     * @return array<string, float>
     */
    public function probabilities(string $id): array
    {
        $probabilities = $this->answer($id)['probabilities'] ?? null;

        if (! is_array($probabilities) || $probabilities === []) {
            throw new SystemOneException("Answer \"{$id}\" has no probabilities.");
        }

        $clean = [];

        foreach ($probabilities as $key => $value) {
            if (! is_numeric($value)) {
                throw new SystemOneException("Answer \"{$id}\" has a non-numeric probability.");
            }

            $clean[(string) $key] = (float) $value;
        }

        return $clean;
    }

    /**
     * The probability of yes for a noul question.
     */
    public function noul(string $id): float
    {
        $noul = $this->answer($id, 'noul')['noul'] ?? null;

        if (! is_numeric($noul)) {
            throw new SystemOneException("Answer \"{$id}\" has no noul probability.");
        }

        return (float) $noul;
    }

    /**
     * The expected level of a score question, zero-indexed (0 is the first level sent).
     */
    public function score(string $id): float
    {
        $score = $this->answer($id, 'score')['score'] ?? null;

        if (! is_numeric($score)) {
            throw new SystemOneException("Answer \"{$id}\" has no score.");
        }

        return (float) $score;
    }

    /**
     * @return array<mixed>
     */
    private function answer(string $id, ?string $type = null): array
    {
        $answer = $this->answers[$id] ?? null;

        if (! is_array($answer)) {
            throw new SystemOneException("No answer for question \"{$id}\".");
        }

        if ($type !== null && ($answer['type'] ?? null) !== $type) {
            throw new SystemOneException("Answer \"{$id}\" is not a {$type} answer.");
        }

        return $answer;
    }
}
