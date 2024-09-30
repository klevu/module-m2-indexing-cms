<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCms\Test\Integration\Console\Command;

use Klevu\Indexing\Console\Command\ConfigurationOverridesRegenerateCommand;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Magento\Framework\App\Filesystem\DirectoryList as AppDirectoryList;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Io\File as FileIo;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @covers \Klevu\Indexing\Console\Command\ConfigurationOverridesRegenerateCommand::class
 * @method ConfigurationOverridesRegenerateCommand instantiateTestObject(?array $arguments = null)
 */
class ConfigurationOverridesRegenerateCommandTest extends TestCase
{
    use ObjectInstantiationTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line Used by traits
    /**
     * @var DirectoryList|null
     */
    private ?DirectoryList $directoryList = null;
    /**
     * @var FileIo|null
     */
    private ?FileIo $fileIo = null;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->objectManager = ObjectManager::getInstance();

        $this->implementationFqcn = ConfigurationOverridesRegenerateCommand::class;
        // newrelic-describe-commands globs onto Console commands
        $this->expectPlugins = true;

        $this->directoryList = $this->objectManager->get(DirectoryList::class);
        $this->fileIo = $this->objectManager->get(FileIo::class);
    }

    public function testExecute_RegeneratesExpectedFiles(): void
    {
        $baseOverridesDirectory = implode(
            separator: DIRECTORY_SEPARATOR,
            array: [
                $this->directoryList->getPath(AppDirectoryList::VAR_DIR),
                'klevu',
                'indexing',
                'pipeline',
                'cms',
            ],
        );

        $expectedFiles = [
            $baseOverridesDirectory . DIRECTORY_SEPARATOR . 'add_update.overrides.yml',
            $baseOverridesDirectory . DIRECTORY_SEPARATOR . 'delete.overrides.yml',
        ];
        foreach ($expectedFiles as $expectedFile) {
            if ($this->fileIo->fileExists($expectedFile)) {
                $this->fileIo->rm($expectedFile);
            }
            $this->assertFileDoesNotExist($expectedFile);
        }

        $configurationOverridesRegenerateCommand = $this->instantiateTestObject();

        $tester = new CommandTester(
            command: $configurationOverridesRegenerateCommand,
        );
        $responseCode = $tester->execute(
            input: [
                '--entity-type' => [
                    'KLEVU_CMS',
                ],
            ],
        );

        $this->assertSame(0, $responseCode);

        foreach ($expectedFiles as $expectedFile) {
            $this->assertFileExists($expectedFile);
            $this->assertNotEmpty(
                actual: $this->fileIo->read($expectedFile),
            );
        }
    }
}
