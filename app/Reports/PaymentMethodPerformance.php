<?php

namespace App\Reports;

use App\Enums\OrderStatus;
use App\Enums\PaymentFailureReason;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * How each way to pay is doing: how often it's tried, how often it works, why it fails and how long people take.
 * Attempts still open are left out of the success rate, since they haven't ended yet.
 */
final class PaymentMethodPerformance
{
    /**
     * @return list<array{method: ?PaymentMethod, label: string, attempts: int, succeeded: int, failed: int, unfinished: int, success_rate: ?float, revenue: Money, median_minutes_to_pay: ?float, top_failure: ?PaymentFailureReason, top_failure_count: int}>
     */
    public static function for(ReportPeriod $period, string $currency = 'NGN'): array
    {
        return $period->apply(Payment::query()->where('currency', $currency)->whereRelation('order', 'status', '!=', OrderStatus::TestPayment), 'created_at')
            ->get(['id', 'method', 'status', 'failure_reason', 'amount', 'created_at', 'paid_at'])
            ->groupBy(fn (Payment $payment): string => $payment->method?->value ?? 'unknown')
            ->map(fn (Collection $payments): array => self::row($payments, $currency))
            ->sortByDesc('attempts')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Payment>  $payments
     * @return array{method: ?PaymentMethod, label: string, attempts: int, succeeded: int, failed: int, unfinished: int, success_rate: ?float, revenue: Money, median_minutes_to_pay: ?float, top_failure: ?PaymentFailureReason, top_failure_count: int}
     */
    private static function row(Collection $payments, string $currency): array
    {
        $method = $payments->first()->method;
        $succeeded = $payments->where('status', PaymentStatus::Succeeded);
        $ended = $payments->reject(fn (Payment $payment): bool => $payment->status === PaymentStatus::Open);
        $failures = $payments->whereNotNull('failure_reason')->countBy(fn (Payment $payment): string => $payment->failure_reason->value)->sortDesc();

        return [
            'method' => $method,
            'label' => $method?->label() ?? 'Not chosen',
            'attempts' => $payments->count(),
            'succeeded' => $succeeded->count(),
            'failed' => $payments->filter(fn (Payment $payment): bool => self::endedInFailure($payment))->count(),
            'unfinished' => $payments->whereIn('status', [PaymentStatus::Open, PaymentStatus::Expired, PaymentStatus::Abandoned])->reject(fn (Payment $payment): bool => self::endedInFailure($payment))->count(),
            'success_rate' => $ended->isNotEmpty() ? round($succeeded->count() / $ended->count() * 100, 1) : null,
            'revenue' => Money::of((int) $succeeded->sum('amount'), $currency),
            'median_minutes_to_pay' => $succeeded->isNotEmpty()
                ? round($succeeded->map(fn (Payment $payment): float => $payment->created_at->diffInSeconds($payment->paid_at) / 60)->median(), 1)
                : null,
            'top_failure' => $failures->isNotEmpty() ? PaymentFailureReason::from($failures->keys()->first()) : null,
            'top_failure_count' => (int) $failures->first(),
        ];
    }

    /**
     * Whether an attempt ended because paying failed. A declined card leaves the checkout open to try again, so one that
     * then closes with a failure on record failed too, rather than being left unfinished.
     */
    private static function endedInFailure(Payment $payment): bool
    {
        return in_array($payment->status, [PaymentStatus::Failed, PaymentStatus::Underpaid], true)
            || ($payment->status === PaymentStatus::Expired && $payment->failure_reason !== null);
    }
}
