<?php

declare(strict_types=1);

namespace Rapidez\RapidezCacheTriggersPoc\Tests\Sources;

use PHPUnit\Framework\Attributes\Test;
use Rapidez\RapidezCacheTriggersPoc\Sources\AttributeOptionSource;
use Rapidez\RapidezCacheTriggersPoc\Sources\AttributeSource;
use Rapidez\RapidezCacheTriggersPoc\Tests\TestCase;

final class AttributeSourceTest extends TestCase
{
    #[Test]
    public function it_misses_changes_when_an_attribute_changed(): void
    {
        $source = new AttributeSource;

        $this->assertFalse($source->hasMissedChanges('1,2,3,4', '1,2,3,4'));
        $this->assertTrue($source->hasMissedChanges('1,2,3,4', '1,2,3,5'));
        $this->assertTrue($source->getChanged('1,2,3,4', '1,2,3,4')->isEmpty());
    }

    #[Test]
    public function it_misses_changes_when_an_option_was_deleted(): void
    {
        $source = new AttributeOptionSource;

        $this->assertFalse($source->hasMissedChanges(['hashes' => [1 => 100, 2 => 200]], ['hashes' => [1 => 100, 2 => 201, 3 => 300]]));
        $this->assertTrue($source->hasMissedChanges(['hashes' => [1 => 100, 2 => 200]], ['hashes' => [1 => 100]]));
        $this->assertEquals(['products' => []], $source->getChanged(['hashes' => [1 => 100]], ['hashes' => [1 => 100]])->toArray());
    }
}
