<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\Widgets\UserStatsOverview;
use App\Http\Controllers\Api\Enum\UserEnum;
use App\Jobs\AdminPasswordResetRequestJob;
use App\Jobs\NewAdminUserJob;
use App\Jobs\NewUserJob;
use App\Models\Designations;
use App\Models\Role;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->disabledOn('edit')
                    ->hintIcon('heroicon-o-question-mark-circle', tooltip: 'This will be updated with the information once the user login.')
                    ->hintColor(Color::Orange)
                    ->required(),
                Forms\Components\TextInput::make('email')
                    ->disabledOn('edit')
                    ->email()
                    ->required(),
                Forms\Components\Select::make('roles')
                    ->required()
                    ->live(onBlur: true)
                    ->native(false)
                    ->visibleOn('create')
                    ->relationship('roles', 'name'),
                Forms\Components\TextInput::make('password')
                    ->hintIcon('heroicon-o-exclamation-circle', tooltip: 'If password field is leave blank, then the system will generate a random password.')
                    ->visibleOn('create')
                    ->hidden(function (Forms\Get $get, $operation): bool {
                        if (! is_null($get('roles')) && $operation === 'create') {
                            $role = Role::findById($get('roles'), 'web');
                            /** @var $role Role */
                            if ($role->hasPermissionTo('access panel', 'web')) {
                                return false;
                            }
                        }

                        return true;
                    })
                    ->reactive()
                    ->revealable()
                    ->nullable()
                    ->password(),
                Forms\Components\Select::make('department')
                    ->required()
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->reactive()
                    ->live()
                    ->afterStateUpdated(function (?string $state, ?string $old, Forms\Set $set) {
                        $set('designation', null);
                    })
                    ->relationship('departments', 'name'),
                Forms\Components\Select::make('designation')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->hintIcon('heroicon-o-question-mark-circle', tooltip: 'To add designation, update the Designations model file.')
                    ->hintColor(Color::Orange)
                    ->native(false)
                    ->options(function (Forms\Get $get): array {
                        if (! $get('department')) {
                            return [];
                        }

                        return Designations::where('departments_id', $get('department'))->pluck('name', 'id')->toArray();

                    }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->orderBy('created_at', 'desc'))
            ->columns(components: [
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\IconColumn::make('status')
                    ->tooltip(function (User $user): string {
                        return match ($user->status) {
                            UserEnum::Invited => 'Invited',
                            UserEnum::Active => 'Active',
                            UserEnum::Deactivate => 'User has been Deactivated by the administrator',
                            default => '',
                        };
                    }),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Role')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->copyable()
                    ->label('Email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('departments.name')
                    ->label('Department')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('designations.name')
                    ->label('Designation')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->modalFooterActionsAlignment(Alignment::Right)
                    ->hidden(fn (User $user): bool => $user->id === auth()->id()),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('update_status')
                        ->form([
                            Forms\Components\Select::make('status')
                                ->label('Change User Account Status')
                                ->required()
                                ->default(function (User $record) {
                                    return $record->status;
                                })
                                ->native(false)
                                ->preload()
                                ->disableOptionWhen(function (string $value, User $user): bool {
                                    if ($user->status !== UserEnum::Invited) {
                                        return $value === UserEnum::Invited->value;
                                    }
                                    if ($user->status === UserEnum::Invited) {
                                        return $value === UserEnum::Active->value;
                                    }
                                })
                                ->options(UserEnum::class),
                        ])
                        ->modalFooterActionsAlignment(Alignment::Right)
                        ->requiresConfirmation()
                        ->action(function (User $user, array $data) {
                            $user->update([
                                'status' => $data['status'],
                            ]);
                        })
                        ->modalWidth(MaxWidth::Small)
                        ->modalAlignment(Alignment::Center)
                        ->icon('heroicon-o-user-circle')
                        ->label('Update Status'),
                    Tables\Actions\Action::make('resend_invitation')
                        ->action(function (User $user) {
                            if ($user->hasPermissionTo('access panel')) {
                                NewAdminUserJob::dispatch($user)
                                    ->afterResponse()
                                    ->afterCommit();
                            }
                            NewUserJob::dispatch($user)
                                ->afterCommit()
                                ->afterResponse();

                            Notification::make('resend_invitation')
                                ->icon('heroicon-o-paper-airplane')
                                ->body('Email Invitation email has been sent.')
                                ->success()
                                ->send();
                        })
                        ->visible(fn (User $user): bool => $user->status === UserEnum::Invited)
                        ->requiresConfirmation()
                        ->modalFooterActionsAlignment(Alignment::Right)
                        ->icon('heroicon-o-arrow-path')
                        ->label('Resend Invitation Email'),
                    Tables\Actions\Action::make('password_reset')
                        ->action(function (User $user) {
                            AdminPasswordResetRequestJob::dispatch($user)
                                ->afterResponse()
                                ->afterCommit();
                            Notification::make('password_reset')
                                ->icon('heroicon-o-paper-airplane')
                                ->body('Password reset email has been sent.')
                                ->success()
                                ->send();
                        })
                        ->visible(fn (User $user): bool => $user->hasPermissionTo('access panel'))
                        ->requiresConfirmation()
                        ->modalFooterActionsAlignment(Alignment::Right)
                        ->icon('heroicon-o-finger-print')
                        ->label('Request Password Reset'),
                    Tables\Actions\Action::make('update_role')
                        ->form([
                            Forms\Components\Select::make('roles')
                                ->label('Change User Role to:')
                                ->required()
                                ->live()
                                ->reactive()
                                ->native(false)
                                ->preload()
                                ->default(function (User $record) {
                                    return $record->getRoleNames()[0] ?? null;
                                })
                                ->options(Role::all()->pluck('name', 'name')),
                            Forms\Components\TextInput::make('password')
                                ->label('Set Temporary Password:')
                                ->reactive()
                                ->hidden(function (Forms\Get $get) {
                                    if (is_null($get('roles'))) {
                                        return true;
                                    }
                                    $role = Role::where('name', $get('roles'))->first();
                                    if ($role->hasPermissionTo('access panel')) {
                                        return false;
                                    } else {
                                        return true;
                                    }
                                })
                                ->revealable()
                                ->required(function (Forms\Get $get) {
                                    $role = Role::where('name', $get('roles'))->first();
                                    if ($role->hasPermissionTo('access panel')) {
                                        return true;
                                    } else {
                                        return false;
                                    }
                                })
                                ->password(),
                        ])
                        ->modalFooterActionsAlignment(Alignment::Right)
                        ->requiresConfirmation()
                        ->action(function (User $user, array $data) {
                            $user->update([
                                'password' => $data['password'] ?? null,
                            ]);
                            $user->syncRoles($data['roles']);
                        })
                        ->modalWidth(MaxWidth::Small)
                        ->modalAlignment(Alignment::Center)
                        ->icon('heroicon-o-shield-check')
                        ->label('Update Role'),
                    Tables\Actions\DeleteAction::make(),
                ])
                    ->hidden(fn (User $user): bool => $user->id === auth()->id())
                    ->icon('heroicon-o-ellipsis-horizontal-circle'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getWidgets(): array
    {
        return [
            UserStatsOverview::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageUsers::route('/'),
        ];
    }
}
