<?php
/**
 * Validate a DevArt source tree without modifying it.
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

// DevArt platform policy — edit only when the production baseline changes.
const DEVART_JOOMLA_PRODUCTION_SERIES = 6;
const DEVART_JOOMLA_MINIMUM = '6.0.0';
const DEVART_JOOMLA_WORKSPACE = '../joomla';
const DEVART_PHP_MINIMUM = '8.3.0';
const DEVART_PHP_COMPATIBILITY_TARGETS = ['8.3', '8.4', '8.5'];

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
function filesWithExtension(string $directory, string $extension): array
{
    $baseDirectory = rtrim($directory, DIRECTORY_SEPARATOR);
    $filter = new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($baseDirectory, FilesystemIterator::SKIP_DOTS),
        static function (SplFileInfo $item) use ($baseDirectory): bool {
            $path = $item->getPathname();
            $relativePath = substr($path, strlen($baseDirectory) + 1);

            if (isExcluded($relativePath)) {
                return false;
            }

            if ($item->isLink()) {
                fail('Symbolic links are not allowed in source: ' . $path);
            }

            return true;
        }
    );
    $iterator = new RecursiveIteratorIterator(
        $filter
    );
    $files = [];

    foreach ($iterator as $item) {
        if ($item->isFile() && strtolower($item->getExtension()) === $extension) {
            $files[] = $item->getPathname();
        }
    }

    sort($files, SORT_STRING);

    return $files;
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
            fail('Symbolic links are not allowed in source: ' . $path);
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
function loadXml(string $path): SimpleXMLElement
{
    if (!is_file($path) || !is_readable($path)) {
        fail('XML file is missing or unreadable: ' . $path);
    }

    $previous = libxml_use_internal_errors(true);
    libxml_clear_errors();
    $xml = simplexml_load_file($path, SimpleXMLElement::class, LIBXML_NONET);
    $errors = libxml_get_errors();
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if ($xml === false) {
        $detail = $errors !== [] ? trim($errors[0]->message) : 'unknown XML error';
        fail('Invalid XML ' . $path . ': ' . $detail);
    }

    return $xml;
}

/**
 * @param list<string> $errors
 */
function validateManifestCreationDate(
    SimpleXMLElement $manifest,
    string $manifestPath,
    array &$errors
): void {
    $isPresent = isset($manifest->creationDate);
    $value = $isPresent ? (string) $manifest->creationDate : '';
    $isValid = preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) === 1;

    if ($isValid) {
        $date = DateTime::createFromFormat('Y-m-d', $value);
        $dateErrors = DateTime::getLastErrors();
        $isValid = $date !== false
            && ($dateErrors === false || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0))
            && $date->format('Y-m-d') === $value;
    }

    if (!$isValid) {
        $currentValue = !$isPresent ? '(missing)' : ($value !== '' ? $value : '(empty)');
        $errors[] = sprintf(
            'Invalid creationDate in %s: current value "%s"; expected format YYYY-MM-DD.',
            $manifestPath,
            $currentValue
        );
    }
}

/**
 * @param list<array{path: string, xml: SimpleXMLElement}> $manifests
 * @param list<string>                                    $errors
 */
function validateManifestVersions(array $manifests, string $repositoryRoot, array &$errors): void
{
    $packageManifest = null;

    foreach ($manifests as $manifest) {
        if ((string) $manifest['xml']['type'] === 'package') {
            $packageManifest = $manifest;
            break;
        }
    }

    if ($packageManifest === null) {
        return;
    }

    $expectedVersion = trim((string) $packageManifest['xml']->version);

    if ($expectedVersion === '') {
        return;
    }

    $pathPrefix = rtrim($repositoryRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    foreach ($manifests as $manifest) {
        $version = trim((string) $manifest['xml']->version);

        if ($version === $expectedVersion) {
            continue;
        }

        $manifestPath = $manifest['path'];

        if (str_starts_with($manifestPath, $pathPrefix)) {
            $manifestPath = substr($manifestPath, strlen($pathPrefix));
        }

        $errors[] = sprintf(
            "Version mismatch\n%s\nfound: %s\nexpected: %s",
            str_replace(DIRECTORY_SEPARATOR, '/', $manifestPath),
            $version !== '' ? $version : '(missing)',
            $expectedVersion
        );
    }
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

function normalizeArchiveFolder(string $folder, array &$errors): string
{
    $normalized = trim(str_replace('\\', '/', $folder), '/');

    if (
        str_starts_with(str_replace('\\', '/', $folder), '/')
        || preg_match('~(^|/)\.\.(/|$)~', $normalized) === 1
    ) {
        $errors[] = 'Unsafe package files folder: ' . $folder;
    }

    return $normalized;
}

/**
 * @param list<string> $errors
 */
function requirePath(string $baseDirectory, string $relativePath, string $context, array &$errors): void
{
    $relativePath = trim(str_replace('\\', '/', $relativePath), '/');

    if (
        $relativePath === ''
        || str_starts_with($relativePath, '/')
        || preg_match('~(^|/)\.\.(/|$)~', $relativePath) === 1
    ) {
        $errors[] = 'Unsafe or empty manifest path in ' . $context . ': ' . $relativePath;
        return;
    }

    $path = $baseDirectory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

    if (!file_exists($path)) {
        $errors[] = 'Missing manifest reference in ' . $context . ': ' . $relativePath;
    }
}

/**
 * Validate conventional Joomla file, language, media, SQL and schema references.
 *
 * @param list<string> $errors
 */
function validateManifestReferences(
    SimpleXMLElement $manifest,
    string $manifestPath,
    string $extensionDirectory,
    array &$errors,
    bool $validateFilesSection = true
): void {
    $context = $manifestPath;

    if (isset($manifest->scriptfile)) {
        requirePath($extensionDirectory, (string) $manifest->scriptfile, $context, $errors);
    }

    if ($validateFilesSection && isset($manifest->files)) {
        $folder = trim((string) $manifest->files['folder'], '/');
        $base = $folder === ''
            ? $extensionDirectory
            : $extensionDirectory . DIRECTORY_SEPARATOR . $folder;

        foreach ($manifest->files->children() as $entry) {
            requirePath($base, (string) $entry, $context, $errors);
        }
    }

    if (isset($manifest->languages)) {
        $folder = trim((string) $manifest->languages['folder'], '/');
        $base = $folder === ''
            ? $extensionDirectory
            : $extensionDirectory . DIRECTORY_SEPARATOR . $folder;

        foreach ($manifest->languages->language as $language) {
            requirePath($base, (string) $language, $context, $errors);
        }
    }

    if (isset($manifest->media)) {
        $folder = trim((string) $manifest->media['folder'], '/');
        $base = $folder === ''
            ? $extensionDirectory
            : $extensionDirectory . DIRECTORY_SEPARATOR . $folder;

        foreach ($manifest->media->children() as $entry) {
            requirePath($base, (string) $entry, $context, $errors);
        }
    }

    if (isset($manifest->administration)) {
        if (isset($manifest->administration->files)) {
            $folder = trim((string) $manifest->administration->files['folder'], '/');
            $base = $folder === ''
                ? $extensionDirectory
                : $extensionDirectory . DIRECTORY_SEPARATOR . $folder;

            foreach ($manifest->administration->files->children() as $entry) {
                requirePath($base, (string) $entry, $context, $errors);
            }
        }

        if (isset($manifest->administration->languages)) {
            $folder = trim((string) $manifest->administration->languages['folder'], '/');
            $base = $folder === ''
                ? $extensionDirectory
                : $extensionDirectory . DIRECTORY_SEPARATOR . $folder;

            foreach ($manifest->administration->languages->language as $language) {
                requirePath($base, (string) $language, $context, $errors);
            }
        }
    }

    foreach (['install', 'uninstall'] as $operation) {
        if (isset($manifest->{$operation}->sql)) {
            $sqlBase = $extensionDirectory;

            if (
                (string) $manifest['type'] === 'component'
                && isset($manifest->administration->files)
            ) {
                $administrationFolder = trim(
                    (string) $manifest->administration->files['folder'],
                    '/'
                );

                if ($administrationFolder !== '') {
                    $sqlBase .= DIRECTORY_SEPARATOR . $administrationFolder;
                }
            }

            foreach ($manifest->{$operation}->sql->file as $file) {
                requirePath($sqlBase, (string) $file, $context, $errors);
            }
        }
    }

    if (isset($manifest->update->schemas)) {
        $schemaBase = $extensionDirectory;

        if (
            (string) $manifest['type'] === 'component'
            && isset($manifest->administration->files)
        ) {
            $administrationFolder = trim(
                (string) $manifest->administration->files['folder'],
                '/'
            );

            if ($administrationFolder !== '') {
                $schemaBase .= DIRECTORY_SEPARATOR . $administrationFolder;
            }
        }

        foreach ($manifest->update->schemas->schemapath as $schemaPath) {
            requirePath($schemaBase, (string) $schemaPath, $context, $errors);
        }
    }
}

/**
 * @return array{path: string, xml: SimpleXMLElement}
 */
function findExtensionManifest(string $extensionDirectory, string $expectedType): array
{
    $candidates = glob($extensionDirectory . DIRECTORY_SEPARATOR . '*.xml');

    if ($candidates === false) {
        fail('Unable to inspect manifests in: ' . $extensionDirectory);
    }

    $matches = [];

    foreach ($candidates as $candidate) {
        $xml = loadXml($candidate);

        if ($xml->getName() === 'extension' && (string) $xml['type'] === $expectedType) {
            $matches[] = ['path' => $candidate, 'xml' => $xml];
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
 * @return array{status: int, output: string}
 */
function lintPhp(string $path): array
{
    $command = [PHP_BINARY, '-l', $path];
    $descriptors = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptors, $pipes);

    if (!is_resource($process)) {
        fail('Unable to start PHP syntax validation for: ' . $path);
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);

    return [
        'status' => $status,
        'output' => trim((string) $stdout . PHP_EOL . (string) $stderr),
    ];
}

function validatePhpRuntime(): void
{
    $minimumId = (int) str_replace('.', '', DEVART_PHP_MINIMUM);

    if (PHP_VERSION_ID < $minimumId) {
        fail('PHP ' . DEVART_PHP_MINIMUM . ' or newer is required for validation.');
    }

    $series = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;

    if (in_array($series, DEVART_PHP_COMPATIBILITY_TARGETS, true)) {
        fwrite(STDOUT, 'PHP compatibility target: ' . $series . ' (matched)' . PHP_EOL);
    } else {
        fwrite(STDOUT, 'Warning: PHP ' . PHP_VERSION
            . ' meets the minimum requirement but is not listed as a declared compatibility target ('
            . implode(', ', DEVART_PHP_COMPATIBILITY_TARGETS) . ').' . PHP_EOL);
    }

    $requiredExtensions = ['json', 'mbstring', 'dom', 'simplexml', 'zlib'];
    $missingRequired = [];

    foreach ($requiredExtensions as $extension) {
        if (!extension_loaded($extension)) {
            $missingRequired[] = $extension;
        }
    }

    if ($missingRequired !== []) {
        fail('Missing required PHP extensions: ' . implode(', ', $missingRequired));
    }

    if (!extension_loaded('mysqli') && !extension_loaded('pdo_mysql')) {
        fail('A MySQL database driver is required: mysqli or pdo_mysql.');
    }

    $recommendedExtensions = ['curl', 'gd', 'openssl', 'xml', 'zip', 'opcache'];
    $missingRecommended = [];

    foreach ($recommendedExtensions as $extension) {
        if (!extension_loaded($extension)) {
            $missingRecommended[] = $extension;
        }
    }

    if ($missingRecommended !== []) {
        fwrite(STDOUT, 'Warning: Missing recommended PHP extensions: '
            . implode(', ', $missingRecommended) . PHP_EOL);
    }

    if (!function_exists('proc_open')) {
        fail('The proc_open function is required for PHP syntax validation.');
    }

    fwrite(STDOUT, 'PHP minimum: ' . DEVART_PHP_MINIMUM . ' (pass)' . PHP_EOL);
    fwrite(STDOUT, 'PHP CLI version: ' . PHP_VERSION . PHP_EOL);
    fwrite(
        STDOUT,
        'Note: CLI PHP validation does not prove compatibility with the web-server PHP SAPI. '
        . 'Final confirmation requires Joomla runtime QA.' . PHP_EOL
    );
}

function extractJoomlaVersionConstant(string $content, string $constant): ?string
{
    $pattern = '/public const ' . preg_quote($constant, '/') . ' = ([^;]+);/';

    if (preg_match($pattern, $content, $matches) !== 1) {
        return null;
    }

    return trim($matches[1], " \t\n\r\0\x0B'\"");
}

/**
 * @return array{
 *     major: int,
 *     minor: int,
 *     patch: int,
 *     extra: string,
 *     dev_status: string,
 *     version: string
 * }|null
 */
function parseJoomlaVersionFile(string $path): ?array
{
    $content = file_get_contents($path);

    if ($content === false) {
        return null;
    }

    $major = extractJoomlaVersionConstant($content, 'MAJOR_VERSION');
    $minor = extractJoomlaVersionConstant($content, 'MINOR_VERSION');
    $patch = extractJoomlaVersionConstant($content, 'PATCH_VERSION');

    if ($major === null || $minor === null || $patch === null) {
        return null;
    }

    $extra = extractJoomlaVersionConstant($content, 'EXTRA_VERSION') ?? '';
    $devStatus = extractJoomlaVersionConstant($content, 'DEV_STATUS') ?? '';
    $version = $major . '.' . $minor . '.' . $patch;

    if ($extra !== '') {
        $version .= '-' . $extra;
    }

    return [
        'major'      => (int) $major,
        'minor'      => (int) $minor,
        'patch'      => (int) $patch,
        'extra'      => $extra,
        'dev_status' => $devStatus,
        'version'    => $version,
    ];
}

/**
 * @return list<array{root: string, version: array{
 *     major: int,
 *     minor: int,
 *     patch: int,
 *     extra: string,
 *     dev_status: string,
 *     version: string
 * }}>
 */
function discoverJoomlaRoots(string $workspacePath, int $maxDepth = 3): array
{
    if (!is_dir($workspacePath)) {
        return [];
    }

    $workspacePath = realpath($workspacePath);

    if ($workspacePath === false) {
        return [];
    }

    $roots = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($workspacePath, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    $iterator->setMaxDepth($maxDepth);

    foreach ($iterator as $item) {
        if (!$item->isFile()) {
            continue;
        }

        $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($workspacePath) + 1));

        if ($relative !== 'libraries/src/Version.php' && !str_ends_with($relative, '/libraries/src/Version.php')) {
            continue;
        }

        $root = dirname($item->getPathname(), 3);
        $version = parseJoomlaVersionFile($item->getPathname());

        if ($version === null) {
            continue;
        }

        $roots[$root] = [
            'root'    => $root,
            'version' => $version,
        ];
    }

    $discovered = array_values($roots);
    usort(
        $discovered,
        static fn(array $left, array $right): int => strcmp($left['root'], $right['root'])
    );

    return $discovered;
}

function classifyJoomlaRoot(array $version): string
{
    $major = $version['major'];
    $fullVersion = $version['major'] . '.' . $version['minor'] . '.' . $version['patch'];
    $isPrerelease = $version['extra'] !== ''
        || strcasecmp($version['dev_status'], 'Stable') !== 0;

    if ($isPrerelease) {
        return 'prerelease/preview (advisory only)';
    }

    if ($major < DEVART_JOOMLA_PRODUCTION_SERIES) {
        return 'older major (not a production compatibility source)';
    }

    if ($major > DEVART_JOOMLA_PRODUCTION_SERIES) {
        return 'future major (advisory only)';
    }

    if (version_compare($fullVersion, DEVART_JOOMLA_MINIMUM, '<')) {
        return 'production-series below minimum';
    }

    return 'production-series match';
}

function validateJoomlaWorkspace(string $repositoryRoot): void
{
    $workspacePath = $repositoryRoot . DIRECTORY_SEPARATOR . str_replace(
        '/',
        DIRECTORY_SEPARATOR,
        DEVART_JOOMLA_WORKSPACE
    );

    fwrite(STDOUT, 'Joomla workspace: ' . DEVART_JOOMLA_WORKSPACE . PHP_EOL);
    fwrite(STDOUT, 'Joomla production series: ' . DEVART_JOOMLA_PRODUCTION_SERIES . PHP_EOL);
    fwrite(STDOUT, 'Joomla minimum: ' . DEVART_JOOMLA_MINIMUM . PHP_EOL);

    if (!is_dir($workspacePath)) {
        fwrite(STDOUT, 'Warning: Local Joomla workspace was not found. '
            . 'Template validation continues without a local Joomla source reference.' . PHP_EOL);

        return;
    }

    $discovered = discoverJoomlaRoots($workspacePath);

    if ($discovered === []) {
        fwrite(STDOUT, 'Warning: No Joomla roots were discovered under '
            . DEVART_JOOMLA_WORKSPACE . '.' . PHP_EOL);

        return;
    }

    foreach ($discovered as $entry) {
        $classification = classifyJoomlaRoot($entry['version']);
        $line = sprintf(
            'Joomla root: %s | version %s | %s',
            $entry['root'],
            $entry['version']['version'],
            $classification
        );

        if (
            $classification === 'production-series below minimum'
            || $classification === 'older major (not a production compatibility source)'
        ) {
            fwrite(STDOUT, 'Warning: ' . $line . PHP_EOL);
        } elseif (
            $classification === 'future major (advisory only)'
            || $classification === 'prerelease/preview (advisory only)'
        ) {
            fwrite(STDOUT, 'Warning: ' . $line . PHP_EOL);
        } else {
            fwrite(STDOUT, $line . ' (pass)' . PHP_EOL);
        }
    }

    fwrite(
        STDOUT,
        'Note: Discovering local Joomla source does not prove runtime compatibility in a deployed site.'
        . PHP_EOL
    );
}

/**
 * @param list<string> $errors
 */
function validatePhpMinimumExact(string $value, string $context, array &$errors): void
{
    if ($value !== DEVART_PHP_MINIMUM) {
        $errors[] = sprintf(
            '%s must declare php_minimum exactly %s; found %s.',
            $context,
            DEVART_PHP_MINIMUM,
            $value !== '' ? $value : '(missing)'
        );
    }
}

/**
 * @param list<string> $errors
 */
function validatePackageIdentity(
    string $packageManifestPath,
    SimpleXMLElement $packageManifest,
    array &$errors
): void {
    $manifestStem = basename($packageManifestPath, '.xml');

    if (!str_starts_with($manifestStem, 'pkg_')) {
        $errors[] = 'Package manifest filename must use the pkg_ prefix: ' . $packageManifestPath;
    }

    $expectedPackageName = str_starts_with($manifestStem, 'pkg_')
        ? substr($manifestStem, 4)
        : $manifestStem;
    $packageName = trim((string) $packageManifest->packagename);
    $packageVersion = trim((string) $packageManifest->version);

    if ($packageName !== $expectedPackageName) {
        $errors[] = sprintf(
            'Package packagename must match the pkg_ manifest stem without the prefix: expected %s, found %s.',
            $expectedPackageName,
            $packageName !== '' ? $packageName : '(missing)'
        );
    }

    if ($packageVersion !== '') {
        $expectedOuterZip = $manifestStem . '_v' . $packageVersion . '.zip';
        fwrite(STDOUT, 'Expected outer package filename: ' . $expectedOuterZip . PHP_EOL);
    }
}

/**
 * @param list<string> $errors
 */
function validateExtensionManifestIdentity(
    string $type,
    string $directoryName,
    string $manifestPath,
    SimpleXMLElement $manifest,
    string $declaredId,
    string $declaredGroup,
    array &$errors
): void {
    $manifestFilename = basename($manifestPath);

    if ($type === 'component') {
        if (!str_starts_with($directoryName, 'com_')) {
            $errors[] = 'Component directory must use the com_ prefix: ' . $directoryName;
        }

        if ($declaredId !== $directoryName) {
            $errors[] = 'Component package id must match directory: ' . $directoryName;
        }

        if ((string) $manifest['type'] !== 'component') {
            $errors[] = sprintf(
                'Component manifest type mismatch for %s (manifest file: %s).',
                $directoryName,
                $manifestFilename
            );
        }

        return;
    }

    if ($type === 'module') {
        if (!str_starts_with($directoryName, 'mod_')) {
            $errors[] = 'Module directory must use the mod_ prefix: ' . $directoryName;
        }

        if ($manifestFilename !== $directoryName . '.xml') {
            $errors[] = sprintf(
                'Module manifest filename must be %s.xml; found %s.',
                $directoryName,
                $manifestFilename
            );
        }

        if ($declaredId !== $directoryName) {
            $errors[] = 'Module package id must match directory and manifest stem: ' . $directoryName;
        }

        return;
    }

    if ($type !== 'plugin') {
        return;
    }

    $manifestGroup = trim((string) $manifest['group']);

    if ($manifestGroup === '' || $manifestGroup !== $declaredGroup) {
        $errors[] = 'Plugin manifest group must match the package inventory group for: ' . $directoryName;
    }

    $pluginId = '';

    foreach ($manifest->files->children() as $entry) {
        if (isset($entry['plugin'])) {
            $pluginId = trim((string) $entry['plugin']);
            break;
        }
    }

    $expectedDirectory = 'plg_' . $declaredGroup . '_' . $pluginId;
    $expectedManifest = $pluginId . '.xml';

    if ($directoryName !== $expectedDirectory) {
        $errors[] = sprintf(
            'Plugin directory must be %s; found %s.',
            $expectedDirectory,
            $directoryName
        );
    }

    if ($manifestFilename !== $expectedManifest) {
        $errors[] = sprintf(
            'Plugin manifest filename must be %s; found %s.',
            $expectedManifest,
            $manifestFilename
        );
    }
}

/**
 * @return list<string>
 */
function findLocalMetadataXmlFiles(string $packageDirectory, string $basename): array
{
    $patterns = [
        $packageDirectory . DIRECTORY_SEPARATOR . $basename,
        $packageDirectory . DIRECTORY_SEPARATOR . '*' . $basename,
    ];
    $matches = [];

    foreach ($patterns as $pattern) {
        $candidates = glob($pattern);

        if ($candidates === false) {
            continue;
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                $matches[] = $candidate;
            }
        }
    }

    $matches = array_values(array_unique($matches));
    sort($matches, SORT_STRING);

    return $matches;
}

/**
 * @param list<string> $errors
 */
function validateLocalUpdateMetadata(
    string $packageDirectory,
    string $packageManifestStem,
    string $packageVersion,
    array &$errors
): void {
    foreach (findLocalMetadataXmlFiles($packageDirectory, 'update.xml') as $updatePath) {
        try {
            $xml = loadXml($updatePath);
        } catch (Throwable $error) {
            $errors[] = $error->getMessage();
            continue;
        }

        if (!isset($xml->update)) {
            continue;
        }

        foreach ($xml->update as $updateNode) {
            $element = trim((string) $updateNode->element);
            $type = trim((string) $updateNode->type);
            $version = trim((string) $updateNode->version);

            if ($element !== '' && $element !== $packageManifestStem) {
                $errors[] = sprintf(
                    'Update metadata element mismatch in %s: expected %s, found %s.',
                    $updatePath,
                    $packageManifestStem,
                    $element
                );
            }

            if ($type !== '' && $type !== 'package') {
                $errors[] = 'Update metadata type must be package in ' . $updatePath;
            }

            if ($version !== '' && $version !== $packageVersion) {
                $errors[] = sprintf(
                    'Update metadata version mismatch in %s: expected %s, found %s.',
                    $updatePath,
                    $packageVersion,
                    $version
                );
            }

            if (isset($updateNode->downloads->downloadurl)) {
                $filename = basename((string) $updateNode->downloads->downloadurl);

                if (
                    $filename !== ''
                    && $filename !== $packageManifestStem . '_v' . $packageVersion . '.zip'
                ) {
                    $errors[] = sprintf(
                        'Update metadata download filename mismatch in %s: expected %s, found %s.',
                        $updatePath,
                        $packageManifestStem . '_v' . $packageVersion . '.zip',
                        $filename
                    );
                }
            }
        }
    }
}

/**
 * @param list<string> $errors
 */
function validateLocalChangelogMetadata(
    string $packageDirectory,
    string $packageManifestStem,
    string $packageVersion,
    array &$errors
): void {
    foreach (findLocalMetadataXmlFiles($packageDirectory, 'changelog.xml') as $changelogPath) {
        try {
            $xml = loadXml($changelogPath);
        } catch (Throwable $error) {
            $errors[] = $error->getMessage();
            continue;
        }

        if (!isset($xml->changelog)) {
            continue;
        }

        foreach ($xml->changelog as $changelogNode) {
            $element = trim((string) $changelogNode->element);
            $type = trim((string) $changelogNode->type);
            $version = trim((string) $changelogNode->version);

            if ($element !== '' && $element !== $packageManifestStem) {
                $errors[] = sprintf(
                    'Changelog metadata element mismatch in %s: expected %s, found %s.',
                    $changelogPath,
                    $packageManifestStem,
                    $element
                );
            }

            if ($type !== '' && $type !== 'package') {
                $errors[] = 'Changelog metadata type must be package in ' . $changelogPath;
            }

            if ($version !== '' && $version !== $packageVersion) {
                $errors[] = sprintf(
                    'Changelog metadata version mismatch in %s: expected %s, found %s.',
                    $changelogPath,
                    $packageVersion,
                    $version
                );
            }
        }
    }
}

/**
 * @return list<string>
 */
function findUnresolvedPlaceholders(string $content): array
{
    if (preg_match_all('/\{\{[A-Z0-9_]+\}\}/', $content, $matches) < 1) {
        return [];
    }

    return array_values(array_unique($matches[0]));
}

/**
 * @param list<string> $errors
 */
function validateUnresolvedPlaceholders(string $repositoryRoot, array &$errors): void
{
    $files = [
        $repositoryRoot . DIRECTORY_SEPARATOR . 'README.md',
        $repositoryRoot . DIRECTORY_SEPARATOR . 'PROJECT_STATUS.md',
        $repositoryRoot . DIRECTORY_SEPARATOR . 'CHANGELOG.md',
        $repositoryRoot . DIRECTORY_SEPARATOR . 'AGENTS.md',
        $repositoryRoot . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'architecture.md',
        $repositoryRoot . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'current-state.md',
        $repositoryRoot . DIRECTORY_SEPARATOR . 'qa' . DIRECTORY_SEPARATOR . 'checklist.md',
    ];

    $sourceRoot = $repositoryRoot . DIRECTORY_SEPARATOR . 'source';
    $sourceExtensions = ['php', 'xml', 'json', 'ini'];

    foreach ($sourceExtensions as $extension) {
        foreach (filesWithExtension($sourceRoot, $extension) as $path) {
            $files[] = $path;
        }
    }

    $files = array_values(array_unique($files));
    sort($files, SORT_STRING);

    foreach ($files as $file) {
        $contents = file_get_contents($file);

        if ($contents === false) {
            $errors[] = 'Unable to read file for placeholder validation: ' . $file;
            continue;
        }

        $placeholders = findUnresolvedPlaceholders($contents);

        if ($placeholders === []) {
            continue;
        }

        $relative = str_starts_with($file, $repositoryRoot . DIRECTORY_SEPARATOR)
            ? substr($file, strlen($repositoryRoot) + 1)
            : $file;

        $errors[] = sprintf(
            'Unresolved placeholders in %s: %s',
            str_replace(DIRECTORY_SEPARATOR, '/', $relative),
            implode(', ', $placeholders)
        );
    }
}

try {
    validatePhpRuntime();

    if (!function_exists('simplexml_load_file')) {
        fail('The PHP SimpleXML extension is required.');
    }

    $repositoryRoot = dirname(__DIR__);
    validateJoomlaWorkspace($repositoryRoot);
    $sourceRoot = $repositoryRoot . DIRECTORY_SEPARATOR . 'source';
    $packageDirectory = $sourceRoot . DIRECTORY_SEPARATOR . 'package';
    $extensionsRoot = $sourceRoot . DIRECTORY_SEPARATOR . 'extensions';
    $typeDirectories = [
        'component' => 'component',
        'module'    => 'modules',
        'plugin'    => 'plugins',
    ];
    $errors = [];

    foreach ([$sourceRoot, $packageDirectory, $extensionsRoot] as $requiredDirectory) {
        if (!is_dir($requiredDirectory) || !is_readable($requiredDirectory)) {
            $errors[] = 'Required directory is missing or unreadable: ' . $requiredDirectory;
        }
    }

    if ($errors !== []) {
        fail(implode(PHP_EOL, $errors));
    }

    $xmlFiles = filesWithExtension($sourceRoot, 'xml');
    $extensionManifests = [];

    foreach ($xmlFiles as $xmlFile) {
        try {
            $xml = loadXml($xmlFile);
        } catch (Throwable $error) {
            $errors[] = $error->getMessage();
            continue;
        }

        if (
            $xml->getName() === 'extension'
            && in_array((string) $xml['type'], ['package', 'component', 'module', 'plugin'], true)
        ) {
            $extensionManifests[] = ['path' => $xmlFile, 'xml' => $xml];
            validateManifestCreationDate($xml, $xmlFile, $errors);
        }
    }

    validateManifestVersions($extensionManifests, $repositoryRoot, $errors);

    $packageManifestPath = findPackageManifest($packageDirectory);
    $packageManifest = loadXml($packageManifestPath);

    if ($packageManifest->getName() !== 'extension' || (string) $packageManifest['type'] !== 'package') {
        $errors[] = 'Unexpected package manifest root or type: ' . $packageManifestPath;
    }

    $packageName = trim((string) $packageManifest->packagename);
    $packageVersion = trim((string) $packageManifest->version);
    $packagePhpMinimum = trim((string) $packageManifest->php_minimum);

    if ($packageName === '' || preg_match('/^[A-Za-z0-9._-]+$/D', $packageName) !== 1) {
        $errors[] = 'Package manifest must declare a safe packagename.';
    }

    if ($packageVersion === '') {
        $errors[] = 'Package manifest version is missing.';
    }

    if ($packagePhpMinimum === '') {
        $errors[] = 'Package manifest php_minimum is missing.';
    } else {
        validatePhpMinimumExact($packagePhpMinimum, 'Package manifest', $errors);
    }

    validatePackageIdentity($packageManifestPath, $packageManifest, $errors);

    validateManifestReferences(
        $packageManifest,
        $packageManifestPath,
        $packageDirectory,
        $errors,
        false
    );

    $declaredDirectories = [
        'component' => [],
        'module'    => [],
        'plugin'    => [],
    ];
    $manifestCount = 1;

    if (!isset($packageManifest->files)) {
        $errors[] = 'Package manifest does not contain a <files> section.';
    } else {
        normalizeArchiveFolder((string) $packageManifest->files['folder'], $errors);
        $seenArchives = [];

        foreach ($packageManifest->files->file as $fileNode) {
            $type = trim((string) $fileNode['type']);
            $archiveName = trim((string) $fileNode);

            if (!isset($typeDirectories[$type])) {
                $errors[] = 'Unsupported package extension type: ' . $type;
                continue;
            }

            if (
                $archiveName === ''
                || basename($archiveName) !== $archiveName
                || !str_ends_with(strtolower($archiveName), '.zip')
            ) {
                $errors[] = 'Unsafe or invalid inner archive name: ' . $archiveName;
                continue;
            }

            if (isset($seenArchives[$archiveName])) {
                $errors[] = 'Duplicate package archive declaration: ' . $archiveName;
                continue;
            }

            $seenArchives[$archiveName] = true;
            $directoryName = substr($archiveName, 0, -4);
            $extensionDirectory = $extensionsRoot
                . DIRECTORY_SEPARATOR
                . $typeDirectories[$type]
                . DIRECTORY_SEPARATOR
                . $directoryName;

            if (!is_dir($extensionDirectory) || !is_readable($extensionDirectory)) {
                $errors[] = 'Declared extension source is missing or unreadable: ' . $extensionDirectory;
                continue;
            }

            try {
                $manifestInfo = findExtensionManifest($extensionDirectory, $type);
            } catch (Throwable $error) {
                $errors[] = $error->getMessage();
                continue;
            }

            $manifestCount++;
            $extensionManifest = $manifestInfo['xml'];
            $extensionPhpMinimum = trim((string) $extensionManifest->php_minimum);

            if ($extensionPhpMinimum === '') {
                $errors[] = $directoryName . ' php_minimum is missing.';
            } else {
                validatePhpMinimumExact($extensionPhpMinimum, $directoryName, $errors);
            }

            $declaredId = trim((string) $fileNode['id']);
            $declaredGroup = $type === 'plugin' ? trim((string) $fileNode['group']) : '';

            validateExtensionManifestIdentity(
                $type,
                $directoryName,
                $manifestInfo['path'],
                $extensionManifest,
                $declaredId,
                $declaredGroup,
                $errors
            );

            if ($type !== 'plugin' && $declaredId !== $directoryName) {
                $errors[] = 'Manifest id does not match source directory for: ' . $archiveName;
            }

            if ($type === 'plugin') {
                $group = trim((string) $fileNode['group']);

                if ($group === '' || (string) $extensionManifest['group'] !== $group) {
                    $errors[] = 'Plugin group mismatch for: ' . $archiveName;
                }

                $pluginId = '';

                foreach ($extensionManifest->files->children() as $entry) {
                    if (isset($entry['plugin'])) {
                        $pluginId = trim((string) $entry['plugin']);
                        break;
                    }
                }

                if ($pluginId === '' || $pluginId !== $declaredId) {
                    $errors[] = 'Plugin id mismatch for: ' . $archiveName;
                }
            }

            validateManifestReferences(
                $extensionManifest,
                $manifestInfo['path'],
                $extensionDirectory,
                $errors
            );

            $declaredDirectories[$type][] = $directoryName;
        }
    }

    validateLocalUpdateMetadata(
        $packageDirectory,
        basename($packageManifestPath, '.xml'),
        $packageVersion,
        $errors
    );
    validateLocalChangelogMetadata(
        $packageDirectory,
        basename($packageManifestPath, '.xml'),
        $packageVersion,
        $errors
    );

    foreach ($typeDirectories as $type => $relativeDirectory) {
        $categoryDirectory = $extensionsRoot . DIRECTORY_SEPARATOR . $relativeDirectory;

        if (!is_dir($categoryDirectory)) {
            if ($declaredDirectories[$type] === []) {
                continue;
            }

            $errors[] = 'Declared extension category directory is missing: ' . $categoryDirectory;
            continue;
        }

        if (!is_readable($categoryDirectory)) {
            $errors[] = 'Extension category directory is unreadable: ' . $categoryDirectory;
            continue;
        }

        $actual = childDirectories($categoryDirectory);
        $declared = array_values(array_unique($declaredDirectories[$type]));
        sort($declared, SORT_STRING);

        if ($actual !== $declared) {
            $errors[] = sprintf(
                'Package manifest and %s source directories differ. Declared: [%s]. Found: [%s].',
                $type,
                implode(', ', $declared),
                implode(', ', $actual)
            );
        }
    }

    $jsonFiles = filesWithExtension($sourceRoot, 'json');

    foreach ($jsonFiles as $jsonFile) {
        $contents = file_get_contents($jsonFile);

        if ($contents === false) {
            $errors[] = 'Unable to read JSON file: ' . $jsonFile;
            continue;
        }

        try {
            json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            $errors[] = 'Invalid JSON ' . $jsonFile . ': ' . $error->getMessage();
        }
    }

    $phpFiles = filesWithExtension($sourceRoot, 'php');

    foreach ($phpFiles as $phpFile) {
        $lint = lintPhp($phpFile);

        if ($lint['status'] !== 0) {
            $errors[] = 'PHP syntax error in ' . $phpFile . ': ' . $lint['output'];
        }
    }

    validateUnresolvedPlaceholders($repositoryRoot, $errors);

    if ($errors !== []) {
        fwrite(STDERR, "Validation failed:\n");

        foreach ($errors as $error) {
            fwrite(STDERR, '- ' . $error . PHP_EOL);
        }

        exit(1);
    }

    fwrite(STDOUT, "Validation passed.\n");
    fwrite(STDOUT, 'Extension manifests: ' . $manifestCount . PHP_EOL);
    fwrite(STDOUT, 'XML files: ' . count($xmlFiles) . PHP_EOL);
    fwrite(STDOUT, 'JSON files: ' . count($jsonFiles) . PHP_EOL);
    fwrite(STDOUT, 'PHP files: ' . count($phpFiles) . PHP_EOL);
} catch (Throwable $error) {
    fwrite(STDERR, 'Validation failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
