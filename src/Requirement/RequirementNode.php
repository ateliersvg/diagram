<?php

declare(strict_types=1);

namespace Atelier\Diagram\Requirement;

use Atelier\Diagram\Exception\InvalidArgumentException;

final readonly class RequirementNode
{
    /**
     * @param array<string, string> $fields
     */
    public function __construct(
        public string $id,
        public RequirementNodeKind $kind,
        public array $fields = [],
    ) {
        if ('' === trim($id)) {
            throw new InvalidArgumentException('Requirement diagram node id must be non-empty.');
        }

        foreach ($fields as $name => $value) {
            if ('' === trim((string) $name)) {
                throw new InvalidArgumentException(\sprintf('Requirement diagram node "%s" contains an empty field name.', $id));
            }
            if ('' === trim($value)) {
                throw new InvalidArgumentException(\sprintf('Requirement diagram node "%s" field "%s" must be non-empty.', $id, $name));
            }
        }
    }
}
