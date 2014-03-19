<?php

namespace Contactzilla\Common\Symfony\Bridge\Monolog;

use Symfony\Bridge\Monolog\Logger as BaseLogger,
    Contactzilla\Common\Monolog\Traits\RequestLoggerTrait;

class Logger extends BaseLogger
{
    use RequestLoggerTrait;
}
