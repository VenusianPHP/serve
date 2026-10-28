<?php

namespace Venusian\Serve\Tests\Validation;

use Voyager\Database\Capsule\Manager as DB;
use Voyager\Database\ConnectionInterface;
use Voyager\Database\Instrument\Model;
use Mariner\Translation\ArrayLoader;
use Mariner\Translation\Translator;
use Mariner\Validation\DatabasePresenceVerifier;
use Mariner\Validation\Rules\Unique;
use Mariner\Validation\Validator;

beforeEach(function () {
    $db = new DB;
    $db->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $db->bootInstrument();
    uniqueRuleCreateSchema();
});

test('it correctly formats astring version of the rule', function () {
    $rule = new Unique('table');
    $rule->where('foo', 'bar');
    expect((string) $rule)->toBe('unique:table,NULL,NULL,id,foo,"bar"');

    $rule = new Unique(EloquentModelStub::class);
    $rule->where('foo', 'bar');
    expect((string) $rule)->toBe('unique:table,NULL,NULL,id,foo,"bar"');

    $rule = new Unique(NoTableName::class);
    $rule->where('foo', 'bar');
    expect((string) $rule)->toBe('unique:no_table_names,NULL,NULL,id,foo,"bar"');

    $rule = new Unique('Venusian\Serve\Tests\Validation\NoTableName');
    $rule->where('foo', 'bar');
    expect((string) $rule)->toBe('unique:no_table_names,NULL,NULL,id,foo,"bar"');

    $rule = new Unique(ClassWithNonEmptyConstructor::class);
    $rule->where('foo', 'bar');
    expect((string) $rule)->toBe('unique:'.ClassWithNonEmptyConstructor::class.',NULL,NULL,id,foo,"bar"');

    $rule = new Unique('table', 'column');
    $rule->ignore('Taylor, Otwell', 'id_column');
    $rule->where('foo', 'bar');
    expect((string) $rule)->toBe('unique:table,column,"Taylor, Otwell",id_column,foo,"bar"');

    $rule = new Unique(PrefixedTableEloquentModelStub::class);
    expect((string) $rule)->toBe('unique:'.PrefixedTableEloquentModelStub::class.',NULL,NULL,id');

    $rule = new Unique(EloquentModelStub::class, 'column');
    $rule->ignore('Taylor, Otwell', 'id_column');
    $rule->where('foo', 'bar');
    expect((string) $rule)->toBe('unique:table,column,"Taylor, Otwell",id_column,foo,"bar"');

    $rule = new Unique(EloquentModelStub::class, 'column');
    $rule->where('foo', 'bar');
    $rule->when(true, function ($rule) {
        $rule->ignore('Taylor, Otwell', 'id_column');
    });
    $rule->unless(true, function ($rule) {
        $rule->ignore('Chris', 'id_column');
    });
    expect((string) $rule)->toBe('unique:table,column,"Taylor, Otwell",id_column,foo,"bar"');

    $rule = new Unique('table', 'column');
    $rule->ignore('Taylor, Otwell"\'..-"', 'id_column');
    $rule->where('foo', 'bar');
    expect((string) $rule)->toBe('unique:table,column,"Taylor, Otwell\"\\\'..-\"",id_column,foo,"bar"');
    expect(stripslashes(str_getcsv('table,column,"Taylor, Otwell\"\\\'..-\"",id_column,foo,"bar"', escape: '\\')[2]))->toBe('Taylor, Otwell"\'..-"');
    expect(stripslashes(str_getcsv('table,column,"Taylor, Otwell\"\\\'..-\"",id_column,foo,"bar"', escape: '\\')[3]))->toBe('id_column');

    $rule = new Unique('table', 'column');
    $rule->ignore(null, 'id_column');
    $rule->where('foo', 'bar');
    expect((string) $rule)->toBe('unique:table,column,NULL,id_column,foo,"bar"');

    $model = new EloquentModelStub(['id_column' => 1]);

    $rule = new Unique('table', 'column');
    $rule->ignore($model);
    $rule->where('foo', 'bar');
    expect((string) $rule)->toBe('unique:table,column,"1",id_column,foo,"bar"');

    $rule = new Unique('table', 'column');
    $rule->ignore($model, 'id_column');
    $rule->where('foo', 'bar');
    expect((string) $rule)->toBe('unique:table,column,"1",id_column,foo,"bar"');

    $rule = new Unique('table');
    $rule->where('foo', '"bar"');
    expect((string) $rule)->toBe('unique:table,NULL,NULL,id,foo,"""bar"""');

    $rule = new Unique(EloquentModelWithConnection::class, 'column');
    $rule->where('foo', 'bar');
    expect((string) $rule)->toBe('unique:mysql.table,column,NULL,id,foo,"bar"');
});

test('it ignores soft deletes', function () {
    $rule = new Unique('table');
    $rule->withoutTrashed();
    expect((string) $rule)->toBe('unique:table,NULL,NULL,id,deleted_at,"NULL"');

    $rule = new Unique('table');
    $rule->withoutTrashed('softdeleted_at');
    expect((string) $rule)->toBe('unique:table,NULL,NULL,id,softdeleted_at,"NULL"');
});

test('it only trashed soft deletes', function () {
    $rule = new Unique('table');
    $rule->onlyTrashed();
    expect((string) $rule)->toBe('unique:table,NULL,NULL,id,deleted_at,"NOT_NULL"');

    $rule = new Unique('table');
    $rule->onlyTrashed('softdeleted_at');
    expect((string) $rule)->toBe('unique:table,NULL,NULL,id,softdeleted_at,"NOT_NULL"');
});

test('it handles null primary key in ignore model', function () {
    $model = new EloquentModelStub(['id_column' => null]);

    $rule = new Unique('table', 'column');
    $rule->ignore($model);
    $rule->where('foo', 'bar');
    expect((string) $rule)->toBe('unique:table,column,NULL,id_column,foo,"bar"');

    $rule = new Unique('table', 'column');
    $rule->ignore($model, 'id_column');
    $rule->where('foo', 'bar');
    expect((string) $rule)->toBe('unique:table,column,NULL,id_column,foo,"bar"');
});

test('it handles where with special values', function () {
    $rule = new Unique('table', 'column');
    $rule->where('foo', null);
    expect((string) $rule)->toBe('unique:table,column,NULL,id,foo,"NULL"');

    $rule = new Unique('table', 'column');
    $rule->whereNot('foo', 'bar');
    expect((string) $rule)->toBe('unique:table,column,NULL,id,foo,"!bar"');

    $rule = new Unique('table', 'column');
    $rule->whereNull('foo');
    expect((string) $rule)->toBe('unique:table,column,NULL,id,foo,"NULL"');

    $rule = new Unique('table', 'column');
    $rule->whereNotNull('foo');
    expect((string) $rule)->toBe('unique:table,column,NULL,id,foo,"NOT_NULL"');

    $rule = new Unique('table', 'column');
    $rule->where('foo', 0);
    expect((string) $rule)->toBe('unique:table,column,NULL,id,foo,"0"');

    $rule = new Unique('table', 'column');
    $rule->where('foo', true);
    expect((string) $rule)->toBe('unique:table,column,NULL,id,foo,"1"');

    $rule = new Unique('table', 'column');
    $rule->where('foo', false);
    expect((string) $rule)->toBe('unique:table,column,NULL,id,foo,"0"');

    $rule = new Unique('table', 'column');
    $rule->whereNot('foo', true);
    expect((string) $rule)->toBe('unique:table,column,NULL,id,foo,"!1"');

    $rule = new Unique('table', 'column');
    $rule->whereNot('foo', false);
    expect((string) $rule)->toBe('unique:table,column,NULL,id,foo,"!0"');
});

test('it validates unique rule with boolean where', function () {
    EloquentModelStub::create(['id_column' => 1, 'type' => 'admin', 'active' => false]);
    EloquentModelStub::create(['id_column' => 2, 'type' => 'admin', 'active' => true]);

    $trans = uniqueRuleGetMarinerArrayTranslator();
    $v = new Validator($trans, [], ['id_column' => (new Unique('table', 'id_column'))->where('active', false)]);
    $v->setPresenceVerifier(new DatabasePresenceVerifier(Model::getConnectionResolver()));

    $v->setData(['id_column' => 1]);
    expect($v->passes())->toBeFalse();

    $v->setData(['id_column' => 2]);
    expect($v->passes())->toBeTrue();

    $v = new Validator($trans, [], ['id_column' => (new Unique('table', 'id_column'))->whereNot('active', false)]);
    $v->setPresenceVerifier(new DatabasePresenceVerifier(Model::getConnectionResolver()));

    $v->setData(['id_column' => 1]);
    expect($v->passes())->toBeTrue();

    $v->setData(['id_column' => 2]);
    expect($v->passes())->toBeFalse();
});

test('it validates unique rule with where in and where not in', function () {
    EloquentModelStub::create(['id_column' => 1, 'type' => 'admin']);
    EloquentModelStub::create(['id_column' => 2, 'type' => 'moderator']);
    EloquentModelStub::create(['id_column' => 3, 'type' => 'editor']);
    EloquentModelStub::create(['id_column' => 4, 'type' => 'user']);

    $rule = new Unique(table: 'table', column: 'id_column');
    $rule->whereIn(column: 'type', values: ['admin', 'moderator', 'editor'])
        ->whereNotIn(column: 'type', values: ['editor']);

    $trans = uniqueRuleGetMarinerArrayTranslator();
    $v = new Validator($trans, [], ['id_column' => $rule]);
    $v->setPresenceVerifier(new DatabasePresenceVerifier(Model::getConnectionResolver()));

    $v->setData(['id_column' => 1]);
    expect($v->passes())->toBeFalse();

    $v->setData(['id_column' => 2]);
    expect($v->passes())->toBeFalse();

    $v->setData(['id_column' => 3]);
    expect($v->passes())->toBeTrue();

    $v->setData(['id_column' => 4]);
    expect($v->passes())->toBeTrue();

    $v->setData(['id_column' => 5]);
    expect($v->passes())->toBeTrue();
});

function uniqueRuleCreateSchema(): void
{
    uniqueRuleConnection()->getSchemaBuilder()->create('table', function ($table) {
        $table->unsignedInteger('id_column');
        $table->string('type');
        $table->boolean('active')->default(true);
        $table->timestamps();
    });
}

function uniqueRuleConnection(): ConnectionInterface
{
    return Model::getConnectionResolver()->connection();
}

function uniqueRuleGetMarinerArrayTranslator(): Translator
{
    return new Translator(
        new ArrayLoader, locale: 'en'
    );
}

class EloquentModelStub extends Model
{
    protected $table = 'table';
    protected $primaryKey = 'id_column';
    protected $guarded = [];
}

class PrefixedTableEloquentModelStub extends Model
{
    protected $table = 'public.table';
    protected $primaryKey = 'id_column';
    protected $guarded = [];
}

class NoTableName extends Model
{
    protected $guarded = [];
    public $timestamps = false;
}

class ClassWithNonEmptyConstructor
{
    private $bar;
    private $baz;

    public function __construct($bar, $baz)
    {
        $this->bar = $bar;
        $this->baz = $baz;
    }
}

class EloquentModelWithConnection extends EloquentModelStub
{
    protected $connection = 'mysql';
}
