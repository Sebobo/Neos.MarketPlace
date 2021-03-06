<?php
namespace Neos\MarketPlace\Command;

/*
 * This file is part of the Neos.MarketPlace package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\MarketPlace\Domain\Model\LogAction;
use Neos\MarketPlace\Domain\Model\Packages;
use Neos\MarketPlace\Domain\Model\Storage;
use Neos\MarketPlace\Service\PackageImporterInterface;
use Packagist\Api\Client;
use Packagist\Api\Result\Package;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Cli\CommandController;
use TYPO3\Flow\Log\SystemLoggerInterface;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;
use TYPO3\TYPO3CR\Search\Indexer\NodeIndexingManager;

/**
 * MarketPlace Command Controller
 */
class MarketPlaceCommandController extends CommandController
{
    /**
     * @var PackageImporterInterface
     * @Flow\Inject
     */
    protected $importer;

    /**
     * @var NodeIndexingManager
     * @Flow\Inject
     */
    protected $nodeIndexingManager;

    /**
     * @var SystemLoggerInterface
     * @Flow\Inject
     */
    protected $logger;

    /**
     * @param string $package
     * @param boolean $disableIndexing
     * @return void
     */
    public function syncCommand($package = null, $disableIndexing = false)
    {
        $beginTime = microtime(true);

        $sync = function () use ($package, $beginTime) {
            $hasError = false;
            $elapsedTime = function ($timer = null) use ($beginTime) {
                return microtime(true) - ($timer ?: $beginTime);
            };
            $count = 0;
            $this->outputLine();
            $this->outputLine('Synchronize with Packagist ...');
            $this->outputLine('------------------------------');
            $storage = new Storage();
            $process = function (Package $package) use ($storage, &$count) {
                $count++;
                $this->outputLine(sprintf('  %d/ %s (%s)', $count, $package->getName(), $package->getTime()));
                $this->importer->process($package, $storage);
            };
            if ($package === null) {
                $this->logger->log(sprintf('action=%s', LogAction::FULL_SYNC_STARTED), LOG_INFO);
                $packages = new Packages();
                foreach ($packages->packages() as $package) {
                    $this->logger->log(sprintf('action=%s package=%s', LogAction::SINGLE_PACKAGE_SYNC_STARTED, $package->getName()), LOG_INFO);
                    $timer = microtime(true);
                    try {
                        $process($package);
                        $this->logger->log(sprintf('action=%s package=%s duration=%f', LogAction::SINGLE_PACKAGE_SYNC_FINISHED, $package->getName(), $elapsedTime($timer)), LOG_INFO);
                    } catch (\Exception $exception) {
                        $this->logger->log(sprintf('action=%s package=%s duration=%f', LogAction::SINGLE_PACKAGE_SYNC_FAILED, $package->getName(), $elapsedTime($timer)), LOG_ERR);
                        $this->logger->logException($exception);
                        $hasError = true;
                    }
                }
                $this->cleanupPackages($storage);
                $this->cleanupVendors($storage);
                $this->logger->log(sprintf('action=%s duration=%f', LogAction::FULL_SYNC_FINISHED, $elapsedTime()), LOG_INFO);
            } else {
                $packageKey = $package;
                $this->logger->log(sprintf('action=%s package=%s', LogAction::SINGLE_PACKAGE_SYNC_STARTED, $package), LOG_INFO);
                try {
                    $client = new Client();
                    $package = $client->get($package);
                    $process($package);
                    $this->logger->log(sprintf('action=%s package=%s duration=%f', LogAction::SINGLE_PACKAGE_SYNC_FINISHED, $packageKey, $elapsedTime()), LOG_INFO);
                } catch (\Exception $exception) {
                    $this->logger->log(sprintf('action=%s package=%s duration=%f', LogAction::SINGLE_PACKAGE_SYNC_FAILED, $packageKey, $elapsedTime()), LOG_ERR);
                    $this->logger->logException($exception);
                    $hasError = true;
                }
            }

            $this->outputLine();
            $this->outputLine(sprintf('%d package(s) imported with success', $this->importer->getProcessedPackagesCount()));

            if ($hasError) {
                $this->outputLine();
                $this->outputLine('Check your log, we have some trouble to sync some pages ...');
            }

            $this->outputLine();
            $this->outputLine(sprintf('Duration: %f seconds', $elapsedTime()));
        };


        if ($disableIndexing === true) {
            $this->nodeIndexingManager->withoutIndexing($sync);
        } else {
            $sync();
        }
    }

    /**
     * @param Storage $storage
     */
    protected function cleanupPackages(Storage $storage)
    {
        $this->outputLine();
        $this->outputLine('Cleanup packages ...');
        $this->outputLine('--------------------');
        $count = $this->importer->cleanupPackages($storage, function (NodeInterface $package) {
            $this->outputLine(sprintf('%s deleted', $package->getLabel()));
        });
        if ($count > 0) {
            $this->outputLine(sprintf('  Deleted %d package(s)', $count));
        }
    }

    /**
     * @param Storage $storage
     */
    protected function cleanupVendors(Storage $storage)
    {
        $this->outputLine();
        $this->outputLine('Cleanup vendors ...');
        $this->outputLine('-------------------');
        $count = $this->importer->cleanupVendors($storage, function (NodeInterface $vendor) {
            $this->outputLine(sprintf('%s deleted', $vendor->getLabel()));
        });
        if ($count > 0) {
            $this->outputLine(sprintf('  Deleted %d vendor(s)', $count));
        }
    }
}
