<?php

namespace Tests\Feature;

use App\Customer;
use App\Http\Middleware\VerifyCsrfToken;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerDocumentsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_customer_documents_can_be_uploaded_and_previewed()
    {
        Storage::fake('local');
        $this->actingAs($this->makeUser());
        $name = 'Document Customer ' . strtoupper(Str::random(8));

        $this->get(route('customers.create'))
            ->assertOk()
            ->assertSee('Identity &amp; Documents', false)
            ->assertSee('name="aadhaar_card"', false)
            ->assertSee('name="pan_card"', false)
            ->assertSee('enctype="multipart/form-data"', false);

        $this->post(route('customers.store'), [
            'name' => $name,
            'customer_type' => 'Retail',
            'phone' => '9876543210',
            'email' => 'customer@example.test',
            'status' => 'active',
            'aadhaar_card' => UploadedFile::fake()->image('aadhaar.jpg'),
            'pan_card' => UploadedFile::fake()->image('pan.png'),
        ])->assertRedirect();

        $customer = Customer::where('name', $name)->firstOrFail();
        $this->assertStringStartsWith('customers/documents/', $customer->aadhaar_card);
        $this->assertStringStartsWith('customers/documents/', $customer->pan_card);
        Storage::disk('local')->assertExists($customer->aadhaar_card);
        Storage::disk('local')->assertExists($customer->pan_card);

        $this->get(route('customers.show', $customer->id))
            ->assertOk()
            ->assertSee('Identity &amp; Documents', false)
            ->assertSee(route('customers.documents.show', [$customer->id, 'aadhaar_card']), false)
            ->assertSee(route('customers.documents.show', [$customer->id, 'pan_card']), false);

        $this->get(route('customers.documents.show', [$customer->id, 'aadhaar_card']))
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg');

        $this->get(route('customers.documents.show', [$customer->id, 'aadhaar_card', 'download' => 1]))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=aadhaar_card.jpg');

        $this->get(route('stock-outwards.create', ['customer_id' => $customer->id]))
            ->assertOk()
            ->assertSee('value="' . $customer->id . '"', false)
            ->assertSee('selected', false);
    }

    public function test_customer_documents_can_be_replaced_without_leaving_old_files()
    {
        Storage::fake('local');
        $this->actingAs($this->makeUser());
        $customer = Customer::create([
            'customer_code' => 'DOC-' . strtoupper(Str::random(8)),
            'name' => 'Replace Customer ' . strtoupper(Str::random(6)),
            'customer_type' => 'Retail',
            'phone' => '9876543210',
            'aadhaar_card' => UploadedFile::fake()->image('old-aadhaar.jpg')->store('customers/documents', 'local'),
            'pan_card' => UploadedFile::fake()->image('old-pan.jpg')->store('customers/documents', 'local'),
            'is_active' => true,
        ]);
        $oldAadhaar = $customer->aadhaar_card;
        $oldPan = $customer->pan_card;

        $this->put(route('customers.update', $customer->id), [
            'name' => $customer->name,
            'customer_type' => 'Retail',
            'phone' => '9876543210',
            'status' => 'active',
            'aadhaar_card' => UploadedFile::fake()->image('new-aadhaar.webp'),
            'pan_card' => UploadedFile::fake()->image('new-pan.png'),
        ])->assertRedirect(route('customers.show', $customer->id));

        $customer->refresh();
        $this->assertNotSame($oldAadhaar, $customer->aadhaar_card);
        $this->assertNotSame($oldPan, $customer->pan_card);
        Storage::disk('local')->assertMissing($oldAadhaar);
        Storage::disk('local')->assertMissing($oldPan);
        Storage::disk('local')->assertExists($customer->aadhaar_card);
        Storage::disk('local')->assertExists($customer->pan_card);

        $this->get(route('customers.edit', $customer->id))
            ->assertOk()
            ->assertSee('Existing Aadhaar Card')
            ->assertSee('Existing PAN Card');
    }

    public function test_invalid_customer_document_is_rejected_and_customers_without_documents_show_fallback()
    {
        Storage::fake('local');
        $this->actingAs($this->makeUser());
        $name = 'No Document Customer ' . strtoupper(Str::random(8));

        $this->post(route('customers.store'), [
            'name' => $name,
            'customer_type' => 'Retail',
            'phone' => '9876543210',
            'status' => 'active',
            'aadhaar_card' => UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload'),
        ])->assertSessionHasErrors('aadhaar_card');
        $this->assertDatabaseMissing('customers', ['name' => $name]);

        $this->post(route('customers.store'), [
            'name' => $name,
            'customer_type' => 'Retail',
            'phone' => 'bad phone',
            'email' => 'not-an-email',
            'status' => 'active',
        ])->assertSessionHasErrors(['phone', 'email']);
        $this->assertDatabaseMissing('customers', ['name' => $name]);

        $customer = Customer::create([
            'customer_code' => 'DOC-' . strtoupper(Str::random(8)),
            'name' => $name,
            'customer_type' => 'Retail',
            'phone' => '9876543210',
            'is_active' => true,
        ]);

        $this->get(route('customers.show', $customer->id))
            ->assertOk()
            ->assertSee('Not uploaded');
    }

    public function test_customer_documents_over_five_megabytes_are_rejected()
    {
        Storage::fake('local');
        $this->actingAs($this->makeUser());
        $name = 'Oversized Document Customer ' . strtoupper(Str::random(8));

        $this->post(route('customers.store'), [
            'name' => $name,
            'customer_type' => 'Retail',
            'phone' => '9876543210',
            'status' => 'active',
            'pan_card' => UploadedFile::fake()->image('large-pan.jpg')->size(5121),
        ])->assertSessionHasErrors('pan_card');

        $this->assertDatabaseMissing('customers', ['name' => $name]);
    }

    public function test_customer_database_failure_removes_uploaded_documents_without_treating_other_fields_as_paths()
    {
        Storage::fake('local');
        $this->actingAs($this->makeUser());
        $name = 'Failed Document Customer ' . strtoupper(Str::random(8));
        Event::listen('eloquent.creating: App\Customer', function () {
            throw new \RuntimeException('Simulated customer write failure.');
        });

        try {
            $response = $this->post(route('customers.store'), [
                'name' => $name,
                'customer_type' => 'Retail',
                'phone' => '9876543210',
                'status' => 'active',
                'aadhaar_card' => UploadedFile::fake()->image('aadhaar.jpg'),
            ]);
        } finally {
            Event::forget('eloquent.creating: App\Customer');
        }

        $response->assertStatus(500);
        $this->assertDatabaseMissing('customers', ['name' => $name]);
        $this->assertSame([], Storage::disk('local')->allFiles('customers/documents'));
    }

    private function makeUser()
    {
        return User::create([
            'name' => 'Customer Document Test User',
            'email' => Str::uuid() . '@example.test',
            'password' => Hash::make('test-password'),
            'role' => 'ADMIN',
        ]);
    }
}
