<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Musing\InertiaTable\Actions\Action;
use Musing\InertiaTable\Columns\ActionColumn;
use Musing\InertiaTable\Columns\BooleanColumn;
use Musing\InertiaTable\Columns\DateTimeColumn;

function anonymousModel(): Model
{
    return new class extends Model
    {
        protected $guarded = [];

        public $timestamps = false;
    };
}

it('serializes the actions column as a non-toggleable, right-aligned action type', function () {
    expect(ActionColumn::new()->toArray())->toMatchArray([
        'attribute' => '__actions',
        'header' => 'Actions',
        'type' => 'action',
        'toggleable' => false,
        'alignment' => 'right',
    ]);
});

it('lets the actions column header be renamed', function () {
    expect(ActionColumn::new('Manage')->toArray()['header'])->toBe('Manage');
});

it('can group row actions in a dropdown', function () {
    expect(ActionColumn::new()->asDropdown()->toArray())
        ->asDropdown->toBeTrue();
});

it('serializes singular plural and all-matching confirmation copy', function () {
    $confirmation = Action::make('delete')->confirm(
        ['Delete :count topic?', 'Delete :count topics?', 'Delete all :count matching topics?'],
        ['One topic will be deleted.', ':count topics will be deleted.', 'All :count matching topics will be deleted.'],
    )->toArray()['confirmation'];

    expect($confirmation['title'])->toBe([
        'Delete :count topic?',
        'Delete :count topics?',
        'Delete all :count matching topics?',
    ])->and($confirmation['message'])->toBe([
        'One topic will be deleted.',
        ':count topics will be deleted.',
        'All :count matching topics will be deleted.',
    ]);
});

it('rejects malformed confirmation variants', function () {
    expect(fn () => Action::make('delete')->confirm(['Only one variant']))
        ->toThrow(LogicException::class);
});

it('serializes a datetime column with its own default format distinct from DateColumn', function () {
    expect(DateTimeColumn::make('published_at')->toArray())->toMatchArray([
        'type' => 'datetime',
        'format' => 'Y-m-d H:i:s',
        'translated' => false,
    ]);
});

it('formats a datetime column value using an explicit format override', function () {
    $model = anonymousModel();
    $model->setAttribute('published_at', Carbon::parse('2026-08-10 15:30:00'));

    expect(DateTimeColumn::make('published_at')->format('d/m/Y H:i')->resolveValue($model))
        ->toBe('10/08/2026 15:30');
});

it('serializes boolean column presentation defaults', function () {
    expect(BooleanColumn::make('is_featured')->toArray())->toMatchArray([
        'type' => 'boolean',
        'trueLabel' => 'Yes',
        'falseLabel' => 'No',
        'trueIcon' => null,
        'falseIcon' => null,
    ]);
});

it('serializes boolean column presentation overrides', function () {
    $column = BooleanColumn::make('is_featured')
        ->trueLabel('Active')
        ->falseLabel('Inactive')
        ->trueIcon('Check')
        ->falseIcon('X');

    expect($column->toArray())->toMatchArray([
        'trueLabel' => 'Active',
        'falseLabel' => 'Inactive',
        'trueIcon' => 'Check',
        'falseIcon' => 'X',
    ]);
});

it('translates package-owned Laravel defaults', function () {
    app()->setLocale('vi');

    expect(ActionColumn::new()->toArray()['header'])->toBe('Thao tác')
        ->and(BooleanColumn::make('is_featured')->toArray())->toMatchArray([
            'trueLabel' => 'Có',
            'falseLabel' => 'Không',
        ])
        ->and(Action::make('delete')->confirm()->toArray()['confirmation'])->toBe([
            'title' => 'Xác nhận thao tác',
            'message' => 'Bạn có chắc chắn muốn thực hiện thao tác này không?',
            'confirmLabel' => 'Xác nhận',
            'cancelLabel' => 'Hủy',
        ]);

    app()->setLocale('en');
});
