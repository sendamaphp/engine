<?php

namespace Sendama\Engine\Core\Coroutines;

interface CoroutineYieldInstructionInterface
{
    public function schedule(CoroutineContext $context): CoroutineYieldInstructionInterface;

    public function isReady(CoroutineContext $context): bool;
}
