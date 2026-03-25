<?php

namespace App\Domain\Competition\Services;

use App\Domain\Competition\Contracts\FormatGenerator;
use App\Domain\Competition\Contracts\FormatResolver;
use App\Domain\Competition\Contracts\FormatRuleset;
use App\Enums\StageType;
use RuntimeException;

/**
 * Resolves format classes (generator, resolver, ruleset) for a given stage type.
 *
 * Uses the competition-formats config to look up registered implementations.
 * Falls back to the service container for resolution, enabling dependency injection.
 *
 * TODO: Register this as a singleton in AppServiceProvider when implementations exist
 * TODO: Support custom format registration via plugins/packages
 */
class FormatRegistry
{
    /**
     * @param array<string, array{generator: class-string|null, resolver: class-string|null, ruleset: class-string|null}> $formats
     */
    public function __construct(
        protected array $formats = [],
    ) {
        if ($formats === []) {
            /** @var array<string, array{generator: class-string|null, resolver: class-string|null, ruleset: class-string|null}> $configFormats */
            $configFormats = config('competition-formats', []);
            $this->formats = $configFormats;
        }
    }

    public function generator(StageType $stageType): FormatGenerator
    {
        return $this->resolve($stageType, 'generator', FormatGenerator::class);
    }

    public function resolver(StageType $stageType): FormatResolver
    {
        return $this->resolve($stageType, 'resolver', FormatResolver::class);
    }

    public function ruleset(StageType $stageType): FormatRuleset
    {
        return $this->resolve($stageType, 'ruleset', FormatRuleset::class);
    }

    public function hasFormat(StageType $stageType): bool
    {
        $config = $this->formats[$stageType->value] ?? null;

        return $config !== null && $config['generator'] !== null;
    }

    /**
     * @template T
     *
     * @param  class-string<T>  $contract
     * @return T
     */
    protected function resolve(StageType $stageType, string $key, string $contract): mixed
    {
        $config = $this->formats[$stageType->value] ?? null;

        if ($config === null || $config[$key] === null) {
            throw new RuntimeException(
                "No {$key} registered for stage type [{$stageType->value}]. "
                . 'Register implementations in config/competition-formats.php.'
            );
        }

        /** @var T */
        return app($config[$key]);
    }
}
