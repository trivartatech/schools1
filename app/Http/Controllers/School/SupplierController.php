<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SupplierController extends Controller
{
    private function schoolId(): int
    {
        return (int) app('current_school_id');
    }

    public function index()
    {
        $suppliers = Supplier::where('school_id', $this->schoolId())
            ->withCount('assets')
            ->withCount('storeItems')
            ->orderBy('name')
            ->get();

        return Inertia::render('School/Inventory/Suppliers', [
            'suppliers' => $suppliers,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:200',
            'contact_person' => 'nullable|string|max:200',
            'phone'          => 'nullable|string|max:20',
            'email'          => 'nullable|email|max:200',
            'gstin'          => 'nullable|string|max:20',
            'address'        => 'nullable|string|max:500',
            'city'           => 'nullable|string|max:100',
            'state'          => 'nullable|string|max:100',
            'website'        => 'nullable|url|max:300',
            'notes'          => 'nullable|string|max:1000',
        ]);

        Supplier::create(['school_id' => $this->schoolId()] + $data);

        return back()->with('success', 'Supplier added.');
    }

    public function update(Request $request, Supplier $supplier)
    {
        abort_unless($supplier->school_id === $this->schoolId(), 403);

        $data = $request->validate([
            'name'           => 'required|string|max:200',
            'contact_person' => 'nullable|string|max:200',
            'phone'          => 'nullable|string|max:20',
            'email'          => 'nullable|email|max:200',
            'gstin'          => 'nullable|string|max:20',
            'address'        => 'nullable|string|max:500',
            'city'           => 'nullable|string|max:100',
            'state'          => 'nullable|string|max:100',
            'website'        => 'nullable|url|max:300',
            'notes'          => 'nullable|string|max:1000',
        ]);

        $supplier->update($data);

        return back()->with('success', 'Supplier updated.');
    }

    public function destroy(Supplier $supplier)
    {
        abort_unless($supplier->school_id === $this->schoolId(), 403);

        if ($supplier->assets()->count() > 0) {
            return back()->withErrors(['supplier' => 'Cannot delete — supplier is linked to assets.']);
        }

        $supplier->delete();

        return back()->with('success', 'Supplier deleted.');
    }
}
