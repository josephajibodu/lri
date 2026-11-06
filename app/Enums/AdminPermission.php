<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * One permission per part of the admin. A role is a set of these, and someone sees only the parts their roles
 * allow. Super admins see everything, including parts added later.
 */
enum AdminPermission: string implements HasDescription, HasLabel
{
    case ViewDashboard = 'view dashboard';
    case ManageOrders = 'manage orders';
    case UseHeadsetDesk = 'use headset desk';
    case ManageTicketTypes = 'manage ticket types';
    case ManageCoupons = 'manage coupons';
    case ManagePaymentMethods = 'manage payment methods';
    case ManageBirthdays = 'manage birthdays';
    case ManageBarracksRegistrations = 'manage barracks registrations';
    case ManagePeople = 'manage people';
    case ManageWebhooks = 'manage webhooks';
    case ManageEmailTool = 'manage email tool';
    case ViewLogs = 'view logs';

    public function getLabel(): string
    {
        return match ($this) {
            self::ViewDashboard => 'Sales dashboard',
            self::ManageOrders => 'Orders',
            self::UseHeadsetDesk => 'Headset desk',
            self::ManageTicketTypes => 'Ticket types',
            self::ManageCoupons => 'Coupons',
            self::ManagePaymentMethods => 'Ways to pay',
            self::ManageBirthdays => 'Birthdays',
            self::ManageBarracksRegistrations => 'Barracks permits',
            self::ManagePeople => 'People and roles',
            self::ManageWebhooks => 'Webhooks',
            self::ManageEmailTool => 'Email tool',
            self::ViewLogs => 'Logs',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::ViewDashboard => 'Money in, the checkout funnel, ways to pay and where buyers come from.',
            self::ManageOrders => 'Every order with the buyer’s details, payments and journey. Can email tickets again, release disputed ones and mark test payments.',
            self::UseHeadsetDesk => 'Find tickets, fill in names and hand over headsets on the day.'.(config('lri.headset_desk.open') ? '' : ' Hidden for now: only super admins can open it.'),
            self::ManageTicketTypes => 'Prices, ticket limits and when sales open.',
            self::ManageCoupons => 'Create coupons, see how much they’ve been used, and disable them.',
            self::ManagePaymentMethods => 'Switch ways to pay on or off and choose the featured ones.',
            self::ManageBirthdays => 'Birthdays members have shared.',
            self::ManageBarracksRegistrations => 'Barracks permit sign-ups, and printing their forms.',
            self::ManagePeople => 'Invite and add people, and create roles and give them out. Only for people you trust with everything.',
            self::ManageWebhooks => 'Payment provider notifications, and replaying them.',
            self::ManageEmailTool => 'What was sent to the email tool, and sending again.',
            self::ViewLogs => 'The site’s error and activity logs.',
        };
    }
}
