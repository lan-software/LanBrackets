<?php

use App\Domain\Competition\Formats\SingleElimination\Generator;
use App\Domain\Competition\Formats\SingleElimination\Resolver;
use App\Domain\Competition\Formats\SingleElimination\Ruleset;

/**
 * Competition format registry.
 *
 * Maps stage types to their generator, resolver, and ruleset implementations.
 * This config is used by the FormatRegistry service to resolve the correct
 * classes for each stage type.
 */
return [
    'single_elimination' => [
        'generator' => Generator::class,
        'resolver' => Resolver::class,
        'ruleset' => Ruleset::class,
    ],

    'double_elimination' => [
        'generator' => App\Domain\Competition\Formats\DoubleElimination\Generator::class,
        'resolver' => App\Domain\Competition\Formats\DoubleElimination\Resolver::class,
        'ruleset' => App\Domain\Competition\Formats\DoubleElimination\Ruleset::class,
    ],

    'swiss' => [
        'generator' => App\Domain\Competition\Formats\Swiss\Generator::class,
        'resolver' => App\Domain\Competition\Formats\Swiss\Resolver::class,
        'ruleset' => App\Domain\Competition\Formats\Swiss\Ruleset::class,
    ],

    'round_robin' => [
        'generator' => App\Domain\Competition\Formats\RoundRobin\Generator::class,
        'resolver' => App\Domain\Competition\Formats\RoundRobin\Resolver::class,
        'ruleset' => App\Domain\Competition\Formats\RoundRobin\Ruleset::class,
    ],

    'group_stage' => [
        'generator' => App\Domain\Competition\Formats\GroupStage\Generator::class,
        'resolver' => App\Domain\Competition\Formats\GroupStage\Resolver::class,
        'ruleset' => App\Domain\Competition\Formats\GroupStage\Ruleset::class,
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
