<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categories\RelationManagers;

use App\Enums\FieldType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;

class FieldsRelationManager extends RelationManager
{
    protected static string $relationship = 'fields';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('app.admin.fields');
    }

    public static function getModelLabel(): string
    {
        return __('app.admin.field');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.admin.fields');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('app.admin.name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('key')
                    ->label(__('app.admin.key'))
                    ->required()
                    ->maxLength(100)
                    ->regex('/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/')
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule) => $rule->where('category_id', $this->getOwnerRecord()->getKey()),
                    )
                    ->extraInputAttributes(['dir' => 'ltr'])
                    ->validationMessages(['regex' => __('app.admin.key_invalid')])
                    ->helperText(__('app.admin.key_help')),
                Select::make('type')
                    ->label(__('app.admin.type'))
                    ->options(FieldType::options())
                    ->required()
                    ->live()
                    ->default(FieldType::Text->value),
                TagsInput::make('options')
                    ->label(__('app.admin.options'))
                    ->helperText(__('app.admin.options_help'))
                    ->visible(fn (Get $get): bool => $get('type') === FieldType::Select->value)
                    ->required(fn (Get $get): bool => $get('type') === FieldType::Select->value)
                    ->columnSpanFull(),
                TextInput::make('unit')
                    ->label(__('app.admin.unit'))
                    ->maxLength(20)
                    ->visible(fn (Get $get): bool => $get('type') === FieldType::Number->value),
                Toggle::make('is_required')
                    ->label(__('app.admin.is_required')),
                Toggle::make('is_filterable')
                    ->label(__('app.admin.is_filterable')),
                TextInput::make('sort_order')
                    ->label(__('app.admin.sort_order'))
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('app.admin.name'))
                    ->searchable(),
                TextColumn::make('key')
                    ->label(__('app.admin.key'))
                    ->extraAttributes(['dir' => 'ltr']),
                TextColumn::make('type')
                    ->label(__('app.admin.type'))
                    ->badge()
                    ->formatStateUsing(fn (FieldType $state): string => $state->label()),
                TextColumn::make('unit')
                    ->label(__('app.admin.unit'))
                    ->placeholder('—'),
                IconColumn::make('is_required')
                    ->label(__('app.admin.is_required'))
                    ->boolean(),
                IconColumn::make('is_filterable')
                    ->label(__('app.admin.is_filterable'))
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->label(__('app.admin.sort_order'))
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
