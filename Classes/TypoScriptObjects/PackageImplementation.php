<?php
namespace Neos\MarketPlace\TypoScriptObjects;

/*
 * This file is part of the Neos.MarketPlace package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use TYPO3\Flow\Annotations as Flow;
use Packagist\Api\Client;
use Packagist\Api\Result\Package;
use TYPO3\Flow\Log\SystemLoggerInterface;
use TYPO3\TypoScript\TypoScriptObjects\AbstractArrayTypoScriptObject;

/**
 * Package TypoScript Implementation
 *
 * @api
 */
class PackageImplementation extends AbstractArrayTypoScriptObject
{
    /**
     * @var SystemLoggerInterface
     * @Flow\Inject
     */
    protected $systemLogger;

    /**
     * @return string
     */
    public function getName()
    {
        return $this->tsValue('name');
    }

    /**
     * Evaluate this TypoScript object and return the result
     *
     * @return Package|null
     */
    public function evaluate()
    {
        try {
            $client = new Client();
            return $client->get($this->getName());
        } catch (\Exception $exception) {
            $this->systemLogger->logException($exception);
            return null;
        }
    }
}
