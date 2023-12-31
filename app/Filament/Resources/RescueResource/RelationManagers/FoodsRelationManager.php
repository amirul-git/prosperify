<?php

namespace App\Filament\Resources\RescueResource\RelationManagers;

use App\Models\Food;
use App\Models\FoodRescueLog;
use App\Models\FoodRescueStoredReceipt;
use App\Models\FoodRescueTakenReceipt;
use App\Models\Rescue;
use App\Models\RescueAssignment;
use App\Models\SubCategory;
use App\Models\Vault;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FoodsRelationManager extends RelationManager
{
    protected static string $relationship = 'foods';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                FileUpload::make('photo')->required()->image()->columnSpan(2)->disk('public')->directory('rescue-documentation'),
                TextInput::make('name')->required(),
                DateTimePicker::make('expired_date')->required(),
                TextInput::make('detail')->required()->columnSpan(2),
                TextInput::make('amount')->numeric()->inputMode('decimal')->required(),
                Select::make('unit_id')->relationship(name: 'unit', titleAttribute: 'name')->preload()->required(),
                Select::make('sub_category_id')->relationship(name: 'subCategory', titleAttribute: 'name')->label('Group of')->required()->preload()->searchable(),
                Select::make('food_rescue_status_id')->relationship('foodRescueStatus', 'name')->required()->preload()->searchable()->default(10),
                Select::make('vault_id')->relationship('vault', 'name')->preload()->columnSpan(2)->required(),
                FileUpload::make('rescue-donor-signature')->image()->disk('public')->directory('rescue-donor-signature')->required()->label('Donor signature'),
                FileUpload::make('rescue-admin-signature')->image()->disk('public')->directory('rescue-admin-signature')->required()->label('Admin signature'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('id')->label('ID'),
                ImageColumn::make('photo')->square(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('amount')->searchable()->sortable(),
                TextColumn::make('unit.name'),
                TextColumn::make('foodRescueStatus.name')->label('Rescue Status'),
                TextColumn::make('category.name'),
                TextColumn::make('subCategory.name')->label('Sub Category'),
                TextColumn::make('stored_at')->date()->label('Stored At'),
                TextColumn::make('vault.name'),
                TextColumn::make('expired_date')->date(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->mutateFormDataUsing(function (array $data): array {
                    $subCategory = SubCategory::find($data['sub_category_id']);
                    $data['category_id'] = $subCategory->category->id;
                    $data['stored_at'] = Carbon::now();

                    return $data;
                })->after(function (Food $food) {
                    $receiptTakenSignature = reset($this->mountedTableActionsData[0]['rescue-donor-signature']);
                    $receiptStoredSignature = reset($this->mountedTableActionsData[0]['rescue-admin-signature']);

                    $user = auth()->user();
                    $rescue = Rescue::find($food->rescue_id);
                    $vault = Vault::find($food->vault_id);
                    // we need to save the food log
                    FoodRescueLog::Create($user, $rescue, $food, $vault);

                    // create rescue Assignment
                    $rescueAssignment = new RescueAssignment();
                    $rescueAssignment->food_id = $food->id;
                    $rescueAssignment->rescue_id = $rescue->id;
                    $rescueAssignment->volunteer_id = $user->id;
                    $rescueAssignment->vault_id = $vault->id;
                    $rescueAssignment->assigner_id = $user->id;
                    $rescueAssignment->save();

                    // create taken receipt
                    FoodRescueTakenReceipt::Create($food, $rescueAssignment, $receiptTakenSignature);

                    // create receipt
                    FoodRescueStoredReceipt::Create($food, $rescueAssignment, $receiptStoredSignature);
                })
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
