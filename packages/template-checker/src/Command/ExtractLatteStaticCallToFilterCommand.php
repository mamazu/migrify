<?php

declare(strict_types=1);

namespace Migrify\TemplateChecker\Command;

use Migrify\TemplateChecker\Finder\GenericFilesFinder;
use Migrify\TemplateChecker\LatteStaticCallAnalyzer;
use Migrify\TemplateChecker\PhpParser\LatteFilterProviderGenerator;
use Migrify\TemplateChecker\StaticCallWithFilterReplacer;
use Migrify\TemplateChecker\ValueObject\ClassMethodName;
use Migrify\TemplateChecker\ValueObject\Option;
use Nette\Utils\FileSystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\SplFileInfo;
use Symplify\PackageBuilder\Console\Command\CommandNaming;
use Symplify\PackageBuilder\Console\ShellCode;

final class ExtractLatteStaticCallToFilterCommand extends Command
{
    /**
     * @var ClassMethodName[]
     */
    private $classMethodNames = [];

    /**
     * @var LatteFilterProviderGenerator
     */
    private $latteFilterProviderGenerator;

    /**
     * @var LatteStaticCallAnalyzer
     */
    private $latteStaticCallAnalyzer;

    /**
     * @var StaticCallWithFilterReplacer
     */
    private $staticCallWithFilterReplacer;

    /**
     * @var GenericFilesFinder
     */
    private $genericFilesFinder;

    /**
     * @var SymfonyStyle
     */
    private $symfonyStyle;

    public function __construct(
        GenericFilesFinder $genericFilesFinder,
        SymfonyStyle $symfonyStyle,
        LatteFilterProviderGenerator $latteFilterProviderGenerator,
        LatteStaticCallAnalyzer $latteStaticCallAnalyzer,
        StaticCallWithFilterReplacer $staticCallWithFilterReplacer
    ) {
        $this->genericFilesFinder = $genericFilesFinder;
        $this->symfonyStyle = $symfonyStyle;
        $this->latteFilterProviderGenerator = $latteFilterProviderGenerator;
        $this->latteStaticCallAnalyzer = $latteStaticCallAnalyzer;
        $this->staticCallWithFilterReplacer = $staticCallWithFilterReplacer;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName(CommandNaming::classToName(self::class));

        $this->addArgument(
            Option::SOURCES,
            InputArgument::REQUIRED | InputArgument::IS_ARRAY,
            'One one or more directories or files to process'
        );
        $this->setDescription(
            'Analyzing latte templates for static calls that should be Latte Filters and extracting them'
        );

        $this->addOption(
            Option::FIX,
            null,
            InputOption::VALUE_NONE,
            'Generate *FilterProvider and replace static calls in templates with filters'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $directories = (array) $input->getArgument(Option::SOURCES);
        $latteFileInfos = $this->genericFilesFinder->find($directories, '*.latte');

        $fileMessage = sprintf('Extracting filters from "%d" files', count($latteFileInfos));
        $this->symfonyStyle->title($fileMessage);

        $classMethodNames = $this->latteStaticCallAnalyzer->analyzeFileInfos($latteFileInfos);

        if ($classMethodNames === []) {
            $this->symfonyStyle->success('No static calls found in templates. Good job!');
            return ShellCode::SUCCESS;
        }

        $this->reportClassMethodNames($classMethodNames);

        $this->symfonyStyle->error(
            'We found some static calls in your templates. Do you want to extract them to filter provider? Just re-run commmand with `--fix` option'
        );

        $isFix = (bool) $input->getOption(Option::FIX);
        $this->generateFilterProviderClasses($isFix);
        $this->updatePathsInTemplates($latteFileInfos, $isFix);

        return ShellCode::ERROR;
    }

    /**
     * @param ClassMethodName[] $classMethodNames
     */
    private function reportClassMethodNames(array $classMethodNames): void
    {
        foreach ($classMethodNames as $classMethodName) {
            $classMethodMessage = sprintf('Static call "%s()" found', $classMethodName->getClassMethodName());
            $this->symfonyStyle->title($classMethodMessage);

            $this->symfonyStyle->writeln('Template call located at: ' . $classMethodName->getLatteFilePath());
            $this->symfonyStyle->writeln('Method located at: ' . $classMethodName->getFileLine());
            $this->symfonyStyle->newLine(2);

            $this->classMethodNames[$classMethodName->getClassMethodName()] = $classMethodName;
        }
    }

    private function generateFilterProviderClasses(bool $isFix): void
    {
        if (! $isFix) {
            return;
        }

        foreach ($this->classMethodNames as $classMethodName) {
            $this->generateFilterProviderFile($classMethodName);
        }
    }

    private function generateFilterProviderFile(ClassMethodName $classMethodName): void
    {
        $generatedContent = $this->latteFilterProviderGenerator->generate($classMethodName);

        $filterProviderClassName = $classMethodName->getFilterProviderClassName();
        $shortFilePath = 'generated/' . $filterProviderClassName . '.php';
        FileSystem::write(getcwd() . '/' . $shortFilePath, $generatedContent);

        $generateMessage = sprintf('File "%s" was generated', $shortFilePath);
        $this->symfonyStyle->note($generateMessage);
    }

    /**
     * @param SplFileInfo[] $fileInfos
     */
    private function updatePathsInTemplates(array $fileInfos, bool $isFix): void
    {
        if (! $isFix) {
            return;
        }

        foreach ($fileInfos as $fileInfo) {
            $changedContent = $this->staticCallWithFilterReplacer->processFileInfo($fileInfo);
            FileSystem::write($fileInfo->getPathname(), $changedContent);
        }
    }
}
