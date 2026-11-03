<?php

namespace App\Enums;

/**
 * The ways a buyer can pay, in our own words. Each provider maps these to its own codes,
 * so one method can move to a different provider without the checkout changing.
 */
enum PaymentMethod: string
{
    case Card = 'card';
    case BankTransfer = 'bank_transfer';
    case Ussd = 'ussd';
    case Opay = 'opay';
    case Palmpay = 'palmpay';
    case MobileMoney = 'mobile_money';
    case Crypto = 'crypto';

    /**
     * The method's name as buyers see it.
     */
    public function label(): string
    {
        return match ($this) {
            self::Card => 'Card',
            self::BankTransfer => 'Bank transfer',
            self::Ussd => 'USSD',
            self::Opay => 'OPay',
            self::Palmpay => 'PalmPay',
            self::MobileMoney => 'Mobile money',
            self::Crypto => 'Crypto',
        };
    }
}
