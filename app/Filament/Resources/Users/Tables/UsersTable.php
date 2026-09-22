<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('app.auth.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->label(__('app.auth.phone'))
                    ->searchable()
                    ->extraAttributes(['dir' => 'ltr']),
                TextColumn::make('roles.name')
                    ->label(__('app.admin.roles'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __('app.admin.role_names.'.$state)),
                TextColumn::make('listings_count')
                    ->label(__('app.admin.listings'))
                    ->sortable(),
                IconColumn::make('is_banned')
                    ->label(__('app.admin.is_banned'))
                    ->boolean()
                    ->trueIcon('heroicon-s-no-symbol')
                    ->falseIcon('heroicon-o-check')
                    ->trueColor('danger')
                    ->falseColor('gray'),
                TextColumn::make('created_at')
                    ->label(__('app.admin.created_at'))
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('is_banned')->label(__('app.admin.is_banned')),
                SelectFilter::make('role')
                    ->label(__('app.admin.roles'))
                    ->options(fn (): array => Role::query()->pluck('name', 'name')->map(fn (string $name) => __('app.admin.role_names.'.$name))->all())
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereHas('roles', fn (Builder $q) => $q->where('name', $data['value']))
                        : $query),
            ])
            ->recordActions([
                EditAction::make(),
                self::banToggle(),
            ]);
    }

    /**
     * Ban / unban. A banned user's listings disappear from the public site (Listing::visible()) and
     * the account is signed out on its next request. Admins cannot ban themselves.
     */
    private static function banToggle(): Action
    {
        return Action::make('toggle_ban')
            ->label(fn (User $record): string => $record->is_banned ? __('app.admin.unban') : __('app.admin.ban'))
            ->icon(fn (User $record) => $record->is_banned ? Heroicon::OutlinedShieldCheck : Heroicon::OutlinedNoSymbol)
            ->color(fn (User $record): string => $record->is_banned ? 'success' : 'danger')
            ->visible(fn (User $record): bool => ! $record->is(auth()->user()))
            ->requiresConfirmation()
            ->action(function (User $record): void {
                $record->update(['is_banned' => ! $record->is_banned]);

                Notification::make()
                    ->title($record->is_banned ? __('app.admin.banned_done') : __('app.admin.unbanned_done'))
                    ->success()
                    ->send();
            });
    }
}
