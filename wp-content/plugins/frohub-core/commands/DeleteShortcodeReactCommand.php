<?php
namespace Pixable\FrohubCore;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteShortcodeReactCommand extends Command
{
    protected static $defaultName = 'delete:shortcode-react';

    public function __construct()
    {
        parent::__construct(self::$defaultName);
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Delete a React-based shortcode and its associated files.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the shortcode (use directory structure if needed, e.g., BookingForm/fh_booking_calender)');
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

        $jsxDirectory = realpath(__DIR__ . '/../src/shortcodes') . DIRECTORY_SEPARATOR . strtolower($directoryPath);
        $jsxFilePath = $jsxDirectory . DIRECTORY_SEPARATOR . "{$fileName}.jsx";

        $mainJsxPath = realpath(__DIR__ . '/../src') . DIRECTORY_SEPARATOR . 'main.jsx';
        $shortcodeFilePath = realpath(__DIR__ . '/../includes/shortcodes') . DIRECTORY_SEPARATOR . 'class-shortcode.php';

        // Function to remove directory if empty, but stop at the root folder (src/shortcodes)
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

        // Delete the JSX component file
        if (file_exists($jsxFilePath)) {
            unlink($jsxFilePath);
            $output->writeln("Deleted JSX file: {$jsxFilePath}");
        } else {
            $output->writeln("JSX file not found: {$jsxFilePath}");
        }

        // Attempt to remove empty namespace folders, stopping at src/shortcodes
        $removeDirIfEmpty($baseDirectory . strtolower($directoryPath), $baseDirectory);
        $removeDirIfEmpty($jsxDirectory, realpath(__DIR__ . '/../src/shortcodes'));

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

        // Update main.jsx
        if (file_exists($mainJsxPath)) {
            $mainJsxContent = file_get_contents($mainJsxPath);

            // Remove the import statement
            $importStatement = "import {$className} from './shortcodes/{$directoryPath}/{$fileName}';";
            $mainJsxContent = str_replace("{$importStatement}\n", '', $mainJsxContent);

            // Remove the initialization logic
            $variableName = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $fileName))));
            $initCode = <<<JSX

const {$variableName}Elements = document.querySelectorAll('.{$fileName}');
{$variableName}Elements.forEach(element => {
    const key = element.getAttribute('data-key');
    createRoot(element).render(
        <{$className} dataKey={key} />
    );
});
JSX;

            $mainJsxContent = str_replace($initCode, '', $mainJsxContent);

            // Save the updated content
            file_put_contents($mainJsxPath, $mainJsxContent);
            $output->writeln("Updated main.jsx: {$mainJsxPath}");
        } else {
            $output->writeln("main.jsx not found: {$mainJsxPath}");
        }

        $output->writeln("Shortcode '{$fileName}' and associated files deleted successfully.");

        return Command::SUCCESS;
    }
}
