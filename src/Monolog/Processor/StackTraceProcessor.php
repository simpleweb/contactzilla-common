<?php

namespace Contactzilla\Common\Monolog\Processor;

/**
 * A monolog processor for adding in a stack trace to the extras
 *
 * @author Steve Lacey <steve@simpleweb.co.uk>
 */
class StackTraceProcessor
{
    public function __invoke(array $record)
    {
        $record['extra']['stack_trace'] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);

        return $record;
    }
}

