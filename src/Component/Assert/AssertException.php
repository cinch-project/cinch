<?php

namespace Cinch\Component\Assert;

use InvalidArgumentException;

class AssertException extends InvalidArgumentException
{
    /**
     * @param AssertException[] $errors
     * @return static
     */
    public static function fromErrors(array $errors): static
    {
        $message = sprintf("The following %d assertion(s) failed\n", count($errors));

        for ($i = 1, $count = count($errors); $i <= $count; $i++)
            $message .= "$i. {$errors[$i - 1]->getMessage()}\n";

        return new static($message, $errors);
    }

    /**
     * @param string $message
     * @param AssertException[] $errors
     */
    public function __construct(string $message, private readonly array $errors = [])
    {
        parent::__construct($message);
    }

    /**
     * @return AssertException[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
