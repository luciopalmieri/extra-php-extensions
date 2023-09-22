<?php

/********************************************************
 *
 * Copies the system dependencies used by a binary/extension.
 *
 * Usage:
 *    php copy-dependencies.php <file-to-analyze> <target-directory>
 *
 * For example:
 *    php copy-dependencies.php /opt/bin/php /opt/lib
 *
 ********************************************************/

if (!isset($argv[1])) {
    echo 'Missing the first argument, check the file to see how to use it' . PHP_EOL;
    exit(1);
}
if (!isset($argv[2])) {
    echo 'Missing the second argument, check the file to see how to use it' . PHP_EOL;
    exit(1);
}
list($_, $pathToCheck, $targetDirectory) = $argv;

// Create the target directory if it doesn't exist
if (! is_dir($targetDirectory)) {
    mkdir($targetDirectory, 0777, true);
}

$arch = 'x86';
if (php_uname('m') !== 'x86_64') {
    $arch = 'arm';
}

$librariesThatExistOnLambda = file(__DIR__ . "/libs-$arch.txt");
$librariesThatExistOnLambda = array_map('trim', $librariesThatExistOnLambda);
// For some reason some libraries are actually not in Lambda, despite being in the docker image 🤷
$librariesThatExistOnLambda = array_filter($librariesThatExistOnLambda, function ($library) {
    return ! strpos($library, 'libgcrypt.so') && ! strpos($library, 'libgpg-error.so');
});

$requiredLibraries = listDependencies($pathToCheck);
// Exclude existing system libraries
$requiredLibraries = array_filter($requiredLibraries, function (string $lib) use ($librariesThatExistOnLambda) {
    // Libraries that we compiled are in /opt/lib or /opt/lib64, we compiled them because they are more
    // recent than the ones in Lambda so we definitely want to use them
    $isALibraryWeCompiled = strpos($lib, '/opt/lib') === 0;
    $doesNotExistInLambda = !in_array(basename($lib), $librariesThatExistOnLambda, true);
    $keep = $isALibraryWeCompiled || $doesNotExistInLambda;
    if (! $keep) {
        echo "Skipping $lib because it's already in Lambda" . PHP_EOL;
    }
    return $keep;
});

// Copy all the libraries
foreach ($requiredLibraries as $libraryPath) {
    $targetPath = $targetDirectory . '/' . basename($libraryPath);
    echo "Copying $libraryPath to $targetPath" . PHP_EOL;
    $success = copy($libraryPath, $targetPath);
    if (! $success) {
        throw new RuntimeException("Could not copy $libraryPath to $targetPath");
    }
}


function listDependencies(string $path): array
{
    // ldd lists the dependencies of a binary or library/extension (.so file)
    exec("ldd $path 2>&1", $lines);
    if (strpos(end($lines), 'exited with unknown exit code (139)')) {
        // We can't use `ldd` on binaries (like /opt/bin/php) because it fails on cross-platform builds
        // so we fall back to `LD_TRACE_LOADED_OBJECTS` (which doesn't work for .so files, that's why we also try `ldd`)
        // See https://stackoverflow.com/a/35905007/245552
        $output = shell_exec("LD_TRACE_LOADED_OBJECTS=1 $path 2>&1");
        if (!$output) {
            throw new RuntimeException("Could not list dependencies for $path");
        }
        $lines = explode(PHP_EOL, $output);
    }
    $dependencies = [];
    foreach ($lines as $line) {
        if (substr($line, -13) === ' => not found') {
            throw new RuntimeException("This library is a dependency for $path but cannot be found by 'ldd':\n$line\n");
        }
        $matches = [];
        if (preg_match('/=> (.*) \(0x[0-9a-f]+\)/', $line, $matches)) {
            $dependencies[] = $matches[1];
        }
    }
    return $dependencies;
}
