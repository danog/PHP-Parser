<?php declare(strict_types=1);

namespace PhpParser;

class JsonDecoder {
    /** @return mixed */
    public function decode(string $json) {
        $value = json_decode($json, true);
        if (json_last_error()) {
            throw new \RuntimeException('JSON decoding error: ' . json_last_error_msg());
        }

        return $this->decodeRecursive($value);
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function decodeRecursive($value) {
        if (\is_array($value)) {
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

    private function decodeArray(array $array): array {
        $decodedArray = [];
        foreach ($array as $key => $value) {
            $decodedArray[$key] = $this->decodeRecursive($value);
        }
        return $decodedArray;
    }

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

            $node->setAttributes($this->decodeArray($value['attributes']));
        }

        foreach ($value as $name => $subNode) {
            if ($name === 'nodeType' || $name === 'attributes') {
                continue;
            }

            $node->setSubNode($name, $this->decodeRecursive($subNode));
        }

        return $node;
    }

    private function decodeComment(array $value): Comment {
        $className = $value['nodeType'] === 'Comment' ? Comment::class : Comment\Doc::class;
        if (!isset($value['text'])) {
            throw new \RuntimeException('Comment must have text');
        }

        return new $className(
            $value['text'],
            $value['line'] ?? -1, $value['filePos'] ?? -1, $value['tokenPos'] ?? -1,
            $value['endLine'] ?? -1, $value['endFilePos'] ?? -1, $value['endTokenPos'] ?? -1
        );
    }

}
