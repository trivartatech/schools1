<?php

namespace App\Observers;

use App\Models\HostelStudent;
use App\Services\HostelFeeService;

class HostelStudentObserver
{
    public function __construct(private HostelFeeService $feeService) {}

    public function created(HostelStudent $allocation): void
    {
        $this->feeService->createFeeEntry($allocation);
    }

    public function updated(HostelStudent $allocation): void
    {
        $this->feeService->syncFeeEntry($allocation);
    }

    public function deleting(HostelStudent $allocation): void
    {
        $this->feeService->cancelFeeEntry($allocation);
    }
}
