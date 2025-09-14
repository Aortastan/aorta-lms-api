<?php

namespace App\Exports;

use App\Models\Tryout;
use App\Models\User;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\Exportable;



class MultiSheetExport implements WithMultipleSheets
{
    private $tryout_uuid;
    private $user_uuid;
    private $sheets;

    use Exportable;
    public function __construct($sheets)
    {
        $this->sheets  = $sheets;
    }
    public function sheets(): array
    {
        return $this->sheets;
    }
}
