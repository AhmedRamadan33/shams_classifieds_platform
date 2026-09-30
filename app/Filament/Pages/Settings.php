<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Settings\SettingField;
use App\Settings\SettingsRegistry;
use App\Settings\SettingsRepository;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

class Settings extends Page
{
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('app.admin.groups.settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('app.admin.settings.title');
    }

    public function getTitle(): string|Htmlable
    {
        return __('app.admin.settings.title');
    }

    public function mount(): void
    {
        $repository = app(SettingsRepository::class);
        $data = [];

        foreach (SettingsRegistry::fields() as $key => $field) {
            $data[$key] = $field->secret ? null : ($repository->get($key) ?? config($field->configPath));
        }

        $this->form->fill($data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('settings')->tabs(
                    collect(SettingsRegistry::tabs())
                        ->map(fn (array $tab, string $key) => Tab::make(__('app.admin.settings.tabs.'.$key))
                            ->icon($tab['icon'])
                            ->schema(collect($tab['fields'])->map($this->component(...))->all()))
                        ->values()
                        ->all(),
                ),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')
                            ->label(__('app.admin.settings.save'))
                            ->submit('save'),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();

        app(SettingsRepository::class)->save($state);

        Notification::make()->success()->title(__('app.admin.settings.saved'))->send();

        $this->mount();
    }

    private function component(SettingField $field): Component
    {
        $component = match ($field->type) {
            'select' => Select::make($field->key)->options($field->selectOptions())->required()->live(),
            'number' => TextInput::make($field->key)->numeric(),
            'email' => TextInput::make($field->key)->email(),
            default => TextInput::make($field->key),
        };

        $component->label($field->label());

        if ($field->secret) {
            $component
                ->password()
                ->revealable()
                ->dehydrated(fn ($state) => filled($state))
                ->helperText(fn () => app(SettingsRepository::class)->get($field->key) !== null
                    ? __('app.admin.settings.secret_saved')
                    : __('app.admin.settings.secret_empty'));
        }

        if ($field->visibleWhenKey !== null) {
            $component->visible(fn (Get $get): bool => $get($field->visibleWhenKey) === $field->visibleWhenValue);
        }

        return $component;
    }
}
