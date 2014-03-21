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
        $record['extra']['stack_trace'] = implode(
            ', ',
            array_map(
                function ($strace) {
                    return $strace['class'] . $strace['type'] . $strace['function'];
                },
                array_filter(
                    array_slice(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 50), 5),
                    function ($strace) {
                        return isset($strace['class']) && strpos($strace['class'], 'Contactzilla') === 0;
                    }
                )
            )
        );

        if (!$record['extra']['stack_trace']) {
            unset($record['extra']['stack_trace']);
        };

        return $record;
    }
}
