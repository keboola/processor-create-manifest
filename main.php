<?php
// Catch all warnings and notices
set_error_handler(function ($errno, $errstr, $errfile, $errline, array $errcontext) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});
require __DIR__ . "/vendor/autoload.php";

use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;

$arguments = getopt("", ["data:"]);
if (!isset($arguments["data"])) {
    $dataFolder = getenv('KBC_DATADIR') === false ? '/data/' : (string)getenv('KBC_DATADIR');
} else {
    $dataFolder = $arguments["data"];
}

$configFile = $dataFolder . "/config.json";
if (!file_exists($configFile)) {
    echo sprintf("Config file '%s' not found" . "\n", $configFile);
    exit(2);
}

function fillHeader($header)
{
    array_walk($header, function (&$value, $index) {
        if (!$value) {
            $value = "col_" . ($index + 1);
        }
    });
    return $header;
}

try {
    $jsonDecode = new JsonDecode(true);
    $jsonEncode = new \Symfony\Component\Serializer\Encoder\JsonEncode();

    $config = $jsonDecode->decode(
        file_get_contents($dataFolder . "/config.json"),
        JsonEncoder::FORMAT
    );
    $outputPath = $dataFolder . "/out/tables";

    $parameters = (new \Symfony\Component\Config\Definition\Processor())->processConfiguration(
        new \Keboola\Processor\CreateManifest\ConfigDefinition(),
        [isset($config["parameters"]) ? $config["parameters"] : []]
    );

    $finder = new \Symfony\Component\Finder\Finder();
    $finder->notName("*.manifest")->in($dataFolder . "/in/tables")->depth(0);

    foreach ($finder as $sourceFile) {
        // read manifest from file or create empty manifest
        if (file_exists($sourceFile->getPathname() . ".manifest")) {
            $manifest = $jsonDecode->decode(
                file_get_contents($sourceFile->getPathname() . ".manifest"),
                JsonEncoder::FORMAT
            );
        } else {
            $manifest = [];
        }

        $manifestVariables = array_keys($manifest);
        $configVariables = [];
        if (isset($config["parameters"]) && is_array($config["parameters"])) {
            $configVariables = array_keys($config["parameters"]);
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

        if (isset($parameters["columns_from"])) {
            $detectFile = $sourceFile->getPathname();
            if (is_dir($sourceFile->getPathname())) {
                $subFinder = new \Symfony\Component\Finder\Finder();
                $subFinder->in($sourceFile->getPathname())->depth(0);
                if (!count($subFinder)) {
                    throw new \Keboola\Processor\CreateManifest\Exception(
                        "Sliced file '{$sourceFile->getPathname()}' does not contain slices."
                    );
                }

                foreach ($subFinder as $slicedFilePart) {
                    $detectFile = $slicedFilePart->getPathname();
                    break;
                }

            }
            $csv = new Keboola\Csv\CsvFile($detectFile, $manifest["delimiter"], $manifest["enclosure"]);
            if ($parameters["columns_from"] === 'auto') {
                $manifest["columns"] = fillHeader(array_fill(0, $csv->getColumnsCount(), ""));
            } elseif ($parameters["columns_from"] === 'header') {
                $manifest["columns"] = fillHeader($csv->getHeader());
            }
        }

        $copyCommand = "mv " . $sourceFile->getPathname() . " " . $outputPath . "/" . $sourceFile->getBasename();
        (new \Symfony\Component\Process\Process($copyCommand))->mustRun();

        try {
            file_put_contents(
                $outputPath . "/" . $sourceFile->getBasename() . ".manifest",
                $jsonEncode->encode($manifest, JsonEncoder::FORMAT)
            );
        } catch (\Symfony\Component\Serializer\Exception\UnexpectedValueException $e) {
            throw new \Keboola\Processor\CreateManifest\Exception("Failed to create manifest: " . $e->getMessage());
        }
    }
} catch (\Keboola\Csv\Exception $e) {
    echo "The CSV file is invalid: " . $e->getMessage();
    exit(1);
} catch (\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException $e) {
    echo "Invalid configuration: " . $e->getMessage();
    exit(1);
} catch (\Keboola\Processor\CreateManifest\Exception $e) {
    echo $e->getMessage();
    exit(1);
}
