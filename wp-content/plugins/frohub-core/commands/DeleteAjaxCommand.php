<?php
namespace Pixable\FrohubCore;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteAjaxCommand extends Command
{
    protected static $defaultName = 'wp-shaper:delete-ajax';

    public function __construct()
    {
        parent::__construct(self::$defaultName);
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Delete an AJAX handler and its references in class-ajax.php.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the AJAX handler to delete (e.g., NamespaceFolder/my_ajax_call)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');

        // Extract namespace and file name
        $parts = explode('/', $name);
        $ajaxName = array_pop($parts); // Get the AJAX function name
        $namespacePath = implode(DIRECTORY_SEPARATOR, $parts); // Directory structure

        // Define paths
        $baseDirectory = realpath(__DIR__ . '/../includes/ajax') . DIRECTORY_SEPARATOR;
        $namespaceDirectory = $baseDirectory . strtolower($namespacePath);
        $phpFilePath = rtrim($namespaceDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . "{$ajaxName}.php";
        $ajaxClassFilePath = $baseDirectory . 'class-ajax.php';

        // Check if the AJAX file exists and delete it
        if (file_exists($phpFilePath)) {
            unlink($phpFilePath);
            $output->writeln("<info>Deleted PHP file: {$phpFilePath}</info>");
        } else {
            $output->writeln("<error>PHP file not found: {$phpFilePath}</error>");
        }

        // Attempt to remove the folder if empty
        $this->removeEmptyFolders($namespaceDirectory, $baseDirectory, $output);

        // Update class-ajax.php
        if (file_exists($ajaxClassFilePath)) {
            $ajaxContent = file_get_contents($ajaxClassFilePath);

            // Remove the use statement
            $className = implode('', array_map('ucfirst', explode('_', $ajaxName)));
            $useStatement = "use FECore\\{$className};";
            $ajaxContent = str_replace("\n{$useStatement}", '', $ajaxContent);

            // Remove the class initialization
            $initStatement = "{$className}::init();";
            $ajaxContent = str_replace("\t\t{$initStatement}\n", '', $ajaxContent);

            // Save the updated content
            file_put_contents($ajaxClassFilePath, $ajaxContent);
            $output->writeln("<info>Updated class-ajax.php to remove references to '{$ajaxName}'.</info>");
        } else {
            $output->writeln("<error>class-ajax.php not found: {$ajaxClassFilePath}</error>");
        }

        $output->writeln("<info>AJAX handler '{$ajaxName}' deleted successfully.</info>");
        return Command::SUCCESS;
    }

    /**
     * Recursively removes empty folders up to the base directory.
     *
     * @param string $dir
     * @param string $baseDirectory
     * @param OutputInterface $output
     */
    private function removeEmptyFolders(string $dir, string $baseDirectory, OutputInterface $output)
    {
        while (is_dir($dir) && count(scandir($dir)) === 2 && $dir !== $baseDirectory) { // Only '.' and '..' exist
            rmdir($dir);
            $output->writeln("<info>Deleted empty directory: {$dir}</info>");
            $dir = dirname($dir);
        }
    }
}
