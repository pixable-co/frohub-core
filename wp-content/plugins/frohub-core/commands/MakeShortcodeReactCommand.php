<?php
namespace Pixable\FrohubCore;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeShortcodeReactCommand extends Command
{
    protected static $defaultName = 'make:shortcode-react';

    public function __construct()
    {
        parent::__construct(self::$defaultName);
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Generate a new React-based shortcode and update related files.')
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

        // Generate a camelCase variable name
        $variableName = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $fileName))));

        // Define the base directory for PHP
        $baseDirectory = realpath(__DIR__ . '/../includes/shortcodes') . DIRECTORY_SEPARATOR;

        // Define the full PHP file path
        $fullDirectoryPath = $baseDirectory . strtolower($directoryPath);
        $phpFilePath = rtrim($fullDirectoryPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . "{$fileName}.php";

        // Ensure the directory exists
        if (!is_dir($fullDirectoryPath)) {
            mkdir($fullDirectoryPath, 0755, true);
        }

        // Create the class-based shortcode PHP file
        $phpContent = <<<PHP
<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class {$className} {

    public static function init() {
        \$self = new self();
        add_shortcode( '{$fileName}', array(\$self, '{$fileName}_shortcode') );
    }

    public function {$fileName}_shortcode() {
        \$unique_key = '{$fileName}' . uniqid();
        return '<div class="{$fileName}" data-key="' . esc_attr(\$unique_key) . '"></div>';
    }
}

PHP;

        file_put_contents($phpFilePath, $phpContent);

        // Update class-shortcode.php
        $shortcodeFilePath = realpath(__DIR__ . '/../includes/shortcodes') . DIRECTORY_SEPARATOR . 'class-shortcode.php';

        if (file_exists($shortcodeFilePath)) {
            $shortcodeContent = file_get_contents($shortcodeFilePath);

            // Add the use statement if it doesn't exist
            $useStatement = "use FECore\\{$className};";
            if (strpos($shortcodeContent, $useStatement) === false) {
                $shortcodeContent = str_replace("namespace FECore;\n", "namespace FECore;\n\n{$useStatement}\n", $shortcodeContent);
            }

            // Add the class initialization in the init() method
            $initStatement = "{$className}::init();";
            if (strpos($shortcodeContent, $initStatement) === false) {
                $shortcodeContent = preg_replace(
                    '/public static function init\(\) \{\n(.*?)\n\t\}/s',
                    "public static function init() {\n$1\n\t\t{$initStatement}\n\t}",
                    $shortcodeContent
                );
            }

            // Save the updated content
            file_put_contents($shortcodeFilePath, $shortcodeContent);
        }

        // Create the React component .jsx file in src/shortcodes/<namespace>
        $jsxDirectory = realpath(__DIR__ . '/../src/shortcodes') . DIRECTORY_SEPARATOR . strtolower($directoryPath);
        $jsxFilePath = $jsxDirectory . DIRECTORY_SEPARATOR . "{$fileName}.jsx";

        if (!is_dir($jsxDirectory)) {
            mkdir($jsxDirectory, 0755, true);
        }

        $jsxComponentContent = <<<JSX
import React from 'react';

const {$className} = ({ dataKey }) => {
    return (
        <div>
            <h1>Welcome to {$className} from React</h1>
        </div>
    );
};

export default {$className};
JSX;

        file_put_contents($jsxFilePath, $jsxComponentContent);

        // Update the main.jsx file
        $mainJsxPath = realpath(__DIR__ . '/../src') . DIRECTORY_SEPARATOR . 'main.jsx';

        if (file_exists($mainJsxPath)) {
            $mainJsxContent = file_get_contents($mainJsxPath);

            // Add the import statement
            $importStatement = "import {$className} from './shortcodes/{$directoryPath}/{$fileName}';";
            if (strpos($mainJsxContent, $importStatement) === false) {
                $mainJsxContent = $importStatement . "\n" . $mainJsxContent;
            }

            // Add the initialization logic
            $initCode = <<<JSX

const {$variableName}Elements = document.querySelectorAll('.{$fileName}');
{$variableName}Elements.forEach(element => {
    const key = element.getAttribute('data-key');
    createRoot(element).render(
        <{$className} dataKey={key} />
    );
});
JSX;

            if (strpos($mainJsxContent, $initCode) === false) {
                $mainJsxContent .= "\n" . $initCode;
            }

            file_put_contents($mainJsxPath, $mainJsxContent);
        } else {
            $output->writeln("<error>main.jsx file not found at {$mainJsxPath}</error>");
            return Command::FAILURE;
        }

        // Output success message
        $output->writeln("Shortcode '{$fileName}', React component, and main.jsx updated successfully.");
        $output->writeln("- PHP file created at: {$phpFilePath}");
        $output->writeln("- JSX file created at: {$jsxFilePath}");
        $output->writeln("- Updated: {$mainJsxPath}");

        return Command::SUCCESS;
    }
}
