<?php declare(strict_types = 1);

namespace PHPStan\Command;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ErrorsConsoleStyle extends \Symfony\Component\Console\Style\SymfonyStyle
{
    /** @var \Symfony\Component\Console\Output\OutputInterface */
    private $output;

    public function __construct(InputInterface $input, OutputInterface $output, bool $showProgress = true)
    {
        parent::__construct($input, $output);
        $this->output = $output;
    }
}
