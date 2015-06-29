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
            ->setName(\ConfigurationCore::get('PS_SHOP_NAME'))
            ->setUuid(\ConfigurationCore::get('EXPRESSLY_PREFERENCES_UUID'))
            ->setImage(\ConfigurationCore::get('EXPRESSLY_PREFERENCES_IMAGE'))
            ->setTerms(\ConfigurationCore::get('EXPRESSLY_PREFERENCES_TERMS'))
            ->setPolicy(\ConfigurationCore::get('EXPRESSLY_PREFERENCES_POLICY'))
            ->setDestination(\ConfigurationCore::get('EXPRESSLY_PREFERENCES_DESTINATION'))
            ->setHost(\ConfigurationCore::get('EXPRESSLY_PREFERENCES_HOST'))
            ->setOffer(\ConfigurationCore::get('EXPRESSLY_PREFERENCES_OFFER'))
            ->setPassword(\ConfigurationCore::get('EXPRESSLY_PREFERENCES_PASSWORD'))
            ->setPath(\ConfigurationCore::get('EXPRESSLY_PREFERENCES_PATH'));

        $this->merchant = $merchant;
    }

    public function getMerchant($update = false)
    {
        if (!$this->merchant instanceof Merchant || $update) {
            $this->updateMerchant();
        }

        return $this->merchant;
    }

    public function setMerchant(Merchant $merchant)
    {
        $this->merchant = $merchant;

        \ConfigurationCore::updateValue('EXPRESSLY_PREFERENCES_UUID', $merchant->getUuid());
        \ConfigurationCore::updateValue('EXPRESSLY_PREFERENCES_IMAGE', $merchant->getImage());
        \ConfigurationCore::updateValue('EXPRESSLY_PREFERENCES_TERMS', $merchant->getTerms());
        \ConfigurationCore::updateValue('EXPRESSLY_PREFERENCES_POLICY', $merchant->getPolicy());
        \ConfigurationCore::updateValue('EXPRESSLY_PREFERENCES_DESTINATION', $merchant->getDestination());
        \ConfigurationCore::updateValue('EXPRESSLY_PREFERENCES_HOST', $merchant->getHost());
        \ConfigurationCore::updateValue('EXPRESSLY_PREFERENCES_OFFER', $merchant->getOffer());
        \ConfigurationCore::updateValue('EXPRESSLY_PREFERENCES_PASSWORD', $merchant->getPassword());
        \ConfigurationCore::updateValue('EXPRESSLY_PREFERENCES_PATH', $merchant->getPath());

        return $this;
    }
}