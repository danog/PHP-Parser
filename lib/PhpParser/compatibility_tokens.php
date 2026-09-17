<?php declare(strict_types=1);

namespace PhpParser;

if (!\function_exists('PhpParser\defineCompatibilityTokens')) {
    function defineCompatibilityTokens(): void {
        // The compiled program runs with every token of the newest supported PHP version defined;
        // nothing to emulate.
    }

    defineCompatibilityTokens();
}
