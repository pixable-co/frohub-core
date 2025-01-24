# Read it First

To clone this repo, please follow this:

- Go to your Local WordPress install root.
- Open Terminal (Mac) or Command Line (Windows)
- Run ```git init```
- Then run ```git remote add origin https://github.com/pixable-co/frohub-core.git```
- Then run ```git pull origin main```
- Then open your Github desktop and on top click 'Add Existing Repository'. Then select your Local WordPress Root.
- Done

# CLI Commands

Please check the CLI commands.

## Installation

Ensure Composer is installed on your system. You can download it from getcomposer.org.

Then after have the repository in your Local WP(wp-content/plugins/plugin-name/). Run This:

```bash
composer install
```

### Commands

To create Shortcode use this:

```bash
php cli.php wp-shaper:make-shortcode <namespace/shortcode_name>
```

To create Shortcode using React:

```bash
php cli.php wp-shaper:make-shortcode-react <namespace/shortcode_name>
```

To create API Endpoints use this:

```bash
php cli.php wp-shaper:make-api <namespace/endpoint_name> [method]
```

To create Ajax use this:

```bash
php cli.php wp-shaper:make-ajax <namespace/ajax_name>
```

To create Ajax with Noprive support use this:

```bash
php cli.php wp-shaper:make-ajax <namespace/ajax_name> --noprive
```

To delete shortcode use this:

```bash
php cli.php delete:shortcode <namespace/shortcode_name>
```

To delete react shortcode use this:

```bash
php cli.php delete:shortcode-react <namespace/shortcode_name>
```

To delete API endpoint use this:

```bash
php cli.php wp-shaper:delete-api <namespace/endpoint_name>
```

To delete Ajax use this:

```bash
php cli.php wp-shaper:delete-ajax <namespace/ajax_name>
```

### Examples

```bash
php cli.php wp-shaper:make-shortcode BookingForm/fh_submit_form
```

```bash
php cli.php make:shortcode-react BookingForm/fh_booking_calender
```

```bash
php cli.php wp-shaper:make-api UserManagement/user_login method:GET
php cli.php wp-shaper:make-api UserManagement/user_login method:POST
```

# CSS & JS

To write custom css and js for shortcodes, please use this files:

```bash
includes/assets/shortcode/style.css
includes/assets/shortcode/scripts.js
```
