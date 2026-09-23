<?php declare(strict_types=1);

namespace PhpParser\Node\Stmt;

use PhpParser\Node;

/**
 * @psalm-import-type AttributeArray from \PhpParser\NodeAttributes
 */
class TraitUse extends Node\Stmt {
    /** @var list<Node\Name> Traits */
    public array $traits;
    /** @var list<TraitUseAdaptation> Adaptations */
    public array $adaptations;

    /**
     * Constructs a trait use node.
     *
     * @param list<Node\Name> $traits Traits
     * @param list<TraitUseAdaptation> $adaptations Adaptations
     * @param \PhpParser\NodeAttributes|AttributeArray $attributes Additional attributes
     */
    public function __construct(array $traits, array $adaptations = [], \PhpParser\NodeAttributes|array $attributes = []) {
        $this->attributes = $attributes instanceof \PhpParser\NodeAttributes ? $attributes : \PhpParser\NodeAttributes::fromArray($attributes);
        $this->traits = $traits;
        $this->adaptations = $adaptations;
    }

    /** @return list<string> */
    public function getSubNodeNames(): array {
        return ['traits', 'adaptations'];
    }

    /**
     * Every sub node by name, in getSubNodeNames() order (one call instead of one getSubNode() per name).
     *
     * @return array<string, Node|list<Node|null>|string|int|float|bool|null>
     */
    public function getSubNodes(): array {
        return ['traits' => $this->traits, 'adaptations' => $this->adaptations];
    }


    /** @return \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null */
    public function getSubNode(string $name): mixed {
        return match ($name) {
            'traits' => $this->traits,
            'adaptations' => $this->adaptations,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

    /** @param \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null $value */
    public function setSubNode(string $name, mixed $value): void {
        switch ($name) {
            case 'traits':
                $this->traits = $value;
                return;
            case 'adaptations':
                $this->adaptations = $value;
                return;
        }
        throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class);
    }

    public function getType(): string {
        return 'Stmt_TraitUse';
    }
}
