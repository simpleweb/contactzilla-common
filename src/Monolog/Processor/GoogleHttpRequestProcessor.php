<?php

namespace Contactzilla\Common\Monolog\Processor;

use Google_Http_Request;

/**
 * A monolog processor for processing Google_HTTP_Requests
 *
 * @author Steve Lacey <steve@simpleweb.co.uk>
 */
class GoogleHttpRequestProcessor extends AbstractRequestProcessor
{
    const REQUEST_CLASS = 'Google_Http_Request';
    const RESPONSE_CLASS = 'Google_Http_Request'; // yeah, Google are smart

    /**
     * A set of params we'll need to build the message
     *
     * @see AbstractRequestProcessor::process
     *
     * @return array
     */
    protected function message(Google_Http_Request $request, Google_Http_Request $response)
    {
        return [
            $request->getRequestMethod(),
            $request->getUrl(),
            'HTTP/1.1',
            $response->getResponseHttpCode(),
            $response->getResponseHeader('content-length') ?: '-'
        ];
    }

    /**
     * Any extra data we want to pass to the monolog
     *
     * @return array
     */
    protected function extra(Google_Http_Request $request, Google_Http_Request $response)
    {
        return [
            'request' => $request->getPostBody(),
            'request_content_length' => $request->getRequestHeader('content-length') ?: '-',
            'request_headers' => $request->getRequestHeaders(),
            'request_method' => $request->getRequestMethod(),
            'request_url' => $request->getUrl(),

            'response' => $response->getResponseBody(),
            'response_content_length' => $response->getResponseHeader('content-length') ?: '-',
            'response_headers' => $response->getResponseHeaders(),
            'response_status' => $response->getResponseHttpCode()
        ];
    }
}
