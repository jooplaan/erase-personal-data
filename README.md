# WP-CLI Erase Personal Data Command

A comprehensive WP-CLI command for erasing personal data from WordPress core and 20+ popular plugins in any WordPress installation.

## Features



## Installation

### Method 1: Install from GitHub (Recommended)

Install this WP-CLI command package directly from GitHub:

#### Global installation

```bash
wp package install https://github.com/jooplaan/erase-personal-data.git
```

#### Manual installation

Clone the repository into your WP-CLI packages directory:

```bash
git clone https://github.com/jooplaan/erase-personal-data.git ~/.wp-cli/packages/erase-personal-data
```

### Method 2: Local Development/Testing

For local development and testing before committing to GitHub, you can require the package directly in a WordPress installation.

Navigate to your WordPress directory and add this to your `wp-cli.yml`:

```yaml
require:
  - /path/to/your/local/erase-personal-data/command.php
```

For example:

```yaml
require:
  - ./erase-personal-data/command.php
```

---
See below for usage instructions.

## Requirements


- PHP 7.4 or higher
- WP-CLI installed and configured
- WordPress installation

## Usage

### Basic Usage

Erase personal data from the current WordPress database with confirmation prompt:

```bash
wp erase-personal-data run
```

### Skip Confirmation

Erase personal data without confirmation prompt:

```bash
wp erase-personal-data run --yes
```

### Preview Changes (Dry Run)

Preview what would be erased without making any actual changes:

```bash
wp erase-personal-data run --dry-run
```

### Skip Form Submissions

Erase personal data but preserve form submissions (Gravity Forms, WPForms, Contact Form 7, Ninja Forms):

```bash
wp erase-personal-data run --skip-forms
```

## What Data Gets Erased?

### WordPress Core

1. **User Emails**: Anonymizes to `user{ID}@example.com` (except user ID 1)
2. **User Logins**: Changes to `user{ID}` (except user ID 1)
3. **Display Names**: Changes to `User {ID}` (except user ID 1)
4. **User Meta**: Clears first name, last name, nickname, and description
5. **Comment Authors**: Anonymizes all comment author information
6. **Comment Author Emails**: Changes to `anonymous@example.com`
7. **Comment Author IPs**: Changes to `0.0.0.0`
8. **Comment Meta**: Removes personal data from comment metadata
9. **Password Reset Keys**: Clears all password reset tokens
10. **Session Tokens**: Removes all user sessions
11. **User Registration IPs**: Deletes IP addresses from various registration logs

### E-commerce Plugins

#### WooCommerce

- Customer usernames → `customer{ID}`
- Customer names → `Customer #{ID}`
- Customer emails → `customer{ID}@example.com`
- Customer addresses (postcode, city, state)
- Order billing/shipping names, emails, phones
- Order billing/shipping addresses
- Subscription payment method history


#### Easy Digital Downloads (EDD)

- Customer names → `Customer #{ID}`
- Customer emails → `customer{ID}@example.com`


### Form Builder Plugins

#### Contact Form 7 (Flamingo)

- Deletes all stored form submissions


#### Gravity Forms

- Entry IP addresses → `0.0.0.0`
- Source URLs and user agents
- Personal data in form fields (emails, phone numbers, text)


#### Ninja Forms

- Deletes all form submissions


#### WPForms

- Entry IP addresses → `0.0.0.0`
- User agents
- Personal data in form field values


### Membership & Community Plugins

#### MemberPress

- Custom member fields (preserves active memberships and product associations)


#### BuddyPress / BuddyBoss

- Extended profile data → `[REDACTED]`


#### WP User Manager

- Custom profile fields


#### bbPress

- Author IP addresses → `0.0.0.0`


### Email Marketing Plugins

#### Newsletter Plugin

- Subscriber emails → `subscriber{ID}@example.com`
- Subscriber names → `Subscriber #{ID}`
- IP addresses → `0.0.0.0`


#### MailPoet

- Subscriber emails → `subscriber{ID}@example.com`
- Subscriber names → `Subscriber #{ID}`


### Learning Management Plugins

#### LearnDash

- User activity metadata


## Supported Plugins

The command automatically detects and sanitizes data from these plugins if installed:

| Plugin | Data Sanitized |
|--------|----------------|
| **WooCommerce** | Customers, orders, billing, shipping, subscriptions |
| **Easy Digital Downloads** | Customer information |
| **Contact Form 7** (Flamingo) | Form submissions |
| **Gravity Forms** | Entries and field data |
| **Ninja Forms** | Submissions |
| **WPForms** | Entries and field data |
| **MemberPress** | Custom member fields |
| **BuddyPress / BuddyBoss** | Extended profiles |
| **WP User Manager** | Custom profile fields |
| **bbPress** | Author IPs |
| **Newsletter** | Subscriber information |
| **MailPoet** | Subscriber information |
| **LearnDash** | User activity |

## Customization

To add custom sanitization queries or modify existing ones, edit the `get_sanitization_queries()` method in `src/ErasePersonalDataCommand.php`.

### Example: Adding Custom Plugin Support

```php
// Custom plugin table sanitization
$custom_table = $wpdb->prefix . 'my_custom_plugin_users';
if ( $this->table_exists( $custom_table ) ) {
    $queries['Anonymize custom plugin emails'] = "
        UPDATE {$custom_table}
        SET email = CONCAT('user', id, '@example.com'),
            name = 'Anonymous User'
    ";
}
```

### Example: Adding Custom User Meta Sanitization

```php
$queries['Clear custom phone numbers'] = "
    DELETE FROM {$wpdb->usermeta}
    WHERE meta_key IN ('phone_number', 'mobile_phone', 'whatsapp')
";
```

## Security Considerations

⚠️ **Warning**: This command makes **irreversible changes** to your database. Always:

1. **Backup your database** before running this command
2. Test on a development/staging environment first
3. Review the sanitization queries to ensure they match your needs
4. Verify compliance with privacy regulations (GDPR, CCPA, etc.)
5. Ensure you have proper permissions and authorization
6. Document the sanitization process for audit purposes

## Use Cases

- **Development Environments**: Sanitize production data for safe local development
- **Staging/Testing**: Clean personal data from production imports
- **GDPR Compliance**: Fulfill right-to-erasure requests on database backups
- **Client Handoffs**: Remove sensitive customer data before transferring databases
- **Security Audits**: Clean data for third-party security testing

## Troubleshooting

### Some Data Not Erased

- Review the queries in `get_sanitization_queries()` method
- Some plugins may store personal data in custom tables not covered by this command
- Check plugin documentation for data storage locations
- Consider extending the command for your specific plugins

### Permission Errors

- Ensure WP-CLI can access the WordPress installation
- Check MySQL user permissions

### Table Not Found Errors

This should not occur as the command checks for table existence before running queries. If you see this error, please report it as a bug.

## Best Practices

1. **Always backup** before running the command
2. **Test queries** on a copy of your database first
3. **Review logs** after running to verify all sanitization completed
4. **Document** which data was sanitized for compliance records
5. **Verify results** by spot-checking anonymized data
6. **Update regularly** as you add new plugins that store personal data

## Development

### Project Structure

```bash
erase-personal-data/
├── .github/
│   └── copilot-instructions.md
├── src/
│   └── ErasePersonalDataCommand.php
├── command.php
├── composer.json
├── .gitignore
└── README.md
```

### Running Tests

```bash
composer test
```

### Code Standards

This project follows WordPress coding standards for PHP.

## Changelog

### Version 1.0.0

- Initial release
- WordPress core data sanitization
- Support for 20+ popular plugins
- Smart table detection
- Confirmation prompt for safety

## License

MIT License - See LICENSE file for details

## Contributing

Contributions are welcome! Here's how you can help:

1. **Report Bugs**: Open an issue with detailed information
2. **Suggest Features**: Propose new plugin support or features
3. **Submit Pull Requests**: Add support for new plugins
4. **Improve Documentation**: Help make this README better

### Adding New Plugin Support

When submitting PRs to add plugin support:

1. Check if the plugin's tables exist using `table_exists()`
2. Use consistent anonymization patterns (e.g., `user{ID}@example.com`)
3. Document what data is being sanitized
4. Test on a database with the plugin installed
5. Update this README with the new plugin in the supported list

## Support

For issues, questions, or contributions:

- Open an issue on GitHub
- Check existing issues for solutions
- Provide detailed information about your environment and the problem

## Disclaimer

This tool is provided as-is. Always test thoroughly and maintain backups. The authors are not responsible for data loss or compliance issues. You are responsible for ensuring this tool meets your specific privacy and legal requirements.
