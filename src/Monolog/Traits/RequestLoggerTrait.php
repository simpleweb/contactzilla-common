<?php

namespace Contactzilla\Common\Monolog\Traits;

trait RequestLoggerTrait
{
    public function addHttpTransaction($request, $response)
    {
        return $this->addDebug(null, [
            'request' => $request,
            'response' => $response
        ]);
    }
}
