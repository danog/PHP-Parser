<?php declare(strict_types=1);

namespace PhpParser;

interface Node {
    /**
     * Gets the type of the node.
     *
     * @psalm-return non-empty-string
     * @return string Type of the node
     */
    public function getType(): string;

    /**
     * Gets the names of the sub nodes.
     *
     * @return string[] Names of sub nodes
     */
    public function getSubNodeNames(): array;

    /**
     * Gets the sub node with the given name (one of getSubNodeNames()).
     */
    /** @return Node|list<Node|null>|string|int|float|bool|null */
    public function getSubNode(string $name): mixed;

    /**
     * Sets the sub node with the given name (one of getSubNodeNames()).
     */
    /** @param Node|list<Node|null>|string|int|float|bool|null $value */
    public function setSubNode(string $name, mixed $value): void;

    /**
     * Gets line the node started in (alias of getStartLine).
     *
     * @return int Start line (or -1 if not available)
     * @phpstan-return -1|positive-int
     *
     * @deprecated Use getStartLine() instead
     */
    public function getLine(): int;

    /**
     * Gets line the node started in.
     *
     * Requires the 'startLine' attribute to be enabled in the lexer (enabled by default).
     *
     * @return int Start line (or -1 if not available)
     * @phpstan-return -1|positive-int
     */
    public function getStartLine(): int;

    /**
     * Gets the line the node ended in.
     *
     * Requires the 'endLine' attribute to be enabled in the lexer (enabled by default).
     *
     * @return int End line (or -1 if not available)
     * @phpstan-return -1|positive-int
     */
    public function getEndLine(): int;

    /**
     * Gets the token offset of the first token that is part of this node.
     *
     * The offset is an index into the array returned by Lexer::getTokens().
     *
     * Requires the 'startTokenPos' attribute to be enabled in the lexer (DISABLED by default).
     *
     * @return int Token start position (or -1 if not available)
     */
    public function getStartTokenPos(): int;

    /**
     * Gets the token offset of the last token that is part of this node.
     *
     * The offset is an index into the array returned by Lexer::getTokens().
     *
     * Requires the 'endTokenPos' attribute to be enabled in the lexer (DISABLED by default).
     *
     * @return int Token end position (or -1 if not available)
     */
    public function getEndTokenPos(): int;

    /**
     * Gets the file offset of the first character that is part of this node.
     *
     * Requires the 'startFilePos' attribute to be enabled in the lexer (DISABLED by default).
     *
     * @return int File start position (or -1 if not available)
     */
    public function getStartFilePos(): int;

    /**
     * Gets the file offset of the last character that is part of this node.
     *
     * Requires the 'endFilePos' attribute to be enabled in the lexer (DISABLED by default).
     *
     * @return int File end position (or -1 if not available)
     */
    public function getEndFilePos(): int;

    /**
     * Gets all comments directly preceding this node.
     *
     * The comments are also available through the "comments" attribute.
     *
     * @return Comment[]
     */
    public function getComments(): array;

    /**
     * Gets the doc comment of the node.
     *
     * @return null|Comment\Doc Doc comment object or null
     */
    public function getDocComment(): ?Comment\Doc;

    /**
     * Sets the doc comment of the node.
     *
     * This will either replace an existing doc comment or add it to the comments array.
     *
     * @param Comment\Doc $docComment Doc comment to set
     */
    public function setDocComment(Comment\Doc $docComment): void;

    /** The node's attributes (the live object: writes change this node). */
    public function attrs(): NodeAttributes;

    /** A copy of the node's attributes. */
    public function getAttributes(): NodeAttributes;

    /** @param NodeAttributes|\PhpParser\NodeAttributes::AttributeArray $attributes */
    public function setAttributes(NodeAttributes|array $attributes): void;
}
