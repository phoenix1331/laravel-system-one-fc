<?php

declare(strict_types=1);

use App\SystemOne\Answers;
use App\SystemOne\SystemOneException;

it('rejects answers in the wrong shape', function (array $answers, Closure $read, string $message): void {
    expect(fn () => $read(new Answers($answers, 10)))->toThrow(SystemOneException::class, $message);
})->with([
    'missing question' => [[], fn (Answers $a) => $a->choice('mentality'), 'No answer for question "mentality"'],
    'wrong type' => [['q' => ['type' => 'noul', 'noul' => 0.5]], fn (Answers $a) => $a->choice('q'), 'is not a choice answer'],
    'choice without a pick' => [['q' => ['type' => 'choice']], fn (Answers $a) => $a->choice('q'), 'has no choice'],
    'no probabilities' => [['q' => ['type' => 'choice', 'choice' => 'a']], fn (Answers $a) => $a->probabilities('q'), 'has no probabilities'],
    'text probability' => [['q' => ['type' => 'choice', 'probabilities' => ['a' => 'high']]], fn (Answers $a) => $a->probabilities('q'), 'non-numeric probability'],
    'noul without a number' => [['q' => ['type' => 'noul', 'noul' => 'yes']], fn (Answers $a) => $a->noul('q'), 'has no noul probability'],
    'score without a number' => [['q' => ['type' => 'score']], fn (Answers $a) => $a->score('q'), 'has no score'],
]);
