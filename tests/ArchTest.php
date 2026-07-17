<?php

use MathiasOnea\Rulebook\Rule;
use MathiasOnea\Rulebook\Rulebook;

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->each->not->toBeUsed();

arch('the contract is an interface')
    ->expect('MathiasOnea\Rulebook\Contracts')
    ->toBeInterfaces();

arch('rulebooks and rules are extension points')
    ->expect([
        Rule::class,
        Rulebook::class,
    ])
    ->toBeAbstract();

arch('the package has no persistence or global API dependencies')
    ->expect('MathiasOnea\Rulebook')
    ->not->toUse([
        'Illuminate\Console',
        'Illuminate\Database',
        'Illuminate\Events',
        'Illuminate\Queue',
        'Illuminate\Support\Facades',
    ]);

it('contains no scaffold or layered namespace leftovers', function () {
    $root = dirname(__DIR__);

    expect(is_dir($root.'/config'))->toBeFalse()
        ->and(is_dir($root.'/database'))->toBeFalse()
        ->and(is_dir($root.'/resources'))->toBeFalse()
        ->and(is_dir($root.'/src/Commands'))->toBeFalse()
        ->and(is_dir($root.'/src/Facades'))->toBeFalse()
        ->and(is_dir($root.'/src/Core'))->toBeFalse()
        ->and(is_dir($root.'/src/Laravel'))->toBeFalse();
});
