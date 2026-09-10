<?php declare(strict_types=1);

namespace PhpParser\Node\Stmt;

use PhpParser\Node;

class Enum_ extends ClassLike {
    /** @var null|Node\Identifier Scalar Type */
    public ?Node $scalarType;
    /** @var Node\Name[] Names of implemented interfaces */
    public array $implements;

    /**
     * @param string|Node\Identifier|null $name Name
     * @param array{
     *     scalarType?: Node\Identifier|null,
     *     implements?: Node\Name[],
     *     stmts?: Node\Stmt[],
     *     attrGroups?: Node\AttributeGroup[],
     * } $subNodes Array of the following optional subnodes:
     *             'scalarType'  => null    : Scalar type
     *             'implements'  => array() : Names of implemented interfaces
     *             'stmts'       => array() : Statements
     *             'attrGroups'  => array() : PHP attribute groups
     * @param \PhpParser\NodeAttributes|\PhpParser\NodeAttributes::AttributeArray $attributes Additional attributes
     */
    public function __construct($name, array $subNodes = [], \PhpParser\NodeAttributes|array $attributes = []) {
        $this->name = \is_string($name) ? new Node\Identifier($name) : $name;
        $this->scalarType = $subNodes['scalarType'] ?? null;
        $this->implements = $subNodes['implements'] ?? [];
        $this->stmts = $subNodes['stmts'] ?? [];
        $this->attrGroups = $subNodes['attrGroups'] ?? [];

        parent::__construct($attributes);
    }

    public function getSubNodeNames(): array {
        return ['attrGroups', 'name', 'scalarType', 'implements', 'stmts'];
    }

    public function getSubNode(string $name): mixed {
        return match ($name) {
            'attrGroups' => $this->attrGroups,
            'name' => $this->name,
            'scalarType' => $this->scalarType,
            'implements' => $this->implements,
            'stmts' => $this->stmts,
            default => throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class),
        };
    }

    public function setSubNode(string $name, mixed $value): void {
        switch ($name) {
            case 'attrGroups':
                $this->attrGroups = $value;
                return;
            case 'name':
                $this->name = $value;
                return;
            case 'scalarType':
                $this->scalarType = $value;
                return;
            case 'implements':
                $this->implements = $value;
                return;
            case 'stmts':
                $this->stmts = $value;
                return;
        }
        throw new \LogicException('Unknown sub node ' . $name . ' on ' . static::class);
    }

    public function getType(): string {
        return 'Stmt_Enum';
    }
}
