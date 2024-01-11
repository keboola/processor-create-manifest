<?php

declare(strict_types=1);

namespace Keboola\Processor\CreateManifest;

use Keboola\Component\Config\BaseConfig;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Config extends BaseConfig
{
    /** @var array */
    private $rawConfig;

    public function __construct(
        array $config,
        ?ConfigurationInterface $configDefinition = null
    ) {
        $this->rawConfig = $config;
        parent::__construct($config, $configDefinition);
    }

    public function getRawConfig(): array
    {
        return $this->rawConfig;
    }
}
