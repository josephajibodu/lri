<?php

namespace App\Enums;

/**
 * The roles the site starts with. Organisers can add more roles in the admin; these names are the ones the code
 * relies on.
 */
enum StaffRole: string
{
    /** Sees and does everything, including parts of the admin added later. Can't be edited or removed. */
    case SuperAdmin = 'Super admin';

    /** Every part of the admin except System, as a role organisers can change. */
    case Admin = 'Admin';

    /** Only the System parts of the admin: webhooks, the email tool and the logs. */
    case Developer = 'Developer';

    /** Only the headset desk. */
    case HeadsetDesk = 'Headset desk';
}
