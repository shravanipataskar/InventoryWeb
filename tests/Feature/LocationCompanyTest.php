<?php

namespace Tests\Feature;

use App\Company;
use App\CompanyDocument;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class LocationCompanyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_location_requires_a_company_and_lists_the_selected_company()
    {
        $this->actingAs(User::create([
            'name' => 'Location Test User',
            'email' => Str::uuid() . '@example.test',
            'password' => Hash::make('test-password'),
        ]));

        $company = Company::create([
            'name' => 'Location Company ' . Str::random(8),
            'code' => 'LOC-' . strtoupper(Str::random(8)),
            'is_active' => true,
        ]);

        $this->get(route('locations.create'))
            ->assertOk()
            ->assertSee('Company / Brand')
            ->assertSee($company->name);

        $this->post(route('locations.store'), [
            'name' => 'Main Store',
            'code' => 'MAIN-' . strtoupper(Str::random(8)),
            'location' => 'Main shop',
        ])->assertSessionHasErrors('company_id');

        $code = 'MAIN-' . strtoupper(Str::random(8));
        $this->post(route('locations.store'), [
            'name' => 'Main Store',
            'code' => $code,
            'company_id' => $company->id,
            'location' => 'Main shop',
        ])->assertRedirect(route('locations.index'));

        $this->assertDatabaseHas('stores', [
            'code' => $code,
            'company_id' => $company->id,
        ]);

        $this->get(route('locations.index'))
            ->assertOk()
            ->assertSee($company->name);
    }

    public function test_company_document_storage_tracks_database_failures_and_preserves_files_on_delete_failure()
    {
        Storage::fake('local');
        $this->actingAs(User::create([
            'name' => 'Company Document Test User',
            'email' => Str::uuid() . '@example.test',
            'password' => Hash::make('test-password'),
            'role' => 'ADMIN',
        ]));
        $company = Company::create([
            'name' => 'Document Company ' . Str::random(8),
            'code' => 'DOC-' . strtoupper(Str::random(8)),
            'is_active' => true,
        ]);
        Event::listen('eloquent.creating: App\CompanyDocument', function () {
            throw new \RuntimeException('Simulated company document write failure.');
        });

        try {
            $response = $this->post(route('companies.documents.store', $company->id), [
                'document' => UploadedFile::fake()->create('company.pdf', 10, 'application/pdf'),
            ]);
        } finally {
            Event::forget('eloquent.creating: App\CompanyDocument');
        }

        $response->assertStatus(500);
        $this->assertDatabaseMissing('company_documents', ['company_id' => $company->id]);
        $this->assertSame([], Storage::disk('local')->allFiles('company-documents/' . $company->id));

        $path = UploadedFile::fake()->create('retained.pdf', 10, 'application/pdf')
            ->store('company-documents/' . $company->id, 'local');
        $document = CompanyDocument::create([
            'company_id' => $company->id,
            'file_name' => 'retained.pdf',
            'storage_path' => $path,
            'file_size' => 10,
        ]);
        Event::listen('eloquent.deleting: App\CompanyDocument', function () {
            throw new \RuntimeException('Simulated company document delete failure.');
        });

        try {
            $response = $this->delete(route('companies.documents.destroy', [$company->id, $document->id]));
        } finally {
            Event::forget('eloquent.deleting: App\CompanyDocument');
        }

        $response->assertStatus(500);
        $this->assertDatabaseHas('company_documents', ['id' => $document->id]);
        Storage::disk('local')->assertExists($path);
    }
}
