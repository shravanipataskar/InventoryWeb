<?php

namespace Tests\Feature;

use App\Quotation;
use App\QuotationRequest;
use App\Supplier;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuotationApprovalStatusFilterTest extends TestCase
{
    use DatabaseTransactions;

    private $admin;
    private $storeId;
    private $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Quotation Approval Filter Test',
            'email' => Str::uuid() . '@example.test',
            'password' => Hash::make('test-password'),
            'role' => 'ADMIN',
        ]);
        $this->actingAs($this->admin);

        $this->storeId = DB::table('stores')->insertGetId([
            'name' => 'Quotation Filter Store ' . Str::random(6),
            'store_code' => 'QF-' . strtoupper(Str::random(8)),
            'code' => 'QF-' . strtoupper(Str::random(8)),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_status_tabs_include_all_rejected_supplier_quotations_and_keep_filters()
    {
        $existingRejectedCount = QuotationRequest::whereIn('status', ['submitted', 'under_review', 'approved', 'rejected'])
            ->where(function ($query) {
                $query->where('status', 'rejected')
                    ->orWhereHas('quotations', function ($query) {
                        $query->where('status', 'rejected');
                    });
            })
            ->count();

        $approvedRequest = $this->createRequest('approved', '2026-10-10');
        $this->createQuotation($approvedRequest, 'Approved Supplier', 'approved');
        $this->createQuotation($approvedRequest, 'Rejected Supplier Alpha', 'rejected');
        $this->createQuotation($approvedRequest, 'Rejected Supplier Beta', 'rejected');

        $rejectedRequest = $this->createRequest('rejected', '2026-10-11');
        $this->createQuotation($rejectedRequest, 'Request Rejected Supplier', 'rejected');

        $pendingRequest = $this->createRequest('submitted', '2026-10-12');
        $this->createQuotation($pendingRequest, 'Pending Supplier', 'submitted');

        $approvedOnlyRequest = $this->createRequest('approved', '2026-10-13');
        $this->createQuotation($approvedOnlyRequest, 'Second Approved Supplier', 'approved');

        $this->get(route('quotations.approval', ['status' => 'rejected']))
            ->assertOk()
            ->assertSee($approvedRequest->quotation_request_code)
            ->assertSee($rejectedRequest->quotation_request_code)
            ->assertSee('Rejected Supplier Alpha')
            ->assertSee('Rejected Supplier Beta')
            ->assertSee('Request Rejected Supplier')
            ->assertDontSee('Approved Supplier')
            ->assertDontSee('Pending Supplier')
            ->assertSee('<em>' . ($existingRejectedCount + 2) . '</em>', false)
            ->assertViewHas('stats', function ($stats) use ($existingRejectedCount) {
                return $stats['rejected'] === $existingRejectedCount + 2;
            });

        $this->get(route('quotations.approval', ['status' => 'all']))
            ->assertOk()
            ->assertSee('Approved Supplier')
            ->assertSee('Pending Supplier')
            ->assertSee('Request Rejected Supplier');

        $this->get(route('quotations.approval', [
            'status' => 'rejected',
            'search' => 'Rejected Supplier Alpha',
            'start_date' => '2026-10-10',
            'end_date' => '2026-10-10',
        ]))
            ->assertOk()
            ->assertSee($approvedRequest->quotation_request_code)
            ->assertSee('Rejected Supplier Alpha')
            ->assertDontSee($rejectedRequest->quotation_request_code)
            ->assertSee('name="status" value="rejected"', false)
            ->assertSee('name="search" value="Rejected Supplier Alpha"', false);

        $this->get(route('quotations.approval', [
            'status' => 'rejected',
            'search' => 'Approved Supplier',
        ]))
            ->assertOk()
            ->assertSee('No rejected quotations found')
            ->assertDontSee($approvedRequest->quotation_request_code);

        $this->get(route('quotations.approval', ['status' => 'pending']))
            ->assertOk()
            ->assertSee('Pending Supplier')
            ->assertDontSee('Rejected Supplier Alpha')
            ->assertDontSee('Approved Supplier');

        $this->get(route('quotations.approval', ['status' => 'approved']))
            ->assertOk()
            ->assertSee('Approved Supplier')
            ->assertSee('Second Approved Supplier')
            ->assertDontSee('Rejected Supplier Alpha')
            ->assertDontSee('Pending Supplier');

        for ($index = 0; $index < 9; $index++) {
            $request = $this->createRequest('rejected', '2026-10-14');
            $this->createQuotation($request, 'Rejected Page Supplier ' . $index, 'rejected');
        }

        $this->get(route('quotations.approval', ['status' => 'rejected', 'page' => 2]))
            ->assertOk()
            ->assertSee($approvedRequest->quotation_request_code)
            ->assertSee('status=rejected');
    }

    public function test_quotation_request_list_shows_summary_and_filters_requests()
    {
        $existingSubmittedQuotationCount = Quotation::where('status', 'submitted')->count();
        $draftRequest = $this->createRequest('draft', '2026-10-14');
        $this->createQuotation($draftRequest, 'Searchable Supplier', 'draft');
        $approvedRequest = $this->createRequest('approved', '2026-10-13');
        $this->createQuotation($approvedRequest, 'Other Supplier', 'approved');
        $submittedRequest = $this->createRequest('submitted', '2026-10-12');
        $this->createQuotation($submittedRequest, 'Submitted Supplier', 'submitted');
        $underReviewRequest = $this->createRequest('submitted', '2026-10-11');
        $this->createQuotation($underReviewRequest, 'Under Review Supplier', 'under_review');

        $this->get(route('quotations.index', [
            'search' => 'Searchable Supplier',
            'status' => 'draft',
            'from_date' => '2026-10-14',
            'to_date' => '2026-10-14',
        ]))
            ->assertOk()
            ->assertSee('Total Requests')
            ->assertSee('Submitted Quotations')
            ->assertSee('Total Suppliers')
            ->assertSee('name="search" value="Searchable Supplier"', false)
            ->assertSee('name="from_date" value="2026-10-14"', false)
            ->assertSee($draftRequest->quotation_request_code)
            ->assertSee('Draft')
            ->assertDontSee($approvedRequest->quotation_request_code)
            ->assertSee('Showing')
            ->assertViewHas('stats', function ($stats) use ($existingSubmittedQuotationCount) {
                return array_key_exists('total', $stats)
                    && array_key_exists('draft', $stats)
                    && $stats['submitted_quotations'] === $existingSubmittedQuotationCount + 2
                    && array_key_exists('approved', $stats)
                    && array_key_exists('suppliers', $stats);
            });
    }

    private function createRequest($status, $date)
    {
        $this->sequence++;

        return QuotationRequest::create([
            'quotation_request_code' => 'QF-' . strtoupper(Str::random(10)) . '-' . $this->sequence,
            'request_date' => $date,
            'store_id' => $this->storeId,
            'created_by' => $this->admin->id,
            'status' => $status,
        ]);
    }

    private function createQuotation(QuotationRequest $request, $supplierName, $status)
    {
        $supplier = Supplier::create([
            'supplier_code' => 'QF-' . strtoupper(Str::random(10)),
            'name' => $supplierName,
            'company_name' => $supplierName,
            'phone' => '9876543210',
            'is_active' => true,
        ]);

        $this->sequence++;

        return Quotation::create([
            'quotation_request_id' => $request->id,
            'supplier_id' => $supplier->id,
            'quotation_code' => 'QF-QT-' . strtoupper(Str::random(10)) . '-' . $this->sequence,
            'quotation_date' => $request->request_date,
            'status' => $status,
            'grand_total' => 1000,
        ]);
    }
}
