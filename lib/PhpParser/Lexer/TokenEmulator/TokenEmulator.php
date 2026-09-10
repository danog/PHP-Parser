<?php declare(strict_types=1);

namespace PhpParser\Lexer\TokenEmulator;

use PhpParser\PhpVersion;
use PhpParser\Token;

/** @internal */
abstract class TokenEmulator {
    abstract public function getPhpVersion(): PhpVersion;

    abstract public function isEmulationNeeded(string $code): bool;

    /**
     * @param list<Token> $tokens Original tokens
     * @return list<Token> Modified Tokens
     */
    abstract public function emulate(string $code, array $tokens): array;

    /**
     * @param list<Token> $tokens Original tokens
     * @return list<Token> Modified Tokens
     */
    abstract public function reverseEmulate(string $code, array $tokens): array;

    /** @param array{int, string, string}[] $patches */
    public function preprocessCode(string $code, array &$patches): string {
        return $code;
    }
}
