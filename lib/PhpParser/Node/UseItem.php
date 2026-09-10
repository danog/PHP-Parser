<?php declare(strict_types=1);

namespace PhpParser\Node;

use PhpParser\Node;
use PhpParser\NodeAbstract;
use PhpParser\Node\Stmt\Use_;

class UseItem extends NodeAbstract {
    /**
     * @var Use_::TYPE_* One of the Stmt\Use_::TYPE_* constants. Will only differ from TYPE_UNKNOWN for mixed group uses
     */
    public int $type;
    /** @var Node\Name Namespace, class, function or constant to alias */
    public Name $name;
    /** @var Identifier|null Alias */
    public ?Identifier $alias;

    /**
     * Constructs an alias (use) item node.
     *
     * @param Node\Name $name Namespace/Class to alias
     * @param null|string|Identifier $alias Alias
     * @param Use_::TYPE_* $type Type of the use element (for mixed group use only)
     * @param \PhpParser\NodeAttributes|\PhpParser\NodeAttributes::AttributeArray $attributes Additional attributes
     */
    public function __construct(Node\Name $name, $alias = null, int $type = Use_::TYPE_UNKNOWN, \PhpParser\NodeAttributes|array $attributes = []) {
        $this->attributes = \PhpParser\NodeAttributes::from($attributes);
        $this->type = $type;
        $this->name = $name;
        $this->alias = \is_string($alias) ? new Identifier($alias) : $alias;
    }

    public function getSubNodeNames(): array {
        return ['type', 'name', 'alias'];
    }

    /** @return \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null */
    public function getSubNode(string $name): mixed {
        return match ($name) {
            'type' => $this->type,
            'name' => $this->name,
            'alias' => $this->alias,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

    /** @param \PhpParser\Node|list<\PhpParser\Node|null>|string|int|float|bool|null $value */
    public function setSubNode(string $name, mixed $value): void {
        switch ($name) {
            case 'type':
                $this->type = $value;
                return;
            case 'name':
                $this->name = $value;
                return;
            case 'alias':
                $this->alias = $value;
                return;
        }
        throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class);
    }

    /**
     * Get alias. If not explicitly given this is the last component of the used name.
     */
    public function getAlias(): Identifier {
        if (null !== $this->alias) {
            return $this->alias;
        }

        return new Identifier($this->name->getLast());
    }

    public function getType(): string {
        return 'UseItem';
    }
}

// @deprecated compatibility alias
class_alias(UseItem::class, Stmt\UseUse::class);
