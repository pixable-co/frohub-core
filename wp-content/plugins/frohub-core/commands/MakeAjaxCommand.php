<?php
namespace Pixable\FrohubCore;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeAjaxCommand extends Command
{
    protected static $defaultName = 'wp-shaper:make-ajax';

    public function __construct()
    {
        parent::__construct(self::$defaultName);
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Generate a new AJAX handler and update class-ajax.php.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the AJAX handler (e.g., NamespaceFolder/my_ajax_call)')
            ->addOption('noprive', null, InputOption::VALUE_NONE, 'Add support for non-logged-in users (wp_ajax_nopriv).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $addNopriv = $input->getOption('noprive');

        // Extract namespace and file name
        $parts = explode('/', $name);
        $ajaxName = array_pop($parts); // Get the AJAX function name
        $namespacePath = implode(DIRECTORY_SEPARATOR, $parts); // Directory structure

        // Convert file name to PascalCase for the class name
        $className = implode('', array_map('ucfirst', explode('_', $ajaxName)));

        // Define paths
        $baseDirectory = realpath(__DIR__ . '/../includes/ajax') . DIRECTORY_SEPARATOR;
        $namespaceDirectory = $baseDirectory . strtolower($namespacePath);
        $phpFilePath = rtrim($namespaceDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . "{$ajaxName}.php";
        $ajaxClassFilePath = $baseDirectory . 'class-ajax.php';

        // Ensure the directory exists
        if (!is_dir($namespaceDirectory)) {
            mkdir($namespaceDirectory, 0755, true);
        }

        // Create the AJAX handler PHP file
        $phpContent = <<<PHP
<?php
namespace FECore;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class {$className} {

    public static function init() {
        \$self = new self();

        // AJAX for logged-in users
        add_action('wp_ajax_frohub/{$ajaxName}', array(\$self, '{$ajaxName}'));
PHP;

        if ($addNopriv) {
            $phpContent .= <<<PHP

        // AJAX for non-logged-in users
        add_action('wp_ajax_nopriv_frohub/{$ajaxName}', array(\$self, '{$ajaxName}'));
PHP;
        }

        $phpContent .= <<<PHP

    }

    public function {$ajaxName}() {
        check_ajax_referer('frohub_nonce');

        // Your AJAX logic here
        wp_send_json_success(array(
            'message' => '{$ajaxName} AJAX handler executed successfully.',
        ));
    }
}
PHP;

        file_put_contents($phpFilePath, $phpContent);

        // Update class-ajax.php
        if (file_exists($ajaxClassFilePath)) {
            $ajaxContent = file_get_contents($ajaxClassFilePath);

            // Add the use statement if it doesn't exist
            $useStatement = "use FECore\\{$className};";
            if (strpos($ajaxContent, $useStatement) === false) {
                $ajaxContent = str_replace("namespace FECore;\n", "namespace FECore;\n\n{$useStatement}\n", $ajaxContent);
            }

            // Add the class initialization in the init() method
            $initStatement = "{$className}::init();";
            if (strpos($ajaxContent, $initStatement) === false) {
                $ajaxContent = preg_replace(
                    '/public static function init\(\) \{\n(.*?)\n\t\}/s',
                    "public static function init() {\n$1\n\t\t{$initStatement}\n\t}",
                    $ajaxContent
                );
            }

            // Save the updated content
            file_put_contents($ajaxClassFilePath, $ajaxContent);
        }

        // Output success message
        $output->writeln("AJAX handler '{$ajaxName}' created with the namespace '{$namespacePath}':");
        $output->writeln("- PHP file created at: {$phpFilePath}");
        $output->writeln("- Updated: {$ajaxClassFilePath}");

        return Command::SUCCESS;
    }
}
