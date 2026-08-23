<?php

namespace Tests\Feature\Integrity;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class PhpOpeningTagTest extends TestCase
{
    /** Every source PHP file must start with the exact bytes "<?php" — no BOM, no leading whitespace or blank lines. */
    public function test_all_php_source_files_start_with_php_tag(): void
    {
        $roots = ['app', 'config', 'database', 'routes', 'tests'];
        $offenders = [];

        foreach ($roots as $root) {
            $dir = base_path($root);

            if (! is_dir($dir)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                // Blade templates legitimately begin with markup, not <?php.
                if (str_ends_with($file->getFilename(), '.blade.php')) {
                    continue;
                }

                $handle = fopen($file->getPathname(), 'rb');
                $head = $handle ? fread($handle, 5) : '';

                if ($handle) {
                    fclose($handle);
                }

                if ($head !== '<?php') {
                    $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
                }
            }
        }

        sort($offenders);

        $this->assertSame(
            [],
            $offenders,
            'These PHP files do not start with the exact bytes "<?php" (a missing tag, '
                ."leading whitespace/blank lines, or a BOM breaks autoload output and namespaces):\n - "
                .implode("\n - ", $offenders)
        );
    }
}
