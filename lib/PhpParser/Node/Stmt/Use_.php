<?php declare(strict_types=1);

namespace PhpParser\Node\Stmt;

use PhpParser\Node\Stmt;
use PhpParser\Node\UseItem;

/**
 * @psalm-import-type AttributeArray from \PhpParser\NodeAttributes
 */
class Use_ extends Stmt {
    /**
     * Unknown type. Both Stmt\Use_ / Stmt\GroupUse and Stmt\UseUse have a $type property, one of them will always be
     * TYPE_UNKNOWN while the other has one of the three other possible types. For normal use statements the type on the
     * Stmt\UseUse is unknown. It's only the other way around for mixed group use declarations.
     */
    public const TYPE_UNKNOWN = 0;
    /** Class or namespace import */
    public const TYPE_NORMAL = 1;
    /** Function import */
    public const TYPE_FUNCTION = 2;
    /** Constant import */
    public const TYPE_CONSTANT = 3;

    /** @var self::TYPE_* Type of alias */
    public int $type;
    /** @var list<UseItem> Aliases */
    public array $uses;

    /**
     * Constructs an alias (use) list node.
     *
     * @param list<UseItem> $uses Aliases
     * @param Stmt\Use_::TYPE_* $type Type of alias
     * @param \PhpParser\NodeAttributes|AttributeArray $attributes Additional attributes
     */
    public function __construct(array $uses, int $type = self::TYPE_NORMAL, \PhpParser\NodeAttributes|array $attributes = []) {
        $this->attributes = $attributes instanceof \PhpParser\NodeAttributes ? $attributes : \PhpParser\NodeAttributes::fromArray($attributes);
        $this->type = $type;
        $this->uses = $uses;
    }

    /** @return list<string> */
    public function getSubNodeNames(): array {
        return ['type', 'uses'];
    }

    /**
     * Every sub node by name, in getSubNodeNames() order (one call instead of one getSubNode() per name).
     *
     * @return array<string, Node|list<Node|null>|string|int|float|bool|null>
     */
    public function getSubNodes(): array {
        return ['type' => $this->type, 'uses' => $this->uses];
    }

    #[\Override]
    public function traverseSubNodes(\PhpParser\NodeTraverser $traverser): void {
        $this->uses = $traverser->traverseArray($this->uses);
        if ($traverser->stopTraversal) {
            return;
        }
    }


    /** @return \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null */
    public function getSubNode(string $name): mixed {
        return match ($name) {
            'type' => $this->type,
            'uses' => $this->uses,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

    /** @param \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null $value */
    public function setSubNode(string $name, mixed $value): void {
        switch ($name) {
            case 'type':
                $this->type = $value;
                return;
            case 'uses':
                $this->uses = $value;
                return;
        }
        throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class);
    }

    public function getType(): string {
        return 'Stmt_Use';
    }
}
