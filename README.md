# WP-CLI Erase Personal Data Command

A comprehensive WP-CLI command for sanitizing personal data from WordPress core and 20+ popular plugins directly in your current WordPress database.

## What It Does

This command helps you safely sanitize your WordPress database by:

1. **Anonymizing** personal data from WordPress core
2. **Sanitizing** data from 20+ popular plugins
3. **Optionally skipping** form submissions if needed
4. **Preview mode** to see what would be changed before making changes

Perfect for GDPR compliance, creating safe development environments from production data, and preparing databases for client handoffs.

## Requirements

- PHP 7.4 or higher
- WP-CLI installed
- MySQL/MariaDB command-line tools
- WordPress installation (single site or multisite)

## Installation

### Method 1: Install stable release (Recommended)

```bash
wp package install jooplaan/erase-personal-data:1.5.2
```

Or install latest from the main branch (for testing):

```bash
wp package install jooplaan/erase-personal-data:dev-main
```

If your environment presumes a master branch or hits GitHub API limits, use the direct URL fallback:

```bash
wp package install https://github.com/jooplaan/erase-personal-data.git
```

### Method 2: Manual Installation

1. Clone this repository:

   ```bash
   git clone https://github.com/jooplaan/erase-personal-data.git
   cd erase-personal-data
   ```

2. Install to WP-CLI packages directory:

   ```bash
   mkdir -p ~/.wp-cli/packages
   ln -s $(pwd) ~/.wp-cli/packages/erase-personal-data
   ```

### Method 3: Local Development

For testing in a specific WordPress installation:

1. Add to your project's `composer.json`:

    ```json
   {
     "require-dev": {
       "wp-cli/erase-personal-data": "dev-main"
     },
     "repositories": [
       {
         "type": "vcs",
         "url": "https://github.com/jooplaan/erase-personal-data.git"
       }
     ]
   }
   ```

2. Run `composer install`

3. Create `wp-cli.yml` in your WordPress root:

   ```yaml
   require:
     - vendor/wp-cli/erase-personal-data/command.php
   ```

## Usage

### Basic Command

```bash
wp erase-personal-data run [--yes] [--dry-run] [--skip-forms]
```

### Parameters

#### `--yes` (optional)

Skip the confirmation prompt and proceed with data erasure immediately.

**Example:**

```bash
wp erase-personal-data run --yes
```

#### `--dry-run` (optional)

Preview what would be erased without making any actual changes to the database. Useful for testing before running the actual command.

**Example:**

```bash
wp erase-personal-data run --dry-run
```

#### `--skip-forms` (optional)

Skip erasing form submissions (Gravity Forms, WPForms, Ninja Forms, Contact Form 7). Only erase other personal data like user info, comments, etc.

**Example:**

```bash
wp erase-personal-data run --skip-forms
```

### Usage Examples

**Run with confirmation prompt (safest):**

```bash
wp erase-personal-data run
```

**Preview what would be erased:**

```bash
wp erase-personal-data run --dry-run
```

**Run without confirmation:**

```bash
wp erase-personal-data run --yes
```

**Erase data but preserve form submissions:**

```bash
wp erase-personal-data run --skip-forms
```

**Combine flags - preview and skip forms:**

```bash
wp erase-personal-data run --dry-run --skip-forms
```

## Multisite Support

✅ **Fully multisite-compatible!**

When running on a WordPress multisite network:

- **User data** is only anonymized for users who belong to the current site
- **Plugin data** (WooCommerce, forms, etc.) is site-specific by default
- **Comments** are already site-specific in WordPress multisite

To sanitize a specific site in a multisite network:

```bash
# Switch to the site you want to sanitize
wp --url=site2.example.com erase-personal-data run --dry-run

# Or use the site ID
wp --url=2 erase-personal-data run --dry-run
```

At runtime, the command reports whether it detected single site or multisite and, on multisite, which site URL/ID is being modified. If you’re on the main site without `--url`, it suggests using `--url=<site>` and `wp site list` to discover site URLs.

**Note:** In multisite, users are shared across the network. The command intelligently targets only users associated with the current site.

## What Data Gets Sanitized

### WordPress Core

- User emails → `user{ID}@example.com` (preserves user ID 1)
- User display names → `User {ID}`
- User metadata (first name, last name, nickname, description)
- Comment authors → `Anonymous`
- Comment emails → `anonymous@example.com`
- Comment IPs → `0.0.0.0`
- Password reset keys
- Session tokens
- User registration IPs

### Pronamic Pay

**Payment Posts (Deleted):**

- Deletes all `pronamic_payment` custom post type posts entirely
- Automatically cleans up orphaned postmeta (including any `_pronamic_payment_*` fields)
- Removes all JSON customer data stored in `post_content`

**Payments Table (Anonymized):**

- Anonymizes customer names in `wp_pronamic_pay_payments` table → `'Anonymous Customer'`
- Anonymizes emails → `payment{ID}@example.com`
- Clears contact details (phone, company name, address, city, zip, country)
- Preserves payment amounts, status, transaction IDs, and other non-personal technical data

This two-pronged approach ensures complete removal of personal data from both the WordPress posts system and the dedicated Pronamic Pay database table.

### Supported Plugins (20+)

The command automatically detects installed plugins and sanitizes their data:

**E-commerce:**

- WooCommerce (customers, orders, billing, shipping, subscriptions)
- Easy Digital Downloads
- Pronamic Pay (deletes payment posts, anonymizes payments table)

**Forms:**

- Contact Form 7 (Flamingo)
- Gravity Forms (modern & legacy tables)
- Ninja Forms
- WPForms

**Membership:**

- MemberPress
- BuddyPress/BuddyBoss
- WP User Manager
- bbPress

**Email Marketing:**

- Newsletter
- MailPoet
- WP Mail SMTP Pro

**Learning:**

- LearnDash

[See full list of supported plugins](#supported-plugins-reference)

## Security & Best Practices

⚠️ **Warning**: This command makes **irreversible changes** to your database.

⚠️ **Important**: This command performs a **best-effort sanitization** of common WordPress plugins and core data. It does **not guarantee** removal of ALL personal data from your database. Some plugins may store data in custom tables or unusual locations not covered by this tool.

### Before Running

1. ✅ **Always backup your database** before importing
2. ✅ Test on a development environment first
3. ✅ Verify you have proper authorization
4. ✅ Review the sanitization queries for your use case
5. ✅ **Manually verify** that sensitive data has been properly sanitized
6. ✅ Check for plugins not yet supported and contribute support for them!

### Recommended Workflow

```bash
# 1. Backup current database
wp db export backup-$(date +%Y%m%d).sql

# 2. Preview what will be erased (dry run)
wp erase-personal-data run --dry-run

# 3. Run the sanitization
wp erase-personal-data run --yes

# 4. Verify results
wp db query "SELECT user_email FROM wp_users LIMIT 5"

# 5. Delete database to no longer have the data on your computer
rm backup-YYMMDD.sql
```

## Contributing

We welcome contributions! Here's how you can help:

### Adding Plugin Support

To add support for a new plugin:

1. **Fork this repository**

2. **Identify the plugin's database tables**
   - Check the plugin's database schema
   - Find tables/fields containing personal data

3. **Add sanitization queries** in `src/ErasePersonalDataCommand.php`
   
   Add your queries in the `get_sanitization_queries()` method:

   ```php
   // Your Plugin Name
   $your_plugin_table = $wpdb->prefix . 'your_plugin_table';
   if ( $this->table_exists( $your_plugin_table ) ) {
       $queries['Anonymize Your Plugin data'] = "
           UPDATE {$your_plugin_table}
           SET email = CONCAT('user', id, '@example.com'),
               name = 'Anonymous User'
       ";
   }
   ```

4. **Update the README**
   - Add the plugin to the supported plugins list
   - Document what data gets sanitized

5. **Test thoroughly**
   - Test on a database with the plugin installed
   - Verify data is properly anonymized
   - Ensure no errors occur if plugin isn't installed

6. **Submit a Pull Request**
   - Clear description of what plugin support was added
   - Explain what data is being sanitized

### Anonymization Patterns

Follow these patterns for consistency:

```php
// Email addresses
"email = CONCAT('user', id, '@example.com')"

// Names
"name = 'Anonymous User'"
"name = CONCAT('User #', id)"

// IPs
"ip = '0.0.0.0'"

// Addresses
"address = '', city = '', zip = ''"

// Generic redaction
"value = '[REDACTED]'"
```

### Code Guidelines

- Check table existence with `$this->table_exists()`
- Use WordPress database prefix: `$wpdb->prefix . 'table_name'`
- Add descriptive query names
- Follow WordPress coding standards
- Add comments for complex logic

### Example Contribution

```php
// Example: Adding support for "My Custom Plugin"
$custom_plugin_users = $wpdb->prefix . 'custom_plugin_users';
if ( $this->table_exists( $custom_plugin_users ) ) {
    // Anonymize user emails
    $queries['Anonymize Custom Plugin user emails'] = "
        UPDATE {$custom_plugin_users}
        SET email = CONCAT('user', id, '@example.com')
    ";
    
    // Clear personal details
    $queries['Clear Custom Plugin personal details'] = "
        UPDATE {$custom_plugin_users}
        SET phone = '',
            address = '',
            notes = '[REDACTED]'
    ";
}
```

## Troubleshooting

### Database Import Fails

**Error**: Import failed with error code

- Check MySQL credentials in `wp-config.php`
- Ensure SQL file is not corrupted
- Verify MySQL user has sufficient privileges (CREATE, INSERT, UPDATE, DELETE)
- Try manual import: `mysql -u user -p database < file.sql`

### Some Data Not Erased

- Plugin tables may use different naming conventions
- Some plugins store data in custom tables not yet supported
- **We welcome your contributions!** If you find a plugin that stores personal data not covered by this tool, please open an issue or submit a pull request to add support

### Missing Plugin Support?

**Help us improve!** If you discover personal data that isn't being sanitized:

1. Open a [GitHub Issue](https://github.com/jooplaan/erase-personal-data/issues) describing what data was missed
2. Submit a [Pull Request](https://github.com/jooplaan/erase-personal-data/pulls) adding support for that plugin (see [Contributing](#contributing) section)
3. Share your sanitization queries with the community

### Permission Errors

- Verify WP-CLI can access the WordPress installation
- Check file permissions on the SQL file
- Ensure MySQL user has required permissions

## Supported Plugins Reference

| Plugin | What Gets Sanitized |
|--------|---------------------|
| **WooCommerce** | Customers, orders, billing addresses, shipping addresses, phone numbers, subscriptions |
| **Easy Digital Downloads** | Customer names and emails |
| **Pronamic Pay** | Deletes all `pronamic_payment` posts + orphaned meta; anonymizes `wp_pronamic_pay_payments` (names, emails, addresses) |
| **Contact Form 7** (Flamingo) | All form submissions (deleted) |
| **Gravity Forms** | Entry IPs, user agents, all form field values (modern & legacy tables) |
| **Ninja Forms** | All submissions (deleted) |
| **WPForms** | Entry IPs, user agents, form field values |
| **MemberPress** | Custom member fields (preserves membership/product data) |
| **BuddyPress / BuddyBoss** | Extended profile fields |
| **WP User Manager** | Custom profile fields |
| **bbPress** | Author IP addresses |
| **Newsletter** | Subscriber emails, names, IPs |
| **MailPoet** | Subscriber emails and names |
| **WP Mail SMTP Pro** | Email logs (recipients, subjects, headers), attachments |
| **LearnDash** | User activity metadata |

## Customization

### Adding Custom Sanitization

Edit `src/ErasePersonalDataCommand.php` and add your queries to the `get_sanitization_queries()` method:

```php
// Custom sanitization example
$queries['Clear custom phone field'] = "
    DELETE FROM {$wpdb->usermeta}
    WHERE meta_key IN ('phone_number', 'mobile', 'whatsapp')
";
```

## Use Cases

- **Development Environments** – Sanitize production data for safe local development
- **Staging Sites** – Clean personal data from production imports
- **GDPR Compliance** – Fulfill right-to-erasure requests on database backups
- **Client Handoffs** – Remove sensitive customer data before transferring databases
- **Security Audits** – Clean data for third-party security testing
- **Testing** – Create realistic test data without exposing real personal information

## License

MIT License

## Support

- **Issues**: [GitHub Issues](https://github.com/jooplaan/erase-personal-data/issues)
- **Contribute**: [Pull Requests Welcome](https://github.com/jooplaan/erase-personal-data/pulls)

### Updating

To update to the latest version of this command, when you have it already installed, use the standard update command:

```bash
wp packages update
```

## Changelog

### Version 1.5.0

- Support for WordPress multisites
- Added more plugins

### Version 1.0.0

- Initial release
- WordPress core data sanitization
- Support for 20+ popular plugins
- Smart table detection
- Interactive file deletion prompt
- Support for Gravity Forms legacy (RG) tables

---

**Disclaimer**: This tool is provided as-is and performs **best-effort sanitization** of personal data. It does not guarantee complete removal of all personal data from your database. Always test thoroughly, maintain backups, and manually verify that sensitive data has been properly sanitized. You are responsible for ensuring this tool meets your specific privacy and legal requirements. If you discover personal data that isn't being sanitized, please contribute improvements via pull requests or open an issue on GitHub.
