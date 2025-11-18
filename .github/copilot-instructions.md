- [x] Verify that the copilot-instructions.md file in the .github directory is created.
- [x] Clarify Project Requirements - WP-CLI command for database import and personal data erasure
- [x] Scaffold the Project - Created composer.json, command.php, and ErasePersonalDataCommand.php
- [x] Customize the Project - Implemented database import, sanitization queries, and file deletion prompt
- [x] Install Required Extensions - Not required for PHP WP-CLI package
- [x] Compile the Project - Composer validation successful
- [x] Create and Run Task - Not applicable for WP-CLI command
- [x] Launch the Project - WP-CLI commands are run directly via terminal
- [x] Ensure Documentation is Complete - README.md created with installation and usage instructions

## Project Overview

This is a custom WP-CLI command package that:
1. Imports a SQL database file into WordPress
2. Runs sanitization queries to erase/anonymize personal data
3. Optionally deletes the source SQL file based on user input

## Project Structure

- `composer.json` - Package metadata and autoload configuration
- `command.php` - Entry point that registers the WP-CLI command
- `src/ErasePersonalDataCommand.php` - Main command class implementation
- `README.md` - Complete installation and usage documentation
- `.gitignore` - Git exclusion rules

## Usage

This command must be used within a WordPress installation with WP-CLI installed:

```bash
wp erase-personal-data import database.sql
wp erase-personal-data import database.sql --delete-file
wp erase-personal-data import database.sql --keep-file
```

## Installation Options

See README.md for detailed installation instructions including:
- Global package installation
- Manual installation to ~/.wp-cli/packages/
- Local development setup with wp-cli.yml

## Customization

Modify the `get_sanitization_queries()` method in `src/ErasePersonalDataCommand.php` to add or modify data sanitization rules.
