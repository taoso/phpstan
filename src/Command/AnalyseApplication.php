<?php declare(strict_types = 1);

namespace PHPStan\Command;

use PHPStan\Analyser\Analyser;
use PHPStan\Analyser\Error;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

class AnalyseApplication
{

    /**
     * @var \PHPStan\Analyser\Analyser
     */
    private $analyser;

    /**
     * @var string[]
     */
    private $fileExtensions;

    /**
     * @var string[]
     */
    private $ignorePathPatterns;

    public function __construct(
        Analyser $analyser,
        array $ignorePathPatterns,
        array $fileExtensions
    ) {
        $this->analyser = $analyser;
        $this->fileExtensions = $fileExtensions;
        $this->ignorePathPatterns = $ignorePathPatterns;
    }

    public function analyse(array $paths, OutputInterface $stdout, OutputInterface $stderr): int
    {
        $errors = [];
        $files = [];

        foreach ($paths as $path) {
            if (!file_exists($path)) {
                $stderr->writeln("Path $path does not exist");
                return 0;
            } elseif (is_file($path)) {
                $files[] = $path;
            } else {
                $finder = new Finder();
                $finder->filter(function (\SplFileInfo $info) {
                    foreach ($this->ignorePathPatterns as $pattern) {
                        if (fnmatch($pattern, $info->getPath())) {
                            return 0;
                        }
                    }
                    return 1;
                });
                foreach ($finder->files()->name('*.{' . implode(',', $this->fileExtensions) . '}')->in($path) as $fileInfo) {
                    $files[] = $fileInfo->getPathname();
                }
            }
        }

        $errors = array_merge($errors, $this->analyser->analyse($files));

        if (count($errors) === 0) {
            return 0;
        }

        foreach ($errors as $error) {
            $stdout->writeln((string)$error);
        }

        return 1;
    }
}
