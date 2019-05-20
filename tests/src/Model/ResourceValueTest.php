<?php
namespace Catalyst\Tests\Model;

use Catalyst\Model\YoYo\ResourceValue;
use Catalyst\Service\JsonService;

class ResourceValueTest extends \PHPUnit\Framework\TestCase
{
    private $jsonString = <<<EOL
{
    "id": "d4700bac-6283-45d1-899f-16cccf665418",
    "resourcePath": "views\\\\0bb36c74-dc11-4a76-8ff5-0d89046b21bf.yy",
    "resourceType": "GMFolder"
}
EOL;

    private $subject;

    protected function setUp(): void
    {
        $this->subject = ResourceValue::createFromObject(JsonService::decode($this->jsonString));
    }

    public function testGetJsonReturnsSameAdValuesCorrect()
    {
        $this->assertEquals(
            $this->jsonString,
            JsonService::encode($this->subject)
        );

        $this->assertEquals(
            JsonService::decode($this->jsonString),
            $this->subject->jsonSerialize()
        );
    }

    public function testValues()
    {
        $this->assertSame('d4700bac-6283-45d1-899f-16cccf665418', (string) $this->subject->id());
        $this->assertSame('GMFolder', (string) $this->subject->resourceType());
        $this->assertSame(
            'views\\0bb36c74-dc11-4a76-8ff5-0d89046b21bf.yy',
            (string) $this->subject->resourcePath()
        );
    }
}