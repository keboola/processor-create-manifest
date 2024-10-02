<?php

declare(strict_types=1);

namespace Keboola\Processor\CreateManifest;

use Keboola\Component\BaseComponent;
use Keboola\Component\Manifest\ManifestManager\Options\OutTable\ManifestOptions;
use Keboola\Component\Manifest\ManifestManager\Options\OutTable\ManifestOptionsSchema;
use Keboola\Component\UserException;
use Keboola\Csv\CsvReader;
use Keboola\Csv\Exception;
use RuntimeException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;

class Component extends BaseComponent
{

    private const DEFAULT_DELIMITER = ',';
    private const DEFAULT_ENCLOSURE = '"';

    protected function run(): void
    {
        $dataFolder = $this->getDataDir();

        /** @var Config $config */
        $config = $this->getConfig();
        $configRawData = $config->getRawConfig();
        $outputPath = $dataFolder . '/out/tables';

        $parameters = $this->getConfig()->getParameters();

        $finder = new Finder();
        $finder->notName('*.manifest')->in($dataFolder . '/in/tables')->depth(0);

        foreach ($finder as $sourceFile) {
            /** @var ManifestOptions $manifest */
            $manifest = $this->getManifestManager()->getTableManifest($sourceFile->getBasename());

            $configVariables = [];
            if (isset($configRawData['parameters'])) {
                $configVariables = array_keys($configRawData['parameters']);
            }

            /*
             * If the manifest value is not set or the user provided value is set (even empty), set the manifest
             * to the parameters value (= user provided value or default)
             * https://github.com/keboola/processor-create-manifest/pull/11#discussion_r185779866
             */
            if ($manifest->getDelimiter() === null || in_array('delimiter', $configVariables)) {
                $manifest->setDelimiter($parameters['delimiter'] ?? self::DEFAULT_DELIMITER);
            }
            if ($manifest->getEnclosure() === null || in_array('enclosure', $configVariables)) {
                $manifest->setEnclosure($parameters['enclosure'] ?? self::DEFAULT_ENCLOSURE);
            }

            if ($manifest->getSchema() === [] || in_array('columns', $configVariables)) {
                $manifest->setSchema([]);
                foreach ($parameters['columns'] as $column) {
                    $manifest->addSchema(new ManifestOptionsSchema($column));
                }
            }

            if ($manifest->isIncremental() === null || in_array('incremental', $configVariables)) {
                $manifest->setIncremental($parameters['incremental']);
            }

            try {
                if (isset($parameters['columns_from'])) {
                    $detectFile = $sourceFile->getPathname();
                    if (is_dir($sourceFile->getPathname())) {
                        $subFinder = new Finder();
                        $subFinder->in($sourceFile->getPathname())->depth(0);
                        if (!count($subFinder)) {
                            throw new UserException(
                                "Sliced file '{$sourceFile->getPathname()}' does not contain any slices " .
                                'to read headers from. Please specify headers manually.',
                            );
                        }

                        foreach ($subFinder as $slicedFilePart) {
                            $detectFile = $slicedFilePart->getPathname();
                            break;
                        }

                        if ($parameters['columns_from'] === 'header') {
                            $csv = new CsvReader(
                                $detectFile,
                                $manifest->getDelimiter(),
                                $manifest->getEnclosure(),
                            );
                            $firstSliceHeader = $csv->getHeader();

                            // ensure all slices have same headers
                            foreach ($subFinder as $slicedFilePart) {
                                $csv = new CsvReader(
                                    $slicedFilePart->getPathname(),
                                    $manifest->getDelimiter(),
                                    $manifest->getEnclosure(),
                                );
                                $header = $csv->getHeader();

                                if ($header !== $firstSliceHeader) {
                                    // phpcs:disable Generic.Files.LineLength
                                    throw new UserException(sprintf(
                                        'All slices of the sliced table "%s" must have the same header ("%s" is first different).',
                                        $sourceFile->getPathname(),
                                        $slicedFilePart->getFilename(),
                                    ));
                                    // phpcs:enable
                                }
                            }
                        }
                    }
                    $csv = new CsvReader(
                        $detectFile,
                        $manifest->getDelimiter(),
                        $manifest->getEnclosure(),
                    );
                    if ($parameters['columns_from'] === 'auto') {
                        $manifest->setSchema(
                            $this->createManifestOptionsSchemas(
                                $this->fillHeader(array_fill(0, $csv->getColumnsCount(), '')),
                            ),
                        );
                    } elseif ($parameters['columns_from'] === 'header') {
                        if (empty($csv->getHeader())) {
                            throw new UserException('Header cannot be empty.');
                        }

                        $manifest->setSchema(
                            $this->createManifestOptionsSchemas($this->fillHeader($csv->getHeader())),
                        );
                    }
                }
            } catch (Exception $e) {
                throw new UserException('The CSV file is invalid: ' . $e->getMessage());
            }

            if (in_array('primary_key', $configVariables)) {
                $primaryKeys = $parameters['primary_key'];
            } else {
                $primaryKeys = [];
                if ($manifest->getSchema() !== null) {
                    $primaryKeys = array_map(
                        fn($schema) => $schema->getName(),
                        array_filter($manifest->getSchema(), fn($schema) => $schema->isPrimaryKey()),
                    );
                }
                $primaryKeys = array_unique(array_merge($primaryKeys, $manifest->getLegacyPrimaryKeys() ?? []));
            }

            $manifest->setLegacyPrimaryKeys(null);
            $primaryKeysSet = [];

            if ($manifest->getSchema() === null && !empty($primaryKeys)) {
                $manifest->setLegacyPrimaryKeys($primaryKeys);
            } elseif ($manifest->getSchema() !== null) {
                foreach ($manifest->getSchema() as $schema) {
                    $schema->setPrimaryKey(in_array($schema->getName(), $primaryKeys));
                    if ($schema->isPrimaryKey()) {
                        $primaryKeysSet[] = $schema->getName();
                    }
                }

                $notFound = array_diff($primaryKeys, $primaryKeysSet);
                if (!empty($notFound)) {
                    $this->getLogger()->info(sprintf(
                        'Invalid primary keys found: "%s", fallback to legacy manifest format.',
                        implode(', ', $notFound),
                    ));
                    $manifest->setLegacyPrimaryKeys($notFound);
                }
            }

            (new Process([
                'mv',
                $sourceFile->getPathname(),
                $outputPath . '/' . $sourceFile->getBasename(),
            ]))->mustRun();

            if (in_array('has_header', $configVariables) === true) {
                $manifest->setHasHeader($parameters['has_header']);
            }

            if ($manifest->getSchema() !== null) {
                $this->checkColumnsNames($manifest->getSchema());
            }

            try {
                $this->getManifestManager()->writeTableManifest(
                    $sourceFile->getBasename(),
                    $manifest,
                    $this->config->getDataTypeSupport()->usingLegacyManifest(),
                );
            } catch (UnexpectedValueException $e) {
                throw new RuntimeException('Failed to create manifest: ' . $e->getMessage());
            }
        }
    }

    protected function getConfigClass(): string
    {
        return Config::class;
    }

    protected function getConfigDefinitionClass(): string
    {
        return ConfigDefinition::class;
    }

    /**
     * @param string[] $header
     * @return string[]
     */
    private function fillHeader(array $header): array
    {
        array_walk(
            $header,
            function (&$value, $index): void {
                if (!$value) {
                    $value = 'col_' . ($index + 1);
                }
            },
        );
        return $header;
    }

    /** @param ManifestOptionsSchema[] $schemas */
    private function checkColumnsNames(array $schemas): void
    {
        $invalidColumnNames = [];
        foreach ($schemas as $schema) {
            if (!mb_check_encoding($schema->getName())) {
                $invalidColumnNames[] = $schema->getName();
            }
        }

        if ($invalidColumnNames !== []) {
            throw new UserException(
                'Column names contain invalid characters, check that the CSV uses UTF8 encoding. Column names: ' .
                implode(', ', $invalidColumnNames),
            );
        }
    }

    /**
     * @param string[] $columns
     * @return ManifestOptionsSchema[]
     */
    private function createManifestOptionsSchemas(array $columns): array
    {
        $schemas = [];
        foreach ($columns as $column) {
            $schemas[] = new ManifestOptionsSchema($column);
        }
        return $schemas;
    }
}
