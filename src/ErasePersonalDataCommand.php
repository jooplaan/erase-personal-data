<?php

namespace WP_CLI\ErasePersonalData;

use WP_CLI;
use WP_CLI_Command;

/**
 * Erases personal data from the current WordPress database.
 */
class ErasePersonalDataCommand extends WP_CLI_Command {

    /**
     * Erase personal data from the current WordPress database.
     *
     * ## OPTIONS
     *
     * [--yes]
     * : Skip the confirmation prompt and proceed with data erasure.
     *
     * [--dry-run]
     * : Preview what would be erased without making any actual changes.
     *
     * [--skip-forms]
     * : Skip erasing form submissions (Gravity Forms, WPForms, etc.). Only erase other personal data.
     *
     * ## EXAMPLES
     *
     *     # Erase personal data with confirmation prompt
     *     wp erase-personal-data run
     *
     *     # Erase personal data without confirmation prompt
     *     wp erase-personal-data run --yes
     *
     *     # Preview what would be erased without making changes
     *     wp erase-personal-data run --dry-run
     *
     *     # Erase personal data but skip form submissions
     *     wp erase-personal-data run --skip-forms
     *
     * @when after_wp_load
     */
    public function run( $args, $assoc_args ) {
        global $wpdb;

        $dry_run = isset( $assoc_args['dry-run'] );
        $skip_forms = isset( $assoc_args['skip-forms'] );

        if ( $dry_run ) {
            WP_CLI::warning( 'DRY RUN MODE: No changes will be made to the database.' );
        } else {
            // Confirmation prompt unless --yes flag is provided
            if ( ! isset( $assoc_args['yes'] ) ) {
                WP_CLI::warning( 'This will IRREVERSIBLY erase personal data from your WordPress database.' );
                WP_CLI::confirm( 'Are you sure you want to continue?', $assoc_args );
            }
        }

        // Erase personal data
        WP_CLI::log( "Starting personal data erasure..." );
        if ( $skip_forms ) {
            WP_CLI::log( "Skipping form submissions as requested." );
        }
        $this->erase_personal_data( $dry_run, $skip_forms );
        
        if ( $dry_run ) {
            WP_CLI::success( "Dry run completed. No data was actually erased." );
        } else {
            WP_CLI::success( "Personal data erased successfully." );
        }
    }

    /**
     * Erase personal data from the database.
     *
     * @param bool $dry_run Whether to run in dry-run mode (preview only).
     * @param bool $skip_forms Whether to skip form submission erasure.
     */
    private function erase_personal_data( $dry_run = false, $skip_forms = false ) {
        global $wpdb;

        // Array of queries to erase personal data
        $queries = $this->get_sanitization_queries( $skip_forms );
        $total = count( $queries );
        $current = 0;

        foreach ( $queries as $description => $query ) {
            $current++;
            $progress = sprintf( '[%d/%d]', $current, $total );
            
            WP_CLI::log( "{$progress} {$description}" );
            
            if ( $dry_run ) {
                // In dry-run mode, estimate how many rows would be affected
                $count = $this->estimate_affected_rows( $query );
                
                if ( $count === false ) {
                    WP_CLI::log( "    [DRY RUN] Unable to estimate affected rows" );
                } else {
                    WP_CLI::log( "    [DRY RUN] Would affect approximately {$count} rows" );
                }
            } else {
                // Actually execute the query
                $result = $wpdb->query( $query );

                if ( $result === false ) {
                    WP_CLI::warning( "Failed to execute: {$description}" );
                } else {
                    WP_CLI::log( "    Affected rows: {$result}" );
                }
            }
        }
    }

    /**
     * Estimate how many rows would be affected by a query.
     *
     * @param string $query The SQL query to analyze.
     * @return int|false The estimated row count or false on failure.
     */
    private function estimate_affected_rows( $query ) {
        global $wpdb;

        // Extract table name and WHERE clause from UPDATE queries
        if ( preg_match( '/UPDATE\s+(\S+)\s+SET\s+.*?(WHERE\s+.*)?$/is', $query, $matches ) ) {
            $table = $matches[1];
            $where = isset( $matches[2] ) ? $matches[2] : '';
            $count_query = "SELECT COUNT(*) FROM {$table} {$where}";
            return (int) $wpdb->get_var( $count_query );
        }

        // Extract table name and WHERE clause from DELETE queries
        if ( preg_match( '/DELETE\s+FROM\s+(\S+)\s*(WHERE\s+.*)?$/is', $query, $matches ) ) {
            $table = $matches[1];
            $where = isset( $matches[2] ) ? $matches[2] : '';
            $count_query = "SELECT COUNT(*) FROM {$table} {$where}";
            return (int) $wpdb->get_var( $count_query );
        }

        return false;
    }

    /**
     * Get array of sanitization queries to erase personal data.
     *
     * @param bool $skip_forms Whether to skip form submission erasure.
     * @return array Associative array of description => SQL query.
     */
    private function get_sanitization_queries( $skip_forms = false ) {
        global $wpdb;

        $queries = [
            'Anonymize user emails' => "
                UPDATE {$wpdb->users}
                SET user_email = CONCAT('user', ID, '@example.com')
                WHERE ID > 1
            ",
            'Clear user display names' => "
                UPDATE {$wpdb->users}
                SET display_name = CONCAT('User ', ID)
                WHERE ID > 1
            ",
            'Clear user first and last names' => "
                UPDATE {$wpdb->usermeta}
                SET meta_value = ''
                WHERE meta_key IN ('first_name', 'last_name', 'nickname')
            ",
            'Clear user descriptions' => "
                UPDATE {$wpdb->usermeta}
                SET meta_value = ''
                WHERE meta_key = 'description'
            ",
            'Anonymize comment authors' => "
                UPDATE {$wpdb->comments}
                SET comment_author = 'Anonymous',
                    comment_author_email = 'anonymous@example.com',
                    comment_author_url = '',
                    comment_author_IP = '0.0.0.0'
            ",
            'Clear personal data from comment meta' => "
                DELETE FROM {$wpdb->commentmeta}
                WHERE meta_key LIKE '%author%'
                   OR meta_key LIKE '%email%'
                   OR meta_key LIKE '%ip%'
            ",
        ];

        // WooCommerce customer data anonymization
        $wc_customers_table = $wpdb->prefix . 'wc_customer_lookup';
        if ( $this->table_exists( $wc_customers_table ) ) {
            $queries['Anonymize WooCommerce customer names'] = "
                UPDATE {$wc_customers_table}
                SET first_name = 'Customer',
                    last_name = CONCAT('#', customer_id)
            ";
            $queries['Anonymize WooCommerce customer emails'] = "
                UPDATE {$wc_customers_table}
                SET email = CONCAT('customer', customer_id, '@example.com')
            ";
            $queries['Anonymize WooCommerce customer addresses'] = "
                UPDATE {$wc_customers_table}
                SET postcode = '',
                    city = '',
                    state = ''
            ";
        }

        // WooCommerce order billing/shipping addresses
        $postmeta_table = $wpdb->postmeta;
        $queries['Anonymize WooCommerce order billing names'] = "
            UPDATE {$postmeta_table}
            SET meta_value = 'Anonymous'
            WHERE meta_key IN ('_billing_first_name', '_billing_last_name', '_shipping_first_name', '_shipping_last_name')
        ";
        $queries['Anonymize WooCommerce order billing emails'] = "
            UPDATE {$postmeta_table}
            SET meta_value = 'anonymous@example.com'
            WHERE meta_key = '_billing_email'
        ";
        $queries['Anonymize WooCommerce order billing phones'] = "
            UPDATE {$postmeta_table}
            SET meta_value = ''
            WHERE meta_key IN ('_billing_phone', '_shipping_phone')
        ";
        $queries['Anonymize WooCommerce order addresses'] = "
            UPDATE {$postmeta_table}
            SET meta_value = ''
            WHERE meta_key IN (
                '_billing_address_1', '_billing_address_2', '_billing_city', 
                '_billing_state', '_billing_postcode', '_billing_country',
                '_shipping_address_1', '_shipping_address_2', '_shipping_city',
                '_shipping_state', '_shipping_postcode', '_shipping_country',
                '_billing_company', '_shipping_company'
            )
        ";

        // Contact Form 7 - Flamingo plugin (stores form submissions)
        if ( ! $skip_forms ) {
            $flamingo_table = $wpdb->prefix . 'flamingo_inbound';
            if ( $this->table_exists( $flamingo_table ) ) {
                $queries['Clear Flamingo contact form submissions'] = "
                    DELETE FROM {$flamingo_table}
                ";
            }
        }

        // Gravity Forms entries
        if ( ! $skip_forms ) {
            $gf_entry_table = $wpdb->prefix . 'gf_entry';
            if ( $this->table_exists( $gf_entry_table ) ) {
                $queries['Anonymize Gravity Forms entry IPs'] = "
                    UPDATE {$gf_entry_table}
                    SET ip = '0.0.0.0',
                        source_url = '',
                        user_agent = ''
                ";
                
                $gf_entry_meta_table = $wpdb->prefix . 'gf_entry_meta';
                
                // Redact all text-based field values (comprehensive approach)
                $queries['Clear Gravity Forms text field values'] = "
                    UPDATE {$gf_entry_meta_table}
                    SET meta_value = '[REDACTED]'
                    WHERE meta_value != ''
                    AND LENGTH(meta_value) > 0
                    AND meta_value NOT IN ('yes', 'no', '1', '0')
                ";
            }

            // Gravity Forms legacy RG tables (older versions)
            $rg_lead_table = $wpdb->prefix . 'rg_lead';
            if ( $this->table_exists( $rg_lead_table ) ) {
                $queries['Anonymize Gravity Forms legacy entry IPs'] = "
                    UPDATE {$rg_lead_table}
                    SET ip = '0.0.0.0',
                        source_url = '',
                        user_agent = ''
                ";
                
                $rg_lead_detail_table = $wpdb->prefix . 'rg_lead_detail';
                if ( $this->table_exists( $rg_lead_detail_table ) ) {
                    $queries['Clear Gravity Forms legacy lead detail values'] = "
                        UPDATE {$rg_lead_detail_table}
                        SET value = '[REDACTED]'
                        WHERE value != ''
                        AND LENGTH(value) > 0
                        AND value NOT IN ('yes', 'no', '1', '0')
                    ";
                }
            }
        }

        // Ninja Forms submissions
        if ( ! $skip_forms ) {
            $nf_submissions_table = $wpdb->prefix . 'nf3_submissions';
            if ( $this->table_exists( $nf_submissions_table ) ) {
                $queries['Clear Ninja Forms submissions'] = "
                    DELETE FROM {$nf_submissions_table}
                ";
            }
        }

        // WPForms entries
        if ( ! $skip_forms ) {
            $wpforms_entries_table = $wpdb->prefix . 'wpforms_entries';
            if ( $this->table_exists( $wpforms_entries_table ) ) {
                $queries['Anonymize WPForms entry IPs'] = "
                    UPDATE {$wpforms_entries_table}
                    SET ip_address = '0.0.0.0',
                        user_agent = ''
                ";
                
                $wpforms_entry_fields_table = $wpdb->prefix . 'wpforms_entry_fields';
                
                // Redact all field values (comprehensive approach)
                $queries['Clear WPForms field values'] = "
                    UPDATE {$wpforms_entry_fields_table}
                    SET value = '[REDACTED]'
                    WHERE value != ''
                    AND LENGTH(value) > 0
                ";
            }
        }

        // MemberPress user metadata
        $mp_user_meta_table = $wpdb->prefix . 'mepr_members';
        if ( $this->table_exists( $mp_user_meta_table ) ) {
            $queries['Clear MemberPress member custom fields'] = "
                DELETE FROM {$wpdb->usermeta}
                WHERE meta_key LIKE 'mepr-%'
                AND meta_key NOT LIKE 'mepr-active-memberships'
                AND meta_key NOT LIKE 'mepr-product-%'
            ";
        }

        // Easy Digital Downloads customer data
        $edd_customers_table = $wpdb->prefix . 'edd_customers';
        if ( $this->table_exists( $edd_customers_table ) ) {
            $queries['Anonymize EDD customer names'] = "
                UPDATE {$edd_customers_table}
                SET name = CONCAT('Customer #', id)
            ";
            $queries['Anonymize EDD customer emails'] = "
                UPDATE {$edd_customers_table}
                SET email = CONCAT('customer', id, '@example.com')
            ";
        }

        // BuddyPress/BuddyBoss extended profile data
        $bp_xprofile_table = $wpdb->prefix . 'bp_xprofile_data';
        if ( $this->table_exists( $bp_xprofile_table ) ) {
            $queries['Clear BuddyPress extended profile data'] = "
                UPDATE {$bp_xprofile_table}
                SET value = '[REDACTED]'
                WHERE field_id > 1
            ";
        }

        // Newsletter subscriptions (Newsletter plugin)
        $newsletter_table = $wpdb->prefix . 'newsletter';
        if ( $this->table_exists( $newsletter_table ) ) {
            $queries['Anonymize Newsletter subscribers'] = "
                UPDATE {$newsletter_table}
                SET email = CONCAT('subscriber', id, '@example.com'),
                    name = 'Subscriber',
                    surname = CONCAT('#', id),
                    ip = '0.0.0.0'
            ";
        }

        // MailPoet subscribers
        $mailpoet_subscribers_table = $wpdb->prefix . 'mailpoet_subscribers';
        if ( $this->table_exists( $mailpoet_subscribers_table ) ) {
            $queries['Anonymize MailPoet subscribers'] = "
                UPDATE {$mailpoet_subscribers_table}
                SET email = CONCAT('subscriber', id, '@example.com'),
                    first_name = 'Subscriber',
                    last_name = CONCAT('#', id)
            ";
        }

        // WP User Manager custom fields
        $wpum_fields_table = $wpdb->prefix . 'wpum_field_meta';
        if ( $this->table_exists( $wpum_fields_table ) ) {
            $queries['Clear WPUM custom profile fields'] = "
                DELETE FROM {$wpdb->usermeta}
                WHERE meta_key LIKE 'wpum_field_%'
            ";
        }

        // WooCommerce Subscriptions
        $queries['Clear WooCommerce subscription custom meta'] = "
            DELETE FROM {$postmeta_table}
            WHERE meta_key LIKE '_subscription_%'
            AND meta_key IN (
                '_subscription_renewal_payment_method_title',
                '_subscription_payment_method_change_history'
            )
        ";

        // bbPress forum user data
        $queries['Anonymize bbPress author IPs'] = "
            UPDATE {$postmeta_table}
            SET meta_value = '0.0.0.0'
            WHERE meta_key = '_bbp_author_ip'
        ";

        // LearnDash quiz/assignment submissions
        $ld_user_activity_table = $wpdb->prefix . 'learndash_user_activity';
        if ( $this->table_exists( $ld_user_activity_table ) ) {
            $queries['Clear LearnDash user activity meta'] = "
                UPDATE {$ld_user_activity_table}
                SET activity_meta = ''
            ";
        }

        // WP Mail SMTP email logs
        $wp_mail_smtp_logs_table = $wpdb->prefix . 'wpmailsmtp_emails_log';
        if ( $this->table_exists( $wp_mail_smtp_logs_table ) ) {
            $queries['Clear WP Mail SMTP email logs'] = "
                DELETE FROM {$wp_mail_smtp_logs_table}
            ";
        }

        $wp_mail_smtp_attachments_table = $wpdb->prefix . 'wpmailsmtp_attachment_files';
        if ( $this->table_exists( $wp_mail_smtp_attachments_table ) ) {
            $queries['Clear WP Mail SMTP attachment files'] = "
                DELETE FROM {$wp_mail_smtp_attachments_table}
            ";
        }

        // User Registration logs (various plugins)
        $queries['Clear user registration IP addresses'] = "
            DELETE FROM {$wpdb->usermeta}
            WHERE meta_key LIKE '%_ip%'
               OR meta_key LIKE '%_ip_address%'
               OR meta_key IN ('user_registration_ip', 'registration_ip', 'signup_ip')
        ";

        // Clear any password reset keys
        $queries['Clear password reset keys'] = "
            DELETE FROM {$wpdb->usermeta}
            WHERE meta_key = 'password_reset_key'
        ";

        // Clear session tokens
        $queries['Clear user session tokens'] = "
            DELETE FROM {$wpdb->usermeta}
            WHERE meta_key = 'session_tokens'
        ";

        // Pronamic Pay payment data
        $pronamic_payments_table = $wpdb->prefix . 'pronamic_pay_payments';
        if ( $this->table_exists( $pronamic_payments_table ) ) {
            $queries['Anonymize Pronamic Pay customer names'] = "
                UPDATE {$pronamic_payments_table}
                SET customer_name = 'Anonymous Customer'
            ";
            $queries['Anonymize Pronamic Pay email addresses'] = "
                UPDATE {$pronamic_payments_table}
                SET email = CONCAT('payment', id, '@example.com')
            ";
            $queries['Clear Pronamic Pay contact details'] = "
                UPDATE {$pronamic_payments_table}
                SET telephone_number = '',
                    company_name = '',
                    address = '',
                    city = '',
                    zip = '',
                    country = ''
                WHERE telephone_number IS NOT NULL
                   OR company_name IS NOT NULL
                   OR address IS NOT NULL
            ";
        }

        return $queries;
    }

    /**
     * Check if a database table exists.
     *
     * @param string $table_name Full table name including prefix.
     * @return bool True if table exists, false otherwise.
     */
    private function table_exists( $table_name ) {
        global $wpdb;
        $result = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) );
        return $result === $table_name;
    }
}
