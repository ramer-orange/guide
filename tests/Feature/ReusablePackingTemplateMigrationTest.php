<?php

use App\Models\TravelOverview;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function reusableTemplateMigration(): object
{
    return require database_path('migrations/2026_10_05_000001_allow_reused_packing_templates.php');
}

function createTemplateNameRow(string $name): void
{
    $overview = TravelOverview::query()->first() ?? TravelOverview::create([
        'user_id' => User::factory()->create()->id,
        'title' => 'Migration test itinerary',
    ]);

    DB::table('template_types')->insert([
        'travel_id' => $overview->id,
        'template_name' => $name,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('restores template uniqueness when rollback data permits and can be reapplied', function () {
    $migration = reusableTemplateMigration();
    expect(Schema::hasIndex('template_types', 'template_types_template_name_unique', 'unique'))->toBeFalse();

    $migration->down();
    expect(Schema::hasIndex('template_types', 'template_types_template_name_unique', 'unique'))->toBeTrue();

    createTemplateNameRow('domestic');
    expect(fn () => DB::transaction(fn () => createTemplateNameRow('domestic')))
        ->toThrow(QueryException::class);

    $migration->up();
    expect(Schema::hasIndex('template_types', 'template_types_template_name_unique', 'unique'))->toBeFalse();
    createTemplateNameRow('domestic');
    expect(DB::table('template_types')->where('template_name', 'domestic')->count())->toBe(2);
});

it('refuses rollback before changing schema when duplicate template names exist', function () {
    $migration = reusableTemplateMigration();
    createTemplateNameRow('domestic');
    createTemplateNameRow('domestic');

    expect(fn () => $migration->down())
        ->toThrow(RuntimeException::class, 'Cannot restore template_types.template_name uniqueness');

    expect(Schema::hasIndex('template_types', 'template_types_template_name_unique', 'unique'))->toBeFalse();
    expect(DB::table('template_types')->where('template_name', 'domestic')->count())->toBe(2);
});
