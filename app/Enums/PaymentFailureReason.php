<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Why a payment attempt failed, in our own words, so failures can be compared across providers and methods.
 */
enum PaymentFailureReason: string implements HasLabel
{
    case InsufficientFunds = 'insufficient_funds';
    case Declined = 'declined';
    case AuthenticationFailed = 'authentication_failed';
    case ExpiredCard = 'expired_card';
    case InvalidDetails = 'invalid_details';
    case TimedOut = 'timed_out';
    case ProviderError = 'provider_error';
    case AmountMismatch = 'amount_mismatch';
    case Unknown = 'unknown';

    /**
     * The reason as organisers read it.
     */
    public function label(): string
    {
        return match ($this) {
            self::InsufficientFunds => 'Insufficient funds',
            self::Declined => 'Declined by the bank',
            self::AuthenticationFailed => 'Failed PIN, OTP or 3D Secure check',
            self::ExpiredCard => 'Expired card',
            self::InvalidDetails => 'Wrong payment details',
            self::TimedOut => 'Timed out',
            self::ProviderError => 'Payment provider error',
            self::AmountMismatch => 'Paid amount did not match the order',
            self::Unknown => 'Unknown',
        };
    }

    /**
     * What happened and what to try next, in words for the buyer.
     */
    public function advice(): string
    {
        return match ($this) {
            self::InsufficientFunds => 'Your bank said there wasn’t enough money in the account. Try another card or pay by bank transfer.',
            self::Declined => 'Your bank declined the payment. Try another card, or pay by bank transfer instead.',
            self::AuthenticationFailed => 'The PIN, OTP or bank check didn’t go through. Try again and enter the code your bank sends.',
            self::ExpiredCard => 'That card has expired. Try another card or pay by bank transfer.',
            self::InvalidDetails => 'Some of the payment details weren’t right. Check them and try again.',
            self::TimedOut => 'The payment took too long and timed out. Try again.',
            self::ProviderError, self::Unknown => 'Something went wrong on the payment side. Try again, or choose another way to pay.',
            self::AmountMismatch => 'The amount that arrived didn’t match your order. We’re looking into it and will be in touch.',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
