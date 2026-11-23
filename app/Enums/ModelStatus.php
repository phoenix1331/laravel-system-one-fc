<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Whether the decision model can answer right now, as Ollama reports it.
 */
enum ModelStatus: string
{
    /** Loaded into memory: the next decision starts straight away. */
    case Ready = 'ready';

    /** Ollama is running but has not loaded the model; the next decision loads it first. */
    case Asleep = 'asleep';

    /** Ollama cannot be reached, so the rule-based bot decides instead. */
    case Offline = 'offline';

    public function label(): string
    {
        return match ($this) {
            self::Ready => 'Model ready',
            self::Asleep => 'Model asleep, wakes on the next decision',
            self::Offline => 'Ollama offline, the bot decides instead',
        };
    }
}
