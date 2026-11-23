<?php

declare(strict_types=1);

use App\Simulation\Tally;

it('keeps a running record with points and goals', function (): void {
    $tally = new Tally;
    $tally->add(2, 1);
    $tally->add(1, 1);
    $tally->add(0, 3);

    expect($tally->record())->toBe('1 / 1 / 1')
        ->and($tally->points())->toBe(4)
        ->and($tally->goals())->toBe('3 / 5');
});
