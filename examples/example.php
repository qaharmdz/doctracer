<?php

include './../vendor/autoload.php';

// Required for example
include './Library/ParentClass.php';
include './Library/Acme.php';

$baseDir   = './';
$targetDir = './Library/'; // Relative to $baseDir

$tracer = new \Mdz\DocTracer($baseDir);

//=== Check directories, multiple inspect is possible
$tracer->inspect($targetDir);
$tracer->inspect('./../src');
// $tracer->inspect('./../vendor/webmozart/assert/src');

//=== Print output
echo $output = $tracer->render([
    '{title}'   => 'DocTracer',
    '{tagline}' => 'PHP ReflectionClass and API documentation',
]);

// Save to file
// file_put_contents('example-api.html', $output);

//=== Dump reports
// var_dump($tracer->getReports());

