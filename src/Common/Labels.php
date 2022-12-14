<?php

namespace Cinch\Common;

use Cinch\Component\Assert\Assert;

class Labels
{
    /** @var string[] */
    private array $values = [];

    /**
     * @param string[] $values
     */
    public function __construct(array $values = [])
    {
        foreach ($values as $v)
            $this->add($v);
    }

    public function add(string $value): self
    {
        /* allow any unicode letter or number and basic separators: space, hyphen, underscore, period, slash */
        Assert::regex(mb_strtolower($value, 'UTF-8'), '~^[\-_./ \p{L}\p{N}]{1,64}$~u', 'label');

        if (!$this->has($value))
            $this->values[] = $value;

        return $this;
    }

    public function has(string $value): bool
    {
        $value = mb_strtolower($value, 'UTF-8');

        foreach ($this->values as $v)
            if (mb_strtolower($v, 'UTF-8') == $value)
                return true;

        return false;
    }

    /**
     * @return string[]
     */
    public function all(): array
    {
        return $this->values;
    }

    public function snapshot(): string|null
    {
        return $this->values ? implode(',', $this->values) : null;
    }

    public static function restore(string|null $data): Labels
    {
        return new Labels($data ? preg_split('~,~', $data, flags: PREG_SPLIT_NO_EMPTY) : []);
    }
}