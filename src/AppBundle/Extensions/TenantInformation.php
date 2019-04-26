<?php
namespace AppBundle\Extensions;

use AppBundle\Services\BillingService;
use AppBundle\Settings\Settings;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Session\Session;
use Doctrine\ORM\EntityManager;

/**
 * Class TenantInformation
 * @package AppBundle\Extensions
 *
 * Make tenant information (from _core DB) available to controllers and templates
 *
 * Much of the session variables are set in CustomConnectionFactory class
 */
class TenantInformation
{
    /**
     * @var Session
     */
    private $session;

    /**
     * @var Container
     */
    private $container;

    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @var BillingService
     */
    private $billingService;

    /**
     * @var Settings
     */
    private $settings;

    function __construct(Session $session,
                         Container $container,
                         EntityManager $entityManager,
                          BillingService $billingService,
                         Settings $settingsService
                    )
    {
        $this->session = $session;
        $this->container = $container;
        $this->entityManager = $entityManager;
        $this->billingService = $billingService;
        $this->settings = $settingsService;
    }

    /**
     * Session information is set in CustomConnectionFactory, when we go to _core DB
     * We don't use container in CustomConnectionFactory due to "Impossible to call set() on a frozen ParameterBag"
     * @return mixed
     */
    public function getAccountCode()
    {
        return $this->session->get('account_code');
    }

    // Used for the location of files on S3 bucket too
    public function getSchema()
    {
        return $this->session->get('account_schema');
    }

    public function getTrialExpiresAt()
    {
        return $this->session->get('trial_expires_at');
    }

    public function getPlan()
    {
        return $this->session->get('plan');
    }

    public function getSubscriptionId()
    {
        return $this->session->get('subscription_id');
    }

    public function getAccountName()
    {
        return $this->session->get('account_name');
    }

    public function getAccountOwnerName()
    {
        return $this->session->get('account_owner_name');
    }

    public function getAccountOwnerEmail()
    {
        return $this->session->get('account_owner_email');
    }

    public function getAccountStatus()
    {
        return $this->session->get('account_status');
    }

    public function getServerName()
    {
        return $this->session->get('server_name');
    }

    public function getAccountDomain()
    {
        return $this->session->get('account_domain');
    }

    public function getCurrency()
    {
        return $this->settings->getSettingValue('org_currency');
    }

    public function getCurrencySymbol()
    {
        $iso = $this->getCurrency();
        return \Symfony\Component\Intl\Intl::getCurrencyBundle()->getCurrencySymbol($iso);
    }

    public function getCountry()
    {
        return $this->settings->getSettingValue('org_country');
    }

    public function getLocale()
    {
        return $this->settings->getSettingValue('org_locale');
    }

    public function getLanguages()
    {
        return explode(',', $this->settings->getSettingValue('org_languages'));
    }

    public function getFixedFeePricing()
    {
        return $this->settings->getSettingValue('fixed_fee_pricing');
    }

    public function getOrgPostcode()
    {
        return $this->settings->getSettingValue('org_postcode');
    }

    public function getLogoUrl()
    {
        return $this->settings->getSettingValue('org_logo_url');
    }

    public function getIndustry()
    {
        return $this->settings->getSettingValue('industry');
    }

    public function getCompanyName()
    {
        return $this->settings->getSettingValue('org_name');
    }

    public function getCompanyEmail()
    {
        return $this->settings->getSettingValue('org_email');
    }

    public function getCompanyEmailFooter()
    {
        return $this->settings->getSettingValue('org_email_footer');
    }

    public function getS3Bucket()
    {
        return $this->container->getParameter('s3_bucket');
    }

    public function getStripePublicApiKey()
    {
        return $this->settings->getSettingValue('stripe_publishable_key');
    }

    public function getStripeAccessToken()
    {
        return $this->settings->getSettingValue('stripe_access_token');
    }

    public function getStripePaymentMethodId()
    {
        return (int)$this->settings->getSettingValue('stripe_payment_method');
    }

    public function getStripeFee()
    {
        return (float)$this->settings->getSettingValue('stripe_fee');
    }

    public function getMinimumPaymentAmount()
    {
        return (float)$this->settings->getSettingValue('stripe_minimum_payment');
    }

    /* Website features */

    public function getSiteTheme()
    {
        if ($this->session->get('previewThemeName')) {
            return $this->session->get('previewThemeName');
        }

        if ($themeName = $this->settings->getSettingValue('site_theme_name')) {
            return $themeName;
        }

        return "default"; // the original theme
    }

    /** this is set when an admin enables site editor mode */
    public function getIsEditMode()
    {
        return $this->session->get('isEditMode');
    }

    public function getSiteHomePage()
    {
        $repo = $this->entityManager->getRepository("AppBundle:Page");

        $criteria = [
            'visibility' => 'PUBLIC',
            'url' => null
        ];
        $page = $repo->findOneBy($criteria, ['sort' => 'ASC']);
        return $page;
    }

    public function getSiteWelcomeUser()
    {
        return $this->settings->getSettingValue('site_welcome_user');
    }

    public function getSiteCSS()
    {
        return $this->settings->getSettingValue('site_css');
    }

    // Custom JavaScript for the public site
    public function getSiteJs()
    {
        return $this->settings->getSettingValue('site_js');
    }

    public function getAllowRegistration()
    {
        return $this->settings->getSettingValue('site_allow_registration');
    }

    // Require log in to search and view items
    public function getSiteIsPrivate()
    {
        return $this->settings->getSettingValue('site_is_private');
    }

    // A link to terms and conditions for the registration page
    public function getTermsUri()
    {
        return $this->settings->getSettingValue('registration_terms_uri');
    }

    public function getCodeStub()
    {
        return $this->settings->getSettingValue('auto_sku_stub');
    }

    public function getLateFee()
    {
        return $this->settings->getSettingValue('daily_overdue_fee');
    }

    public function getGoogleLogin()
    {
        return $this->settings->getSettingValue('site_google_login');
    }

    public function getFacebookLogin()
    {
        return $this->settings->getSettingValue('site_facebook_login');
    }

    public function getTwitterLogin()
    {
        return $this->settings->getSettingValue('site_twitter_login');
    }

    /* Email templates */

    public function getEmailWelcomeSubject()
    {
        return nl2br($this->settings->getSettingValue('email_welcome_subject'));
    }

    public function getEmailWelcomeHeader()
    {
        return nl2br($this->settings->getSettingValue('email_welcome_head'));
    }

    public function getEmailWelcomeFooter()
    {
        return nl2br($this->settings->getSettingValue('email_welcome_foot'));
    }

    public function getEmailLoanConfirmationHeader()
    {
        return nl2br($this->settings->getSettingValue('email_loan_confirmation_head'));
    }

    public function getEmailLoanConfirmationFooter()
    {
        return nl2br($this->settings->getSettingValue('email_loan_confirmation_foot'));
    }

    public function getEmailLoanReminderHeader()
    {
        return nl2br($this->settings->getSettingValue('email_loan_reminder_head'));
    }

    public function getEmailLoanReminderFooter()
    {
        return nl2br($this->settings->getSettingValue('email_loan_reminder_foot'));
    }

    public function getEmailReservationReminderHeader()
    {
        return nl2br($this->settings->getSettingValue('email_reservation_reminder_head'));
    }

    public function getEmailReservationReminderFooter()
    {
        return nl2br($this->settings->getSettingValue('email_reservation_reminder_foot'));
    }

    public function getEmailMembershipExpiryHeader()
    {
        return nl2br($this->settings->getSettingValue('email_membership_expiry_head'));
    }

    public function getEmailMembershipExpiryFooter()
    {
        return nl2br($this->settings->getSettingValue('email_membership_expiry_foot'));
    }

    public function getEmailLoanExtensionHeader()
    {
        return nl2br($this->settings->getSettingValue('email_loan_extension_head'));
    }

    public function getEmailLoanExtensionFooter()
    {
        return nl2br($this->settings->getSettingValue('email_loan_extension_foot'));
    }

    public function getEmailReservationHeader()
    {
        return nl2br($this->settings->getSettingValue('email_reserve_confirmation_head'));
    }

    public function getEmailReservationFooter()
    {
        return nl2br($this->settings->getSettingValue('email_reserve_confirmation_foot'));
    }

    public function getEmailOverdueHeader()
    {
        return nl2br($this->settings->getSettingValue('email_loan_overdue_head'));
    }

    public function getEmailOverdueFooter()
    {
        return nl2br($this->settings->getSettingValue('email_loan_overdue_foot'));
    }

    /** OTHER */

    public function getMailchimpApiKey()
    {
        return $this->settings->getSettingValue('mailchimp_api_key');
    }

    public function getEnabledWaitingList()
    {
        return $this->settings->getSettingValue('enable_waiting_list');
    }

    public function getChargeDailyFee()
    {
        return $this->settings->getSettingValue('charge_daily_fee');
    }

    public function getIsMultiSite()
    {
        return $this->settings->getSettingValue('multi_site');
    }

    public function getBasket()
    {
        /** @var $basket \AppBundle\Entity\Loan */
        $basket = $this->session->get('basket');
        return $basket;
    }

    public function getOpeningHoursAreSet()
    {
        return (int)$this->settings->getSettingValue('setup_opening_hours');
    }

    public function getFeature($feature)
    {
        return $this->billingService->isEnabled($this->session->get('plan'), $feature);
    }

    public function getLoanTerms()
    {
        return $this->settings->getSettingValue('loan_terms');
    }

    public function getSiteFontName()
    {
        return $this->settings->getSettingValue('site_font_name');
    }

}