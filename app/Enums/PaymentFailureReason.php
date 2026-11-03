<?php

namespace App\Enums;

/**
 * Why a payment attempt failed, in our own words, so failures can be compared across providers and methods.
 */
enum PaymentFailureReason: string
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
}
