<?php

namespace GiveTwice\ProductInfoFetcher\Enum;

enum FetchOutcome: string
{
    case Success = 'success';
    case Incomplete = 'incomplete';
    case Blocked = 'blocked';
    case Consent = 'consent';
    case Removed = 'removed';
    case Error = 'error';
}
