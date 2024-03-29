<?php

declare(strict_types=1);

namespace Keboola\Processor\CreateManifest;

use Keboola\Component\BaseComponent;
use Keboola\Component\UserException;
use Keboola\Csv\CsvReader;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;
use Symfony\Component\Serializer\Encoder\JsonEncoder;

class Component extends BaseComponent
{
    public function run(): void
    {
        $jsonEncode = new \Symfony\Component\Serializer\Encoder\JsonEncode();

        $dataFolder = $this->getDataDir();

        /** @var Config $config */
        $config = $this->getConfig();
        $configRawData = $config->getRawConfig();
        $outputPath = $dataFolder . "/out/tables";

        $parameters = $this->getConfig()->getParameters();

        $finder = new Finder();
        $finder->notName("*.manifest")->in($dataFolder . "/in/tables")->depth(0);

        foreach ($finder as $sourceFile) {
            $manifest = $this->getManifestManager()->getTableManifest($sourceFile->getBasename());

            $manifestVariables = array_keys($manifest);
            $configVariables = [];
            if (isset($configRawData["parameters"]) && is_array($configRawData["parameters"])) {
                $configVariables = array_keys($configRawData["parameters"]);
            }

            /*
             * If the manifest value is not set or the user provided value is set (even empty), set the manifest
             * to the parameters value (= user provided value or default)
             * https://github.com/keboola/processor-create-manifest/pull/11#discussion_r185779866
             */
            if (!in_array("delimiter", $manifestVariables) || in_array("delimiter", $configVariables)) {
                $manifest["delimiter"] = $parameters["delimiter"];
            }
            if (!in_array("enclosure", $manifestVariables) || in_array("enclosure", $configVariables)) {
                $manifest["enclosure"] = $parameters["enclosure"];
            }
            if (!in_array("primary_key", $manifestVariables) || in_array("primary_key", $configVariables)) {
                $manifest["primary_key"] = $parameters["primary_key"];
            }
            if (!in_array("incremental", $manifestVariables) || in_array("incremental", $configVariables)) {
                $manifest["incremental"] = $parameters["incremental"];
            }
            if (!in_array("columns", $manifestVariables) || in_array("columns", $configVariables)) {
                $manifest["columns"] = $parameters["columns"];
            }

            try {
                if (isset($parameters["columns_from"])) {
                    $detectFile = $sourceFile->getPathname();
                    if (is_dir($sourceFile->getPathname())) {
                        $subFinder = new Finder();
                        $subFinder->in($sourceFile->getPathname())->depth(0);
                        if (!count($subFinder)) {
                            throw new UserException(
                                "Sliced file '{$sourceFile->getPathname()}' does not contain any slices " .
                                "to read headers from. Please specify headers manually."
                            );
                        }

                        foreach ($subFinder as $slicedFilePart) {
                            $detectFile = $slicedFilePart->getPathname();
                            break;
                        }

                        if ($parameters["columns_from"] === 'header') {
                            $csv = new CsvReader(
                                $detectFile,
                                $manifest["delimiter"],
                                $manifest["enclosure"]
                            );
                            $firstSliceHeader = $csv->getHeader();

                            // ensure all slices have same headers
                            foreach ($subFinder as $slicedFilePart) {
                                $csv = new CsvReader(
                                    $slicedFilePart->getPathname(),
                                    $manifest["delimiter"],
                                    $manifest["enclosure"]
                                );
                                $header = $csv->getHeader();

                                if ($header !== $firstSliceHeader) {
                                    // phpcs:disable Generic.Files.LineLength
                                    throw new UserException(sprintf(
                                        'All slices of the sliced table "%s" must have the same header ("%s" is first different).',
                                        $sourceFile->getPathname(),
                                        $slicedFilePart->getFilename()
                                    ));
                                    // phpcs:enable
                                }
                            }
                        }
                    }
                    $csv = new CsvReader($detectFile, $manifest["delimiter"], $manifest["enclosure"]);
                    if ($parameters["columns_from"] === 'auto') {
                        $manifest["columns"] = $this->fillHeader(array_fill(0, $csv->getColumnsCount(), ""));
                    } elseif ($parameters["columns_from"] === 'header') {
                        if (empty($csv->getHeader())) {
                            throw new UserException('Header cannot be empty.');
                        }
                        $manifest["columns"] = $this->fillHeader($csv->getHeader());
                    }
                }
            } catch (\Keboola\Csv\Exception $e) {
                throw new UserException('The CSV file is invalid: ' . $e->getMessage());
            }

            (new Process([
                "mv",
                $sourceFile->getPathname(),
                $outputPath . "/" . $sourceFile->getBasename(),
            ]))->mustRun();

            if (!mb_check_encoding($manifest['columns'])) {
                throw new UserException(
                    'Column names contain invalid characters, check that the CSV uses UTF8 encoding. Column names: ' .
                    mb_convert_encoding(implode(', ', $manifest['columns']), 'UTF-8', 'UTF-8')
                );
            }
            try {
                file_put_contents(
                    $outputPath . "/" . $sourceFile->getBasename() . ".manifest",
                    $jsonEncode->encode($manifest, JsonEncoder::FORMAT)
                );
            } catch (\Symfony\Component\Serializer\Exception\UnexpectedValueException $e) {
                throw new \RuntimeException("Failed to create manifest: " . $e->getMessage());
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

    private function fillHeader(array $header): array
    {
        array_walk(
            $header,
            function (&$value, $index): void {
                if (!$value) {
                    $value = "col_" . ($index + 1);
                }
            }
        );
        return $header;
    }
}
