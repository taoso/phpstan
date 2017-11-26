<?php declare(strict_types = 1);

namespace PHPStan\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\StreamOutput;

class AnalyseApplicationIntegrationTest extends \PHPStan\TestCase
{
    public function testExecuteOnADirectoryWithoutFiles()
    {
        $path = __DIR__ . '/test';
        @mkdir($path);
        chmod($path, 0777);
        $output = $this->runPath($path, 0);
        @unlink($path);

        $this->assertContains('', $output);
    }

    public function testExecuteOnAFile()
    {
        $output = $this->runPath(__DIR__ . '/data/file-without-errors.php', 0);
        $this->assertContains('', $output);
    }

    public function testExecuteOnANonExistentPath()
    {
        $path = __DIR__ . '/foo';
        $output = $this->runPath($path, 0);
        $this->assertContains(sprintf(
            'Path %s does not exist',
            $path
        ), $output);
    }

    public function testExecuteOnAFileWithErrors()
    {
        $path = __DIR__ . '/../Rules/Functions/data/nonexistent-function.php';
        $output = $this->runPath($path, 1);
        $this->assertContains('Function foobarNonExistentFunction not found', $output);
    }

    private function runPath(string $path, int $expectedStatusCode): string
    {
        $analyserApplication = $this->getContainer()->get(AnalyseApplication::class);
        $output = new StreamOutput(fopen('php://memory', 'w', false));

        $statusCode = $analyserApplication->analyse([$path], $output, $output, false);
        $this->assertSame($expectedStatusCode, $statusCode);

        rewind($output->getStream());

        return stream_get_contents($output->getStream());
    }
}
