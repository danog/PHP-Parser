<?php declare(strict_types=1);

namespace PhpParser\NodeVisitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * Visitor that connects a child node to its parent node
 * as well as its sibling nodes.
 *
 * With <code>$weakReferences=false</code> on the child node, the parent node can be accessed through
 * <code>$node->attrs()->parent</code>, the previous
 * node can be accessed through <code>$node->attrs()->previous</code>,
 * and the next node can be accessed through <code>$node->attrs()->next</code>.
 *
 * With <code>$weakReferences=true</code> attribute names are prefixed by "weak_", e.g. "weak_parent".
 */
final class NodeConnectingVisitor extends NodeVisitorAbstract {
    /**
     * @var Node[]
     */
    private array $stack = [];

    /**
     * @var ?Node
     */
    private $previous;

    private bool $weakReferences;

    public function __construct(bool $weakReferences = false) {
        $this->weakReferences = $weakReferences;
    }

    public function beforeTraverse(array $nodes) {
        $this->stack    = [];
        $this->previous = null;
    }

    public function enterNode(Node $node) {
        if (!empty($this->stack)) {
            $parent = $this->stack[count($this->stack) - 1];
            if ($this->weakReferences) {
                $node->attrs()->weak_parent = \WeakReference::create($parent);
            } else {
                $node->attrs()->parent = $parent;
            }
        }

        if ($this->previous !== null) {
            if (
                $this->weakReferences
            ) {
                if ($this->previous->attrs()->weak_parent === $node->attrs()->weak_parent) {
                    $node->attrs()->weak_previous = \WeakReference::create($this->previous);
                    $this->previous->attrs()->weak_next = \WeakReference::create($node);
                }
            } elseif ($this->previous->attrs()->parent === $node->attrs()->parent) {
                $node->attrs()->previous = $this->previous;
                $this->previous->attrs()->next = $node;
            }
        }

        $this->stack[] = $node;
    }

    public function leaveNode(Node $node) {
        $this->previous = $node;

        array_pop($this->stack);
    }
}
