<?php

declare(strict_types=1);

namespace SupermonNg\Services\Ami;

/**
 * Parsed XStat / SawStat AMI snapshot.
 *
 * @phpstan-type ConnRow list<string>
 * @phpstan-type KeyupRow array{node: string, isKeyed: string, keyed: string, unkeyed: string}
 */
final class AmiParseResult
{
    /** @param array<string, string> $parsedVars */
    /** @param list<ConnRow> $conns */
    /** @param array<string, KeyupRow> $keyups */
    /** @param array<string, array{mode: string}> $modes */
    /** @param list<string> $allLinkedNodes */
    public function __construct(
        public readonly array $parsedVars,
        public readonly array $conns,
        public readonly array $keyups,
        public readonly array $modes,
        public readonly array $allLinkedNodes,
    ) {
    }
}
