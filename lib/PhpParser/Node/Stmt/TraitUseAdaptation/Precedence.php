<?php declare(strict_types=1);

namespace PhpParser\Node\Stmt\TraitUseAdaptation;

use PhpParser\Node;

class Precedence extends Node\Stmt\TraitUseAdaptation {
    /** @var list<Node\Name> Overwritten traits */
    public array $insteadof;

    /**
     * Constructs a trait use precedence adaptation node.
     *
     * @param Node\Name $trait Trait name
     * @param string|Node\Identifier $method Method name
     * @param list<Node\Name> $insteadof Overwritten traits
     * @param \PhpParser\NodeAttributes|\PhpParser\NodeAttributes::AttributeArray $attributes Additional attributes
     */
    public function __construct(Node\Name $trait, $method, array $insteadof, \PhpParser\NodeAttributes|array $attributes = []) {
        $this->attributes = \PhpParser\NodeAttributes::from($attributes);
        $this->trait = $trait;
        $this->method = \is_string($method) ? new Node\Identifier($method) : $method;
        $this->insteadof = $insteadof;
    }

    public function getSubNodeNames(): array {
        return ['trait', 'method', 'insteadof'];
    }

    /** @return \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null */
    public function getSubNode(string $name): mixed {
        return match ($name) {
            'trait' => $this->trait,
            'method' => $this->method,
            'insteadof' => $this->insteadof,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

    /** @param \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null $value */
    public function setSubNode(string $name, mixed $value): void {
        switch ($name) {
            case 'trait':
                $this->trait = $value;
                return;
            case 'method':
                $this->method = $value;
                return;
            case 'insteadof':
                $this->insteadof = $value;
                return;
        }
        throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class);
    }

    public function getType(): string {
        return 'Stmt_TraitUseAdaptation_Precedence';
    }
}
