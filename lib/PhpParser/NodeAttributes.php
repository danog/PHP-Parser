<?php declare(strict_types=1);

namespace PhpParser;

use PhpParser\Node\Name;

/**
 * The attributes of a node: a fixed set of typed, optional values (positions, comments, formatting hints
 * and the annotations visitors attach). This replaces the string-keyed attribute array: the compiled
 * program never looks attributes up by name.
 *
 * @psalm-type AttributeArray = array{startLine?: int, endLine?: int, startTokenPos?: int, endTokenPos?: int, startFilePos?: int, endFilePos?: int, kind?: int, comments?: list<Comment>, rawValue?: string, docLabel?: string, docIndentation?: string, propertyName?: string, hasLeadingNewline?: bool, shouldPrintRawValue?: bool, pure?: bool, memoizable?: bool, external_mutation_free?: bool, allMatched?: bool, resolvedName?: Name|string, namespacedName?: Name|string, originalName?: Name|string, parent?: Node, previous?: Node, next?: Node, origNode?: Node, weak_parent?: \WeakReference<Node>, weak_previous?: \WeakReference<Node>, weak_next?: \WeakReference<Node>}
 */
final class NodeAttributes {
    public ?int $startLine = null;
    public ?int $endLine = null;
    public ?int $startTokenPos = null;
    public ?int $endTokenPos = null;
    public ?int $startFilePos = null;
    public ?int $endFilePos = null;
    public ?int $kind = null;
    /** @var list<Comment>|null */
    public ?array $comments = null;
    public ?string $rawValue = null;
    public ?string $docLabel = null;
    public ?string $docIndentation = null;
    public ?string $propertyName = null;
    public ?bool $hasLeadingNewline = null;
    public ?bool $shouldPrintRawValue = null;
    public ?bool $pure = null;
    public ?bool $memoizable = null;
    public ?bool $external_mutation_free = null;
    public ?bool $allMatched = null;
    public Name|string|null $resolvedName = null;
    public Name|string|null $namespacedName = null;
    public Name|string|null $originalName = null;
    public ?Node $parent = null;
    public ?Node $previous = null;
    public ?Node $next = null;
    public ?Node $origNode = null;
    public ?\WeakReference $weak_parent = null;
    public ?\WeakReference $weak_previous = null;
    public ?\WeakReference $weak_next = null;

    /** @param NodeAttributes|AttributeArray $attributes */
    public static function from(NodeAttributes|array $attributes): self {
        return $attributes instanceof self ? clone $attributes : self::fromArray($attributes);
    }

    /** @param AttributeArray $attributes */
    public static function fromArray(array $attributes): self {
        $a = new self();
        if (isset($attributes['startLine'])) {
            $a->startLine = $attributes['startLine'];
        }
        if (isset($attributes['endLine'])) {
            $a->endLine = $attributes['endLine'];
        }
        if (isset($attributes['startTokenPos'])) {
            $a->startTokenPos = $attributes['startTokenPos'];
        }
        if (isset($attributes['endTokenPos'])) {
            $a->endTokenPos = $attributes['endTokenPos'];
        }
        if (isset($attributes['startFilePos'])) {
            $a->startFilePos = $attributes['startFilePos'];
        }
        if (isset($attributes['endFilePos'])) {
            $a->endFilePos = $attributes['endFilePos'];
        }
        if (isset($attributes['kind'])) {
            $a->kind = $attributes['kind'];
        }
        if (isset($attributes['comments'])) {
            $a->comments = $attributes['comments'];
        }
        if (isset($attributes['rawValue'])) {
            $a->rawValue = $attributes['rawValue'];
        }
        if (isset($attributes['docLabel'])) {
            $a->docLabel = $attributes['docLabel'];
        }
        if (isset($attributes['docIndentation'])) {
            $a->docIndentation = $attributes['docIndentation'];
        }
        if (isset($attributes['propertyName'])) {
            $a->propertyName = $attributes['propertyName'];
        }
        if (isset($attributes['hasLeadingNewline'])) {
            $a->hasLeadingNewline = $attributes['hasLeadingNewline'];
        }
        if (isset($attributes['shouldPrintRawValue'])) {
            $a->shouldPrintRawValue = $attributes['shouldPrintRawValue'];
        }
        if (isset($attributes['pure'])) {
            $a->pure = $attributes['pure'];
        }
        if (isset($attributes['memoizable'])) {
            $a->memoizable = $attributes['memoizable'];
        }
        if (isset($attributes['external_mutation_free'])) {
            $a->external_mutation_free = $attributes['external_mutation_free'];
        }
        if (isset($attributes['allMatched'])) {
            $a->allMatched = $attributes['allMatched'];
        }
        if (isset($attributes['resolvedName'])) {
            $a->resolvedName = $attributes['resolvedName'];
        }
        if (isset($attributes['namespacedName'])) {
            $a->namespacedName = $attributes['namespacedName'];
        }
        if (isset($attributes['originalName'])) {
            $a->originalName = $attributes['originalName'];
        }
        if (isset($attributes['parent'])) {
            $a->parent = $attributes['parent'];
        }
        if (isset($attributes['previous'])) {
            $a->previous = $attributes['previous'];
        }
        if (isset($attributes['next'])) {
            $a->next = $attributes['next'];
        }
        if (isset($attributes['origNode'])) {
            $a->origNode = $attributes['origNode'];
        }
        if (isset($attributes['weak_parent'])) {
            $a->weak_parent = $attributes['weak_parent'];
        }
        if (isset($attributes['weak_previous'])) {
            $a->weak_previous = $attributes['weak_previous'];
        }
        if (isset($attributes['weak_next'])) {
            $a->weak_next = $attributes['weak_next'];
        }
        return $a;
    }

    /** @return AttributeArray */
    public function toArray(): array {
        $out = [];
        if ($this->startLine !== null) {
            $out['startLine'] = $this->startLine;
        }
        if ($this->startTokenPos !== null) {
            $out['startTokenPos'] = $this->startTokenPos;
        }
        if ($this->startFilePos !== null) {
            $out['startFilePos'] = $this->startFilePos;
        }
        if ($this->endLine !== null) {
            $out['endLine'] = $this->endLine;
        }
        if ($this->endTokenPos !== null) {
            $out['endTokenPos'] = $this->endTokenPos;
        }
        if ($this->endFilePos !== null) {
            $out['endFilePos'] = $this->endFilePos;
        }
        if ($this->kind !== null) {
            $out['kind'] = $this->kind;
        }
        if ($this->comments !== null) {
            $out['comments'] = $this->comments;
        }
        if ($this->docLabel !== null) {
            $out['docLabel'] = $this->docLabel;
        }
        if ($this->docIndentation !== null) {
            $out['docIndentation'] = $this->docIndentation;
        }
        if ($this->rawValue !== null) {
            $out['rawValue'] = $this->rawValue;
        }
        if ($this->propertyName !== null) {
            $out['propertyName'] = $this->propertyName;
        }
        if ($this->hasLeadingNewline !== null) {
            $out['hasLeadingNewline'] = $this->hasLeadingNewline;
        }
        if ($this->shouldPrintRawValue !== null) {
            $out['shouldPrintRawValue'] = $this->shouldPrintRawValue;
        }
        if ($this->pure !== null) {
            $out['pure'] = $this->pure;
        }
        if ($this->memoizable !== null) {
            $out['memoizable'] = $this->memoizable;
        }
        if ($this->external_mutation_free !== null) {
            $out['external_mutation_free'] = $this->external_mutation_free;
        }
        if ($this->allMatched !== null) {
            $out['allMatched'] = $this->allMatched;
        }
        if ($this->resolvedName !== null) {
            $out['resolvedName'] = $this->resolvedName;
        }
        if ($this->namespacedName !== null) {
            $out['namespacedName'] = $this->namespacedName;
        }
        if ($this->originalName !== null) {
            $out['originalName'] = $this->originalName;
        }
        if ($this->parent !== null) {
            $out['parent'] = $this->parent;
        }
        if ($this->previous !== null) {
            $out['previous'] = $this->previous;
        }
        if ($this->next !== null) {
            $out['next'] = $this->next;
        }
        if ($this->origNode !== null) {
            $out['origNode'] = $this->origNode;
        }
        if ($this->weak_parent !== null) {
            $out['weak_parent'] = $this->weak_parent;
        }
        if ($this->weak_previous !== null) {
            $out['weak_previous'] = $this->weak_previous;
        }
        if ($this->weak_next !== null) {
            $out['weak_next'] = $this->weak_next;
        }
        return $out;
    }
}
