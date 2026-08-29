<?php

namespace App\Support;

use App\Models\DiningTable;
use App\Models\Outlet;
use App\Models\TableQrToken;
use App\Models\Tenant;

final readonly class PublicTableAccess
{
    public function __construct(
        public string $plainToken,
        public TableQrToken $qrToken,
        public Tenant $tenant,
        public Outlet $outlet,
        public DiningTable $table,
    ) {}
}
