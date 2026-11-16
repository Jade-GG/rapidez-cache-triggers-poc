<?php

namespace Rapidez\RapidezCacheTriggersPoc\Exceptions;

use Exception;

class SourceUnavailableException extends Exception
{
    public static function forSource(string $key): static
    {
        return new static("Source \"{$key}\" is unavailable, its changes can't be detected (e.g. its indexer isn't set to \"Update by Schedule\" anymore).");
    }
}
