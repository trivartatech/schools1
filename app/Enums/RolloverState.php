<?php

namespace App\Enums;

enum RolloverState: string
{
    case Draft             = 'draft';
    case StructureRunning  = 'structure_running';
    case StructureDone     = 'structure_done';
    case StudentsRunning   = 'students_running';
    case StudentsDone      = 'students_done';
    case FeesRunning       = 'fees_running';
    case FeesDone          = 'fees_done';
    case Finalized         = 'finalized';
    case Failed            = 'failed';
    case Cancelled         = 'cancelled';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Finalized, self::Failed, self::Cancelled]);
    }

    public function isRunning(): bool
    {
        return in_array($this, [
            self::StructureRunning,
            self::StudentsRunning,
            self::FeesRunning,
        ]);
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft            => 'Draft',
            self::StructureRunning => 'Cloning structure…',
            self::StructureDone    => 'Structure cloned',
            self::StudentsRunning  => 'Promoting students…',
            self::StudentsDone     => 'Students promoted',
            self::FeesRunning      => 'Carrying forward fees…',
            self::FeesDone         => 'Fees carried forward',
            self::Finalized        => 'Finalized',
            self::Failed           => 'Failed',
            self::Cancelled        => 'Cancelled',
        };
    }
}
