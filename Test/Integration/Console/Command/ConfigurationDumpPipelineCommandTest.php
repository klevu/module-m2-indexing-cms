<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\IndexingCms\Test\Integration\Console\Command;

use Klevu\Indexing\Console\Command\ConfigurationDumpPipelineCommand;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Yaml;

/**
 * @covers \Klevu\Indexing\Console\Command\ConfigurationDumpPipelineCommandTest::class
 * @method ConfigurationDumpPipelineCommand instantiateTestObject(?array $arguments = null)
 */
class ConfigurationDumpPipelineCommandTest extends TestCase
{
    use ObjectInstantiationTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line Used by traits

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->objectManager = ObjectManager::getInstance();

        $this->implementationFqcn = ConfigurationDumpPipelineCommand::class;
        // newrelic-describe-commands globs onto Console commands
        $this->expectPlugins = true;
    }

    /**
     * @testWith ["KLEVU_CMS::add"]
     *           ["KLEVU_CMS::update"]
     *           ["KLEVU_CMS::delete"]
     *
     * @param string $pipelineIdentifier
     *
     * @return void
     */
    public function testExecute_ContainsExpectedPipelines(
        string $pipelineIdentifier,
    ): void {
        $configurationDumpPipelineCommand = $this->instantiateTestObject();

        $tester = new CommandTester(
            command: $configurationDumpPipelineCommand,
        );
        $responseCode = $tester->execute(
            input: [
                'pipelineIdentifier' => $pipelineIdentifier,
            ],
        );

        $this->assertSame(0, $responseCode);

        $output = $tester->getDisplay();

        $config = Yaml::parse(
            input: $output,
        );
        $this->assertIsArray($config);
        $this->assertNotEmpty($config);
    }
}
