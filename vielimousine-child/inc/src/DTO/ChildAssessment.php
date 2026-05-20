<?php
declare(strict_types=1);

namespace Vie\DTO;

final readonly class ChildAssessment
{
    public function __construct(
        public int  $age,
        public bool $isFree,
        public bool $treatedAsAdult,
        public int  $childIndex = 0,
    ) {
    }

    public function toArray(): array
    {
        return [
            'age'              => $this->age,
            'is_free'          => $this->isFree,
            'treated_as_adult' => $this->treatedAsAdult,
            'child_index'      => $this->childIndex,
        ];
    }
}
