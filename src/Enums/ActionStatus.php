<?php

declare(strict_types=1);

namespace Karnoweb\Calendar\Enums;

enum ActionStatus: string
{
    case Success = 'SUCCESS';
    case ValidationError = 'VALIDATION_ERROR';
    case Forbidden = 'FORBIDDEN';
    case NotFound = 'NOT_FOUND';
    case Conflict = 'CONFLICT';
    case ReadOnly = 'READ_ONLY';
    case Unsupported = 'UNSUPPORTED';
    case DomainRejected = 'DOMAIN_REJECTED';
    case RateLimited = 'RATE_LIMITED';
    case RetryableError = 'RETRYABLE_ERROR';
    case UnknownOutcome = 'UNKNOWN_OUTCOME';
}
