<?php

namespace Rapidez\RapidezCacheTriggersPoc\Models\Changelogs;

use Rapidez\RapidezCacheTriggersPoc\Models\Changelog;

class FulltextChangelog extends Changelog
{
    protected string $view = 'catalogsearch_fulltext';
}
