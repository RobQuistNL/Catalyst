<?php

namespace Catalyst\Tests\Service;

use Catalyst\Entity\CatalystEntity;
use Catalyst\Exception\PackageNotFoundException;
use Catalyst\Exception\UnresolveableDependenciesException;
use Catalyst\Model\Repository;
use Catalyst\Service\PackageService;

class PackageServiceTest extends \PHPUnit\Framework\TestCase
{
    /** @var PackageService */
    private $subject;

    protected function setUp() : void
    {
        $this->subject = new PackageService();
    }

    private function prepareRepository(Repository $repository)
    {
        $this->subject->clearRepositories();
        $this->subject->addRepository($repository);
    }

    public function testGetPackage()
    {
        // Clear
        $this->subject->clearRepositories();

        // Add testing one with missing packages to catch misses
        $this->subject->addRepository($this->getSimpleRepository());

        // Add a real one
        $this->subject->addRepository($this->getRealRepository());

        $package = $this->subject->getPackage('dukesoft/simple-project', '1.0.0');

        $this->assertInstanceOf(CatalystEntity::class, $package);
        $this->assertSame('dukesoft/simple-project', $package->name());
    }

    public function testGetPackageNotFound()
    {
        $this->expectException(PackageNotFoundException::class);
        $this->prepareRepository($this->getRealRepository());

        $this->subject->getPackage('dukesoft/test-package', '23.34.56');
    }

    public function testGetDependenciesNotFound()
    {
        $this->expectException(PackageNotFoundException::class);
        $this->prepareRepository($this->getRealRepository());

        $this->subject->getPackageDependencies('dukesoft/test-package', '23.34.56');
    }

    /**
     * @dataProvider provideSimpleResolveTestPackages
     */
    public function testSolveable($requirements, $expected)
    {
        $this->prepareRepository($this->getSimpleRepository());
        $mockProject = \Mockery::mock(CatalystEntity::class);

        $mockProject->shouldReceive('require')
            ->once()
            ->andReturn($requirements);

        $mockProject->shouldReceive('repositories')
            ->once()
            ->andReturn([]);

        $this->assertEquals($expected, $this->subject->solveDependencies($mockProject));
    }

    public static function provideSimpleResolveTestPackages()
    {
        return [
            'Patch version only' => [
                '$requirements' => ['dukesoft/test-package' => '~1.0.0'],
                '$expected' => ['dukesoft/test-package' => 'v1.0.2']
            ],
            'Latest miner release' => [
                '$requirements' => ['dukesoft/test-package' => '~1.0'],
                '$expected' => ['dukesoft/test-package' => '1.430.429']
            ],
            'Latest version' => [
                '$requirements' => ['dukesoft/test-package' => '*'],
                '$expected' => ['dukesoft/test-package' => '2.0.2']
            ],
            'latest major 1 version but under 1.3' => [
                '$requirements' => ['dukesoft/test-package' => '1.* < 1.3'],
                '$expected' => ['dukesoft/test-package' => 'v1.1.2']
            ],

            // Nested
            'Static references' => [
                '$requirements' => ['othervendor/test-package' => '1.0.1'],
                '$expected' => [
                    'othervendor/test-package' => '1.0.1',
                    'dukesoft/anotherpackage' => '0.1.0',
                ]
            ],
            'Loose constraints' => [
                '$requirements' => ['othervendor/test-package' => '*'],
                '$expected' => [
                    'othervendor/test-package' => 'v1.0.2',
                    'dukesoft/anotherpackage' => '1.5.1',
                ]
            ],

            //More nested
            'Static references 2 levels nesting' => [
                '$requirements' => ['othervendor/nesting-package' => '*'],
                '$expected' => [
                    'othervendor/nesting-package' => '1.3.2',
                    'othervendor/test-package' => 'v1.0.2',
                    'dukesoft/anotherpackage' => '1.5.1',
                ]
            ],
            'Constraining other packages' => [
                '$requirements' => ['othervendor/nesting-package' => '*', 'dukesoft/anotherpackage' => '~1.2.0'],
                '$expected' => [
                    'othervendor/nesting-package' => '1.3.2',
                    'dukesoft/anotherpackage' => '1.2.1',
                    'othervendor/test-package' => 'v1.0.2',
                ]
            ],
            'Other constraints' => [
                '$requirements' => ['othervendor/nesting-package' => '>=1', 'dukesoft/anotherpackage' => '<=1.4.0'],
                '$expected' => [
                    'othervendor/nesting-package' => '1.3.2',
                    'dukesoft/anotherpackage' => '1.2.1',
                    'othervendor/test-package' => 'v1.0.2',
                ]
            ],
            '4 layer constraints' => [
                '$requirements' => [
                    'othervendor/nesting-package' => '>=1',
                    'dukesoft/anotherpackage' => '<=1.4.0',
                    'othervendor/another-package' => '*',
                ],
                '$expected' => [
                    'othervendor/nesting-package' => '1.3.2',
                    'dukesoft/anotherpackage' => '1.2.0',
                    'othervendor/another-package' => '1.0.1',
                    'othervendor/test-package' => 'v1.0.2',
                ]
            ],
        ];
    }

    /**
     * @dataProvider provideNotSolveable
     */
    public function testNotSolveable($requirements, $expectedException, $expectedMessage)
    {
        $this->expectException($expectedException);
        $this->expectExceptionMessage($expectedMessage);
        $this->prepareRepository($this->getSimpleRepository());
        $mockProject = \Mockery::mock(CatalystEntity::class);

        $mockProject->shouldReceive('require')
            ->once()
            ->andReturn($requirements);

        $mockProject->shouldReceive('repositories')
            ->once()
            ->andReturn([]);

        $this->subject->solveDependencies($mockProject);
    }

    public static function provideNotSolveable()
    {
        return [
            'Package that doesnt exist' => [
                '$requirements' => ['othervendor/weird-package' => '*'],
                '$expectedException' => UnresolveableDependenciesException::class,
                '$expectedMessage' => 'can be found',
            ],
            'Impossible constraints' => [
                '$requirements' => [
                    'othervendor/package-requiring-latest-test' => '*',
                    'othervendor/package-requiring-early-test' => '*',
                ],
                '$expectedException' => UnresolveableDependenciesException::class,
                '$expectedMessage' => 'due to a dependency constraint',
            ],
        ];
    }

    public function testPackageExists()
    {
        $this->prepareRepository($this->getSimpleRepository());

        $this->assertTrue($this->subject->packageExists('dukesoft/test-package', '1.0.1'));
        $this->assertFalse($this->subject->packageExists('dukesoft/nope-package', '1.0.1'));
        $this->assertFalse($this->subject->packageExists('dukesoft/test-package', '1.0.1243'));
    }

    private function getRealRepository()
    {
        return new Repository(Repository::REPO_DIRECTORY, __DIR__ . '/../../projects');
    }

    private function getSimpleRepository()
    {
        $repository = new Repository(Repository::REPO_CATALYST, 'not-really');
        $repository->setAvailablePackages([
            'dukesoft/test-package' => [
                'source' => '',
                'versions' => [
                    'v1.0.0' => [],
                    '1.0.1' => [],
                    'v1.0.2' => [],
                    'v1.1.2' => [],
                    '1.430.429' => [],
                    '2.0.0' => [],
                    '2.0.2' => [],
                    'dev' => [],
                ],
            ],
            'othervendor/nesting-package' => [
                'source' => 'git@github.com:othervendor/nesting-package.git',
                'versions' => [
                    '1.1.1' => ['dukesoft/anotherpackage' => '0.1.0'],
                    '1.3.2' => ['othervendor/test-package' => '^1.0'],
                ]
            ],
            'othervendor/test-package' => [
                'source' => 'git@github.com:othervendor/test-package.git',
                'versions' => [
                    '1.0.1' => ['dukesoft/anotherpackage' => '0.1.0'],
                    'v1.0.2' => ['dukesoft/anotherpackage' => '^1.0'],
                ]
            ],
            'dukesoft/anotherpackage' => [
                'source' => 'git@github.com:dukesoft/anotherpackage.git',
                'versions' => [
                    '0.0.1' => [],
                    '0.1.0' => [],
                    '1.0.0' => [],
                    '1.2.0' => [],
                    '1.2.1' => [],
                    '1.5.1' => [],
                    '2.0.0' => [],
                ]
            ],
            'othervendor/another-package' => [
                'source' => 'git@github.com:othervendor/test-package.git',
                'versions' => [
                    '1.0.1' => ['dukesoft/anotherpackage' => '<1.2.1'],
                ]
            ],
            'othervendor/package-requiring-latest-test' => [
                'source' => 'git@github.com:othervendor/test-package.git',
                'versions' => [
                    '1.0.0' => ['dukesoft/test-package' => '>=2'],
                ]
            ],
            'othervendor/package-requiring-early-test' => [
                'source' => 'git@github.com:othervendor/test-package.git',
                'versions' => [
                    '1.0.0' => ['dukesoft/test-package' => '1.0.1'],
                ]
            ],
        ]);

        return $repository;
    }
}