<?php

use App\Enums\CheckoutStep;
use App\Enums\OrderStatus;
use App\Enums\PaymentFailureReason;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\CheckoutEvent;
use App\Models\Order;
use App\Models\Payment;
use App\Reports\CheckoutFunnel;
use App\Reports\PaymentMethodPerformance;
use App\Reports\ReportPeriod;
use App\Reports\SalesBySource;
use App\Reports\SalesSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function stepFor(string $visitorId, CheckoutStep $step, ?string $at = null): void
{
    CheckoutEvent::factory()->create(['visitor_id' => $visitorId, 'step' => $step, 'occurred_at' => $at ?? now()]);
}

test('the funnel counts each person once per step and shows the drop between steps', function () {
    foreach (['a', 'b', 'c', 'd'] as $visitor) {
        stepFor("visitor-{$visitor}", CheckoutStep::TicketPageViewed);
    }
    stepFor('visitor-a', CheckoutStep::TicketPageViewed);
    stepFor('visitor-a', CheckoutStep::FormStarted);
    stepFor('visitor-b', CheckoutStep::FormStarted);
    stepFor('visitor-a', CheckoutStep::DetailsSubmitted);
    stepFor('visitor-a', CheckoutStep::CheckoutOpened);
    stepFor('visitor-a', CheckoutStep::PaymentSucceeded);

    $funnel = collect(CheckoutFunnel::for(new ReportPeriod))->keyBy(fn (array $row): string => $row['step']->value);

    expect($funnel['ticket_page_viewed'])->people->toBe(4)->of_previous->toBeNull()
        ->and($funnel['form_started'])->people->toBe(2)->of_start->toBe(50.0)->of_previous->toBe(50.0)
        ->and($funnel['details_submitted'])->people->toBe(1)->of_previous->toBe(50.0)
        ->and($funnel['payment_succeeded'])->people->toBe(1)->of_start->toBe(25.0);
});

test('reports only count what happened inside the period', function () {
    stepFor('visitor-a', CheckoutStep::TicketPageViewed, now()->subDays(40)->toDateTimeString());
    stepFor('visitor-b', CheckoutStep::TicketPageViewed);

    $period = ReportPeriod::fromDates(now()->subDays(29)->toDateString(), now()->toDateString());

    expect(CheckoutFunnel::for($period)[0]['people'])->toBe(1);
});

test('each way to pay shows its success rate, leaving out attempts still open', function () {
    $paid = Payment::factory()->method(PaymentMethod::BankTransfer)->succeeded()->count(3)->create(['created_at' => now()->subMinutes(10), 'paid_at' => now()]);
    Payment::factory()->method(PaymentMethod::BankTransfer)->failed(PaymentFailureReason::TimedOut)->create();
    Payment::factory()->method(PaymentMethod::BankTransfer)->create(['status' => PaymentStatus::Open]);
    Payment::factory()->method(PaymentMethod::Card)->failed(PaymentFailureReason::InsufficientFunds)->count(2)->create();
    Payment::factory()->method(PaymentMethod::Card)->failed(PaymentFailureReason::Declined)->create();
    Payment::factory()->method(PaymentMethod::Card)->succeeded()->create(['created_at' => now()->subMinutes(2), 'paid_at' => now()]);

    $rows = collect(PaymentMethodPerformance::for(new ReportPeriod))->keyBy(fn (array $row): string => $row['method']->value);

    expect($rows['bank_transfer'])
        ->attempts->toBe(5)
        ->succeeded->toBe(3)
        ->unfinished->toBe(1)
        ->success_rate->toBe(75.0)
        ->median_minutes_to_pay->toBe(10.0)
        ->top_failure->toBe(PaymentFailureReason::TimedOut)
        ->and($rows['card'])
        ->success_rate->toBe(25.0)
        ->failed->toBe(3)
        ->top_failure->toBe(PaymentFailureReason::InsufficientFunds)
        ->top_failure_count->toBe(2)
        ->and($rows['bank_transfer']['revenue']->amount)->toBe($paid->sum('amount'));
});

test('orders are credited to their campaign, the site that sent them, or direct', function () {
    Order::factory()->paid()->create(['amount' => 1_000_000, 'attribution' => ['source' => 'instagram', 'campaign' => 'launch']]);
    Order::factory()->create(['attribution' => ['source' => 'instagram', 'campaign' => 'launch']]);
    Order::factory()->paid()->create(['amount' => 2_000_000, 'attribution' => ['referrer' => 'l.facebook.com']]);
    Order::factory()->create(['attribution' => null]);

    $rows = collect(SalesBySource::for(new ReportPeriod))->keyBy('source');

    expect($rows['instagram'])->campaign->toBe('launch')->orders->toBe(2)->paid_orders->toBe(1)->conversion_rate->toBe(50.0)
        ->and($rows['l.facebook.com']['revenue']->format())->toBe('₦20,000')
        ->and($rows['direct'])->orders->toBe(1)->paid_orders->toBe(0)
        ->and($rows->keys()->first())->toBe('l.facebook.com');
});

test('the summary adds up revenue, tickets and how many visitors bought', function () {
    Order::factory()->paid()->create(['quantity' => 2, 'amount' => 2_000_000]);
    Order::factory()->paid()->create(['quantity' => 1, 'amount' => 1_000_000]);
    Order::factory()->create(['status' => OrderStatus::Abandoned, 'abandoned_at' => now()]);
    foreach (range(1, 6) as $visitor) {
        stepFor("visitor-{$visitor}", CheckoutStep::TicketPageViewed);
    }

    $summary = SalesSummary::for(new ReportPeriod);

    expect($summary['revenue']->format())->toBe('₦30,000')
        ->and($summary['tickets_sold'])->toBe(3)
        ->and($summary['paid_orders'])->toBe(2)
        ->and($summary['abandoned_orders'])->toBe(1)
        ->and($summary['conversion_rate'])->toBe(33.3)
        ->and($summary['average_order']->format())->toBe('₦15,000');
});
