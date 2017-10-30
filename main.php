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
    echo "Data folder not set." . "\n";
    exit(2);
}

$configFile = $arguments["data"] . "/config.json";
if (!file_exists($configFile)) {
    echo "Config file not found" . "\n";
    exit(2);
}

try {
    $jsonDecode = new JsonDecode(true);
    $jsonEncode = new \Symfony\Component\Serializer\Encoder\JsonEncode();

    $config = $jsonDecode->decode(
        file_get_contents($arguments["data"] . "/config.json"),
        JsonEncoder::FORMAT
    );
    $outputPath = $arguments["data"] . "/out/tables";

    $parameters = (new \Symfony\Component\Config\Definition\Processor())->processConfiguration(
        new \Keboola\Processor\CreateManifest\ConfigDefinition(),
        [isset($config["parameters"]) ? $config["parameters"] : []]
    );

    $finder = new \Symfony\Component\Finder\Finder();
    $finder->notContains(".manifest")->in($arguments["data"] . "/in/tables/")->depth(0);

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

        $copyCommand = "cp -R " . $sourceFile->getPathName() . " " . $outputPath . "/" . $sourceFile->getBasename();
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
