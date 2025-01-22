<?php
namespace Pixable\FrohubCore;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteShortcodeCommand extends Command
{
    protected static $defaultName = 'wp-shaper:delete-shortcode';

    public function __construct()
    {
        parent::__construct(self::$defaultName);
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Delete a PHP shortcode and update class-shortcode.php.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the shortcode (use directory structure if needed, e.g., BookingForm/fh_submit_form)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');

        // Extract directory and filename
        $parts = explode('/', $name);
        $fileName = array_pop($parts); // Get the last part as the file name
        $directoryPath = implode(DIRECTORY_SEPARATOR, $parts); // Remaining parts form the directory path

        // Ensure the file name is all lowercase
        $fileName = strtolower($fileName);

        // Generate the class name in PascalCase
        $className = implode('', array_map('ucfirst', explode('_', $fileName)));

        // Define file paths
        $baseDirectory = realpath(__DIR__ . '/../includes/shortcodes') . DIRECTORY_SEPARATOR;
        $phpFilePath = rtrim($baseDirectory . strtolower($directoryPath), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . "{$fileName}.php";
        $shortcodeFilePath = $baseDirectory . 'class-shortcode.php';

        // Function to remove directory if empty
        $removeDirIfEmpty = function ($dir, $stopAt) use ($output) {
            while (is_dir($dir) && count(scandir($dir)) === 2 && $dir !== $stopAt) { // Only '.' and '..' exist
                rmdir($dir);
                $output->writeln("Deleted directory: {$dir}");
                $dir = dirname($dir); // Go up one level
            }
        };

        // Delete the PHP shortcode file
        if (file_exists($phpFilePath)) {
            unlink($phpFilePath);
            $output->writeln("Deleted PHP file: {$phpFilePath}");
        } else {
            $output->writeln("PHP file not found: {$phpFilePath}");
        }

        // Attempt to remove empty namespace folders, stopping at the base directory
        $removeDirIfEmpty($baseDirectory . strtolower($directoryPath), $baseDirectory);

        // Update class-shortcode.php
        if (file_exists($shortcodeFilePath)) {
            $shortcodeContent = file_get_contents($shortcodeFilePath);

            // Remove the use statement
            $useStatement = "use FECore\\{$className};";
            $shortcodeContent = str_replace("\n{$useStatement}", '', $shortcodeContent);

            // Remove the class initialization
            $initStatement = "{$className}::init();";
            $shortcodeContent = str_replace("\t\t{$initStatement}\n", '', $shortcodeContent);

            // Save the updated content
            file_put_contents($shortcodeFilePath, $shortcodeContent);
            $output->writeln("Updated class-shortcode.php: {$shortcodeFilePath}");
        } else {
            $output->writeln("class-shortcode.php not found: {$shortcodeFilePath}");
        }

        $output->writeln("Shortcode '{$fileName}' and associated files deleted successfully.");

        return Command::SUCCESS;
    }
}
