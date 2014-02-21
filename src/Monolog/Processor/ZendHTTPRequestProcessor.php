<?php

namespace Contactzilla\Common\Monolog\Processor;

use Contactzilla,
    Zend_HTTP_Response;

/**
 * A monolog processor for processing Contactzilla\Zend\HTTP\Client requests
 *
 * @author Steve Lacey <steve@simpleweb.co.uk>
 */
class ZendHTTPRequestProcessor extends AbstractRequestProcessor
{
    /**
     * Zend_Http_Client is kind of request like, except that it doesn't
     * give visibility to the method or headers... useful
     */
    const REQUEST_CLASS = 'Contactzilla\Zend\HTTP\Client';
    const RESPONSE_CLASS = 'Zend_HTTP_Response';

    /**
     * A set of params we'll need to build the message
     *
     * @see AbstractRequestProcessor::process
     *
     * @return array
     */
    protected function message(Contactzilla\Zend\HTTP\Client $request, Zend_HTTP_Response $response)
    {
        return [
            $request->getMethod(),
            $request->getUrl(),
            'HTTP/1.1',
            $response->getStatus(),
            $response->getHeader('content-length') ?: '-'
        ];
    }

    /**
     * Any extra data we want to pass to the monolog
     *
     * @return array
     */
    protected function extra(Contactzilla\Zend\HTTP\Client $request, Zend_HTTP_Response $response)
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
