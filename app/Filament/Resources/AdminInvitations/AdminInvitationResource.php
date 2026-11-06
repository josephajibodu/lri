<?php

namespace App\Filament\Resources\AdminInvitations;

use App\Actions\AdminInvitations\SendAdminInvitation;
use App\Enums\AdminInvitationStatus;
use App\Enums\AdminPermission;
use App\Enums\StaffRole;
use App\Exceptions\AdminInvitationNotUsable;
use App\Filament\Resources\AdminInvitations\Pages\ManageAdminInvitations;
use App\Filament\Resources\Users\UserResource;
use App\Models\AdminInvitation;
use App\Support\AdminActivity;
use BackedEnum;
use Carbon\CarbonInterface;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use UnitEnum;

/**
 * Inviting people to the admin with their roles already chosen. They choose their own name and password when they
 * accept, so nobody has to send a password around.
 */
class AdminInvitationResource extends Resource
{
    protected static ?string $model = AdminInvitation::class;

    protected static ?string $slug = 'invitations';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Invitations';

    protected static ?string $modelLabel = 'invitation';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'email';

    public static function getNavigationBadge(): ?string
    {
        $waiting = AdminInvitation::query()->waiting()->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['roles', 'invitedBy']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('email')
                    ->searchable()
                    ->weight(FontWeight::Medium)
                    ->description(fn (AdminInvitation $record): string => 'Invited by '.$record->inviterName().' on '.$record->created_at->format('j M Y')),
                TextColumn::make('roles.name')->label('Roles')->badge(),
                TextColumn::make('status')
                    ->badge()
                    ->state(fn (AdminInvitation $record): AdminInvitationStatus => $record->status())
                    ->description(fn (AdminInvitation $record): string => static::statusDetail($record)),
            ])
            ->recordActions([
                static::sendAgainAction(),
                static::cancelAction(),
            ])
            ->emptyStateIcon(Heroicon::OutlinedEnvelope)
            ->emptyStateHeading('No invitations yet')
            ->emptyStateDescription('Invite someone to help run the admin. They’ll choose their own name and password.');
    }

    /**
     * The button that invites someone. It's on this page and on People, where you'd also look for it.
     */
    public static function inviteAction(): Action
    {
        return Action::make('invite')
            ->label('Invite someone')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->authorize('create', AdminInvitation::class)
            ->modalIcon(Heroicon::OutlinedEnvelope)
            ->modalHeading('Invite someone to the admin')
            ->modalDescription(fn (): string => 'We’ll email them a link to accept or decline. It works for '.static::lifetime().', and they choose their own password.')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Send invitation')
            ->schema([
                TextInput::make('email')
                    ->label('Their email address')
                    ->validationAttribute('email address')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->rule(fn (SendAdminInvitation $send): Closure => function (string $attribute, mixed $value, Closure $fail) use ($send): void {
                        try {
                            $send->ensureCanBeInvited((string) $value);
                        } catch (AdminInvitationNotUsable $exception) {
                            $fail($exception->getMessage());
                        }
                    }),
                CheckboxList::make('roles')
                    ->label('Their roles')
                    ->validationAttribute('roles')
                    ->helperText('Decides which parts of the admin they’ll see. Set up roles under Settings > Roles.')
                    ->options(fn (): Collection => static::invitableRoles()->pluck('name', 'id'))
                    ->descriptions(fn (): Collection => static::invitableRoles()->mapWithKeys(fn (Role $role): array => [$role->id => static::whatRoleSees($role)]))
                    ->required()
                    ->rule(fn (): Closure => UserResource::onlyAssignableRoles())
                    ->validationMessages(['required' => 'Choose at least one role, or they won’t be able to sign in.']),
            ])
            ->action(function (array $data, SendAdminInvitation $send): void {
                $invitation = $send->handle($data['email'], $data['roles'], auth()->user());

                Notification::make()
                    ->title("Invitation sent to {$invitation->email}")
                    ->success()
                    ->send();
            });
    }

    private static function sendAgainAction(): Action
    {
        return Action::make('sendAgain')
            ->label('Send again')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->authorize('update')
            ->visible(fn (AdminInvitation $record): bool => $record->canBeSentAgain())
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedPaperAirplane)
            ->modalHeading(fn (AdminInvitation $record): string => "Send a new link to {$record->email}?")
            ->modalDescription(fn (): string => 'They get a new email with a link that works for '.static::lifetime().'. The link in any earlier email stops working.')
            ->modalSubmitActionLabel('Send new link')
            ->action(function (AdminInvitation $record, SendAdminInvitation $send): void {
                try {
                    $send->again($record);
                } catch (AdminInvitationNotUsable $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title("New link sent to {$record->email}")->success()->send();
            });
    }

    private static function cancelAction(): Action
    {
        return Action::make('cancelInvitation')
            ->label('Cancel')
            ->icon(Heroicon::OutlinedXMark)
            ->color('danger')
            ->authorize('delete')
            ->visible(fn (AdminInvitation $record): bool => $record->isWaiting())
            ->requiresConfirmation()
            ->modalHeading(fn (AdminInvitation $record): string => "Cancel the invitation to {$record->email}?")
            ->modalDescription('The link in their email stops working straight away.')
            ->modalSubmitActionLabel('Yes, cancel it')
            ->modalCancelActionLabel('Keep it')
            ->action(function (AdminInvitation $record): void {
                $record->forceFill(['cancelled_at' => now()])->save();
                AdminActivity::record("Cancelled the invitation to {$record->email}", $record);

                Notification::make()->title('Invitation cancelled')->success()->send();
            });
    }

    /**
     * The roles offered in the form, with what they can see. Only a super admin can invite another super admin.
     *
     * @return Collection<int, Role>
     */
    private static function invitableRoles(): Collection
    {
        return UserResource::assignableRoles(Role::query())
            ->with('permissions')
            ->orderBy('name')
            ->get();
    }

    private static function whatRoleSees(Role $role): string
    {
        if ($role->name === StaffRole::SuperAdmin->value) {
            return 'Sees and does everything, including sections added later.';
        }

        $sections = $role->permissions
            ->map(fn (Permission $permission): ?string => AdminPermission::tryFrom($permission->name)?->getLabel())
            ->filter();

        return match (true) {
            $sections->isEmpty() => 'Can sign in, but can’t see any section yet.',
            $sections->count() === count(AdminPermission::cases()) => 'Sees every section.',
            default => 'Sees '.$sections->join(', ', ' and ').'.',
        };
    }

    private static function statusDetail(AdminInvitation $record): string
    {
        return match ($record->status()) {
            AdminInvitationStatus::Waiting => 'Link works for another '.$record->expires_at->diffForHumans(syntax: CarbonInterface::DIFF_ABSOLUTE),
            AdminInvitationStatus::Expired => 'Ran out '.$record->expires_at->diffForHumans(),
            AdminInvitationStatus::Accepted => $record->accepted_at->diffForHumans(),
            AdminInvitationStatus::Declined => $record->declined_at->diffForHumans(),
            AdminInvitationStatus::Cancelled => $record->cancelled_at->diffForHumans(),
        };
    }

    /**
     * How long a link works, such as "24 hours".
     */
    private static function lifetime(): string
    {
        return trans_choice('{1} one hour|[2,*] :count hours', config('lri.admin_invitations.lifetime_hours'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAdminInvitations::route('/'),
        ];
    }
}
