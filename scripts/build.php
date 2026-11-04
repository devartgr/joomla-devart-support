<?php
/**
 * Build an installable DevArt package from the live source tree.
 *
 * @package     DevArt
 * @copyright   Copyright (C) 2026 DevArt. All rights reserved.
 * @license     GNU General Public License version 3 or later.
 */

declare(strict_types=1);

const EXCLUDED_NAMES = [
    '.DS_Store',
    'Thumbs.db',
    '.git',
    '.github',
    '.svn',
    '.idea',
    '.vscode',
    '.cursor',
    '.cursorignore',
    '.cursorindexingignore',
    '.gitkeep',
];

/**
 * @throws RuntimeException
 */
function fail(string $message): never
{
    throw new RuntimeException($message);
}

function isExcluded(string $relativePath): bool
{
    $segments = explode('/', str_replace('\\', '/', $relativePath));

    foreach ($segments as $segment) {
        if (
            in_array($segment, EXCLUDED_NAMES, true)
            || str_starts_with($segment, '._')
            || str_ends_with($segment, '.swp')
            || str_ends_with($segment, '.tmp')
            || str_ends_with($segment, '.zip')
            || str_ends_with($segment, '.cache')
            || str_ends_with($segment, '~')
        ) {
            return true;
        }
    }

    return false;
}

/**
 * @return list<string>
 */
function childDirectories(string $directory): array
{
    $entries = scandir($directory);

    if ($entries === false) {
        fail('Unable to read directory: ' . $directory);
    }

    $directories = [];

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..' || isExcluded($entry)) {
            continue;
        }

        $path = $directory . DIRECTORY_SEPARATOR . $entry;

        if (is_link($path)) {
            fail('Symbolic links are not allowed in extension sources: ' . $path);
        }

        if (is_dir($path)) {
            $directories[] = $entry;
        }
    }

    sort($directories, SORT_STRING);

    return $directories;
}

/**
 * @throws RuntimeException
 */
function loadManifest(string $path): SimpleXMLElement
{
    if (!is_file($path) || !is_readable($path)) {
        fail('Manifest is missing or unreadable: ' . $path);
    }

    $previous = libxml_use_internal_errors(true);
    libxml_clear_errors();
    $manifest = simplexml_load_file($path, SimpleXMLElement::class, LIBXML_NONET);
    $errors = libxml_get_errors();
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if ($manifest === false) {
        $detail = $errors !== [] ? trim($errors[0]->message) : 'unknown XML error';
        fail('Invalid manifest ' . $path . ': ' . $detail);
    }

    if ($manifest->getName() !== 'extension') {
        fail('Expected an <extension> manifest: ' . $path);
    }

    return $manifest;
}

function findPackageManifest(string $packageDirectory): string
{
    $candidates = glob($packageDirectory . DIRECTORY_SEPARATOR . 'pkg_*.xml');

    if ($candidates === false) {
        fail('Unable to inspect package manifests in: ' . $packageDirectory);
    }

    $candidates = array_values(array_filter($candidates, 'is_file'));
    sort($candidates, SORT_STRING);

    if (count($candidates) !== 1) {
        fail(sprintf(
            'Expected exactly one pkg_*.xml manifest in %s; found %d.',
            $packageDirectory,
            count($candidates)
        ));
    }

    return $candidates[0];
}

function normalizeArchiveFolder(string $folder): string
{
    $folder = str_replace('\\', '/', $folder);

    if (
        str_starts_with($folder, '/')
        || preg_match('~(^|/)\.\.(/|$)~', $folder) === 1
    ) {
        fail('Unsafe package files folder: ' . $folder);
    }

    return trim($folder, '/');
}

/**
 * @return array{path: string, manifest: SimpleXMLElement}
 */
function findExtensionManifest(string $extensionDirectory, string $expectedType): array
{
    $candidates = glob($extensionDirectory . DIRECTORY_SEPARATOR . '*.xml');

    if ($candidates === false) {
        fail('Unable to inspect extension manifests in: ' . $extensionDirectory);
    }

    $matches = [];

    foreach ($candidates as $candidate) {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $xml = simplexml_load_file($candidate, SimpleXMLElement::class, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (
            $xml instanceof SimpleXMLElement
            && $xml->getName() === 'extension'
            && (string) $xml['type'] === $expectedType
        ) {
            $matches[] = ['path' => $candidate, 'manifest' => $xml];
        }
    }

    if (count($matches) !== 1) {
        fail(sprintf(
            'Expected exactly one %s manifest in %s; found %d.',
            $expectedType,
            $extensionDirectory,
            count($matches)
        ));
    }

    return $matches[0];
}

/**
 * @return list<array{absolute: string, relative: string, directory: bool}>
 */
function collectArchiveEntries(string $sourceDirectory): array
{
    $sourceDirectory = rtrim($sourceDirectory, DIRECTORY_SEPARATOR);
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceDirectory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    $entries = [];

    foreach ($iterator as $item) {
        $absolute = $item->getPathname();
        $relative = str_replace(
            DIRECTORY_SEPARATOR,
            '/',
            substr($absolute, strlen($sourceDirectory) + 1)
        );

        if ($item->isLink()) {
            fail('Symbolic links are not allowed in package sources: ' . $absolute);
        }

        if (isExcluded($relative)) {
            continue;
        }

        if (!$item->isDir() && !$item->isFile()) {
            fail('Unsupported filesystem entry: ' . $absolute);
        }

        if (!$item->isReadable()) {
            fail('Unreadable package source: ' . $absolute);
        }

        $entries[] = [
            'absolute'  => $absolute,
            'relative'  => $relative,
            'directory' => $item->isDir(),
        ];
    }

    usort(
        $entries,
        static fn(array $left, array $right): int => strcmp($left['relative'], $right['relative'])
    );

    return $entries;
}

/**
 * @return array<string, string>
 */
function sourceIntegrityInventory(string $sourceRoot): array
{
    $sourceRoot = rtrim($sourceRoot, DIRECTORY_SEPARATOR);
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS)
    );
    $inventory = [];

    foreach ($iterator as $item) {
        if (!$item->isFile()) {
            continue;
        }

        $absolute = $item->getPathname();
        $relative = str_replace(
            DIRECTORY_SEPARATOR,
            '/',
            substr($absolute, strlen($sourceRoot) + 1)
        );

        $hash = hash_file('sha256', $absolute);

        if ($hash === false) {
            fail('Unable to hash source file: ' . $absolute);
        }

        $inventory[$relative] = $hash;
    }

    ksort($inventory, SORT_STRING);

    return $inventory;
}

/**
 * @param array<string, string> $before
 * @param array<string, string> $after
 * @return list<string>
 */
function compareSourceInventories(array $before, array $after): array
{
    $changes = [];
    $paths = array_unique(array_merge(array_keys($before), array_keys($after)));
    sort($paths, SORT_STRING);

    foreach ($paths as $path) {
        $had = $before[$path] ?? null;
        $has = $after[$path] ?? null;

        if ($had === null) {
            $changes[] = 'added: ' . $path;
        } elseif ($has === null) {
            $changes[] = 'removed: ' . $path;
        } elseif ($had !== $has) {
            $changes[] = 'changed: ' . $path;
        }
    }

    return $changes;
}

function runValidationScript(string $repositoryRoot): void
{
    $script = $repositoryRoot . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'validate.php';

    if (!is_file($script)) {
        fail('Validation script is missing: ' . $script);
    }

    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script);
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptors, $pipes, $repositoryRoot);

    if (!is_resource($process)) {
        fail('Unable to start validation script.');
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    if ($stdout !== false && $stdout !== '') {
        fwrite(STDOUT, $stdout);
    }

    if ($stderr !== false && $stderr !== '') {
        fwrite(STDERR, $stderr);
    }

    if ($exitCode !== 0) {
        fwrite(STDERR, 'Build aborted because validation failed.' . PHP_EOL);
        exit(1);
    }
}

/**
 * @throws RuntimeException
 */
function createZipFromDirectory(string $sourceDirectory, string $zipPath): void
{
    if (!is_dir($sourceDirectory)) {
        fail('Package source directory does not exist: ' . $sourceDirectory);
    }

    if (file_exists($zipPath)) {
        fail('Refusing to overwrite ZIP: ' . $zipPath);
    }

    $zip = new ZipArchive();
    $result = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::EXCL);

    if ($result !== true) {
        fail('Unable to create ZIP ' . $zipPath . ' (ZipArchive error ' . $result . ').');
    }

    try {
        foreach (collectArchiveEntries($sourceDirectory) as $entry) {
            $added = $entry['directory']
                ? $zip->addEmptyDir($entry['relative'])
                : $zip->addFile($entry['absolute'], $entry['relative']);

            if (!$added) {
                fail('Unable to add to ZIP: ' . $entry['absolute']);
            }
        }

        if ($zip->numFiles === 0) {
            fail('Refusing to create an empty ZIP from: ' . $sourceDirectory);
        }
    } finally {
        if (!$zip->close()) {
            fail('Unable to finalize ZIP: ' . $zipPath);
        }
    }
}

/**
 * @param list<string> $requiredEntries
 * @param list<string> $allowedEmbeddedZipEntries Inner ZIP paths permitted in an outer package only.
 */
function validateZip(string $zipPath, array $requiredEntries, array $allowedEmbeddedZipEntries = []): void
{
    $zip = new ZipArchive();
    $result = $zip->open($zipPath, ZipArchive::CHECKCONS);

    if ($result !== true) {
        fail('ZIP validation failed for ' . $zipPath . ' (ZipArchive error ' . $result . ').');
    }

    $allowedEmbeddedZipEntries = array_values(array_unique($allowedEmbeddedZipEntries));
    sort($allowedEmbeddedZipEntries, SORT_STRING);
    $allowedEmbeddedZipLookup = array_fill_keys($allowedEmbeddedZipEntries, true);
    $isOuterPackage = $allowedEmbeddedZipEntries !== [];
    $foundEmbeddedZipEntries = [];

    try {
        $names = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);

            if ($name === false) {
                fail('Unable to read ZIP entry ' . $index . ' from: ' . $zipPath);
            }

            if (str_starts_with($name, '/') || str_contains($name, '../')) {
                fail('Unsafe ZIP entry "' . $name . '" in: ' . $zipPath);
            }

            if (str_ends_with(strtolower($name), '.zip')) {
                if (!$isOuterPackage) {
                    fail('Nested ZIP entry "' . $name . '" is not allowed in: ' . $zipPath);
                }

                if (!isset($allowedEmbeddedZipLookup[$name])) {
                    fail('Undeclared ZIP entry "' . $name . '" in: ' . $zipPath);
                }

                if (isset($foundEmbeddedZipEntries[$name])) {
                    fail('Duplicate embedded ZIP entry "' . $name . '" in: ' . $zipPath);
                }

                $signature = $zip->getFromIndex($index, 4, ZipArchive::FL_UNCHANGED);

                if (
                    $signature === false
                    || (
                        $signature !== "PK\x03\x04"
                        && $signature !== "PK\x05\x06"
                    )
                ) {
                    fail('Invalid embedded ZIP signature for "' . $name . '" in: ' . $zipPath);
                }

                $foundEmbeddedZipEntries[$name] = true;
            } elseif (isExcluded(rtrim($name, '/'))) {
                fail('Unsafe or excluded ZIP entry "' . $name . '" in: ' . $zipPath);
            }

            if (isset($names[$name])) {
                fail('Duplicate ZIP entry "' . $name . '" in: ' . $zipPath);
            }

            $names[$name] = true;
        }

        if ($isOuterPackage) {
            foreach ($allowedEmbeddedZipEntries as $allowedEmbeddedZipEntry) {
                if (!isset($foundEmbeddedZipEntries[$allowedEmbeddedZipEntry])) {
                    fail(
                        'Declared inner ZIP "' . $allowedEmbeddedZipEntry . '" is missing from: ' . $zipPath
                    );
                }
            }
        }

        foreach ($requiredEntries as $requiredEntry) {
            if (!isset($names[$requiredEntry])) {
                fail('Required ZIP entry "' . $requiredEntry . '" is missing from: ' . $zipPath);
            }
        }
    } finally {
        $zip->close();
    }
}

function removeTemporaryDirectory(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        $removed = $item->isDir() && !$item->isLink()
            ? rmdir($item->getPathname())
            : unlink($item->getPathname());

        if (!$removed) {
            throw new RuntimeException('Unable to remove temporary path: ' . $item->getPathname());
        }
    }

    if (!rmdir($directory)) {
        throw new RuntimeException('Unable to remove temporary directory: ' . $directory);
    }
}

$temporaryDirectory = null;
$publishedPackage = null;
$publishedChecksum = null;

try {
    if (PHP_VERSION_ID < 80300) {
        fail('PHP 8.3 or newer is required.');
    }

    if (!class_exists(ZipArchive::class)) {
        fail('The PHP ZIP extension is required.');
    }

    if (!function_exists('simplexml_load_file')) {
        fail('The PHP SimpleXML extension is required.');
    }

    if (!function_exists('proc_open')) {
        fail('The proc_open function is required to run validation before build.');
    }

    $repositoryRoot = dirname(__DIR__);
    runValidationScript($repositoryRoot);

    $sourceRoot = $repositoryRoot . DIRECTORY_SEPARATOR . 'source';
    $packageSource = $sourceRoot . DIRECTORY_SEPARATOR . 'package';
    $extensionsRoot = $sourceRoot . DIRECTORY_SEPARATOR . 'extensions';
    $buildsDirectory = $repositoryRoot . DIRECTORY_SEPARATOR . 'builds';

    foreach ([$packageSource, $extensionsRoot] as $requiredDirectory) {
        if (!is_dir($requiredDirectory) || !is_readable($requiredDirectory)) {
            fail('Required source directory is missing or unreadable: ' . $requiredDirectory);
        }
    }

    if (!is_dir($buildsDirectory) && !mkdir($buildsDirectory, 0775, true)) {
        fail('Unable to create builds directory: ' . $buildsDirectory);
    }

    if (!is_writable($buildsDirectory)) {
        fail('Builds directory is not writable: ' . $buildsDirectory);
    }

    $sourceIntegrityBefore = sourceIntegrityInventory($sourceRoot);

    $packageManifestPath = findPackageManifest($packageSource);
    $packageManifest = loadManifest($packageManifestPath);

    if ((string) $packageManifest['type'] !== 'package') {
        fail('Unexpected package manifest type: ' . (string) $packageManifest['type']);
    }

    $packageName = trim((string) $packageManifest->packagename);
    $packageVersion = trim((string) $packageManifest->version);

    if ($packageName === '' || $packageVersion === '') {
        fail('Package manifest must declare packagename and version.');
    }

    $outerFilenamePrefix = basename($packageManifestPath, '.xml');

    foreach ([$outerFilenamePrefix, $packageVersion] as $filenamePart) {
        if (preg_match('/^[A-Za-z0-9._-]+$/D', $filenamePart) !== 1) {
            fail('Unsafe package filename value: ' . $filenamePart);
        }
    }

    $outerFilename = $outerFilenamePrefix . '_v' . $packageVersion . '.zip';
    $outputPath = $buildsDirectory . DIRECTORY_SEPARATOR . $outerFilename;
    $checksumPath = $outputPath . '.sha256';

    if (file_exists($outputPath) || file_exists($checksumPath)) {
        fail('Build already exists; refusing to overwrite: ' . $outerFilename);
    }

    $temporaryDirectory = $buildsDirectory
        . DIRECTORY_SEPARATOR
        . '.build-' . bin2hex(random_bytes(8));

    if (!mkdir($temporaryDirectory, 0700)) {
        fail('Unable to create temporary build directory: ' . $temporaryDirectory);
    }

    $typeDirectories = [
        'component' => 'component',
        'module'    => 'modules',
        'plugin'    => 'plugins',
    ];
    $declaredDirectories = [
        'component' => [],
        'module'    => [],
        'plugin'    => [],
    ];
    $innerArchives = [];

    if (!isset($packageManifest->files)) {
        fail('Package manifest does not contain a <files> section.');
    }

    $packageFilesFolder = normalizeArchiveFolder((string) $packageManifest->files['folder']);

    foreach ($packageManifest->files->file as $fileNode) {
        $type = trim((string) $fileNode['type']);
        $archiveName = trim((string) $fileNode);

        if (!isset($typeDirectories[$type])) {
            fail('Unsupported package extension type: ' . $type);
        }

        if (
            $archiveName === ''
            || basename($archiveName) !== $archiveName
            || !str_ends_with(strtolower($archiveName), '.zip')
        ) {
            fail('Unsafe or invalid inner archive name: ' . $archiveName);
        }

        $archivePath = $packageFilesFolder === ''
            ? $archiveName
            : $packageFilesFolder . '/' . $archiveName;

        if (isset($innerArchives[$archivePath])) {
            fail('Duplicate inner archive declaration: ' . $archiveName);
        }

        $directoryName = substr($archiveName, 0, -4);
        $sourceDirectory = $extensionsRoot
            . DIRECTORY_SEPARATOR
            . $typeDirectories[$type]
            . DIRECTORY_SEPARATOR
            . $directoryName;

        if (!is_dir($sourceDirectory) || !is_readable($sourceDirectory)) {
            fail('Declared extension source is missing or unreadable: ' . $sourceDirectory);
        }

        $manifestInfo = findExtensionManifest($sourceDirectory, $type);
        $extensionManifest = $manifestInfo['manifest'];
        $extensionVersion = trim((string) $extensionManifest->version);

        if ($extensionVersion !== $packageVersion) {
            fail(sprintf(
                'Version mismatch for %s: package is %s, extension is %s.',
                $directoryName,
                $packageVersion,
                $extensionVersion !== '' ? $extensionVersion : '(missing)'
            ));
        }

        $declaredId = trim((string) $fileNode['id']);

        if ($type !== 'plugin' && $declaredId !== $directoryName) {
            fail('Manifest id does not match source directory for: ' . $archiveName);
        }

        if ($type === 'plugin') {
            $group = trim((string) $fileNode['group']);

            if ($group === '' || (string) $extensionManifest['group'] !== $group) {
                fail('Plugin group mismatch for: ' . $archiveName);
            }

            $pluginId = '';

            foreach ($extensionManifest->files->children() as $entry) {
                if (isset($entry['plugin'])) {
                    $pluginId = trim((string) $entry['plugin']);
                    break;
                }
            }

            if ($pluginId === '' || $pluginId !== $declaredId) {
                fail('Plugin id mismatch for: ' . $archiveName);
            }
        }

        $innerPath = $temporaryDirectory . DIRECTORY_SEPARATOR . $archiveName;
        createZipFromDirectory($sourceDirectory, $innerPath);
        validateZip($innerPath, [basename($manifestInfo['path'])]);

        $declaredDirectories[$type][] = $directoryName;
        $innerArchives[$archivePath] = $innerPath;
    }

    foreach ($typeDirectories as $type => $relativeDirectory) {
        $categoryDirectory = $extensionsRoot . DIRECTORY_SEPARATOR . $relativeDirectory;

        if (!is_dir($categoryDirectory)) {
            if ($declaredDirectories[$type] === []) {
                continue;
            }

            fail('Declared extension category directory is missing: ' . $categoryDirectory);
        }

        if (!is_readable($categoryDirectory)) {
            fail('Extension category directory is unreadable: ' . $categoryDirectory);
        }

        $actual = childDirectories($categoryDirectory);
        $declared = array_values(array_unique($declaredDirectories[$type]));
        sort($declared, SORT_STRING);

        if ($actual !== $declared) {
            fail(sprintf(
                'Package manifest and %s source directories differ. Declared: [%s]. Found: [%s].',
                $type,
                implode(', ', $declared),
                implode(', ', $actual)
            ));
        }
    }

    ksort($innerArchives, SORT_STRING);
    $outerTemporaryPath = $temporaryDirectory . DIRECTORY_SEPARATOR . $outerFilename;
    $outerZip = new ZipArchive();
    $openResult = $outerZip->open($outerTemporaryPath, ZipArchive::CREATE | ZipArchive::EXCL);

    if ($openResult !== true) {
        fail('Unable to create outer package ZIP (ZipArchive error ' . $openResult . ').');
    }

    try {
        foreach (collectArchiveEntries($packageSource) as $entry) {
            $added = $entry['directory']
                ? $outerZip->addEmptyDir($entry['relative'])
                : $outerZip->addFile($entry['absolute'], $entry['relative']);

            if (!$added) {
                fail('Unable to add package source to outer ZIP: ' . $entry['absolute']);
            }
        }

        foreach ($innerArchives as $archivePath => $innerPath) {
            if (!$outerZip->addFile($innerPath, $archivePath)) {
                fail('Unable to add inner ZIP to outer package: ' . $archivePath);
            }
        }
    } finally {
        if (!$outerZip->close()) {
            fail('Unable to finalize outer package ZIP.');
        }
    }

    validateZip(
        $outerTemporaryPath,
        array_merge([basename($packageManifestPath)], array_keys($innerArchives)),
        array_keys($innerArchives)
    );

    $checksum = hash_file('sha256', $outerTemporaryPath);

    if ($checksum === false) {
        fail('Unable to calculate SHA-256 for: ' . $outerTemporaryPath);
    }

    if (!link($outerTemporaryPath, $outputPath)) {
        fail('Unable to publish build without overwriting an existing file: ' . $outputPath);
    }

    $publishedPackage = $outputPath;
    $checksumHandle = fopen($checksumPath, 'x');

    if ($checksumHandle === false) {
        fail('Unable to create checksum without overwriting an existing file: ' . $checksumPath);
    }

    $publishedChecksum = $checksumPath;

    try {
        $checksumLine = $checksum . '  ' . $outerFilename . PHP_EOL;

        if (fwrite($checksumHandle, $checksumLine) !== strlen($checksumLine)) {
            fail('Unable to write complete checksum file: ' . $checksumPath);
        }
    } finally {
        fclose($checksumHandle);
    }

    removeTemporaryDirectory($temporaryDirectory);
    $temporaryDirectory = null;

    $sourceIntegrityAfter = sourceIntegrityInventory($sourceRoot);
    $sourceChanges = compareSourceInventories($sourceIntegrityBefore, $sourceIntegrityAfter);

    if ($sourceChanges !== []) {
        if ($publishedPackage !== null && is_file($publishedPackage) && !unlink($publishedPackage)) {
            fwrite(STDERR, 'Unable to remove package after source integrity failure: ' . $publishedPackage . PHP_EOL);
        }

        if ($publishedChecksum !== null && is_file($publishedChecksum) && !unlink($publishedChecksum)) {
            fwrite(STDERR, 'Unable to remove checksum after source integrity failure: ' . $publishedChecksum . PHP_EOL);
        }

        fwrite(STDERR, "Source integrity: FAIL\n");

        foreach ($sourceChanges as $change) {
            fwrite(STDERR, '- ' . $change . PHP_EOL);
        }

        exit(1);
    }

    $publishedPackage = null;
    $publishedChecksum = null;

    fwrite(STDOUT, 'Source integrity: PASS' . PHP_EOL);
    fwrite(STDOUT, 'Built: ' . $outputPath . PHP_EOL);
    fwrite(STDOUT, 'SHA-256: ' . $checksum . PHP_EOL);
} catch (Throwable $error) {
    if ($temporaryDirectory !== null) {
        try {
            removeTemporaryDirectory($temporaryDirectory);
        } catch (Throwable $cleanupError) {
            fwrite(STDERR, 'Cleanup failed: ' . $cleanupError->getMessage() . PHP_EOL);
        }
    }

    if ($publishedPackage !== null && is_file($publishedPackage) && !unlink($publishedPackage)) {
        fwrite(STDERR, 'Unable to remove incomplete package: ' . $publishedPackage . PHP_EOL);
    }

    if ($publishedChecksum !== null && is_file($publishedChecksum) && !unlink($publishedChecksum)) {
        fwrite(STDERR, 'Unable to remove incomplete checksum: ' . $publishedChecksum . PHP_EOL);
    }

    fwrite(STDERR, 'Build failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
