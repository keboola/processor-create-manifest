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
    $dataFolder = "/data";
} else {
    $dataFolder = $arguments["data"];
}

$configFile = $dataFolder . "/config.json";
if (!file_exists($configFile)) {
    echo "Config file not found" . "\n";
    exit(2);
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

        // use defaults or force overwrite values
        if (!isset($manifest["delimiter"]) || isset($config["parameters"]["delimiter"])) {
            $manifest["delimiter"] = $parameters["delimiter"];
        }
        if (!isset($manifest["enclosure"]) || isset($config["parameters"]["enclosure"])) {
            $manifest["enclosure"] = $parameters["enclosure"];
        }
        if (!isset($manifest["primary_key"]) || isset($config["parameters"]["primary_key"])) {
            $manifest["primary_key"] = $parameters["primary_key"];
        }
        if (!isset($manifest["incremental"]) || isset($config["parameters"]["incremental"])) {
            $manifest["incremental"] = $parameters["incremental"];
        }
        if (!isset($manifest["primary_key"]) || isset($config["parameters"]["columns"])) {
            $manifest["columns"] = $parameters["columns"];
        }

        if (isset($parameters["columns_from"])) {
            $detectFile = $sourceFile->getPathname();
            if (is_dir($sourceFile->getPathname())) {
                $subFinder = new \Symfony\Component\Finder\Finder();
                $subFinder->in($sourceFile->getPathname())->depth(0);
                if (!count($subFinder)) {
                    throw new \Keboola\Processor\CreateManifest\Exception("Sliced file '{$sourceFile->getPathname()}' does not contain slices.");
                }

                foreach ($subFinder as $slicedFilePart) {
                    $detectFile = $slicedFilePart->getPathname();
                    break;
                }

            }
            $csv = new Keboola\Csv\CsvFile($detectFile, $manifest["delimiter"], $manifest["enclosure"]);
            if ($parameters["columns_from"] === 'auto') {
                $manifest["columns"] = array_map(function($index) {
                    return "col_{$index}";
                }, range(1, $csv->getColumnsCount(), 1));
            } elseif ($parameters["columns_from"] === 'header') {
                $manifest["columns"] = $csv->getHeader();
            }
        }

        $copyCommand = "mv " . $sourceFile->getPathName() . " " . $outputPath . "/" . $sourceFile->getBasename();
        (new \Symfony\Component\Process\Process($copyCommand))->mustRun();

        file_put_contents($outputPath . "/" . $sourceFile->getBasename() . ".manifest", $jsonEncode->encode($manifest, JsonEncoder::FORMAT));
    }
} catch (\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException $e) {
    echo "Invalid configuration: " . $e->getMessage();
    exit(1);
} catch (\Keboola\Processor\CreateManifest\Exception $e) {
    echo $e->getMessage();
    exit(1);
}
