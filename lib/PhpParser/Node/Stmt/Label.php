<?php declare(strict_types=1);

namespace PhpParser\Node\Stmt;

use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt;

/**
 * @psalm-import-type AttributeArray from \PhpParser\NodeAttributes
 */
class Label extends Stmt {
    /** @var Identifier Name */
    public Identifier $name;

    /**
     * Constructs a label node.
     *
     * @param string|Identifier $name Name
     * @param \PhpParser\NodeAttributes|AttributeArray $attributes Additional attributes
     */
    public function __construct($name, \PhpParser\NodeAttributes|array $attributes = []) {
        $this->attributes = $attributes instanceof \PhpParser\NodeAttributes ? $attributes : \PhpParser\NodeAttributes::fromArray($attributes);
        $this->name = \is_string($name) ? new Identifier($name) : $name;
    }

    /** @return list<string> */
    public function getSubNodeNames(): array {
        return ['name'];
    }

    /**
     * Every sub node by name, in getSubNodeNames() order (one call instead of one getSubNode() per name).
     *
     * @return array<string, Node|list<Node|null>|string|int|float|bool|null>
     */
    public function getSubNodes(): array {
        return ['name' => $this->name];
    }

    #[\Override]
    public function traverseSubNodes(\PhpParser\NodeTraverser $traverser): void {
        $this->name = $traverser->traverseRequiredChildNode($this->name);
        if ($traverser->stopTraversal) {
            return;
        }
    }


    /** @return \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null */
    public function getSubNode(string $name): mixed {
        return match ($name) {
            'name' => $this->name,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

    /** @param \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null $value */
    public function setSubNode(string $name, mixed $value): void {
        switch ($name) {
            case 'name':
                $this->name = $value;
                return;
        }
        throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class);
    }

    public function getType(): string {
        return 'Stmt_Label';
    }
}
