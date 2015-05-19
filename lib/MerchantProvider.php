<?php

namespace Module\Expressly;

use Expressly\Entity\Merchant;
use Expressly\Provider\MerchantProviderInterface;

class MerchantProvider implements MerchantProviderInterface
{
    private $merchant;

    public function __construct()
    {
        if (\ConfigurationCore::get('EXPRESSLY_PREFERENCES_DESTINATION')) {
            $this->updateMerchant();
        }
    }

    private function updateMerchant()
    {
        $merchant = new Merchant();
        $merchant
            ->setDestination(\ConfigurationCore::get('EXPRESSLY_PREFERENCES_DESTINATION'))
            ->setHost(\ConfigurationCore::get('EXPRESSLY_PREFERENCES_HOST'))
            ->setOffer(\ConfigurationCore::get('EXPRESSLY_PREFERENCES_OFFER'))
            ->setPassword(\ConfigurationCore::get('EXPRESSLY_PREFERENCES_PASSWORD'))
            ->setPath(\ConfigurationCore::get('EXPRESSLY_PREFERENCES_PATH'));

        $this->merchant = $merchant;
    }

    public function setMerchant(Merchant $merchant)
    {
        $this->merchant = $merchant;

        return $this;
    }

    public function getMerchant()
    {
        if (!$this->merchant instanceof Merchant) {
            $this->updateMerchant();
        }

        return $this->merchant;
    }
}