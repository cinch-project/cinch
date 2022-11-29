<?php

namespace Cinch\Component\Assert;

class CinchAssertFactory implements AssertFactory
{
    public function createException(string $message): AssertException
    {
        return new AssertException($message);
    }

    public function createExceptionFromErrors(array $errors): AssertException
    {
        return AssertException::fromErrors($errors);
    }

    public function createChain(mixed $value, string $message = ''): AssertChain
    {
        return new AssertChain(Assert::class, $value, $message);
    }

    public function createMany(): AssertMany
    {
        return new AssertMany($this);
    }
}