<?php

namespace App\Services\AI;

class AiProviderException extends \RuntimeException
{
    /** codes: not_configured, auth_failed, rate_limited, provider_unavailable, bad_request, invalid_output, budget_exceeded */
    public function __construct(public string $errorCode, string $message = '', public bool $retryable = false)
    {
        parent::__construct($message ?: $errorCode);
    }
}
