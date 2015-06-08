<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class Expressly extends ModuleCore
{
    public $app;
    public $dispatcher;

    public function __construct()
    {
        $this->name = "expressly";
        $this->tab = "advertising";
        $this->version = "1.0.0";
        $this->author = "Expressly";
        $this->need_instance = 1;
        $this->ps_versions_compliancy = array(
            'min' => '1.6',
            'max' => _PS_VERSION_
        );
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = 'Expressly';
        $this->description = 'Description.';
        $this->confirmUninstall = 'Are you sure you want to uninstall?';
        require __DIR__ . '/vendor/autoload.php';
        $this->setup();
    }

    private function setup()
    {
        $expressly = new Expressly\Client(Expressly\Entity\MerchantType::PRESTASHOP);
        $app = $expressly->getApp();

        // override MerchantProvider
        $app['merchant.provider'] = $app->share(function () {
            return new Module\Expressly\MerchantProvider();
        });

        $this->app = $app;
        $this->dispatcher = $this->app['dispatcher'];
    }

    public function install($register = false)
    {
        $url = sprintf('http://%s', $_SERVER['HTTP_HOST']);

        ConfigurationCore::updateValue('EXPRESSLY_PREFERENCES_UUID', '');
        ConfigurationCore::updateValue('EXPRESSLY_PREFERENCES_IMAGE',
            sprintf('%s/img/%s', $url, ConfigurationCore::get('PS_LOGO')));
        ConfigurationCore::updateValue('EXPRESSLY_PREFERENCES_TERMS', $url . '/content/3-terms-and-conditions-of-use');
        ConfigurationCore::updateValue('EXPRESSLY_PREFERENCES_POLICY', $url . '/content/3-terms-and-conditions-of-use');
        ConfigurationCore::updateValue('EXPRESSLY_PREFERENCES_HOST', $url);
        ConfigurationCore::updateValue('EXPRESSLY_PREFERENCES_DESTINATION', '/');
        ConfigurationCore::updateValue('EXPRESSLY_PREFERENCES_OFFER', true);
        ConfigurationCore::updateValue('EXPRESSLY_PREFERENCES_PASSWORD', '');
        ConfigurationCore::updateValue('EXPRESSLY_PREFERENCES_PATH',
            '?controller=dispatcher&fc=module&module=expressly&xly=');

        try {
            $provider = $this->app['merchant.provider'];
            $merchant = $provider->getMerchant(true);
            $event = new Expressly\Event\MerchantEvent($merchant);
            $this->dispatcher->dispatch('merchant.register', $event);

            $content = $event->getContent();
//            if (!$event->getResponse()->isSuccessful()) {
//                $this->error = true;
//                throw new \Exception(self::processError($event));
//            }

            $merchant
                ->setUuid($content['uuid'])
                ->setPassword($content['secretKey']);

            $provider->setMerchant($merchant);

            if (!parent::install()) {
                return false;
            }
        } catch (Buzz\Exception\RequestException $e) {
            $this->app['logger']->addError((string)$e);
            $this->_errors[] = $e->getMessage() . '. Please contact expressly.';

            return false;
        } catch (\Exception $e) {
            $this->app['logger']->addError((string)$e);
            $this->_errors[] = (string)$e->getMessage();

            return false;
        }

        return true;
    }

    public static function processError(Symfony\Component\EventDispatcher\Event $event)
    {
        $content = $event->getContent();
        $message[] = $content['message'];

        $addBulletpoints = function ($key) use ($content, $message) {
            if (!empty($content[$key])) {
                $message[] = '<ul>';

                foreach ($content[$key] as $point) {
                    $message[] = "<li>{$point}</li>";
                }

                $message[] = '</ul>';
            }
        };

        $addBulletpoints('actions');
        $addBulletpoints('causes');

        $output = '
	 	<div class="bootstrap">
		<div class="module_error alert alert-danger" >
			<button type="button" class="close" data-dismiss="alert">&times;</button>
			'.implode('', $message).'
		</div>
		</div>';
        return $output;

        return implode('', $message);
    }

    public function getContent()
    {
        /*
         * This is a terrible method to display errors
         * TODO: Evaluate an override that makes sense
         */
        $error = '';

        try {
            if (Tools::isSubmit('submitExpresslyPreferences')) {
                ConfigurationCore::updateValue('EXPRESSLY_PREFERENCES_IMAGE',
                    Tools::getValue('EXPRESSLY_PREFERENCES_IMAGE'));
                ConfigurationCore::updateValue('EXPRESSLY_PREFERENCES_TERMS',
                    Tools::getValue('EXPRESSLY_PREFERENCES_TERMS'));
                ConfigurationCore::updateValue('EXPRESSLY_PREFERENCES_POLICY',
                    Tools::getValue('EXPRESSLY_PREFERENCES_POLICY'));
                ConfigurationCore::updateValue('EXPRESSLY_PREFERENCES_DESTINATION',
                    Tools::getValue('EXPRESSLY_PREFERENCES_DESTINATION'));
                ConfigurationCore::updateValue('EXPRESSLY_PREFERENCES_OFFER',
                    Tools::getValue('EXPRESSLY_PREFERENCES_OFFER'));

                if (ConfigurationCore::get('EXPRESSLY_PREFERENCES_PASSWORD') != Tools::getValue('EXPRESSLY_PREFERENCES_PASSWORD')) {
                    $oldPassword = ConfigurationCore::get('EXPRESSLY_PREFERENCES_PASSWORD');
                    ConfigurationCore::updateValue('EXPRESSLY_PREFERENCES_PASSWORD',
                        Tools::getValue('EXPRESSLY_PREFERENCES_PASSWORD'));

                    // Send password update
                    $merchant = $this->app['merchant.provider']->getMerchant(true);
                    $event = new Expressly\Event\MerchantUpdatePasswordEvent($merchant, $oldPassword);
                    $this->dispatcher->dispatch('merchant.password.update', $event);
                }

                $merchant = $this->app['merchant.provider']->getMerchant(true);
                $event = new Expressly\Event\MerchantEvent($merchant);
                $this->dispatcher->dispatch('merchant.update', $event);

                if (!$event->isSuccessful()) {
                    $error = self::processError($event);
                }
            }
        } catch (\Exception $e) {
            $this->app['logger']->addError((string)$e);
            $error = $this->displayError((string)$e);
        }

        return $error . $this->displayForm();
    }

    public function displayForm()
    {
        $uuid = ConfigurationCore::get('EXPRESSLY_PREFERENCES_UUID');
        $image = ConfigurationCore::get('EXPRESSLY_PREFERENCES_IMAGE');
        $terms = ConfigurationCore::get('EXPRESSLY_PREFERENCES_TERMS');
        $policy = ConfigurationCore::get('EXPRESSLY_PREFERENCES_POLICY');
        $password = ConfigurationCore::get('EXPRESSLY_PREFERENCES_PASSWORD');

        $fields = array(
            'form' => array(
                'legend' => array(
                    'title' => 'Expressly',
                    'icon' => 'icon-cogs'
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => 'Shop Image URL',
                        'desc' => sprintf('<img src="%s" />', $image),
                        'name' => 'EXPRESSLY_PREFERENCES_IMAGE',
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => 'Terms and Conditions URL',
                        'desc' => sprintf('URL for the Terms & Conditions for your store. <a href="%s">Check</a>',
                            $terms),
                        'name' => 'EXPRESSLY_PREFERENCES_TERMS',
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => 'Privacy Policy URL',
                        'desc' => sprintf('URL for the Privacy Policy for your store. <a href="%s">Check</a>', $policy),
                        'name' => 'EXPRESSLY_PREFERENCES_POLICY',
                        'required' => true
                    ),
//                    array(
//                        'type' => 'text',
//                        'label' => 'Destination',
//                        'desc' => 'Redirect destination after checkout',
//                        'name' => 'EXPRESSLY_PREFERENCES_DESTINATION',
//                        'required' => true
//                    ),
//                    array(
//                        'type' => 'switch',
//                        'label' => 'Show offers',
//                        'desc' => 'Show offers after checkout',
//                        'name' => 'EXPRESSLY_PREFERENCES_OFFER',
//                        'is_bool' => true,
//                        'values' => array(
//                            array(
//                                'id' => 'active_on',
//                                'value' => true,
//                                'label' => 'Enabled'
//                            ),
//                            array(
//                                'id' => 'active_off',
//                                'value' => false,
//                                'label' => 'Disabled'
//                            )
//                        ),
//                        'required' => true
//                    ),
                    array(
                        'type' => 'text',
                        'label' => 'Password',
                        'desc' => 'Expressly password for your store',
                        'name' => 'EXPRESSLY_PREFERENCES_PASSWORD',
                        'required' => true
                    )
                ),
                'submit' => array(
                    'title' => 'Save'
                )
            )
        );

        if (empty($uuid) && empty($password)) {
            $register = ToolsCore::getValue('register');
            if (!empty($register)) {
                $this->install(true);
            }

            $fields['form']['buttons'][] = array(
                'href' => '#',
                'title' => 'Register',
                'icon' => 'process-icon-envelope'
            );
        }

        $form = new HelperFormCore();
        $form->module = $this;
        $form->show_toolbar = false;
        $form->table = $this->table;
        $form->identifier = $this->identifier;
        $form->submit_action = 'submitExpresslyPreferences';
        $form->token = Tools::getAdminTokenLite('AdminModules');

        $form->fields_value['EXPRESSLY_PREFERENCES_IMAGE'] = $image;
        $form->fields_value['EXPRESSLY_PREFERENCES_TERMS'] = $terms;
        $form->fields_value['EXPRESSLY_PREFERENCES_POLICY'] = $policy;
//        $form->fields_value['EXPRESSLY_PREFERENCES_DESTINATION'] = ConfigurationCore::get('EXPRESSLY_PREFERENCES_DESTINATION');
//        $form->fields_value['EXPRESSLY_PREFERENCES_OFFER'] = ConfigurationCore::get('EXPRESSLY_PREFERENCES_OFFER');
        $form->fields_value['EXPRESSLY_PREFERENCES_PASSWORD'] = $password;

        $language = new LanguageCore((int)ConfigurationCore::get('PS_LANG_DEFAULT'));
        $form->default_form_language = $language->id;

        return $form->generateForm(array($fields));
    }

    public function uninstall()
    {
        if (!parent::uninstall()) {
            return false;
        }

        $merchant = $this->app['merchant.provider']->getMerchant();
        $this->dispatcher->dispatch('merchant.delete', new Expressly\Event\PasswordedEvent($merchant));

        ConfigurationCore::deleteByName('EXPRESSLY_PREFERENCES_IMAGE');
        ConfigurationCore::deleteByName('EXPRESSLY_PREFERENCES_TERMS');
        ConfigurationCore::deleteByName('EXPRESSLY_PREFERENCES_POLICY');
        ConfigurationCore::deleteByName('EXPRESSLY_PREFERENCES_HOST');
//        ConfigurationCore::deleteByName('EXPRESSLY_PREFERENCES_DESTINATION');
//        ConfigurationCore::deleteByName('EXPRESSLY_PREFERENCES_OFFER');
        ConfigurationCore::deleteByName('EXPRESSLY_PREFERENCES_PASSWORD');
        ConfigurationCore::deleteByName('EXPRESSLY_PREFERENCES_PATH');

        return true;
    }
}