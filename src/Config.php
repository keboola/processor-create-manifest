<?php

declare(strict_types=1);

namespace Keboola\Processor\CreateManifest;

use Keboola\Component\Config\BaseConfig;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Config extends BaseConfig
{
    /** @var array<string, array<string, string>> */
    private array $rawConfig;

    /**
     * @param array<string, array<string, string>> $config
     */
    public function __construct(
        array $config,
        ?ConfigurationInterface $configDefinition = null,
    ) {
        $this->rawConfig = $config;
        parent::__construct($config, $configDefinition);
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function getRawConfig(): array
    {
        return $this->rawConfig;
    }
}
