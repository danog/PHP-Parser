<?php declare(strict_types=1);

namespace PhpParser\Node\Stmt\TraitUseAdaptation;

use PhpParser\Node;

class Alias extends Node\Stmt\TraitUseAdaptation {
    /** @var null|int New modifier */
    public ?int $newModifier;
    /** @var null|Node\Identifier New name */
    public ?Node\Identifier $newName;

    /**
     * Constructs a trait use precedence adaptation node.
     *
     * @param null|Node\Name $trait Trait name
     * @param string|Node\Identifier $method Method name
     * @param null|int $newModifier New modifier
     * @param null|string|Node\Identifier $newName New name
     * @param \PhpParser\NodeAttributes|\PhpParser\NodeAttributes::AttributeArray $attributes Additional attributes
     */
    public function __construct(?Node\Name $trait, $method, ?int $newModifier, $newName, \PhpParser\NodeAttributes|array $attributes = []) {
        $this->attributes = \PhpParser\NodeAttributes::from($attributes);
        $this->trait = $trait;
        $this->method = \is_string($method) ? new Node\Identifier($method) : $method;
        $this->newModifier = $newModifier;
        $this->newName = \is_string($newName) ? new Node\Identifier($newName) : $newName;
    }

    public function getSubNodeNames(): array {
        return ['trait', 'method', 'newModifier', 'newName'];
    }

    public function getSubNode(string $name): mixed {
        return match ($name) {
            'trait' => $this->trait,
            'method' => $this->method,
            'newModifier' => $this->newModifier,
            'newName' => $this->newName,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

    public function setSubNode(string $name, mixed $value): void {
        switch ($name) {
            case 'trait':
                $this->trait = $value;
                return;
            case 'method':
                $this->method = $value;
                return;
            case 'newModifier':
                $this->newModifier = $value;
                return;
            case 'newName':
                $this->newName = $value;
                return;
        }
        throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class);
    }

    public function getType(): string {
        return 'Stmt_TraitUseAdaptation_Alias';
    }
}
