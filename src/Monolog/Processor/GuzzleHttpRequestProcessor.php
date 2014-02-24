<?php

namespace Contactzilla\Common\Monolog\Processor;

use Guzzle;

/**
 * A monolog processor for processing Guzzle\HTTP\Message\Requests
 *
 * @author Steve Lacey <steve@simpleweb.co.uk>
 */
class GuzzleHttpRequestProcessor extends AbstractRequestProcessor
{
    const REQUEST_CLASS = 'Guzzle\HTTP\Message\Request';
    const RESPONSE_CLASS = 'Guzzle\HTTP\Message\Response';

    /**
     * A set of params we'll need to build the message
     *
     * @see AbstractRequestProcessor::process
     *
     * @return array
     */
    protected function message(Guzzle\HTTP\Message\Request $request, Guzzle\HTTP\Message\Response $response)
    {
        return [
            $request->getMethod(),
            $request->getUrl(),
            'HTTP/' . $request->getProtocolVersion(),
            $response->getStatusCode(),
            $response->getHeader('content-length') ?: '-'
        ];
    }

    /**
     * Any extra data we want to pass to the monolog
     *
     * @return array
     */
    protected function extra(Guzzle\HTTP\Message\Request $request, Guzzle\HTTP\Message\Response $response)
    {
        return [
            'request' => $request instanceOf Guzzle\HTTP\Message\EntityEnclosingRequestInterface ? $request->getPostFields()->toArray() ?: $request->getBody() : null,
            'request_content_length' => $request->getHeader('content-length') ?: '-',
            'request_headers' => $request->getHeaders(),
            'request_method' => $request->getMethod(),
            'request_url' => $request->getUrl(),

            'response' => $response->getBody(true),
            'response_content_length' => $response->getHeader('content-length') ?: '-',
            'response_headers' => $response->getHeaders(),
            'response_status' => $response->getStatusCode()
        ];
    }
}
