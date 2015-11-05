<?php

namespace Module\Expressly;

use Expressly\Entity\Merchant;
use Expressly\Provider\MerchantProviderInterface;

class MerchantProvider implements MerchantProviderInterface
{
    private $merchant;

    const APIKEY = 'EXPRESSLY_PREFERENCES_APIKEY';
    const HOST = 'EXPRESSLY_PREFERENCES_HOST';
    const PATH = 'EXPRESSLY_PREFERENCES_PATH';

    public function __construct()
    {
        if (\ConfigurationCore::get(self::APIKEY)) {
            $this->updateMerchant();
        }
    }

    private function updateMerchant()
    {
        $merchant = new Merchant();
        $merchant
            ->setApiKey(\ConfigurationCore::get(self::APIKEY))
            ->setHost(\ConfigurationCore::get(self::HOST))
            ->setPath(\ConfigurationCore::get(self::PATH));

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

        \ConfigurationCore::updateValue(self::APIKEY, $merchant->getApiKey());
        \ConfigurationCore::updateValue(self::HOST, $merchant->getHost());
        \ConfigurationCore::updateValue(self::PATH, $merchant->getPath());

        return $this;
    }
}