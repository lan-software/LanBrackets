<?php

/**
 * Competition format registry.
 *
 * Maps stage types to their generator, resolver, and ruleset implementations.
 * This config is used by the FormatRegistry service to resolve the correct
 * classes for each stage type.
 *
 */
return [
    'single_elimination' => [
        'generator' => \App\Domain\Competition\Formats\SingleElimination\Generator::class,
        'resolver' => \App\Domain\Competition\Formats\SingleElimination\Resolver::class,
        'ruleset' => \App\Domain\Competition\Formats\SingleElimination\Ruleset::class,
    ],

    'double_elimination' => [
        'generator' => \App\Domain\Competition\Formats\DoubleElimination\Generator::class,
        'resolver' => \App\Domain\Competition\Formats\DoubleElimination\Resolver::class,
        'ruleset' => \App\Domain\Competition\Formats\DoubleElimination\Ruleset::class,
    ],

    'swiss' => [
        'generator' => null, // TODO: Implement
        'resolver' => null,  // TODO: Implement
        'ruleset' => null,   // TODO: Implement
    ],

    'round_robin' => [
        'generator' => null, // TODO: Implement
        'resolver' => null,  // TODO: Implement
        'ruleset' => null,   // TODO: Implement
    ],

    'group_stage' => [
        'generator' => null, // TODO: Implement
        'resolver' => null,  // TODO: Implement
        'ruleset' => null,   // TODO: Implement
    ],

    'race_heat' => [
        'generator' => null, // TODO: Implement (Race support)
        'resolver' => null,  // TODO: Implement (Race support)
        'ruleset' => null,   // TODO: Implement (Race support)
    ],

    'final_stage' => [
        'generator' => null, // TODO: Implement
        'resolver' => null,  // TODO: Implement
        'ruleset' => null,   // TODO: Implement
    ],
];
