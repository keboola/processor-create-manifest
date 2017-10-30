<?php
require_once(__DIR__ . "/../vendor/autoload.php");

$testFolder = __DIR__;

$finder = new \Symfony\Component\Finder\Finder();
$finder->directories()->in($testFolder)->depth(0);
foreach ($finder as $testSuite) {
    print "Test " . $testSuite->getPathName() . "\n";
    $temp = new \Keboola\Temp\Temp("processor-create-manifest");
    $temp->initRunFolder();

    $copyCommand = "cp -R " . $testSuite->getPathName() . "/source " . $temp->getTmpFolder();
    (new \Symfony\Component\Process\Process($copyCommand))->mustRun();

    mkdir($temp->getTmpFolder() . "/out/tables", 0777, true);
    mkdir($temp->getTmpFolder() . "/out/files", 0777, true);

    $runCommand = "php /code/main.php --data=" . $temp->getTmpFolder();
    (new \Symfony\Component\Process\Process($runCommand))->mustRun();

    $diffCommand = "diff --recursive " . $testSuite->getPathName() . "/expected/data/out " . $temp->getTmpFolder() . "/out";
    $diffProcess = new \Symfony\Component\Process\Process($diffCommand);
    $diffProcess->run();
    if ($diffProcess->getExitCode() > 0) {
        print $diffProcess->getOutput();
        exit(1);
    }
}
