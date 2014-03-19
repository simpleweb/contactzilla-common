<?php

namespace Contactzilla\Common\Monolog;

use Monolog\Logger as BaseLogger,
    Contactzilla\Common\Monolog\Traits\RequestLoggerTrait;

class Logger extends BaseLogger
{
    use RequestLoggerTrait;
}
