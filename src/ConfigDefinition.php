<?php

namespace Keboola\Processor\CreateManifest;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class ConfigDefinition implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root("parameters");

        $rootNode
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
            ->end()
        ;
        return $treeBuilder;
    }
}
