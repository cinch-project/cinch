<?php

namespace Cinch\Component\Assert;

interface AssertFactory
{
    /** Creates an AssertException.
     * @param string $message
     * @return AssertException
     */
    public function createException(string $message): AssertException;

    /** Creates an AssertException from an array of exceptions.
     * @note used by AssertMany
     * @param AssertException[] $errors
     * @return AssertException
     */
    public function createExceptionFromErrors(array $errors): AssertException;

    /** Creates an AssertChain.
     * @param mixed $value value to assert
     * @param string $message optional message providing context
     * @return AssertChain
     */
    public function createChain(mixed $value, string $message = ''): AssertChain;

    /** Creates an AssertMany.
     * @return AssertMany
     */
    public function createMany(): AssertMany;
}
