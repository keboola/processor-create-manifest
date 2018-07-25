<?php

declare(strict_types=1);

namespace Keboola\Processor\CreateManifest;

use Keboola\Component\Config\BaseConfigDefinition;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class ConfigDefinition extends BaseConfigDefinition
{
    /**
     * Definition of parameters section. Override in extending class to validate parameters sent to the component early.
     */
    protected function getParametersDefinition(): ArrayNodeDefinition
    {
        $builder = new TreeBuilder();
        /** @var ArrayNodeDefinition $parametersNode */
        $parametersNode = $builder->root('parameters');

        // @formatter:off
        /** @noinspection NullPointerExceptionInspection */
        $parametersNode
            ->children()
                ->scalarNode("delimiter")
                    ->defaultValue(",")
                ->end()
                ->scalarNode("enclosure")
                    ->defaultValue("\"")
                ->end()
                ->arrayNode("columns")
                    ->scalarPrototype()
                    ->end()
                ->end()
                ->enumNode("columns_from")
                    ->values(["header", "auto"])
                ->end()
                ->arrayNode("primary_key")
                    ->scalarPrototype()
                    ->end()
                ->end()
                ->booleanNode("incremental")
                    ->defaultFalse()
                ->end()
            ->end();
        // @formatter:on
        return $parametersNode;
    }

    /**
     * Root definition to be overridden in special cases
     */
    protected function getRootDefinition(TreeBuilder $treeBuilder): ArrayNodeDefinition
    {
        $rootNode = parent::getRootDefinition($treeBuilder);
        // @formatter:off
        $rootNode
            // to ensure that parameters default values are generated even if the key is missing
            ->beforeNormalization()
                ->always(function ($v) {
                    if (!isset($v['parameters'])) {
                        $v['parameters'] = [];
                    }
                    return $v;
                })
            ->end();
        // @formatter:on

        return $rootNode;
    }
}
