<?php

namespace AppBundle\Repository;

/**
 * SettingRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class SettingRepository extends \Doctrine\ORM\EntityRepository
{

    /**
     * @return array
     */
    public function getSettingsKeys()
    {
        $validKeys = array(
            'org_timezone',
            'org_currency',
            'default_checkin_location',
            'default_loan_fee',
            'default_loan_days',
            'min_loan_days',
            'max_loan_days',
            'daily_overdue_fee',
            'org_name',
            'org_address',
            'org_country',
            'org_postcode',
            'org_email',
            'org_email_footer',
            'org_logo_url',
            'org_locale',
            'org_languages',
            'industry',

            // Reminders
            'automate_email_loan_reminder',
            'automate_email_reservation_reminder',
            'automate_email_membership',
            'automate_email_overdue_days',

            // Setup values
            'multi_site',
            'setup_opening_hours',

            // Stripe card details
            'stripe_access_token',
            'stripe_refresh_token',
            'stripe_user_id',
            'stripe_publishable_key',
            'stripe_payment_method',
            'stripe_minimum_payment',
            'stripe_fee',
            'stripe_use_saved_cards',

            'site_is_private',
            'site_welcome',
            'site_welcome_user',
            'site_css',
            'site_js',
            'site_google_login',
            'site_facebook_login',
            'site_twitter_login',
            'site_allow_registration',
            'site_description',
            'site_font_name',
            'site_theme_name',
            'logo_image_name',

            'registration_terms_uri',
            'auto_sku_stub',

            'email_membership_expiry_head',
            'email_membership_expiry_foot',

            'email_loan_reminder_head',
            'email_loan_reminder_foot',

            'email_loan_overdue_head',
            'email_loan_overdue_foot',

            'email_reservation_reminder_head',
            'email_reservation_reminder_foot',

            'email_loan_confirmation_subject',
            'email_loan_confirmation_head',
            'email_loan_confirmation_foot',

            'email_reserve_confirmation_subject',
            'email_reserve_confirmation_head',
            'email_reserve_confirmation_foot',

            'email_loan_extension_subject',
            'email_loan_extension_head',
            'email_loan_extension_foot',

            'email_welcome_subject',
            'email_welcome_head',
            'email_welcome_foot',

            'loan_terms', // terms and conditions

            'mailchimp_api_key',
            'mailchimp_default_list_id',
            'mailchimp_double_optin',
            'enable_waiting_list',

            'reservation_fee',
            'charge_daily_fee',
            'fixed_fee_pricing',

            'open_days', // legacy, now done per site
        );

        return $validKeys;
    }

    /**
     * @return array
     */
    public function getAllSettings()
    {
        $settingsArray = array();

        // initialise the settings array in case the DB has no values
        $keys = $this->getSettingsKeys();
        foreach ($keys AS $key) {
            $settingsArray[$key] = '';
        }

        $settings = $this->findAll();

        foreach ($settings AS $setting) {
            /** @var $setting \AppBundle\Entity\Setting */
            $setupKey   = $setting->getSetupKey();
            $setupValue = $setting->getSetupValue();
            $settingsArray[$setupKey] = $setupValue;
        }

        // Set predefined values for new (as yet unset) settings
        // These will be shown in the UI, used in the app, and saved when settings are next saved
        // THIS CODE IS IN SettingRepository AND Setting service
        $newSettings = [
            'automate_email_loan_reminder' => 1,
            'automate_email_reservation_reminder' => 1,
            'automate_email_membership' => 1,
            'org_locale' => 'en',
        ];
        foreach ($newSettings AS $k => $v) {
            if ($settingsArray[$k] == null) {
                $settingsArray[$k] = $v;
            }
        }

        return $settingsArray;
    }

}
