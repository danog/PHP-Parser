<?php declare(strict_types=1);

namespace PhpParser;

/**
 * @psalm-type JsonScalar = scalar|null
 * @psalm-type JsonInput = JsonScalar|array<array-key, JsonScalar|array<array-key, JsonScalar|array<array-key, JsonScalar|array>>>
 * @psalm-type Decoded = Node|Comment|JsonScalar|array<array-key, Node|Comment|JsonScalar|array<array-key, Node|Comment|JsonScalar|array>>
 */
class JsonDecoder {
    /** @return Decoded */
    public function decode(string $json) {
        /** @var JsonInput $value */
        $value = json_decode($json, true);
        if (json_last_error()) {
            throw new \RuntimeException('JSON decoding error: ' . json_last_error_msg());
        }

        return $this->decodeRecursive($value);
    }

    /**
     * @param JsonInput $value
     * @return Decoded
     */
    private function decodeRecursive($value) {
        if (\is_array($value)) {
            /** @var array<array-key, JsonInput> $value */
            if (isset($value['nodeType'])) {
                if ($value['nodeType'] === 'Comment' || $value['nodeType'] === 'Comment_Doc') {
                    return $this->decodeComment($value);
                }
                return $this->decodeNode($value);
            }
            return $this->decodeArray($value);
        }
        return $value;
    }

    /**
     * @param array<array-key, JsonInput> $array
     * @return array<array-key, Decoded>
     */
    private function decodeArray(array $array): array {
        $decodedArray = [];
        foreach ($array as $key => $value) {
            $decodedArray[$key] = $this->decodeRecursive($value);
        }
        return $decodedArray;
    }

    /** @param array<array-key, JsonInput> $value */
    private function decodeNode(array $value): Node {
        $nodeType = $value['nodeType'];
        if (!\is_string($nodeType)) {
            throw new \RuntimeException('Node type must be a string');
        }

        // Nodes cannot be created from their type name without reflection, which the compiled program
        // does not have.
        throw new \RuntimeException('Decoding nodes from JSON is not supported: ' . $nodeType);

        if (isset($value['attributes'])) {
            if (!\is_array($value['attributes'])) {
                throw new \RuntimeException('Attributes must be an array');
            }

            /** @var \PhpParser\NodeAttributes::AttributeArray $attributes */
            $attributes = $this->decodeArray($value['attributes']);
            $node->setAttributes($attributes);
        }

        foreach ($value as $name => $subNode) {
            if ($name === 'nodeType' || $name === 'attributes') {
                continue;
            }

            $node->setSubNode($name, $this->decodeRecursive($subNode));
        }

        return $node;
    }

    /** @param array<array-key, JsonInput> $value */
    private function decodeComment(array $value): Comment {
        if (!isset($value['text'])) {
            throw new \RuntimeException('Comment must have text');
        }

        if ($value['nodeType'] === 'Comment') {
            return new Comment(
                $value['text'],
                $value['line'] ?? -1, $value['filePos'] ?? -1, $value['tokenPos'] ?? -1,
                $value['endLine'] ?? -1, $value['endFilePos'] ?? -1, $value['endTokenPos'] ?? -1
            );
        }
        return new Comment\Doc(
            $value['text'],
            $value['line'] ?? -1, $value['filePos'] ?? -1, $value['tokenPos'] ?? -1,
            $value['endLine'] ?? -1, $value['endFilePos'] ?? -1, $value['endTokenPos'] ?? -1
        );
    }

}
