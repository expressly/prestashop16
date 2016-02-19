<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class Expressly extends ModuleCore
{
    public $setup = false;
    public $app;
    public $dispatcher;

    public function __construct()
    {
        $this->name = "expressly";
        $this->tab = "advertising_marketing";
        $this->version = "0.3.0";
        $this->author = "Expressly";
        $this->need_instance = 1;
        $this->ps_versions_compliancy = array(
            'min' => '1.6',
            'max' => _PS_VERSION_
        );
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = 'Expressly';
        $this->description = 'https://buyexpressly.com';
        $this->confirmUninstall = 'Are you sure you want to uninstall?';
    }

    public function getApp()
    {
        $this->setup();

        return $this->app;
    }

    /**
     * Cannot be autoloaded in the constructor as Avalara disagrees with PHP namespaces being anymore than 1 level deep.
     * Application instantiation has to now be called explicitly.
     */
    private function setup()
    {
        if (!$this->setup) {
            /**
             * Has been reported to Avalara, awaiting response.
             * Temporary fix until Avalara fixes their autoloading issue, which violates PHP namespaces.
             */
            spl_autoload_unregister('avalaraAutoload');
            require_once __DIR__ . '/vendor/autoload.php';

            $expressly = new Expressly\Client(Expressly\Entity\MerchantType::PRESTASHOP);
            $app = $expressly->getApp();

            // override MerchantProvider
            $app['merchant.provider'] = function () {
                return new Module\Expressly\MerchantProvider();
            };

            $this->app = $app;
            $this->dispatcher = $this->app['dispatcher'];

            $this->setup = true;
        }
    }

    public function getDispatcher()
    {
        $this->setup();

        return $this->dispatcher;
    }

    public function getContent()
    {
        $errors = array();
        $this->setup();

        try {
            if (Tools::isSubmit('submitExpresslyPreferences')) {
                $provider = $this->app['merchant.provider'];
                $merchant = $provider->getMerchant();

                $merchant->setApiKey(Tools::getValue(Module\Expressly\MerchantProvider::APIKEY));
                $provider->setMerchant($merchant);

                $event = new Expressly\Event\PasswordedEvent($merchant);
                $this->dispatcher->dispatch(Expressly\Subscriber\MerchantSubscriber::MERCHANT_REGISTER, $event);

                if (!$event->isSuccessful()) {
                    throw new Expressly\Exception\GenericException(self::processError($event));
                }
            }
        } catch (Buzz\Exception\RequestException $e) {
            $this->app['logger']->error(Expressly\Exception\ExceptionFormatter::format($e));
            $errors[] = $this->displayError('We had trouble talking to the server. The server could be down; please contact expressly.');
        } catch (\Exception $e) {
            $this->app['logger']->error(Expressly\Exception\ExceptionFormatter::format($e));
            $errors[] = $this->displayError((string)$e->getMessage());
        }

        /*
         * This is a terrible method to display errors
         * TODO: Evaluate an override that makes sense
         */

        return implode('', $errors) . $this->displayForm();
    }

    public static function processError($event)
    {
        $content = $event->getContent();
        $message = array(
            $content['description']
        );

        $addBulletpoints = function ($key, $title) use ($content, &$message) {
            if (!empty($content[$key])) {
                $message[] = '<br>';
                $message[] = $title;
                $message[] = '<ul>';

                foreach ($content[$key] as $point) {
                    $message[] = "<li>{$point}</li>";
                }

                $message[] = '</ul>';
            }
        };

        // TODO: translatable
        $addBulletpoints('causes', 'Possible causes:');
        $addBulletpoints('actions', 'Possible resolutions:');

        return implode('', $message);
    }

    public function displayForm()
    {
        $fields = array(
            'form' => array(
                'legend' => array(
                    'title' => 'Expressly',
                    'icon' => 'icon-cogs'
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => 'API Key',
                        'desc' => 'API Key provided from our <a href="https://buyexpressly.com/#/install#api">portal</a>. If you do not have an API Key, please follow the previous link for instructions on how to create one.',
                        'name' => 'EXPRESSLY_PREFERENCES_APIKEY'
                    )
                ),
                'submit' => array(
                    'title' => 'Save'
                )
            )
        );

        $form = new HelperFormCore();
        $form->module = $this;
        $form->show_toolbar = false;
        $form->table = $this->table;
        $form->identifier = $this->identifier;
        $form->submit_action = 'submitExpresslyPreferences';
        $form->token = Tools::getAdminTokenLite('AdminModules');

        $form->fields_value[Module\Expressly\MerchantProvider::APIKEY] = ConfigurationCore::get(Module\Expressly\MerchantProvider::APIKEY);

        $language = new LanguageCore((int)ConfigurationCore::get('PS_LANG_DEFAULT'));
        $form->default_form_language = $language->id;

        return $form->generateForm(array($fields));
    }

    public function install($register = false)
    {
        if (!$register && !parent::install()) {
            return false;
        }

        $this->setup();

        $url = sprintf('http://%s', $_SERVER['HTTP_HOST']);
        $url = rtrim($url, '/') . '/';

        ConfigurationCore::updateValue(Module\Expressly\MerchantProvider::APIKEY, '');
        ConfigurationCore::updateValue(Module\Expressly\MerchantProvider::HOST, $url);
        ConfigurationCore::updateValue(
            Module\Expressly\MerchantProvider::PATH,
            '?controller=dispatcher&fc=module&module=expressly&xly='
        );

        $this->registerHook('DisplayThankYouBanner');

        return true;
    }

    /**
     * Add {hook h='displayThankYouBanner' mod='expressly'} to your theme template
     */
    public function hookDisplayThankYouBanner($params)
    {
        $this->setup();

        $merchant = $this->app['merchant.provider']->getMerchant();
        $email = $this->context->customer->email;

        $event = new Expressly\Event\BannerEvent($merchant, $email);

        try {
            $this->dispatcher->dispatch(Expressly\Subscriber\BannerSubscriber::BANNER_REQUEST, $event);

            if (!$event->isSuccessful()) {
                throw new Expressly\Exception\GenericException(self::processError($event));
            }
        } catch (Buzz\Exception\RequestException $e) {

            $this->app['logger']->error(Expressly\Exception\ExceptionFormatter::format($e));
            $errors[] = $this->displayError('We had trouble talking to the server. The server could be down; please contact expressly.');

        } catch (\Exception $e) {

            $this->app['logger']->error(Expressly\Exception\ExceptionFormatter::format($e));
            $errors[] = $this->displayError((string)$e->getMessage());

        }

        return Expressly\Helper\BannerHelper::toHtml($event);
    }

    public function uninstall()
    {
        if (!parent::uninstall()) {
            return false;
        }

        try {
            $this->setup();
            $merchant = $this->app['merchant.provider']->getMerchant();
            $this->dispatcher->dispatch(
                Expressly\Subscriber\MerchantSubscriber::MERCHANT_DELETE,
                new Expressly\Event\PasswordedEvent($merchant)
            );
        } catch (\Exception $e) {
            $this->app['logger']->error(Expressly\Exception\ExceptionFormatter::format($e));
        }

        ConfigurationCore::deleteByName(Module\Expressly\MerchantProvider::APIKEY);
        ConfigurationCore::deleteByName(Module\Expressly\MerchantProvider::HOST);
        ConfigurationCore::deleteByName(Module\Expressly\MerchantProvider::PATH);

        $this->unregisterHook('DisplayThankYouBanner');

        return true;
    }
}
