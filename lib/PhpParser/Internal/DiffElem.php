<?php declare(strict_types=1);

namespace PhpParser\Internal;

use PhpParser\Node;

/**
 * @internal
 */
class DiffElem {
    public const TYPE_KEEP = 0;
    public const TYPE_REMOVE = 1;
    public const TYPE_ADD = 2;
    public const TYPE_REPLACE = 3;

    /** @var int One of the TYPE_* constants */
    public int $type;
    /** @var Node|null Is null for add operations */
    public $old;
    /** @var Node|null Is null for remove operations */
    public $new;

    /**
     * @param int $type One of the TYPE_* constants
     * @param Node|null $old Is null for add operations
     * @param Node|null $new Is null for remove operations
     */
    public function __construct(int $type, $old, $new) {
        $this->type = $type;
        $this->old = $old;
        $this->new = $new;
    }
}
