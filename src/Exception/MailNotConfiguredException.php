<?php

namespace App\Exception;

/**
 * Thrown when MAILER_DSN is null or otherwise disabled, so no real email can be sent.
 */
class MailNotConfiguredException extends \RuntimeException
{
}
