<?php

namespace Database\Seeders;

use App\Models\Notice;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::query()->firstOrCreate(
            ['code' => 'STMS-LP'],
            [
                'name' => "St. Mary's LP School",
                'district' => 'Ernakulam',
                'type' => 'aided',
            ],
        );

        $admin = User::query()->firstOrCreate(
            ['phone' => '9876543210'],
            ['name' => 'School Admin'],
        );

        $admin->schools()->syncWithoutDetaching([
            $school->id => ['role' => 'school_admin', 'is_active' => true],
        ]);

        $parent = User::query()->firstOrCreate(
            ['phone' => '9123456789'],
            ['name' => 'Anu\'s Parent'],
        );

        $parent->schools()->syncWithoutDetaching([
            $school->id => ['role' => 'parent', 'is_active' => true],
        ]);

        Notice::query()->updateOrCreate(
            [
                'school_id' => $school->id,
                'magic_link_token' => 'demo123abc',
            ],
            [
                'author_id' => $admin->id,
                'title' => 'നാളെ സ്കൂൾ അവധി (മഴ)',
                'body' => 'അത്യാവശ്യ അറിയിപ്പ്: നാളെ മഴ കാരണം സ്കൂൾ അവധിയാണ്. എല്ലാ വിദ്യാർത്ഥികളും വീട്ടിൽ തന്നെ ഇരിക്കുക.',
                'title_en' => 'School closed tomorrow (rain)',
                'body_en' => 'Urgent notice: School will remain closed tomorrow due to heavy rain. All students should stay home.',
                'priority' => 'urgent',
                'audience_type' => 'whole_school',
                'status' => 'published',
                'published_at' => now(),
            ],
        );
    }
}
