<?php

namespace Contactzilla\Common\Monolog\Processor;

use Sabre;

/**
 * A monolog processor for processing Sabre\HTTP\Requests
 *
 * @author Steve Lacey <steve@simpleweb.co.uk>
 */
class SabreHTTPRequestProcessor extends AbstractRequestProcessor
{
    const REQUEST_CLASS = 'Sabre\HTTP\Request';
    const RESPONSE_CLASS = 'Sabre\HTTP\Response';

    /**
     * A set of params we'll need to build the message
     *
     * @see AbstractRequestProcessor::process
     *
     * @return array
     */
    protected function message(Sabre\HTTP\Request $request, Sabre\HTTP\Response $response)
    {
        return [
            $request->getMethod(),
            $request->getUrl(),
            'HTTP/' . $request->getHTTPVersion(),
            $response->getStatus(),
            $response->getHeader('content-length') ?: '-'
        ];
    }

    /**
     * Any extra data we want to pass to the monolog
     *
     * @return array
     */
    protected function extra(Sabre\HTTP\Request $request, Sabre\HTTP\Response $response)
    {
        return [
            'request' => $request->getBody(),
            'request_content_length' => $request->getHeader('content-length') ?: '-',
            'request_headers' => $request->getHeaders(),
            'request_method' => $request->getMethod(),
            'request_url' => $request->getUrl(),

            'response' => $response->getBody(),
            'response_content_length' => $response->getHeader('content-length') ?: '-',
            'response_headers' => $response->getHeaders(),
            'response_status' => $response->getStatus()
        ];
    }
}
