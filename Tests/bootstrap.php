<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

// Start from a clean slate: the functional tests compile real containers into the
// system temp directory and a stale one would silently invalidate the assertions.
// The account screens' kernels too: they run without debug, so nothing rebuilds their
// container when the code changes — a constructor's new argument met the old container.
foreach (['jul6art-admin-bundle-tests', 'jul6art-admin-bundle-account-tests'] as $name) {
    $buildDir = sys_get_temp_dir().'/'.$name;

    if (!is_dir($buildDir)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($buildDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo) {
            continue;
        }

        if ($file->isDir()) {
            @rmdir($file->getPathname());
        } else {
            @unlink($file->getPathname());
        }
    }

    @rmdir($buildDir);
}
