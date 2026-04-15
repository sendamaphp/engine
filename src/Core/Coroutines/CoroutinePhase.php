<?php

namespace Sendama\Engine\Core\Coroutines;

enum CoroutinePhase: string
{
    case Update = 'update';
    case FixedUpdate = 'fixed_update';
}
