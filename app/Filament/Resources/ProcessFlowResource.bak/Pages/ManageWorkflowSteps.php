<?php

namespace App\Filament\Resources\ProcessFlowResource\Pages;

use App\Filament\Resources\ProcessFlowResource;
use App\Models\WorkflowStep;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ManageWorkflowSteps extends ManageRelatedRecords
{
    protected static string $resource = ProcessFlowResource::class;

    protected static string $relationship = 'workflowSteps';

    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';

    public function getTitle(): string
    {
        return "Manage Workflow Steps - {$this->getOwnerRecord()->name}";
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Step Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        
                        Forms\Components\TextInput::make('step_order')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->default(function () {
                                $maxOrder = $this->getOwnerRecord()
                                    ->workflowSteps()
                                    ->max('step_order');
                                return ($maxOrder ?? 0) + 1;
                            }),
                        
                        Forms\Components\Select::make('step_type')
                            ->required()
                            ->options([
                                'manual' => 'Manual Task',
                                'automatic' => 'Automatic Action',
                                'approval' => 'Approval Required',
                                'notification' => 'Send Notification',
                                'billing' => 'Billing Process',
                                'packing' => 'Packing Process',
                                'return' => 'Return Process',
                            ])
                            ->reactive(),
                        
                        Forms\Components\TextInput::make('assigned_role')
                            ->label('Assigned Role')
                            ->helperText('Leave empty for automatic assignment')
                            ->visible(fn (Forms\Get $get) => in_array($get('step_type'), ['manual', 'approval', 'billing', 'packing', 'return'])),
                        
                        Forms\Components\Toggle::make('auto_execute')
                            ->label('Auto Execute')
                            ->helperText('Execute this step automatically when conditions are met')
                            ->default(false),
                    ])
                    ->columns(2), 
               Forms\Components\Section::make('Step Conditions')
                    ->schema([
                        Forms\Components\Builder::make('conditions')
                            ->blocks([
                                Forms\Components\Builder\Block::make('condition')
                                    ->schema([
                                        Forms\Components\Select::make('field')
                                            ->options([
                                                'platform_type' => 'Platform Type',
                                                'total_amount' => 'Total Amount',
                                                'currency' => 'Currency',
                                                'status' => 'Order Status',
                                                'customer_email' => 'Customer Email',
                                                'order_items_count' => 'Number of Items',
                                                'raw_data.payment_method' => 'Payment Method',
                                                'raw_data.priority' => 'Priority',
                                            ])
                                            ->required(),
                                        
                                        Forms\Components\Select::make('operator')
                                            ->options([
                                                '=' => 'Equals',
                                                '!=' => 'Not equals',
                                                '>' => 'Greater than',
                                                '<' => 'Less than',
                                                '>=' => 'Greater than or equal',
                                                '<=' => 'Less than or equal',
                                                'in' => 'In list',
                                                'contains' => 'Contains',
                                                'starts_with' => 'Starts with',
                                                'ends_with' => 'Ends with',
                                            ])
                                            ->required(),
                                        
                                        Forms\Components\TextInput::make('value')
                                            ->required()
                                            ->helperText('For "in" operator, use comma-separated values'),
                                    ])
                                    ->columns(3),
                            ])
                            ->collapsible()
                            ->cloneable()
                            ->addActionLabel('Add Condition')
                            ->helperText('Conditions must be met for this step to execute'),
                    ]),

                Forms\Components\Section::make('Step Configuration')
                    ->schema([
                        Forms\Components\KeyValue::make('configuration')
                            ->keyLabel('Setting')
                            ->valueLabel('Value')
                            ->addActionLabel('Add Setting')
                            ->helperText('Step-specific configuration options'),
                    ]),
            ]);
    }  
  public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('step_order')
                    ->label('Order')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\BadgeColumn::make('step_type')
                    ->label('Type')
                    ->colors([
                        'primary' => 'manual',
                        'success' => 'automatic',
                        'warning' => 'approval',
                        'info' => 'notification',
                        'danger' => 'billing',
                        'secondary' => 'packing',
                        'gray' => 'return',
                    ]),
                
                Tables\Columns\TextColumn::make('assigned_role')
                    ->label('Role')
                    ->placeholder('Auto-assigned'),
                
                Tables\Columns\IconColumn::make('auto_execute')
                    ->boolean()
                    ->label('Auto'),
                
                Tables\Columns\TextColumn::make('conditions')
                    ->label('Conditions')
                    ->formatStateUsing(fn ($state) => is_array($state) ? count($state) . ' condition(s)' : 'None')
                    ->color(fn ($state) => is_array($state) && count($state) > 0 ? 'success' : 'gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('step_type')
                    ->options([
                        'manual' => 'Manual Task',
                        'automatic' => 'Automatic Action',
                        'approval' => 'Approval Required',
                        'notification' => 'Send Notification',
                        'billing' => 'Billing Process',
                        'packing' => 'Packing Process',
                        'return' => 'Return Process',
                    ]),
                
                Tables\Filters\TernaryFilter::make('auto_execute')
                    ->label('Auto Execute'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['process_flow_id'] = $this->getOwnerRecord()->id;
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\Action::make('test_step')
                    ->label('Test')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->action(function (WorkflowStep $record) {
                        // This would implement step testing functionality
                        $this->notify('success', 'Step test functionality would be implemented here');
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('step_order')
            ->defaultSort('step_order');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back_to_flow')
                ->label('Back to Process Flow')
                ->icon('heroicon-o-arrow-left')
                ->url(fn (): string => ProcessFlowResource::getUrl('view', ['record' => $this->getOwnerRecord()])),
            
            Actions\Action::make('test_workflow')
                ->label('Test Workflow')
                ->icon('heroicon-o-play-circle')
                ->color('success')
                ->action(function () {
                    // This would implement workflow testing functionality
                    $this->notify('success', 'Workflow test functionality would be implemented here');
                }),
        ];
    }
}