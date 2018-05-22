<?php

declare(strict_types=1);

namespace Keboola\Processor\CreateManifest;

use Keboola\Component\Config\BaseConfigDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class ConfigDefinition extends BaseConfigDefinition
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root("config");
        // @formatter:off
        /** @noinspection NullPointerExceptionInspection */
        $rootNode
            // to ensure that parameters default values are generated even if the key is missing
            ->beforeNormalization()
                ->always(function ($v) {
                    if (!isset($v['parameters'])) {
                        $v['parameters'] = [];
                    }
                    return $v;
                })
            ->end()
            ->children()
                ->arrayNode('parameters')
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
                ->end()
            ->end()
        ;
        // @formatter:on
        return $treeBuilder;
    }
}
