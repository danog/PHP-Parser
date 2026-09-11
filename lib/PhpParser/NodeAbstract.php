<?php declare(strict_types=1);

namespace PhpParser;

abstract class NodeAbstract implements Node, \JsonSerializable {
    protected NodeAttributes $attributes;

    /**
     * Creates a Node.
     *
     * @param NodeAttributes|\PhpParser\NodeAttributes::AttributeArray $attributes
     */
    public function __construct(NodeAttributes|array $attributes = []) {
        $this->attributes = NodeAttributes::from($attributes);
    }

    /**
     * Gets line the node started in (alias of getStartLine).
     *
     * @return int Start line (or -1 if not available)
     * @phpstan-return -1|positive-int
     */
    public function getLine(): int {
        return $this->attributes->startLine ?? -1;
    }

    /**
     * Gets line the node started in.
     *
     * Requires the 'startLine' attribute to be enabled in the lexer (enabled by default).
     *
     * @return int Start line (or -1 if not available)
     * @phpstan-return -1|positive-int
     */
    public function getStartLine(): int {
        return $this->attributes->startLine ?? -1;
    }

    /**
     * Gets the line the node ended in.
     *
     * Requires the 'endLine' attribute to be enabled in the lexer (enabled by default).
     *
     * @return int End line (or -1 if not available)
     * @phpstan-return -1|positive-int
     */
    public function getEndLine(): int {
        return $this->attributes->endLine ?? -1;
    }

    /**
     * Gets the token offset of the first token that is part of this node.
     *
     * The offset is an index into the array returned by Lexer::getTokens().
     *
     * Requires the 'startTokenPos' attribute to be enabled in the lexer (DISABLED by default).
     *
     * @return int Token start position (or -1 if not available)
     */
    public function getStartTokenPos(): int {
        return $this->attributes->startTokenPos ?? -1;
    }

    /**
     * Gets the token offset of the last token that is part of this node.
     *
     * The offset is an index into the array returned by Lexer::getTokens().
     *
     * Requires the 'endTokenPos' attribute to be enabled in the lexer (DISABLED by default).
     *
     * @return int Token end position (or -1 if not available)
     */
    public function getEndTokenPos(): int {
        return $this->attributes->endTokenPos ?? -1;
    }

    /**
     * Gets the file offset of the first character that is part of this node.
     *
     * Requires the 'startFilePos' attribute to be enabled in the lexer (DISABLED by default).
     *
     * @return int File start position (or -1 if not available)
     */
    public function getStartFilePos(): int {
        return $this->attributes->startFilePos ?? -1;
    }

    /**
     * Gets the file offset of the last character that is part of this node.
     *
     * Requires the 'endFilePos' attribute to be enabled in the lexer (DISABLED by default).
     *
     * @return int File end position (or -1 if not available)
     */
    public function getEndFilePos(): int {
        return $this->attributes->endFilePos ?? -1;
    }

    /**
     * Gets all comments directly preceding this node.
     *
     * The comments are also available through the "comments" attribute.
     *
     * @return list<Comment>
     */
    public function getComments(): array {
        return $this->attributes->comments ?? [];
    }

    /**
     * Gets the doc comment of the node.
     *
     * @return null|Comment\Doc Doc comment object or null
     */
    public function getDocComment(): ?Comment\Doc {
        $comments = $this->getComments();
        for ($i = count($comments) - 1; $i >= 0; $i--) {
            $comment = $comments[$i];
            if ($comment instanceof Comment\Doc) {
                return $comment;
            }
        }

        return null;
    }

    /**
     * Sets the doc comment of the node.
     *
     * This will either replace an existing doc comment or add it to the comments array.
     *
     * @param Comment\Doc $docComment Doc comment to set
     */
    public function setDocComment(Comment\Doc $docComment): void {
        $comments = $this->getComments();
        for ($i = count($comments) - 1; $i >= 0; $i--) {
            if ($comments[$i] instanceof Comment\Doc) {
                // Replace existing doc comment.
                $comments[$i] = $docComment;
                $this->attributes->comments = $comments;
                return;
            }
        }

        // Append new doc comment.
        $comments[] = $docComment;
        $this->attributes->comments = $comments;
    }

    public function __clone() {
        $this->attributes = clone $this->attributes;
    }

    /** The node's attributes (the live object: writes change this node). */
    public function attrs(): NodeAttributes {
        return $this->attributes;
    }

    /** A copy of the node's attributes (for nodes built from this one). */
    public function getAttributes(): NodeAttributes {
        return clone $this->attributes;
    }

    /** @param NodeAttributes|\PhpParser\NodeAttributes::AttributeArray $attributes */
    public function setAttributes(NodeAttributes|array $attributes): void {
        $this->attributes = NodeAttributes::from($attributes);
    }

    /**
     * @return array<string, Node|list<Node|null>|scalar|null|array<string, mixed>>
     */
    public function jsonSerialize(): array {
        $result = ['nodeType' => $this->getType(), 'attributes' => $this->attributes->toArray()];
        foreach ($this->getSubNodeNames() as $name) {
            $result[$name] = $this->getSubNode($name);
        }
        return $result;
    }
}
