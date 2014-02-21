<?php

namespace Contactzilla\Common\Monolog\Processor;

/**
 * An abstract monolog processor for standardising the logging
 * of various HTTP requests and responses
 *
 * @author Steve Lacey <steve@simpleweb.co.uk>
 */
abstract class AbstractRequestProcessor
{
    const MESSAGE_FORMAT = '%s %s %s %d %s';

    public function __invoke(array $record)
    {
        if (isset($record['context'], $record['context']['request'], $record['context']['response'])) {
            $request = $record['context']['request'];
            $response = $record['context']['response'];
        }

        if (isset($request, $response) && is_a($request, $this::REQUEST_CLASS) && is_a($response, $this::RESPONSE_CLASS)) {
            $record['message'] = call_user_func_array('sprintf', array_merge([$this::MESSAGE_FORMAT], $this->message($request, $response)));

            $record['extra'] = array_merge($record['extra'], $this->extra($request, $response));

            unset($record['context']['request'], $record['context']['response']);
        }

        return $record;
    }
}
