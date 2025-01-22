# Frohub CLI Commands

Please check the CLI commands.

## Installation

Ensure Composer is installed on your system. You can download it from getcomposer.org.

Then after have the repository in your Local WP. Run This:

```bash
composer install
```

### Commands

To create Shortcode use this:

```bash
php cli.php wp-shaper:make-shortcode <namespace/shortcode_name>
```

To create API Endpoints use this:

```bash
php cli.php wp-shaper:make-api <namespace/endpoint_name> [method]
```


### Examples

```bash
php cli.php wp-shaper:make-shortcode BookingForm/fh_submit_form
```

```bash
php cli.php wp-shaper:make-api UserManagement/user_login method:GET
php cli.php wp-shaper:make-api UserManagement/user_login method:POST
```


