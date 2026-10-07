<?php

namespace App\Http\Controllers;

use App\Category;
use App\Company;
use App\CompanyDocument;
use App\StockInward;
use App\StockOutward;
use App\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $totalCompanies = Company::count();
        $activeCompanies = Company::where('is_active', true)->count();
        $inactiveCompanies = $totalCompanies - $activeCompanies;
        $totalProducts = DB::table('products')->whereNotNull('company_id')->count();
        $categoryCounts = DB::table('products')
            ->select('company_id', DB::raw('COUNT(DISTINCT category_id) as categories_count'))
            ->whereNotNull('company_id')
            ->groupBy('company_id')
            ->pluck('categories_count', 'company_id');

        $query = Company::withCount('products');
        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($companyQuery) use ($search) {
                $companyQuery->where('name', 'like', '%' . $search . '%')
                    ->orWhere('code', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        if ($request->input('status') === 'active') {
            $query->where('is_active', true);
        } elseif ($request->input('status') === 'inactive') {
            $query->where('is_active', false);
        }

        $sortOptions = [
            'name' => ['name', 'asc'],
            'name_desc' => ['name', 'desc'],
            'newest' => ['created_at', 'desc'],
            'oldest' => ['created_at', 'asc'],
        ];
        $sort = $request->input('sort', 'name');
        if (!array_key_exists($sort, $sortOptions)) {
            $sort = 'name';
        }

        $query->orderBy($sortOptions[$sort][0], $sortOptions[$sort][1]);
        $companies = $query->paginate(10)->appends($request->query());

        return view('companies.index', compact(
            'companies',
            'totalCompanies',
            'activeCompanies',
            'inactiveCompanies',
            'totalProducts',
            'categoryCounts',
            'sort'
        ));
    }

    public function create()
    {
        return view('companies.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:100|unique:companies,code',
            'description' => 'nullable|string',
        ]);

        $company = Company::create($data + ['is_active' => true]);

        return redirect()
            ->route('companies.show', $company->id)
            ->with('success', 'Company created successfully.');
    }

    public function show(Request $request, $id)
    {
        $company = Company::withCount('products')->findOrFail($id);
        $tab = $request->query('tab', 'overview');
        $validTabs = ['overview', 'products', 'categories', 'suppliers', 'stock', 'documents'];
        if (!in_array($tab, $validTabs, true)) {
            $tab = 'overview';
        }

        $companyProducts = $company->products();
        $totalProducts = (clone $companyProducts)->count();
        $totalCategories = (clone $companyProducts)->distinct()->count('category_id');
        $currentStock = (clone $companyProducts)->sum('current_stock');
        $activeProducts = (clone $companyProducts)->where('is_active', true)->count();
        $stockValue = (clone $companyProducts)
            ->selectRaw('COALESCE(SUM(current_stock * purchase_price), 0) as aggregate')
            ->value('aggregate');
        $categorySummary = DB::table('categories')
            ->join('products', 'products.category_id', '=', 'categories.id')
            ->where('products.company_id', $company->id)
            ->select(
                'categories.id',
                'categories.name',
                DB::raw('COUNT(products.id) as products_count'),
                DB::raw('COALESCE(SUM(products.current_stock), 0) as current_stock'),
                DB::raw('COALESCE(SUM(products.current_stock * products.purchase_price), 0) as stock_value')
            )
            ->groupBy('categories.id', 'categories.name')
            ->orderBy('categories.name')
            ->get();

        $products = collect();
        $categories = collect();
        $suppliers = collect();
        $stockInwards = collect();
        $stockOutwards = collect();
        $documents = collect();

        if ($tab === 'products' || $tab === 'overview') {
            $products = $company->products()
                ->with(['category', 'unit'])
                ->orderBy('name')
                ->limit($tab === 'overview' ? 6 : 100)
                ->get();
        }

        if ($tab === 'categories') {
            $categories = Category::whereHas('products', function ($query) use ($company) {
                $query->where('company_id', $company->id);
            })->withCount(['products as company_products_count' => function ($query) use ($company) {
                $query->where('company_id', $company->id);
            }])->orderBy('name')->get();
        }

        if ($tab === 'suppliers') {
            $suppliers = Supplier::whereHas('stockInwards', function ($query) use ($company) {
                $query->whereHas('product', function ($productQuery) use ($company) {
                    $productQuery->where('company_id', $company->id);
                });
            })->withCount(['stockInwards as company_inwards_count' => function ($query) use ($company) {
                $query->whereHas('product', function ($productQuery) use ($company) {
                    $productQuery->where('company_id', $company->id);
                });
            }])->orderBy('name')->get();
        }

        if ($tab === 'stock') {
            $stockInwards = StockInward::with(['product', 'supplier'])
                ->whereHas('product', function ($query) use ($company) {
                    $query->where('company_id', $company->id);
                })->where('is_active', true)->latest('inward_date')->limit(25)->get();

            $stockOutwards = StockOutward::with('product')
                ->whereHas('product', function ($query) use ($company) {
                    $query->where('company_id', $company->id);
                })->where('is_active', true)->latest('outward_date')->limit(25)->get();
        }

        if ($tab === 'documents') {
            $documents = $company->documents()->latest()->get();
        }

        return view('companies.show', compact(
            'company',
            'tab',
            'totalProducts',
            'totalCategories',
            'currentStock',
            'activeProducts',
            'stockValue',
            'categorySummary',
            'products',
            'categories',
            'suppliers',
            'stockInwards',
            'stockOutwards',
            'documents'
        ));
    }

    public function edit($id)
    {
        $company = Company::findOrFail($id);

        return view('companies.edit', compact('company'));
    }

    public function update(Request $request, $id)
    {
        $company = Company::findOrFail($id);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:100|unique:companies,code,' . $company->id,
            'description' => 'nullable|string',
        ]);
        $company->update($data);

        return redirect()
            ->route('companies.show', $company->id)
            ->with('success', 'Company updated successfully.');
    }

    public function status(Request $request, $id)
    {
        $data = $request->validate([
            'is_active' => 'required|boolean',
        ]);
        $company = Company::findOrFail($id);
        $company->update(['is_active' => (bool) $data['is_active']]);

        return redirect()
            ->route('companies.index')
            ->with('success', $company->is_active
                ? 'Company activated successfully.'
                : 'Company deactivated successfully. Its products remain in inventory.');
    }

    public function destroy($id)
    {
        $company = Company::findOrFail($id);
        $company->update(['is_active' => false]);

        return redirect()
            ->route('companies.index')
            ->with('success', 'Company deactivated successfully. Its products remain in inventory.');
    }

    public function storeDocument(Request $request, $id)
    {
        $company = Company::findOrFail($id);
        $data = $request->validate([
            'document' => 'required|file|max:10240|mimes:pdf,doc,docx,xls,xlsx,csv,txt,png,jpg,jpeg',
        ]);

        $file = $data['document'];
        $path = $file->store('company-documents/' . $company->id);
        if (!$path) {
            throw new \RuntimeException('The company document could not be saved.');
        }

        $company->documents()->create([
            'file_name' => $file->getClientOriginalName(),
            'storage_path' => $path,
            'file_size' => $file->getSize(),
        ]);

        return redirect()
            ->route('companies.show', ['company' => $company->id, 'tab' => 'documents'])
            ->with('success', 'Company document uploaded successfully.');
    }

    public function downloadDocument($companyId, $documentId)
    {
        $document = CompanyDocument::where('company_id', $companyId)
            ->findOrFail($documentId);

        if (!Storage::disk('local')->exists($document->storage_path)) {
            abort(404, 'The company document could not be found.');
        }

        return Storage::disk('local')->download(
            $document->storage_path,
            $document->file_name
        );
    }

    public function destroyDocument($companyId, $documentId)
    {
        $document = CompanyDocument::where('company_id', $companyId)
            ->findOrFail($documentId);

        if (Storage::disk('local')->exists($document->storage_path)
            && !Storage::disk('local')->delete($document->storage_path)) {
            throw new \RuntimeException('The company document could not be deleted from storage.');
        }

        $document->delete();

        return redirect()
            ->route('companies.show', ['company' => $companyId, 'tab' => 'documents'])
            ->with('success', 'Company document deleted successfully.');
    }
}
