<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Activities\ActivityResource;
use App\Models\Activity;
use Filament\Actions\Action;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * The latest lines of the activity log, for super admins only. The rest of the dashboard can be given to other roles,
 * so this widget checks for itself.
 */
class RecentActivity extends TableWidget
{
    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent activity';

    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', Activity::class) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->description('The latest changes people made in the admin. Only super admins see this.')
            ->query(Activity::query()->latest('id')->limit(10))
            ->columns(ActivityResource::logColumns())
            ->searchable(false)
            ->headerActions([
                Action::make('seeFullLog')
                    ->label('See the full log')
                    ->color('gray')
                    ->url(ActivityResource::getUrl()),
            ])
            ->emptyStateHeading('Nothing recorded yet')
            ->paginated(false);
    }
}
