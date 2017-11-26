<?php declare(strict_types = 1);

namespace PHPStan\Command;

use PhpParser\Node\Stmt\Catch_;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use PHPStan\Rules\RegistryFactory;
use PHPStan\Reflection\PropertiesClassReflectionExtension;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;

class AnalyseCommand extends \Symfony\Component\Console\Command\Command
{
    const NAME = 'analyse';

    const OPTION_LEVEL = 'level';
    const OPTION_RULE = 'rule';
    const OPTION_AUTOLOAD_FILE = 'autoload-file';
    const OPTION_EXCLUED_RULE = 'exclude-rule';
    const OPTION_IGNORE_PATH = 'ignore-path';
    const OPTION_IGNORE_ERROR = 'ignore-error';
    const OPTION_EXTENSION = 'extension';

    const DEFAULT_LEVEL = 0;

    /**
     * @var \Psr\Container\ContainerInterface
     */
    private $container;

    protected function configure()
    {
        $this->setName(self::NAME)
            ->setDescription('Analyses source code')
            ->setDefinition([
                new InputArgument('paths', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'Paths with source code to run analysis on'),
                new InputOption(self::OPTION_LEVEL, 'l', InputOption::VALUE_REQUIRED, 'Level of rule options - the higher the stricter'),
                new InputOption(self::OPTION_AUTOLOAD_FILE, 'a', InputOption::VALUE_OPTIONAL|InputOption::VALUE_IS_ARRAY, 'Project\'s additional autoload file path'),
                new InputOption(self::OPTION_RULE, 'r', InputOption::VALUE_OPTIONAL|InputOption::VALUE_IS_ARRAY, "Rule to be used. use FQCN for custom rule. the builtin rules:\n".implode("\n", RegistryFactory::getRuleArgList(65535))),
                new InputOption(self::OPTION_EXCLUED_RULE, 'R', InputOption::VALUE_OPTIONAL|InputOption::VALUE_IS_ARRAY, "Rule to be excluded"),
                new InputOption(self::OPTION_IGNORE_PATH, 'P', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Fnmatch pattern for file path to be ignored'),
                new InputOption(self::OPTION_IGNORE_ERROR, 'E', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Preg pattern **WITHOUT DELIMITER** for error to be ignored'),
                new InputOption(self::OPTION_EXTENSION, 'x', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Extension class name to be used, must be FQCN'),
            ]);
    }

    public function getAliases(): array
    {
        return ['analyze'];
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initAutoload($input);
        $this->initContainer($input);
        $this->setMiscOptions($input);
        $this->initRules($input);

        $stderr = ($output instanceof ConsoleOutputInterface) ? $output->getErrorOutput() : $output;
        $paths = $input->getArgument('paths');

        $application = $this->container->get(AnalyseApplication::class);

        return $application->analyse($paths, $output, $stderr);
    }

    private function initAutoload(InputInterface $input)
    {
        $autoloadFiles = $input->getOption(self::OPTION_AUTOLOAD_FILE);
        foreach ($autoloadFiles as $autoloadFile) {
            if (is_file($autoloadFile)) {
                require_once $autoloadFile;
            }
        }
    }

    private function initContainer(InputInterface $input)
    {
        $builder = new \DI\ContainerBuilder();
        $builder->addDefinitions(__DIR__.'/../../conf/di.php');

        $extensionDefinitions = [];
        $extensionNames = $input->getOption(self::OPTION_EXTENSION);
        foreach ($extensionNames as $extensionName) {
            if (!class_exists($extensionName, true)) {
                continue;
            }
            $interfaces = class_implements($extensionName);
            foreach ($interfaces as $interface) {
                switch ($interface) {
                    case PropertiesClassReflectionExtension::class:
                    case MethodsClassReflectionExtension::class:
                    case DynamicMethodReturnTypeExtension::class:
                    case DynamicStaticMethodReturnTypeExtension::class:
                        $extensionDefinitions[$interface][] = \DI\get($extensionName);
                        break;
                }
            }
        }

        foreach ($extensionDefinitions as $interface => $extensions) {
            $builder->addDefinitions([
                $interface => \DI\add($extensions), // use add to append
            ]);
        }

        $container = $builder->build();
        $container->set(\Interop\Container\ContainerInterface::class, $container);

        $this->container = $container;

        return $this->container;
    }

    private function setMiscOptions(InputInterface $input)
    {
        $ignorePathPatterns = $input->getOption(self::OPTION_IGNORE_PATH);
        if ($ignorePathPatterns) {
            $this->container->set('ignorePathPatterns', $ignorePathPatterns);
        }
        $ignoreErrors = $input->getOption(self::OPTION_IGNORE_ERROR);

        if ($ignoreErrors) {
            $this->container->set('ignoreErrors', $ignoreErrors);
        }
    }

    private function initRules(InputInterface $input)
    {
        $level = (int)$input->getOption(self::OPTION_LEVEL);
        $level = $level ? $level : self::DEFAULT_LEVEL;

        switch ($level) {
        case 2:
            $this->container->set('checkThisOnly', false);
            break;
        case 5:
            $this->container->set('checkFunctionArgumentTypes', true);
            $this->container->set('enableUnionTypes', true);
            break;
        }

        $rules = $input->getOption(self::OPTION_RULE);
        if (!$rules) {
            $rules = RegistryFactory::getRuleArgList($level);
        }

        $excludeRules = $input->getOption(self::OPTION_EXCLUED_RULE);
        if ($excludeRules) {
            $rules = array_values(array_diff($rules, $excludeRules));
        }

        RegistryFactory::setRules($rules);
    }
}
