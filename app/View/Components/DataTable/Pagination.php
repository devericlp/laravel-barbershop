<?php

namespace App\View\Components\DataTable;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Pagination extends Component
{
    public const array PER_PAGE_OPTIONS = [10, 25, 50];

    public function __construct(
        public int $from,
        public int $to,
        public int $total,
        public int $currentPage,
        public int $lastPage,
    ) {}

    /** @return list<int> */
    public function perPageOptions(): array
    {
        return self::PER_PAGE_OPTIONS;
    }

    /** @return list<int|null> */
    public function links(): array
    {
        $pages = collect([1, $this->lastPage, ...range(max(1, $this->currentPage - 1), min($this->lastPage, $this->currentPage + 1))])
            ->unique()
            ->sort()
            ->values();

        $links = [];

        foreach ($pages as $index => $page) {
            if ($index > 0 && $page - $pages[$index - 1] > 1) {
                $links[] = null;
            }

            $links[] = $page;
        }

        return $links;
    }

    public function render(): View
    {
        return view('components.data-table.pagination');
    }
}
