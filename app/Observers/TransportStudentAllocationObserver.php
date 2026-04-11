<?php

namespace App\Observers;

use App\Models\TransportStudentAllocation;
use App\Services\TransportFeeService;

class TransportStudentAllocationObserver
{
    public function __construct(private TransportFeeService $feeService) {}

    public function created(TransportStudentAllocation $allocation): void
    {
        $allocation->loadMissing(['route:id,route_code', 'stop:id,stop_name']);
        $this->feeService->createFeeEntry($allocation);
    }

    public function updated(TransportStudentAllocation $allocation): void
    {
        $this->feeService->syncFeeEntry($allocation);
    }

    public function deleting(TransportStudentAllocation $allocation): void
    {
        $this->feeService->cancelFeeEntry($allocation);
    }
}
